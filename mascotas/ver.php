<?php
require_once __DIR__ . '/../config/config.php';
require_vet();

$id = (int)($_GET['id'] ?? 0);

// 1. Obtener los datos de la mascota y su dueño
$q = db()->prepare("
    SELECT p.*, c.full_name, c.dni, c.id client_id 
    FROM pets p 
    JOIN clients c ON c.id = p.client_id 
    WHERE p.id = ?
");
$q->execute([$id]);
$p = $q->fetch();

if (!$p) {
    http_response_code(404);
    exit('Mascota no encontrada');
}

// 2. Obtener el historial clínico (consultas en todas las clínicas vinculadas)
$q = db()->prepare("
    SELECT co.*, cl.name clinic_name, vu.username 
    FROM consultations co 
    JOIN clinics cl ON cl.id = co.clinic_id 
    JOIN vet_users vu ON vu.id = co.vet_user_id 
    WHERE co.pet_id = ? 
    ORDER BY co.created_at DESC
");
$q->execute([$id]);
$consults = $q->fetchAll();

$pageTitle = 'Historial de mascota';
require __DIR__ . '/../includes/header.php';
?>

<div class="panel">
    <?php if ($p['photo_path']): ?>
        <img class="pet-photo" src="<?= url('archivo_mascota.php?id=' . (int)$p['id']) ?>" alt="Foto de mascota">
    <?php endif; ?>
    
    <span class="pill">ID de mascota: <?= (int)$p['id'] ?></span>
    <h1><?= e($p['name']) ?></h1>
    
    <p>
        <?= e($p['animal_type']) ?> · 
        <?= e($p['breed']) ?> · 
        Peso <?= e((string)$p['weight_kg']) ?> kg · 
        Altura <?= e((string)$p['height_cm']) ?> cm
    </p>
    
    <p>Dueño: <?= e($p['full_name']) ?> (DNI <?= e($p['dni']) ?>)</p>
    
    <div class="actions">
        <a class="btn" href="<?= url('consultas/nueva.php?pet_id=' . $id) ?>">Agregar consulta</a>
        <a class="btn secondary" href="<?= url('clientes/ver.php?id=' . (int)$p['client_id']) ?>">Volver al cliente</a>
    </div>
</div>

<div class="card">
    <h2>Historial clínico</h2>
    
    <?php if (!$consults): ?>
        <p>No hay consultas registradas.</p>
    <?php else: ?>
        <?php foreach ($consults as $c): ?>
            <div class="panel">
                <div class="actions">
                    <span class="pill"><?= e($c['status']) ?></span>
                    <span class="muted"><?= e($c['created_at']) ?> · <?= e($c['clinic_name']) ?></span>
                </div>
                
                <h3><?= e($c['reason']) ?></h3>
                
                <p><strong>Motivo / notas:</strong> <?= nl2br(e($c['notes'])) ?></p>
                
                <?php if ($c['diagnosis']): ?>
                    <p><strong>Diagnóstico:</strong> <?= nl2br(e($c['diagnosis'])) ?></p>
                <?php endif; ?>
                
                <?php if ($c['treatment']): ?>
                    <p><strong>Tratamiento:</strong> <?= nl2br(e($c['treatment'])) ?></p>
                <?php endif; ?>
                
                <?php if ($c['vaccine_expiry']): ?>
                    <p>
                        Vencimiento de vacuna: <?= e($c['vaccine_expiry']) ?> · 
                        Próxima dosis: <?= e($c['next_dose']) ?>
                    </p>
                <?php endif; ?>
                
                <a class="btn secondary" href="<?= url('consultas/ver.php?id=' . (int)$c['id']) ?>">Ver consulta y archivos</a>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>