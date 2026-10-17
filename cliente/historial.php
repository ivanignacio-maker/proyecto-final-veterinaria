<?php
require_once __DIR__ . '/../config/config.php';
require_client();

$pid = (int)($_GET['pet_id'] ?? 0);

// 1. Obtener la mascota y validar que pertenece al cliente actual
$q = db()->prepare("SELECT p.* FROM pets p WHERE p.id = ? AND p.client_id = ?");
$q->execute([$pid, $_SESSION['client_id']]);
$p = $q->fetch();

if (!$p) {
    exit('Mascota no encontrada');
}

// 2. Obtener todo el historial de consultas de la mascota
$q = db()->prepare("
    SELECT co.*, cl.name clinic_name 
    FROM consultations co 
    JOIN clinics cl ON cl.id = co.clinic_id 
    WHERE co.pet_id = ? 
    ORDER BY co.created_at DESC
");
$q->execute([$pid]);
$rows = $q->fetchAll();

$pageTitle = 'Historial médico';
require __DIR__ . '/../includes/header.php';
?>

<div class="panel">
    <span class="pill">ID #<?= (int)$p['id'] ?></span>
    <h1>Historial de <?= e($p['name']) ?></h1>
    
    <p><?= e($p['animal_type']) ?> · <?= e($p['breed']) ?></p>
    
    <a class="btn secondary" href="<?= url('cliente/mascotas.php') ?>">Volver a mis mascotas</a>
</div>

<?php if (!$rows): ?>
    <div class="card">
        <p>Esta mascota todavía no tiene consultas registradas.</p>
    </div>
<?php else: ?>
    <?php foreach ($rows as $r): ?>
        <div class="card">
            <span class="pill"><?= e($r['status']) ?></span>
            <h2><?= e($r['reason']) ?></h2>
            
            <p class="muted"><?= e($r['created_at']) ?> · <?= e($r['clinic_name']) ?></p>
            
            <p>
                <strong>Notas:</strong><br>
                <?= nl2br(e($r['notes'])) ?>
            </p>
            
            <p>
                <strong>Diagnóstico:</strong><br>
                <?= nl2br(e($r['diagnosis'])) ?>
            </p>
            
            <p>
                <strong>Tratamiento:</strong><br>
                <?= nl2br(e($r['treatment'])) ?>
            </p>
            
            <?php if ($r['vaccine_expiry']): ?>
                <p>
                    Vencimiento de vacuna: <?= e($r['vaccine_expiry']) ?> · 
                    Próxima dosis: <?= e($r['next_dose']) ?>
                </p>
            <?php endif; ?>
            
            <h3>Adjuntar documento a esta consulta</h3>
            
            <form method="post" enctype="multipart/form-data" action="<?= url('cliente/subir_archivo.php') ?>">
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="consultation_id" value="<?= (int)$r['id'] ?>">
                
                <label>Archivo PDF, JPG o PNG (máx. 5 MB)</label>
                <input type="file" name="attachment" accept=".pdf,.jpg,.jpeg,.png" required>
                <button>Adjuntar archivo</button>
            </form>
            
            <?php 
            // 3. Obtener los archivos adjuntos específicos de esta consulta
            $qf = db()->prepare("
                SELECT id, original_name, created_at 
                FROM consultation_files 
                WHERE consultation_id = ? 
                ORDER BY created_at DESC
            ");
            $qf->execute([$r['id']]);
            $archivos = $qf->fetchAll();
            ?>
            
            <?php foreach ($archivos as $f): ?>
                <p class="muted">
                    Adjunto: 
                    <a href="<?= url('archivo_consulta.php?id=' . (int)$f['id']) ?>">
                        <?= e($f['original_name']) ?>
                    </a> 
                    · <?= e($f['created_at']) ?>
                </p>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>