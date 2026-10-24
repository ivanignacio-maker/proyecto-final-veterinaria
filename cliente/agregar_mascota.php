<?php
require_once __DIR__ . '/../config/config.php';
require_client();
csrf_check();

$error = '';

// Procesar el formulario cuando el cliente envía los datos
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name  = format_name(trim($_POST['name'] ?? ''));
    $type  = format_name(trim($_POST['animal_type'] ?? ''));
    $sexo  = trim($_POST['sexo'] ?? '');
    $breed = format_name(trim($_POST['breed'] ?? ''));

    if (!in_array($sexo, ['Macho', 'Hembra'], true)) {
        $sexo = null;
    }
    
    // Convertir campos opcionales numéricos a null si están vacíos
    $weight = ($_POST['weight_kg'] ?? '') !== '' ? $_POST['weight_kg'] : null;
    $height = ($_POST['height_cm'] ?? '') !== '' ? $_POST['height_cm'] : null;
    
    if (!$name || !$type || !$sexo) {
        $error = 'El nombre, tipo de animal y sexo son obligatorios.';
    } else {
        $photoRel = null;
        if (!empty($_FILES['photo']['name'])) {
            $f = $_FILES['photo'];
            $mime = $f['error'] === UPLOAD_ERR_OK ? (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']) : '';
            $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
            if ($f['error'] !== UPLOAD_ERR_OK || $f['size'] > 3 * 1024 * 1024 || !isset($allowed[$mime])) {
                $error = 'La foto debe ser formato JPG, PNG o WEBP y pesar hasta 3 MB.';
            } else {
                $dir = ensure_upload_dir() . DIRECTORY_SEPARATOR . 'pets';
                if (!is_dir($dir)) mkdir($dir, 0750, true);
                $rel = 'pets/' . bin2hex(random_bytes(20)) . '.' . $allowed[$mime];
                if (move_uploaded_file($f['tmp_name'], ensure_upload_dir() . DIRECTORY_SEPARATOR . $rel)) {
                    $photoRel = $rel;
                }
            }
        }

        if ($error === '') {
            $q = db()->prepare("
                INSERT INTO pets(client_id, name, animal_type, sexo, breed, weight_kg, height_cm, birth_date, photo_path) 
                VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $q->execute([
                $_SESSION['client_id'], 
                $name, 
                $type, 
                $sexo,
                $breed ?: null, 
                $weight, 
                $height, 
                $_POST['birth_date'] ?: null,
                $photoRel
            ]);
            
            flash('Mascota agregada a tu perfil exitosamente.');
            header('Location: ' . url('cliente/mascotas.php'));
            exit;
        }
    }
}

$pageTitle = 'Agregar mascota';
require __DIR__ . '/../includes/header.php';
?>

<div class="panel">
    <h1><i class="fa-solid fa-paw" style="color: var(--green); margin-right: 8px;"></i>Registrar una mascota</h1>
    <p class="muted">La mascota quedará vinculada a tu cuenta. La veterinaria podrá completar su ficha médica y vacunas.</p>
    
    <?php if ($error): ?>
        <div class="notice error">
            <span class="notice-icon"><i class="fa-solid fa-triangle-exclamation"></i></span>
            <span><?= e($error) ?></span>
        </div>
    <?php endif; ?>
    
    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
        
        <div class="form-grid">
            <div>
                <label>Nombre de la mascota *</label>
                <input name="name" placeholder="Ej. Luna" required>
            </div>
            
            <div>
                <label>Tipo de animal *</label>
                <select name="animal_type" required>
                    <option value="">Seleccionar especie</option>
                    <option value="Perro">Perro</option>
                    <option value="Gato">Gato</option>
                    <option value="Ave">Ave</option>
                    <option value="Roedor">Roedor</option>
                    <option value="Reptil">Reptil</option>
                    <option value="Otro">Otro</option>
                </select>
            </div>

            <div>
                <label>Sexo *</label>
                <select name="sexo" required>
                    <option value="">Seleccionar sexo</option>
                    <option value="Macho">Macho</option>
                    <option value="Hembra">Hembra</option>
                </select>
            </div>
            
            <div>
                <label>Raza</label>
                <input name="breed" placeholder="Ej. Golden Retriever, Mestizo">
            </div>
            
            <div>
                <label>Peso aproximado (kg)</label>
                <input type="number" min="0" step=".01" name="weight_kg" placeholder="Ej. 12.5">
            </div>
            
            <div>
                <label>Altura aproximada (cm)</label>
                <input type="number" min="0" step=".1" name="height_cm" placeholder="Ej. 45">
            </div>
            
            <div>
                <label>Fecha de nacimiento estimada</label>
                <input type="date" name="birth_date">
            </div>

            <div>
                <label>Foto de perfil (opcional)</label>
                <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" style="font-size: 13px;">
                <div style="font-size: 11px; color: var(--muted); margin-top: 4px;">JPG, PNG o WEBP. Máximo 3 MB.</div>
            </div>
        </div>
        
        <div class="actions">
            <button class="btn primary" type="submit">
                <i class="fa-solid fa-paw" style="margin-right: 6px;"></i> Guardar mascota
            </button>
            <a class="btn secondary" href="<?= url('cliente/mascotas.php') ?>">Cancelar</a>
        </div>
    </form>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
