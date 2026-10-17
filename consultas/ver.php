<?php
require_once __DIR__ . '/../config/config.php';
require_vet();

$id = (int)($_GET['id'] ?? 0);

// 1. Obtener los datos principales de la consulta, mascota, cliente y clínica
$q = db()->prepare("
    SELECT co.*, p.name pet_name, p.id pet_id, c.full_name, cl.name clinic_name 
    FROM consultations co 
    JOIN pets p ON p.id = co.pet_id 
    JOIN clients c ON c.id = p.client_id 
    JOIN clinics cl ON cl.id = co.clinic_id 
    WHERE co.id = ?
");
$q->execute([$id]);
$c = $q->fetch();

if (!$c) {
    exit('Consulta no encontrada');
}

// 2. Obtener el medicamento asociado a la consulta (si existe)
$q = db()->prepare("
    SELECT cm.*, m.name medication_name 
    FROM consultation_medications cm 
    LEFT JOIN medications m ON m.id = cm.medication_id 
    WHERE cm.consultation_id = ?
");
$q->execute([$id]);
$med = $q->fetch();

// 3. Obtener los archivos adjuntos vinculados a la consulta
$q = db()->prepare("
    SELECT * 
    FROM consultation_files 
    WHERE consultation_id = ? 
    ORDER BY created_at DESC
");
$q->execute([$id]);
$files = $q->fetchAll();

$pageTitle = 'Consulta';
require __DIR__ . '/../includes/header.php';
?>

<div class="panel">
    <span class="pill"><?= e($c['status']) ?></span>
    
    <h1><?= e($c['reason']) ?> · <?= e($c['pet_name']) ?></h1>
    
    <p><?= e($c['created_at']) ?> · <?= e($c['clinic_name']) ?></p>
    <p><strong>Responsable:</strong> <?= e($c['full_name']) ?></p>
    
    <p>
        <strong>Notas:</strong><br>
        <?= nl2br(e($c['notes'])) ?>
    </p>
    
    <p>
        <strong>Diagnóstico:</strong><br>
        <?= nl2br(e($c['diagnosis'])) ?>
    </p>
    
    <p>
        <strong>Tratamiento:</strong><br>
        <?= nl2br(e($c['treatment'])) ?>
    </p>
    
    <?php if ($med): ?>
        <p>
            <strong>Medicamento:</strong> 
            <?= e($med['other_medication'] ?: ($med['medication_name'] ?: 'Medicamento de stock')) ?> 
            <?= $med['purchase_needed'] ? '(pendiente de compra)' : '' ?>
        </p>
    <?php endif; ?>
    
    <?php if ($c['vaccine_expiry']): ?>
        <p>
            Vencimiento: <?= e($c['vaccine_expiry']) ?> · 
            Próxima dosis: <?= e($c['next_dose']) ?>
        </p>
    <?php endif; ?>
    
    <div class="actions">
        <a class="btn secondary" href="<?= url('mascotas/ver.php?id=' . (int)$c['pet_id']) ?>">Volver al historial</a>
        <a class="btn" href="<?= url('consultas/editar.php?id=' . (int)$id) ?>">Editar consulta</a>
    </div>
</div>

<div class="card">
    <h2>Archivos adjuntos</h2>
    
    <?php if (!$files): ?>
        <p>No hay archivos adjuntos.</p>
    <?php else: ?>
        <?php foreach ($files as $f): ?>
            <p>
                <a href="<?= url('archivo_consulta.php?id=' . (int)$f['id']) ?>">
                    <?= e($f['original_name']) ?>
                </a> 
                · <?= e($f['created_at']) ?>
            </p>
        <?php endforeach; ?>
    <?php endif; ?>
    
    <p class="muted">Los adjuntos se agregan por separado y no alteran los datos clínicos de la consulta.</p>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>