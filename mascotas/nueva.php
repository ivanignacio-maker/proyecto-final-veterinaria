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
    $name = trim($_POST['name'] ?? '');
    $type = trim($_POST['animal_type'] ?? '');
    $breed = trim($_POST['breed'] ?? '');
    
    // Convertir campos opcionales numéricos
    $weight = ($_POST['weight_kg'] ?? '') !== '' ? $_POST['weight_kg'] : null;
    $height = ($_POST['height_cm'] ?? '') !== '' ? $_POST['height_cm'] : null;
    
    $photo = null;

    // Validación de campos obligatorios
    if (!$name || !$type) {
        $error = 'Nombre y tipo de animal son obligatorios.';
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
        $q = db()->prepare("INSERT INTO pets(client_id, name, animal_type, breed, weight_kg, height_cm, birth_date, photo_path) VALUES(?, ?, ?, ?, ?, ?, ?, ?)");
        $q->execute([
            $clientId, 
            $name, 
            $type, 
            $breed ?: null, 
            $weight, 
            $height, 
            $_POST['birth_date'] ?: null, 
            $photo
        ]);
        
        $pid = (int)db()->lastInsertId();
        
        // Registrar acción para auditoría
        audit_log('pet', $pid, 'created', ['name' => $name, 'client_id' => $clientId]);
        
        header('Location: ' . url('mascotas/ver.php?id=' . $pid));
        exit;
    }
}

$pageTitle = 'Nueva mascota';
require __DIR__ . '/../includes/header.php';
?>

<div class="panel">
    <h1>Agregar mascota</h1>
    
    <p class="muted">
        Responsable: <strong><?= e($client['full_name']) ?></strong> · DNI <?= e($client['dni']) ?>
    </p>
    
    <?php if ($error): ?>
        <p role="alert"><?= e($error) ?></p>
    <?php endif; ?>
    
    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
        <input type="hidden" name="client_id" value="<?= $clientId ?>">
        
        <div class="form-grid">
            <div>
                <label>Nombre</label>
                <input name="name" required>
            </div>
            
            <div>
                <label>Tipo de animal</label>
                <select name="animal_type" required>
                    <option value="">Seleccionar</option>
                    <option>Perro</option>
                    <option>Gato</option>
                    <option>Ave</option>
                    <option>Roedor</option>
                    <option>Reptil</option>
                    <option>Otro</option>
                </select>
            </div>
            
            <div>
                <label>Raza</label>
                <input name="breed">
            </div>
            
            <div>
                <label>Peso (kg)</label>
                <input type="number" min="0" step=".01" name="weight_kg">
            </div>
            
            <div>
                <label>Altura (cm)</label>
                <input type="number" min="0" step=".1" name="height_cm">
            </div>
            
            <div>
                <label>Fecha de nacimiento</label>
                <input type="date" name="birth_date">
            </div>
            
            <div>
                <label>Foto de perfil (opcional, máx. 3 MB)</label>
                <input type="file" name="photo" accept=".jpg,.jpeg,.png,.webp">
            </div>
        </div>
        
        <div class="actions">
            <button>Guardar mascota</button>
            <a class="btn secondary" href="<?= url('clientes/ver.php?id=' . $clientId) ?>">Cancelar</a>
        </div>
    </form>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>