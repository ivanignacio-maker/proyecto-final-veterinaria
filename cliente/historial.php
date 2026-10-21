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
    <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; flex-wrap: wrap;">
        <div>
            <span class="pill">ID Mascota #<?= (int)$p['id'] ?></span>
            <h1 class="name-titlecase" style="margin: 8px 0 4px;">Libreta e historial de <?= e_name($p['name']) ?></h1>
            <p class="name-titlecase" style="color: var(--green); font-weight: 600; margin: 0;">
                🐾 <?= e_name($p['animal_type']) ?> <?= $p['breed'] ? '· ' . e_name($p['breed']) : '' ?>
            </p>
        </div>
        <div>
            <a class="btn secondary" href="<?= url('cliente/mascotas.php') ?>">← Volver a mis mascotas</a>
        </div>
    </div>
</div>

<?php if (!$rows): ?>
    <div class="card" style="text-align: center; padding: 40px 20px; margin-top: 24px;">
        <span style="font-size: 38px; display: block; margin-bottom: 12px;">📋</span>
        <h3>Esta mascota todavía no tiene consultas médicas registradas</h3>
        <p class="muted">Cuando asistas a una veterinaria de la red, los profesionales cargarán aquí su ficha técnica y vacunas.</p>
    </div>
<?php else: ?>
    <?php foreach ($rows as $r): ?>
        <?php
            $statusClass = $r['status'] === 'Dado de Alta' ? 'badge-success' : 'badge-warning';
        ?>
        <div class="card" style="margin-top: 24px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                <span class="badge <?= $statusClass ?>"><?= e($r['status']) ?></span>
                <span class="muted" style="font-size: 13px;">
                    📅 <?= date('d/m/Y H:i', strtotime($r['created_at'])) ?> hs
                </span>
            </div>
            
            <h2 style="margin: 4px 0 8px;"><?= e($r['reason']) ?></h2>
            <p class="muted name-titlecase" style="margin-bottom: 16px;">
                🏥 Atendido en: <strong><?= e_name($r['clinic_name']) ?></strong>
            </p>
            
            <div style="background: #fcfdfc; border: 1px solid var(--line); border-radius: 12px; padding: 16px; margin-bottom: 18px;">
                <p style="margin: 0 0 10px;">
                    <strong style="color: var(--ink);">Evolución y notas:</strong><br>
                    <span style="color: #374151;"><?= nl2br(e($r['notes'])) ?></span>
                </p>
                
                <?php if ($r['diagnosis']): ?>
                    <p style="margin: 0 0 10px;">
                        <strong style="color: var(--ink);">Diagnóstico:</strong><br>
                        <span style="color: #374151;"><?= nl2br(e($r['diagnosis'])) ?></span>
                    </p>
                <?php endif; ?>
                
                <?php if ($r['treatment']): ?>
                    <p style="margin: 0;">
                        <strong style="color: var(--ink);">Indicaciones y tratamiento:</strong><br>
                        <span style="color: #374151;"><?= nl2br(e($r['treatment'])) ?></span>
                    </p>
                <?php endif; ?>
            </div>
            
            <?php if ($r['vaccine_expiry']): ?>
                <div class="notice" style="margin-bottom: 18px;">
                    💉 <strong>Vacuna aplicada</strong> · Vencimiento: <?= date('d/m/Y', strtotime($r['vaccine_expiry'])) ?>
                    <?= $r['next_dose'] ? '· Próximo refuerzo sugerido: ' . date('d/m/Y', strtotime($r['next_dose'])) : '' ?>
                </div>
            <?php endif; ?>
            
            <div style="border-top: 1px solid var(--line); padding-top: 18px; margin-top: 16px;">
                <h4 style="margin: 0 0 10px; font-size: 14px; font-weight: 700;">📎 Documentos y estudios adjuntos</h4>
                
                <?php 
                $qf = db()->prepare("
                    SELECT id, original_name, created_at 
                    FROM consultation_files 
                    WHERE consultation_id = ? 
                    ORDER BY created_at DESC
                ");
                $qf->execute([$r['id']]);
                $archivos = $qf->fetchAll();
                ?>
                
                <?php if ($archivos): ?>
                    <div style="display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 16px;">
                        <?php foreach ($archivos as $f): ?>
                            <a class="btn secondary btn-sm" href="<?= url('archivo_consulta.php?id=' . (int)$f['id']) ?>" target="_blank">
                                📄 <?= e($f['original_name']) ?> <span class="muted" style="font-size: 11px;">(<?= date('d/m/y', strtotime($f['created_at'])) ?>)</span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <form method="post" enctype="multipart/form-data" action="<?= url('cliente/subir_archivo.php') ?>" style="display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap; margin-top: 10px;">
                    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                    <input type="hidden" name="consultation_id" value="<?= (int)$r['id'] ?>">
                    
                    <div style="flex: 1; min-width: 240px;">
                        <label style="margin-bottom: 4px;">Subir nuevo estudio complementario (PDF, JPG, PNG)</label>
                        <input type="file" name="attachment" accept=".pdf,.jpg,.jpeg,.png" required style="margin-top: 0;">
                    </div>
                    
                    <button class="btn secondary" type="submit">Subir estudio</button>
                </form>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>