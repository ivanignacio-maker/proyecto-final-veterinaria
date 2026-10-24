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

$pageTitle = 'Historial médico de ' . $p['name'];
require __DIR__ . '/../includes/header.php';
?>

<div class="panel">
    <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; flex-wrap: wrap;">
        <div>
            <span class="pill">ID Mascota #<?= (int)$p['id'] ?></span>
            <h1 class="name-titlecase" style="margin: 8px 0 4px;">Libreta e historial de <?= e_name($p['name']) ?></h1>
            <p class="name-titlecase" style="color: var(--green); font-weight: 600; margin: 0;">
                <i class="fa-solid fa-paw" style="margin-right: 4px;"></i> <?= e_name($p['animal_type']) ?> <?= $p['breed'] ? '· ' . e_name($p['breed']) : '' ?>
            </p>
        </div>
        <div>
            <a class="btn secondary" href="<?= url('cliente/mascotas.php') ?>">
                <i class="fa-solid fa-arrow-left" style="margin-right: 6px;"></i> Volver a mis mascotas
            </a>
        </div>
    </div>
</div>

<?php if (!$rows): ?>
    <div class="card" style="text-align: center; padding: 48px 20px; margin-top: 24px; background: #ffffff; border-radius: 18px; border: 1px solid var(--line);">
        <span style="font-size: 40px; color: var(--green); display: block; margin-bottom: 12px;"><i class="fa-solid fa-clipboard-list"></i></span>
        <h3>Esta mascota todavía no tiene consultas médicas registradas</h3>
        <p class="muted">Cuando asistas a una veterinaria de la red, los profesionales cargarán aquí su ficha técnica, diagnósticos y vacunas.</p>
    </div>
