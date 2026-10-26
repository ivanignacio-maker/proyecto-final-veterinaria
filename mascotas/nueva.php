<?php
require_once __DIR__ . '/../config/config.php';
require_vet();
csrf_check();

// Obtener el ID del cliente y verificar que exista
$clientId = (int)($_GET['client_id'] ?? $_POST['client_id'] ?? 0);
$q = db()->prepare("SELECT * FROM clients WHERE id = ?");
$q->execute([$clientId]);
$client = $q->fetch();

if (!$client) {
    exit('Cliente no encontrado');
}

$error = '';

// 1. Procesar el formulario de creación de mascota
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = format_name(trim($_POST['name'] ?? ''));
    $type = format_name(trim($_POST['animal_type'] ?? ''));
    $sexo = trim($_POST['sexo'] ?? '');
    $breed = format_name(trim($_POST['breed'] ?? ''));

    if (!in_array($sexo, ['Macho', 'Hembra'], true)) {
        $sexo = null;
    }
    
    // Convertir campos opcionales numéricos
    $weight = ($_POST['weight_kg'] ?? '') !== '' ? $_POST['weight_kg'] : null;
    $height = ($_POST['height_cm'] ?? '') !== '' ? $_POST['height_cm'] : null;
    
    $photo = null;

    // Validación de campos obligatorios
    if (!$name || !$type || !$sexo) {
        $error = 'Nombre, tipo de animal y sexo son obligatorios.';
    }

    // 2. Procesar subida de foto (si no hay errores previos y se envió un archivo)
    if (!$error && !empty($_FILES['photo']['name'])) {
        $f = $_FILES['photo'];
        
        // Obtener el tipo MIME real del archivo
        $mime = $f['error'] === UPLOAD_ERR_OK ? (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']) : '';
        
        $allowed = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp'
        ];

        // Validar que el archivo subió correctamente, pesa hasta 3 MB y es un formato permitido
        if ($f['error'] !== UPLOAD_ERR_OK || $f['size'] > 3 * 1024 * 1024 || !isset($allowed[$mime])) {
            $error = 'La foto debe ser JPG, PNG o WEBP y pesar hasta 3 MB.';
        } else {
            // Asegurar que el directorio de mascotas existe
            $dir = ensure_upload_dir() . DIRECTORY_SEPARATOR . 'pets';
            if (!is_dir($dir)) {
                mkdir($dir, 0750, true);
            }
            
            // Generar nombre de archivo único
            $photo = 'pets/' . bin2hex(random_bytes(20)) . '.' . $allowed[$mime];
            
            // Mover archivo a su ruta final
            if (!move_uploaded_file($f['tmp_name'], ensure_upload_dir() . DIRECTORY_SEPARATOR . $photo)) {
                $error = 'No se pudo guardar la foto.';
            }
        }
    }

    // 3. Guardar en base de datos si no hay errores
    if (!$error) {
        $q = db()->prepare("INSERT INTO pets(client_id, name, animal_type, sexo, breed, weight_kg, height_cm, birth_date, photo_path) VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $q->execute([
            $clientId, 
            $name, 
            $type, 
            $sexo,
            $breed ?: null, 
            $weight, 
            $height, 
            $_POST['birth_date'] ?: null, 
            $photo
        ]);
        
        $pid = (int)db()->lastInsertId();
        
        // Registrar acción para auditoría
        audit_log('pet', $pid, 'created', ['name' => $name, 'client_id' => $clientId]);
        
        flash('Mascota registrada exitosamente.');
        header('Location: ' . url('mascotas/ver.php?id=' . $pid));
        exit;
    }
}

$pageTitle = 'Registrar Mascota | ' . APP_NAME;
require __DIR__ . '/../includes/header.php';
?>

