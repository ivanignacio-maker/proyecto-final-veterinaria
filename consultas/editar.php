<?php
require_once __DIR__ . '/../config/config.php';
require_vet();
csrf_check();

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

// 1. Obtener la información actual de la consulta y la mascota asociada
$q = db()->prepare("
    SELECT co.*, p.name pet_name, p.id pet_id 
    FROM consultations co 
    JOIN pets p ON p.id = co.pet_id 
    WHERE co.id = ? AND co.clinic_id = ?
");
// Solo se puede editar una consulta de la propia clínica.
$q->execute([$id, (int)$_SESSION['clinic_id']]);
$c = $q->fetch();

if (!$c) {
    // Diferenciar una consulta existente de otra clínica de un ID que no existe.
    $q = db()->prepare("SELECT clinic_id FROM consultations WHERE id = ? LIMIT 1");
    $q->execute([$id]);
    $existingClinicId = $q->fetchColumn();

    if ($existingClinicId !== false && (int)$existingClinicId !== (int)$_SESSION['clinic_id']) {
        http_response_code(403);
        $pageTitle = 'No se puede editar esta consulta';
        require __DIR__ . '/../includes/header.php';
        ?>
        <section class="panel" style="max-width: 760px; margin: 36px auto;">
            <div class="notice error" role="alert">
                Esta consulta pertenece a otra veterinaria. Solo podés editar consultas registradas por tu veterinaria.
            </div>
            <a class="btn secondary" href="<?= url('consultas/ver.php?id=' . $id) ?>">
                <i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Volver a la consulta
            </a>
        </section>
        <?php
        require __DIR__ . '/../includes/footer.php';
        exit;
    }

    http_response_code(404);
    exit('Consulta no encontrada');
}

$q = db()->prepare("
    SELECT cm.*, m.name medication_name
    FROM consultation_medications cm
    LEFT JOIN medications m ON m.id = cm.medication_id
    WHERE cm.consultation_id = ?
");
$q->execute([$id]);
$currentMedication = $q->fetch() ?: null;
$currentMedicationId = !empty($currentMedication['medication_id']) ? (int)$currentMedication['medication_id'] : 0;
$isHistoricalMedication = $currentMedication
    && $currentMedicationId === 0
    && (int)$currentMedication['purchase_needed'] === 0
    && trim((string)$currentMedication['other_medication']) !== '';
$selectedMedication = $currentMedicationId > 0
    ? (string)$currentMedicationId
    : (($currentMedication && (int)$currentMedication['purchase_needed'] === 1)
        ? 'otro'
        : ($isHistoricalMedication ? 'historico' : ''));
$otherMedication = trim((string)($currentMedication['other_medication'] ?? ''));

$error = '';

// Valores que se muestran en el formulario. Si el envío falla (por ejemplo, código incorrecto)
// se vuelven a mostrar los que escribió el veterinario, para que no pierda lo que tipeó.
$v = [
    'reason'         => (string)$c['reason'],
    'status'         => (string)$c['status'],
    'vaccine_expiry' => (string)$c['vaccine_expiry'],
    'next_dose'      => (string)$c['next_dose'],
    'notes'          => (string)$c['notes'],
    'diagnosis'      => (string)$c['diagnosis'],
    'treatment'      => (string)$c['treatment'],
];

// 2. Procesar la actualización de la consulta
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reason = trim($_POST['reason'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $diagnosis = trim($_POST['diagnosis'] ?? '');
    $treatment = trim($_POST['treatment'] ?? '');
    $status = $_POST['status'] ?? 'En proceso';
    $selectedMedication = trim((string)($_POST['medicamento_id'] ?? ''));
    $postedOtherMedication = trim((string)($_POST['other_medication'] ?? ''));
    // El nombre del medicamento retirado es una copia del historial y no debe poder alterarse aquí.
    $otherMedication = $selectedMedication === 'historico' && $isHistoricalMedication
        ? trim((string)$currentMedication['other_medication'])
        : $postedOtherMedication;
    
    $v = [
        'reason' => $reason, 'status' => $status,
        'vaccine_expiry' => (string)($_POST['vaccine_expiry'] ?? ''), 'next_dose' => (string)($_POST['next_dose'] ?? ''),
        'notes' => $notes, 'diagnosis' => $diagnosis, 'treatment' => $treatment,
    ];

    // Validar campos obligatorios
    $authCode = trim($_POST['auth_code'] ?? '');

    if (!$reason || !$notes || !in_array($status, ['En proceso', 'Dado de Alta'], true)) {
        $error = 'Revisá los campos obligatorios.';
    } elseif ($selectedMedication !== '' && !in_array($selectedMedication, ['otro', 'historico'], true) && !ctype_digit($selectedMedication)) {
        $error = 'Elegí un medicamento válido del stock.';
    } elseif ($selectedMedication === 'otro' && $otherMedication === '') {
        $error = 'Indicá el nombre del medicamento que se va a comprar.';
    } elseif ($selectedMedication === 'historico' && !$isHistoricalMedication) {
        $error = 'Ese medicamento ya no está disponible para asignar desde el stock.';
    } elseif ($authCode === '') {
        $error = 'Para guardar los cambios, solicitale al tutor el código de autorización generado en su libreta digital.';
    } else {
        // Preparar estado anterior para el registro de auditoría
        $before = [
            'reason'    => $c['reason'],
            'notes'     => $c['notes'],
            'diagnosis' => $c['diagnosis'],
            'treatment' => $c['treatment'],
            'status'    => $c['status']
        ];
        
        // Asignar fechas solo si el motivo es una vacuna
        $expiry = $reason === 'Vacuna' ? ($v['vaccine_expiry'] ?: null) : null;
        $next = $reason === 'Vacuna' ? ($v['next_dose'] ?: null) : null;

        $pdo = db();
        $pdo->beginTransaction();
        try {
            // Si el stock no alcanza, la transacción revierte también el uso del código del tutor.
            if (!verify_and_use_pet_access_code((int)$c['pet_id'], $authCode, (int)$_SESSION['vet_user_id'])) {
                $pdo->rollBack();
                $error = 'El código de autorización es inválido, ya fue utilizado o ha expirado. Solicitale al dueño que te dicte el código activo de su pantalla.';
            } else {
                $q = $pdo->prepare("SELECT medication_id FROM consultation_medications WHERE consultation_id = ? FOR UPDATE");
                $q->execute([$id]);
                $lockedMedicationId = $q->fetchColumn();
                $oldMedicationId = $lockedMedicationId === false || $lockedMedicationId === null ? null : (int)$lockedMedicationId;
                $newMedicationId = $selectedMedication !== '' && !in_array($selectedMedication, ['otro', 'historico'], true) ? (int)$selectedMedication : null;

                // Bloqueamos las filas de inventario en orden estable para impedir que dos
                // consultas descuenten a la vez la última unidad.
                $stockIds = array_values(array_unique(array_filter([$oldMedicationId, $newMedicationId])));
                sort($stockIds, SORT_NUMERIC);
                $stockById = [];
                if ($stockIds) {
                    $placeholders = implode(',', array_fill(0, count($stockIds), '?'));
                    $q = $pdo->prepare("SELECT id, quantity FROM medications WHERE clinic_id = ? AND id IN ($placeholders) ORDER BY id FOR UPDATE");
                    $q->execute(array_merge([(int)$_SESSION['clinic_id']], $stockIds));
                    foreach ($q->fetchAll() as $stockRow) {
                        $stockById[(int)$stockRow['id']] = (int)$stockRow['quantity'];
                    }
                }

                if ($newMedicationId !== null && !array_key_exists($newMedicationId, $stockById)) {
                    $pdo->rollBack();
                    $error = 'El medicamento seleccionado ya no pertenece al stock de esta veterinaria. Actualizá la página y elegí otro.';
                } elseif ($newMedicationId !== null && $newMedicationId !== $oldMedicationId && $stockById[$newMedicationId] < 1) {
                    $pdo->rollBack();
                    $error = 'No quedan unidades de ese medicamento. Elegí otro del stock o actualizá las existencias.';
                } else {
                    if ($oldMedicationId !== $newMedicationId && $oldMedicationId !== null && array_key_exists($oldMedicationId, $stockById)) {
                        $q = $pdo->prepare('UPDATE medications SET quantity = quantity + 1 WHERE id = ? AND clinic_id = ?');
                        $q->execute([$oldMedicationId, (int)$_SESSION['clinic_id']]);
                    }
                    if ($newMedicationId !== null && $newMedicationId !== $oldMedicationId) {
                        $q = $pdo->prepare('UPDATE medications SET quantity = quantity - 1 WHERE id = ? AND clinic_id = ? AND quantity > 0');
                        $q->execute([$newMedicationId, (int)$_SESSION['clinic_id']]);
                        if ($q->rowCount() !== 1) {
                            throw new RuntimeException('El stock cambió durante la operación.');
                        }
                    }

                    $q = $pdo->prepare("UPDATE consultations SET reason = ?, notes = ?, diagnosis = ?, treatment = ?, status = ?, vaccine_expiry = ?, next_dose = ? WHERE id = ? AND clinic_id = ?");
                    $q->execute([$reason, $notes, $diagnosis ?: null, $treatment ?: null, $status, $expiry, $next, $id, (int)$_SESSION['clinic_id']]);

                    if ($newMedicationId !== null || in_array($selectedMedication, ['otro', 'historico'], true)) {
                        $q = $pdo->prepare("INSERT INTO consultation_medications (consultation_id, medication_id, other_medication, purchase_needed) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE medication_id = VALUES(medication_id), other_medication = VALUES(other_medication), purchase_needed = VALUES(purchase_needed)");
                        $q->execute([
                            $id,
                            $newMedicationId,
                            in_array($selectedMedication, ['otro', 'historico'], true) ? $otherMedication : null,
                            $selectedMedication === 'otro' ? 1 : 0,
                        ]);
                    } else {
                        $q = $pdo->prepare('DELETE FROM consultation_medications WHERE consultation_id = ?');
                        $q->execute([$id]);
                    }

                    $newMedicationName = null;
                    if ($selectedMedication === 'otro') {
                        $newMedicationName = $otherMedication . ' (comprar en farmacia)';
                    } elseif ($selectedMedication === 'historico') {
                        $newMedicationName = $otherMedication;
                    } elseif ($newMedicationId !== null) {
                        $q = $pdo->prepare('SELECT name FROM medications WHERE id = ? AND clinic_id = ?');
                        $q->execute([$newMedicationId, (int)$_SESSION['clinic_id']]);
                        $newMedicationName = (string)($q->fetchColumn() ?: '');
                    }
                    $before['medication'] = ($currentMedication['other_medication'] ?? '') ?: ($currentMedication['medication_name'] ?? null);
                    $before['medication_id'] = $oldMedicationId;
                    $before['purchase_needed'] = (int)($currentMedication['purchase_needed'] ?? 0);
                    $pdo->commit();

                    audit_log('consultation', $id, 'updated', [
                        'before' => $before,
                        'after' => [
                            'reason' => $reason,
                            'notes' => $notes,
                            'diagnosis' => $diagnosis,
                            'treatment' => $treatment,
                            'status' => $status,
                            'medication' => $newMedicationName,
                            'medication_id' => $newMedicationId,
                            'purchase_needed' => $selectedMedication === 'otro' ? 1 : 0,
                        ]
                    ]);

                    header('Location: ' . url('consultas/ver.php?id=' . $id));
                    exit;
                }
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = 'No se pudieron guardar los cambios. Revisá el stock disponible e intentá de nuevo.';
        }
    }
}

$q = db()->prepare("SELECT id, name, quantity FROM medications WHERE clinic_id = ? AND (quantity > 0 OR id = ?) ORDER BY name");
$q->execute([(int)$_SESSION['clinic_id'], $currentMedicationId ?: null]);
$medications = $q->fetchAll();

$pageTitle = 'Editar consulta | ' . APP_NAME;
require __DIR__ . '/../includes/header.php';
?>

<div class="panel">
    <h1><i class="fa-solid fa-pen-to-square" style="color: var(--green); margin-right: 8px;"></i>Editar consulta clínica</h1>
    
    <p class="muted">
        Paciente: <strong class="name-titlecase"><i class="fa-solid fa-paw" style="color: var(--green); margin-right: 4px;"></i> <?= e_name($c['pet_name']) ?></strong> · ID de consulta #<?= (int)$id ?>
    </p>
    <p class="muted" style="font-size: 13px;"><i class="fa-solid fa-shield-halved" style="color: var(--green); margin-right: 4px;"></i> Las modificaciones quedan registradas en el libro de auditoría médica.</p>
    
    <?php if ($error): ?>
        <div class="notice error">
            <span class="notice-icon"><i class="fa-solid fa-triangle-exclamation"></i></span>
            <span><?= e($error) ?></span>
        </div>
    <?php endif; ?>
    
    <form method="post">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
        <input type="hidden" name="id" value="<?= (int)$id ?>">

        <!-- Igual que al crear la consulta: sin el código del tutor no se puede modificar el historial -->
        <div class="consultation-auth-code" style="background: #f0f7f4; border: 1.5px solid #cce8d5; border-radius: 14px; padding: 18px; margin-bottom: 22px;">
            <div style="display: flex; gap: 14px; align-items: flex-start;">
                <div style="font-size: 26px; color: var(--green); margin-top: 2px;">
                    <i class="fa-solid fa-shield-halved"></i>
                </div>
                <div style="flex: 1;">
                    <label style="font-size: 15px; font-weight: 800; color: var(--ink); margin-bottom: 4px; display: block;">
                        Código de autorización del tutor *
                    </label>
                    <p class="muted" style="margin: 0 0 10px; font-size: 13px;">
                        Para modificar esta consulta, solicitale al dueño de <strong><?= e_name($c['pet_name']) ?></strong> el código temporal de 6 dígitos que figura en su libreta sanitaria o sección "Mis mascotas". Cada código sirve una sola vez y vence a los 30 minutos.
                    </p>
                    <div style="max-width: 240px;">
                        <input class="consultation-auth-code-input" type="text" name="auth_code" maxlength="10" placeholder="Ej. 749281" required autocomplete="off" inputmode="numeric" style="font-size: 18px; font-weight: 700; letter-spacing: 3px; font-family: monospace; text-align: center; margin: 0; background: #fff;">
                    </div>
                </div>
            </div>
        </div>
        
        <label>Motivo de atención</label>
        <select name="reason">
            <?php foreach (['Consulta general', 'Vacuna', 'Control', 'Cirugía', 'Urgencia', 'Desparasitación', 'Otro'] as $r): ?>
                <option <?= $v['reason'] === $r ? 'selected' : '' ?>>
                    <?= e($r) ?>
                </option>
            <?php endforeach; ?>
        </select>
        
        <div class="form-grid">
            <div>
                <label>Estado del paciente</label>
                <select name="status">
                    <option <?= $v['status'] === 'En proceso' ? 'selected' : '' ?>>En proceso</option>
                    <option <?= $v['status'] === 'Dado de Alta' ? 'selected' : '' ?>>Dado de Alta</option>
                </select>
            </div>
            
            <div>
                <label>Vencimiento de vacuna (si aplica)</label>
                <input type="date" name="vaccine_expiry" value="<?= e($v['vaccine_expiry']) ?>">
            </div>
            
            <div>
                <label>Próxima dosis sugerida</label>
                <input type="date" name="next_dose" value="<?= e($v['next_dose']) ?>">
            </div>
        </div>
        
        <label>Evolución y notas clínicas</label>
        <textarea name="notes" rows="4" required><?= e($v['notes']) ?></textarea>
        
        <label>Diagnóstico presuntivo o definitivo</label>
        <textarea name="diagnosis" rows="3"><?= e($v['diagnosis']) ?></textarea>
        
        <label>Indicaciones y tratamiento farmacológico</label>
        <textarea name="treatment" rows="3"><?= e($v['treatment']) ?></textarea>

        <label>Medicamento asignado (según stock de la veterinaria)</label>
        <select name="medicamento_id" id="medicamento_id">
            <option value="" <?= $selectedMedication === '' ? 'selected' : '' ?>>No corresponde / Ninguno</option>
            <?php foreach ($medications as $medication): ?>
                <?php $availableQuantity = (int)$medication['quantity'] + ((int)$medication['id'] === $currentMedicationId ? 1 : 0); ?>
                <option value="<?= (int)$medication['id'] ?>" <?= $selectedMedication === (string)$medication['id'] ? 'selected' : '' ?>>
                    <?= e($medication['name']) ?> — disponibles: <?= $availableQuantity ?><?= (int)$medication['id'] === $currentMedicationId ? ' (incluye la unidad asignada)' : '' ?>
                </option>
            <?php endforeach; ?>
            <option value="otro" <?= $selectedMedication === 'otro' ? 'selected' : '' ?>>Otro (comprar en farmacia)</option>
            <?php if ($isHistoricalMedication): ?>
                <option value="historico" <?= $selectedMedication === 'historico' ? 'selected' : '' ?>>Medicamento retirado del stock (mantener en historial)</option>
            <?php endif; ?>
        </select>
        <div id="otro-medicamento" <?= in_array($selectedMedication, ['otro', 'historico'], true) ? '' : 'hidden' ?>>
            <label id="other-medication-label"><?= $selectedMedication === 'historico' ? 'Nombre conservado en el historial' : 'Medicamento a comprar' ?></label>
            <input name="other_medication" id="other_medication" value="<?= e($otherMedication) ?>" placeholder="Nombre comercial o genérico" <?= $selectedMedication === 'otro' ? 'required' : '' ?> <?= $selectedMedication === 'historico' ? 'readonly' : '' ?>>
        </div>
        
        <div class="actions">
            <button class="btn primary" type="submit">
                <i class="fa-solid fa-floppy-disk" style="margin-right: 6px;"></i> Guardar cambios
            </button>
            <a class="btn secondary" href="<?= url('consultas/ver.php?id=' . $id) ?>">Cancelar</a>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const medicationSelect = document.getElementById('medicamento_id');
    const otherMedication = document.getElementById('otro-medicamento');
    const otherName = document.getElementById('other_medication');
    const otherLabel = document.getElementById('other-medication-label');
    if (!medicationSelect || !otherMedication || !otherName) return;

    function updateOtherMedication() {
        const isOther = medicationSelect.value === 'otro';
        const isHistorical = medicationSelect.value === 'historico';
        otherMedication.hidden = !isOther && !isHistorical;
        otherName.required = isOther;
        otherName.readOnly = isHistorical;
        if (otherLabel) otherLabel.textContent = isHistorical ? 'Nombre conservado en el historial' : 'Medicamento a comprar';
    }

    medicationSelect.addEventListener('change', updateOtherMedication);
    updateOtherMedication();
});
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
