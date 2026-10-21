<?php
require_once __DIR__ . '/../config/config.php';
require_vet();
csrf_check();

// 1. Obtener la mascota y validar su existencia
$petId = (int)($_GET['pet_id'] ?? 0);
$q = db()->prepare("
    SELECT p.*, c.full_name 
    FROM pets p 
    JOIN clients c ON c.id = p.client_id 
    WHERE p.id = ?
");
$q->execute([$petId]);
$pet = $q->fetch();

if (!$pet) {
    exit('Mascota no encontrada');
}

// 2. Obtener lista de medicamentos disponibles en la clínica
$q = db()->prepare("SELECT * FROM medications WHERE clinic_id = ? ORDER BY name");
$q->execute([$_SESSION['clinic_id']]);
$meds = $q->fetchAll();

$error = '';

// 3. Procesar el formulario de nueva consulta
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reason = $_POST['motivo'] ?? '';
    $notes = trim($_POST['notes'] ?? '');
    
    if (!$reason || !$notes) {
        $error = 'Motivo y descripción son obligatorios.';
    } else {
        // Preparar fechas solo si el motivo es vacunación
        $expiry = $reason === 'Vacuna' ? ($_POST['vaccine_expiry'] ?: null) : null;
        $next = $reason === 'Vacuna' ? ($_POST['next_dose'] ?: null) : null;
        
        $pdo = db();
        
        // 3.1. Insertar la consulta
        $q = $pdo->prepare("
            INSERT INTO consultations(
                pet_id, clinic_id, vet_user_id, reason, notes, 
                diagnosis, treatment, status, vaccine_expiry, next_dose
            ) VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $q->execute([
            $petId, 
            $_SESSION['clinic_id'], 
            $_SESSION['vet_user_id'], 
            $reason, 
            $notes, 
            trim($_POST['diagnosis'] ?? '') ?: null, 
            trim($_POST['treatment'] ?? '') ?: null, 
            $_POST['status'] ?? 'En proceso', 
            $expiry, 
            $next
        ]);
        
        $cid = (int)$pdo->lastInsertId();
        
        // 3.2. Procesar la asignación y descuento de medicamentos
        $mid = $_POST['medicamento_id'] ?? '';
        $other = trim($_POST['other_medication'] ?? '');
        $purchase = 0;
        
        if ($mid === 'otro') {
            $purchase = 1;
            $mid = null;
        } elseif ($mid !== '') {
            $mid = (int)$mid;
            
            // Restar una unidad del stock local de forma segura
            $q = $pdo->prepare("
                UPDATE medications 
                SET quantity = GREATEST(quantity - 1, 0) 
                WHERE id = ? AND clinic_id = ? AND quantity > 0
            ");
            $q->execute([$mid, $_SESSION['clinic_id']]);
        } else {
            $mid = null;
        }
        
        // 3.3. Vincular el medicamento a la consulta actual
        if ($mid !== null || $other !== '') {
            $q = $pdo->prepare("
                INSERT INTO consultation_medications(consultation_id, medication_id, other_medication, purchase_needed) 
                VALUES(?, ?, ?, ?)
            ");
            $q->execute([$cid, $mid, $other ?: null, $purchase]);
        }
        
        header('Location: ' . url('consultas/ver.php?id=' . $cid));
        exit;
    }
}

$pageTitle = 'Asentar Consulta Médica | ' . APP_NAME;
require __DIR__ . '/../includes/header.php';
?>

<div class="panel">
    <h1><i class="fa-solid fa-stethoscope" style="color: var(--green); margin-right: 8px;"></i>Asentar consulta médica</h1>
    <p class="muted">
        Paciente: <strong class="name-titlecase"><i class="fa-solid fa-paw" style="color: var(--green); margin-right: 4px;"></i> <?= e_name($pet['name']) ?></strong> · 
        Tutor: <strong><?= e_name($pet['full_name']) ?></strong>
    </p>
    
    <?php if ($error): ?>
        <div class="notice error">
            <span class="notice-icon"><i class="fa-solid fa-triangle-exclamation"></i></span>
            <span><?= e($error) ?></span>
        </div>
    <?php endif; ?>
    
    <form method="post" style="margin-top: 18px;">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
        
        <div class="form-grid">
            <div>
                <label>Motivo / tipo de consulta</label>
                <select name="motivo" required id="motivo_select">
                    <option value="">Seleccionar motivo...</option>
                    <?php foreach (['Consulta general', 'Vacuna', 'Control', 'Cirugía', 'Urgencia', 'Desparasitación', 'Dermatología', 'Otro'] as $r): ?>
                        <option><?= e($r) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div>
                <label>Estado clínico</label>
                <select name="status">
                    <option>En proceso</option>
                    <option>Dado de Alta</option>
                </select>
            </div>
        </div>
        
        <div id="vacuna-fields" hidden>
            <div class="form-grid">
                <div>
                    <label>Vencimiento de vacuna</label>
                    <input type="date" name="vaccine_expiry">
                </div>
                <div>
                    <label>Próxima dosis (si corresponde)</label>
                    <input type="date" name="next_dose">
                </div>
            </div>
        </div>
        
        <label>Notas / motivo detallado del cuadro</label>
        <textarea name="notes" placeholder="Describa síntomas, examen físico, constantes..." required></textarea>
        
        <label>Diagnóstico</label>
        <textarea name="diagnosis" placeholder="Diagnóstico presuntivo o definitivo..."></textarea>
        
        <label>Tratamiento indicado y posología</label>
        <textarea name="treatment" placeholder="Instrucciones para el tutor, fármacos, frecuencia..."></textarea>
        
        <label>Medicamento del stock de la veterinaria</label>
        <select name="medicamento_id">
            <option value="">No corresponde / Ninguno</option>
            <?php foreach ($meds as $m): ?>
                <option value="<?= (int)$m['id'] ?>" <?= $m['quantity'] < 1 ? 'disabled' : '' ?>>
                    <?= e($m['name']) ?> — stock: <?= (int)$m['quantity'] ?>
                </option>
            <?php endforeach; ?>
            <option value="otro">Otro (comprar en farmacia)</option>
        </select>
        
        <div id="otro-medicamento" hidden>
            <label>Medicamento a comprar</label>
            <input name="other_medication" placeholder="Nombre comercial o genérico">
        </div>
        
        <div class="actions">
            <button type="submit" class="btn primary">
                <span>Guardar consulta</span>
                <i class="fa-solid fa-arrow-right" style="margin-left: 6px;"></i>
            </button>
            <a class="btn secondary" href="<?= url('mascotas/ver.php?id=' . $petId) ?>">Cancelar</a>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const motivoSelect = document.getElementById('motivo_select');
    const vacunaFields = document.getElementById('vacuna-fields');
    if (motivoSelect && vacunaFields) {
        motivoSelect.addEventListener('change', function() {
            vacunaFields.hidden = (this.value !== 'Vacuna');
        });
    }
});
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