<div class="panel">
    <h1><i class="fa-solid fa-paw" style="color: var(--green); margin-right: 8px;"></i>Registrar nueva mascota</h1>
    
    <p class="muted">
        Tutor responsable: <strong><?= e_name($client['full_name']) ?></strong> · DNI: <strong><?= e($client['dni']) ?></strong>
    </p>
    
    <?php if ($error): ?>
        <div class="notice error">
            <span class="notice-icon"><i class="fa-solid fa-triangle-exclamation"></i></span>
            <span><?= e($error) ?></span>
        </div>
    <?php endif; ?>
    
    <form method="post" enctype="multipart/form-data" style="margin-top: 18px;">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
        <input type="hidden" name="client_id" value="<?= $clientId ?>">

        <!-- Vista previa dinámica del avatar según tipo de animal -->
        <div class="pet-avatar-preview-box" style="display: flex; align-items: center; gap: 18px; padding: 16px 20px; background: var(--paper); border: 1.5px solid var(--line); border-radius: 18px; margin-bottom: 22px;">
            <div style="position: relative; width: 76px; height: 76px; flex-shrink: 0;">
                <img id="petAvatarPreviewImg" src="<?= url('assets/otro_generico.png') ?>" alt="Avatar" style="width: 100%; height: 100%; border-radius: 18px; object-fit: cover; border: 2.5px solid var(--green); box-shadow: 0 4px 14px rgba(35,119,101,0.15); transition: transform 0.15s ease;">
            </div>
            <div style="flex: 1;">
                <div style="font-weight: 700; font-size: 15px; color: var(--ink);" id="petAvatarBadgeTitle">
                    Avatar ilustrado: Otro
                </div>
                <p class="muted" style="margin: 4px 0 0; font-size: 13px;" id="petAvatarBadgeSub">
                    Seleccioná un tipo de animal para asignar su avatar o adjuntá una foto propia.
                </p>
            </div>
        </div>
        
        <div class="form-grid">
            <div>
                <label>Nombre de la mascota</label>
                <input name="name" required placeholder="Ej. Milo">
            </div>
            
            <div>
                <label>Tipo de animal</label>
                <select name="animal_type" required>
                    <option value="">Seleccionar especie...</option>
                    <option>Perro</option>
                    <option>Gato</option>
                    <option>Ave</option>
                    <option>Roedor</option>
                    <option>Reptil</option>
                    <option>Otro</option>
                </select>
            </div>

            <div>
                <label>Sexo *</label>
                <select name="sexo" required>
                    <option value="">Seleccionar sexo...</option>
                    <option value="Macho">Macho</option>
                    <option value="Hembra">Hembra</option>
                </select>
            </div>
            
            <div>
                <label>Raza</label>
                <input name="breed" placeholder="Ej. Golden Retriever">
            </div>
            
            <div>
                <label>Peso aproximado (kg)</label>
                <input type="number" min="0" step=".01" name="weight_kg" placeholder="Ej. 14.5">
            </div>
            
            <div>
                <label>Altura (cm)</label>
                <input type="number" min="0" step=".1" name="height_cm" placeholder="Ej. 45">
            </div>
            
            <div>
                <label>Fecha de nacimiento estimada</label>
                <input type="date" name="birth_date">
            </div>
            
            <div style="grid-column: 1 / -1;">
                <label>Foto de perfil (opcional, máx. 3 MB)</label>
                <input type="file" name="photo" accept=".jpg,.jpeg,.png,.webp">
                <small class="muted" style="display: block; margin-top: 4px;">Admite imágenes JPG, PNG o WEBP. Máximo 3 MB.</small>
            </div>
        </div>
        
        <div class="actions">
            <button type="submit" class="btn primary">
                <span>Guardar mascota</span>
                <i class="fa-solid fa-arrow-right" style="margin-left: 6px;"></i>
            </button>
            <a class="btn secondary" href="<?= url('clientes/ver.php?id=' . $clientId) ?>">Cancelar</a>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    setupPetAvatarLivePreview({
        typeInput: document.querySelector('select[name="animal_type"]'),
        photoInput: document.querySelector('input[name="photo"]'),
        previewImg: document.getElementById('petAvatarPreviewImg'),
        titleEl: document.getElementById('petAvatarBadgeTitle'),
        subEl: document.getElementById('petAvatarBadgeSub')
    });
});
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