<?php else: ?>
    <?php foreach ($rows as $r): ?>
        <?php
            $statusClass = $r['status'] === 'Dado de Alta' ? 'badge-success' : 'badge-warning';
        ?>
        <div class="card" style="margin-top: 24px; background: #ffffff; border-radius: 18px; border: 1px solid var(--line); padding: 24px; box-shadow: 0 4px 18px rgba(24,59,53,0.03);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; flex-wrap: wrap; gap: 8px;">
                <span class="badge <?= $statusClass ?>"><?= e($r['status']) ?></span>
                <span class="muted" style="font-size: 13px;">
                    <i class="fa-solid fa-calendar-day" style="margin-right: 4px;"></i> <?= date('d/m/Y H:i', strtotime($r['created_at'])) ?> hs
                </span>
            </div>
            
            <h2 style="margin: 4px 0 8px; font-size: 20px; color: var(--ink);"><?= e($r['reason']) ?></h2>
            <p class="muted name-titlecase" style="margin-bottom: 16px; font-size: 14px;">
                <i class="fa-solid fa-hospital" style="color: var(--green); margin-right: 4px;"></i> Atendido en: <strong><?= e_name($r['clinic_name']) ?></strong>
            </p>
            
            <div style="background: #fcfdfc; border: 1px solid var(--line); border-radius: 12px; padding: 18px; margin-bottom: 18px;">
                <p style="margin: 0 0 12px;">
                    <strong style="color: var(--ink); font-size: 13px;"><i class="fa-solid fa-file-medical" style="color: var(--green); margin-right: 6px;"></i>Evolución y notas:</strong><br>
                    <span style="color: #374151; font-size: 14px; line-height: 1.6; display: block; margin-top: 4px;"><?= nl2br(e($r['notes'])) ?></span>
                </p>
                
                <?php if ($r['diagnosis']): ?>
                    <p style="margin: 0 0 12px;">
                        <strong style="color: var(--ink); font-size: 13px;"><i class="fa-solid fa-stethoscope" style="color: var(--green); margin-right: 6px;"></i>Diagnóstico:</strong><br>
                        <span style="color: #374151; font-size: 14px; line-height: 1.6; display: block; margin-top: 4px;"><?= nl2br(e($r['diagnosis'])) ?></span>
                    </p>
                <?php endif; ?>
                
                <?php if ($r['treatment']): ?>
                    <p style="margin: 0;">
                        <strong style="color: var(--ink); font-size: 13px;"><i class="fa-solid fa-pills" style="color: var(--green); margin-right: 6px;"></i>Indicaciones y tratamiento:</strong><br>
                        <span style="color: #374151; font-size: 14px; line-height: 1.6; display: block; margin-top: 4px;"><?= nl2br(e($r['treatment'])) ?></span>
                    </p>
                <?php endif; ?>
            </div>
            
            <?php if ($r['vaccine_expiry']): ?>
                <div class="notice" style="margin-bottom: 18px; background: #eaf7ee; border: 1px solid #cce8d5; color: #1e6348; border-radius: 12px; padding: 12px 16px;">
                    <i class="fa-solid fa-syringe" style="margin-right: 6px;"></i> <strong>Vacuna aplicada</strong> · Vencimiento: <?= date('d/m/Y', strtotime($r['vaccine_expiry'])) ?>
                    <?= $r['next_dose'] ? '· Próximo refuerzo sugerido: ' . date('d/m/Y', strtotime($r['next_dose'])) : '' ?>
                </div>
            <?php endif; ?>
            
            <div style="border-top: 1px solid var(--line); padding-top: 18px; margin-top: 16px;">
                <h4 style="margin: 0 0 12px; font-size: 14px; font-weight: 700; color: var(--ink);">
                    <i class="fa-solid fa-paperclip" style="color: var(--green); margin-right: 6px;"></i>Documentos y estudios adjuntos
                </h4>
                
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
                                <i class="fa-solid fa-file-pdf" style="color: #dc2626; margin-right: 4px;"></i> <?= e($f['original_name']) ?> 
                                <span class="muted" style="font-size: 11px;">(<?= date('d/m/y', strtotime($f['created_at'])) ?>)</span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <form method="post" enctype="multipart/form-data" action="<?= url('cliente/subir_archivo.php') ?>" style="margin-top: 14px;">
                    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                    <input type="hidden" name="consultation_id" value="<?= (int)$r['id'] ?>">
                    
                    <div class="attachment-uploader">
                        <?php
                            $uploadError = (int)($_GET['consultation_id'] ?? 0) === (int)$r['id']
                                ? (string)($_GET['upload_error'] ?? '')
                                : '';
                            $uploadErrorMessage = $uploadError === 'invalid'
                                ? 'Solo se aceptan archivos PDF, JPG o PNG de hasta 5 MB. El archivo no se adjuntó.'
                                : ($uploadError === 'save' ? 'No se pudo guardar el archivo. No se adjuntó; intentá nuevamente.' : '');
                        ?>
                        <div class="notice error attachment-upload-error" role="alert" aria-live="polite" style="display: <?= $uploadErrorMessage ? 'flex' : 'none' ?>; margin-bottom: 12px;">
                            <i class="fa-solid fa-circle-exclamation notice-icon"></i>
                            <span><?= e($uploadErrorMessage) ?></span>
                        </div>

                        <!-- Dropzone interactivo (Attachments) -->
                        <div class="attachment-dropzone" style="padding: 18px 16px;">
                            <div class="attachment-dropzone-icon" style="width: 40px; height: 40px; font-size: 18px; margin-bottom: 8px;">
                                <i class="fa-solid fa-cloud-arrow-up"></i>
                            </div>
                            <div class="attachment-dropzone-title" style="font-size: 14px;">
                                <span>Subir estudio complementario</span> o arrastralo acá
                            </div>
                            <div class="attachment-dropzone-subtitle" style="font-size: 12px;">
                                PDF, JPG o PNG (hasta 5 MB)
                            </div>
                        </div>

                        <input type="file" name="attachment" class="attachment-input-hidden" accept=".pdf,.jpg,.jpeg,.png" required>

                        <!-- Tarjeta de archivo seleccionado (Attachments Card) -->
                        <div class="attachment-preview-card">
                            <div class="attachment-card-body">
                                <div class="attachment-card-left">
                                    <div class="attachment-thumb"></div>
                                    <div class="attachment-info">
                                        <div class="attachment-filename">estudio.pdf</div>
                                        <div class="attachment-meta">
                                            <span class="attachment-filesize">0 KB</span>
                                            <span>·</span>
                                            <span class="attachment-badge-ready">Listo para adjuntar</span>
                                        </div>
                                    </div>
                                </div>

                                <button type="button" class="attachment-remove-btn" title="Quitar archivo" aria-label="Quitar archivo">
                                    <i class="fa-solid fa-xmark"></i>
                                </button>
                            </div>

                            <!-- Barra de progreso fluida -->
                            <div class="attachment-progress-wrapper">
                                <div class="attachment-progress-bar"></div>
                            </div>

                            <div class="attachment-card-footer">
                                <span class="muted" style="font-size: 12px;">
                                    Consulta del <?= date('d/m/Y', strtotime($r['created_at'])) ?>
                                </span>

                                <!-- Flow Button (@xubohuah) -->
                                <button type="submit" class="btn-flow" style="padding: 10px 20px; font-size: 13.5px;">
                                    <i class="fa-solid fa-arrow-up-from-bracket btn-flow-icon"></i>
                                    <span>Subir a mi libreta</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
