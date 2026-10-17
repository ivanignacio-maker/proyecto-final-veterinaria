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
    WHERE co.id = ?
");
$q->execute([$id]);
$c = $q->fetch();

if (!$c) {
    http_response_code(404);
    exit('Consulta no encontrada');
}

$error = '';

// 2. Procesar la actualización de la consulta
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reason = trim($_POST['reason'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $diagnosis = trim($_POST['diagnosis'] ?? '');
    $treatment = trim($_POST['treatment'] ?? '');
    $status = $_POST['status'] ?? 'En proceso';
    
    // Validar campos obligatorios
    if (!$reason || !$notes || !in_array($status, ['En proceso', 'Dado de Alta'], true)) {
        $error = 'Revisá los campos obligatorios.';
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
        $expiry = $reason === 'Vacuna' ? ($_POST['vaccine_expiry'] ?: null) : null;
        $next = $reason === 'Vacuna' ? ($_POST['next_dose'] ?: null) : null;
        
        // Actualizar en la base de datos
        $q = db()->prepare("
            UPDATE consultations 
            SET reason = ?, notes = ?, diagnosis = ?, treatment = ?, status = ?, vaccine_expiry = ?, next_dose = ? 
            WHERE id = ?
        ");
        $q->execute([
            $reason, 
            $notes, 
            $diagnosis ?: null, 
            $treatment ?: null, 
            $status, 
            $expiry, 
            $next, 
            $id
        ]);
        
        // Registrar la modificación en la auditoría
        audit_log('consultation', $id, 'updated', [
            'before' => $before,
            'after'  => [
                'reason'    => $reason,
                'notes'     => $notes,
                'diagnosis' => $diagnosis,
                'treatment' => $treatment,
                'status'    => $status
            ]
        ]);
        
        header('Location: ' . url('consultas/ver.php?id=' . $id));
        exit;
    }
}

$pageTitle = 'Editar consulta';
require __DIR__ . '/../includes/header.php';
?>

<div class="panel">
    <h1>Editar consulta</h1>
    
    <p class="muted">
        Mascota: <?= e($c['pet_name']) ?> · ID consulta #<?= (int)$id ?>
    </p>
    <p>Las modificaciones quedan registradas en auditoría.</p>
    
    <?php if ($error): ?>
        <p role="alert"><?= e($error) ?></p>
    <?php endif; ?>
    
    <form method="post">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
        <input type="hidden" name="id" value="<?= (int)$id ?>">
        
        <label>Motivo</label>
        <select name="reason">
            <?php foreach (['Consulta general', 'Vacuna', 'Control', 'Cirugía', 'Urgencia', 'Desparasitación', 'Otro'] as $r): ?>
                <option <?= $c['reason'] === $r ? 'selected' : '' ?>>
                    <?= e($r) ?>
                </option>
            <?php endforeach; ?>
        </select>
        
        <div class="form-grid">
            <div>
                <label>Estado</label>
                <select name="status">
                    <option <?= $c['status'] === 'En proceso' ? 'selected' : '' ?>>En proceso</option>
                    <option <?= $c['status'] === 'Dado de Alta' ? 'selected' : '' ?>>Dado de Alta</option>
                </select>
            </div>
            
            <div>
                <label>Vencimiento de vacuna</label>
                <input type="date" name="vaccine_expiry" value="<?= e($c['vaccine_expiry']) ?>">
            </div>
            
            <div>
                <label>Próxima dosis</label>
                <input type="date" name="next_dose" value="<?= e($c['next_dose']) ?>">
            </div>
        </div>
        
        <label>Notas</label>
        <textarea name="notes" required><?= e($c['notes']) ?></textarea>
        
        <label>Diagnóstico</label>
        <textarea name="diagnosis"><?= e($c['diagnosis']) ?></textarea>
        
        <label>Tratamiento</label>
        <textarea name="treatment"><?= e($c['treatment']) ?></textarea>
        
        <div class="actions">
            <button>Guardar cambios</button>
            <a class="btn secondary" href="<?= url('consultas/ver.php?id=' . $id) ?>">Cancelar</a>
        </div>
    </form>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>