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
    http_response_code(404);
    exit('Consulta no encontrada');
}

// Subir nuevo archivo adjunto a esta consulta
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_FILES['attachment'])) {
    csrf_check();
    if (!empty($_FILES['attachment']['name']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['attachment'];
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        $allowed = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'];

        if (in_array($mime, $allowed, true)) {
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $storedName = 'cf_' . $id . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
            $destPath = ensure_upload_dir() . DIRECTORY_SEPARATOR . $storedName;

            if (move_uploaded_file($file['tmp_name'], $destPath)) {
                $q = db()->prepare("
                    INSERT INTO consultation_files (consultation_id, uploaded_by, uploader_id, original_name, stored_name, mime_type)
                    VALUES (?, 'veterinaria', ?, ?, ?, ?)
                ");
                $q->execute([$id, $_SESSION['vet_user_id'], $file['name'], $storedName, $mime]);
                flash('Archivo adjuntado correctamente.');
                header('Location: ' . url('consultas/ver.php?id=' . $id));
                exit;
            }
        } else {
            flash('Formato no permitido. Solo se admiten archivos PDF o imágenes (JPG, PNG, WEBP).');
        }
    }
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

$pageTitle = 'Consulta · ' . e_name($c['pet_name']) . ' | ' . APP_NAME;
require __DIR__ . '/../includes/header.php';
?>

<div class="panel">
    <div style="display: flex; gap: 8px; align-items: center; margin-bottom: 8px; flex-wrap: wrap;">
        <span class="pill"><?= e($c['status']) ?></span>
        <span class="muted" style="font-size: 13px;">
            <i class="fa-solid fa-calendar-day" style="margin-right: 4px;"></i> <?= e($c['created_at']) ?> · 
            <i class="fa-solid fa-hospital" style="margin-right: 4px; color: var(--green);"></i> <?= e_name($c['clinic_name']) ?>
        </span>
    </div>
    
    <h1 style="margin: 0 0 10px;"><?= e($c['reason']) ?> · <?= e_name($c['pet_name']) ?></h1>
    
    <p style="font-size: 15px; margin: 0 0 18px;">
        <i class="fa-solid fa-user" style="color: var(--green); margin-right: 6px;"></i> <strong>Tutor responsable:</strong> <?= e_name($c['full_name']) ?>
    </p>
    
    <div style="background: var(--paper); border: 1px solid var(--line); border-radius: 14px; padding: 18px; margin-bottom: 20px;">
        <p style="margin: 0 0 12px; font-size: 14.5px;">
            <strong>Motivo / Notas clínicas:</strong><br>
            <span style="color: var(--ink);"><?= nl2br(e($c['notes'])) ?></span>
        </p>
        
        <?php if ($c['diagnosis']): ?>
            <p style="margin: 0 0 12px; font-size: 14.5px;">
                <strong>Diagnóstico:</strong><br>
                <span style="color: var(--ink);"><?= nl2br(e($c['diagnosis'])) ?></span>
            </p>
        <?php endif; ?>
        
        <?php if ($c['treatment']): ?>
            <p style="margin: 0 0 12px; font-size: 14.5px;">
                <strong>Tratamiento y posología:</strong><br>
                <span style="color: var(--ink);"><?= nl2br(e($c['treatment'])) ?></span>
            </p>
        <?php endif; ?>
        
        <?php if ($med): ?>
            <p style="margin: 0 0 12px; font-size: 14.5px;">
                <strong>Medicamento asignado:</strong> 
                <span class="pill">
                    <?= e($med['other_medication'] ?: ($med['medication_name'] ?: 'Medicamento de stock')) ?>
                    <?= $med['purchase_needed'] ? '(comprar en farmacia)' : '' ?>
                </span>
            </p>
        <?php endif; ?>
        
        <?php if ($c['vaccine_expiry']): ?>
            <p style="margin: 0; font-size: 14px; color: var(--green-dark);">
                <i class="fa-solid fa-syringe" style="color: var(--green); margin-right: 6px;"></i> <strong>Vencimiento de vacuna:</strong> <?= e($c['vaccine_expiry']) ?> · 
                <strong>Próxima dosis:</strong> <?= e($c['next_dose']) ?>
            </p>
        <?php endif; ?>
    </div>
    
    <div class="actions">
        <a class="btn primary" href="<?= url('consultas/editar.php?id=' . (int)$id) ?>">
            <i class="fa-solid fa-pen-to-square" style="margin-right: 6px;"></i> Editar consulta
        </a>
        <a class="btn secondary" href="<?= url('mascotas/ver.php?id=' . (int)$c['pet_id']) ?>">
            <i class="fa-solid fa-arrow-left" style="margin-right: 6px;"></i> Volver al historial de <?= e_name($c['pet_name']) ?>
        </a>
    </div>
</div>

<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 10px;">
        <h2 style="margin: 0;"><i class="fa-solid fa-paperclip" style="color: var(--green); margin-right: 8px;"></i>Archivos y Documentos Adjuntos (<?= count($files) ?>)</h2>
    </div>
    
    <?php if (!$files): ?>
        <p class="muted" style="margin-bottom: 20px;">No hay archivos adjuntos en esta consulta todavía (recetas, análisis o comprobantes).</p>
    <?php else: ?>
        <div style="display: flex; flex-direction: column; gap: 10px; margin-bottom: 24px;">
            <?php foreach ($files as $f): ?>
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px 16px; background: var(--paper); border: 1px solid var(--line); border-radius: 12px; flex-wrap: wrap; gap: 10px;">
                    <div>
                        <strong style="color: var(--ink); font-size: 14.5px;"><i class="fa-solid fa-file-pdf" style="color: #dc2626; margin-right: 6px;"></i> <?= e($f['original_name']) ?></strong>
                        <div class="muted" style="font-size: 12px;">Subido el <?= e($f['created_at']) ?> por <?= e($f['uploaded_by']) ?></div>
                    </div>
                    <a class="btn secondary" href="<?= url('archivo_consulta.php?id=' . (int)$f['id']) ?>" style="font-size: 13px; padding: 8px 14px;">
                        <span>Descargar / Abrir</span>
                        <i class="fa-solid fa-arrow-up-right-from-square" style="margin-left: 6px;"></i>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Formulario moderno para adjuntar archivo con componentes Attachments y Flow Button -->
    <div style="background: #ffffff; border: 1px solid var(--line); border-radius: 18px; padding: 24px; box-shadow: 0 4px 18px rgba(24, 59, 53, 0.04);">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px; flex-wrap: wrap; gap: 8px;">
            <div>
                <h3 style="margin: 0 0 6px; font-size: 17px; font-family: 'Manrope', sans-serif; color: var(--ink);">
                    <i class="fa-solid fa-cloud-arrow-up" style="color: var(--green); margin-right: 6px;"></i>Adjuntar estudio, receta médica o comprobante
                </h3>
                <p class="muted" style="font-size: 13.5px; margin: 0;">
                    El archivo quedará disponible para la clínica y en la libreta sanitaria del tutor responsable.
                </p>
            </div>
            <span class="badge" style="background: var(--mint); color: var(--green); font-size: 11.5px; font-weight: 700; padding: 4px 10px; border-radius: 20px;">
                <i class="fa-solid fa-shield-halved" style="margin-right: 4px;"></i> Cifrado médico
            </span>
        </div>
        
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
            
            <div class="attachment-uploader">
                <!-- 1. Dropzone interactivo (Attachments) -->
                <div class="attachment-dropzone">
                    <div class="attachment-dropzone-icon">
                        <i class="fa-solid fa-cloud-arrow-up"></i>
                    </div>
                    <div class="attachment-dropzone-title">
                        <span>Hacé clic para seleccionar</span> o arrastrá tu archivo acá
                    </div>
                    <div class="attachment-dropzone-subtitle">
                        Formatos aceptados: PDF, JPG, PNG o WEBP (hasta 10 MB)
                    </div>
                </div>

                <input type="file" name="attachment" class="attachment-input-hidden" accept=".pdf,.jpg,.jpeg,.png,.webp" required>

                <!-- 2. Tarjeta de archivo seleccionado (Attachments Card) -->
                <div class="attachment-preview-card">
                    <div class="attachment-card-body">
                        <div class="attachment-card-left">
                            <div class="attachment-thumb"></div>
                            <div class="attachment-info">
                                <div class="attachment-filename">archivo.pdf</div>
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
                            <i class="fa-solid fa-circle-check" style="color: var(--green); margin-right: 4px;"></i> Verificado para la consulta #<?= (int)$id ?>
                        </span>

                        <!-- 3. Flow Button (@xubohuah) -->
                        <button type="submit" class="btn-flow">
                            <i class="fa-solid fa-arrow-up-from-bracket btn-flow-icon"></i>
                            <span>Subir y guardar estudio</span>
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
