<?php
require_once __DIR__ . '/../config/config.php';
require_client();
csrf_check();

// 1. Verificar que la petición sea POST, de lo contrario redirigir
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . url('cliente/mascotas.php'));
    exit;
}

$cid = (int)($_POST['consultation_id'] ?? 0);

// 2. Validar que la consulta exista y la mascota pertenezca al cliente actual
$q = db()->prepare("
    SELECT co.id, co.pet_id 
    FROM consultations co 
    JOIN pets p ON p.id = co.pet_id 
    WHERE co.id = ? AND p.client_id = ?
");
$q->execute([$cid, $_SESSION['client_id']]);
$c = $q->fetch();

if (!$c) {
    exit('Consulta no autorizada.');
}

// 3. Validar el archivo enviado (existencia, errores en la subida y tamaño máximo de 5 MB)
$f = $_FILES['attachment'] ?? null;

if (!$f || $f['error'] !== UPLOAD_ERR_OK || $f['size'] > 5 * 1024 * 1024) {
    exit('Archivo inválido o mayor a 5 MB.');
}

// 4. Validar que el tipo MIME sea seguro y permitido
$mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
$allowed = [
    'application/pdf' => 'pdf',
    'image/jpeg'      => 'jpg',
    'image/png'       => 'png'
];

if (!isset($allowed[$mime])) {
    exit('Solo se aceptan PDF, JPG y PNG.');
}

// 5. Preparar el directorio de subidas y guardar el archivo con un nombre aleatorio
$dir = __DIR__ . '/../uploads';

if (!is_dir($dir)) {
    mkdir($dir, 0750, true);
}

$stored = bin2hex(random_bytes(24)) . '.' . $allowed[$mime];

if (!move_uploaded_file($f['tmp_name'], $dir . '/' . $stored)) {
    exit('No se pudo guardar el archivo.');
}

// 6. Registrar el archivo en la base de datos vinculado a la consulta
$q = db()->prepare("
    INSERT INTO consultation_files(consultation_id, uploaded_by, uploader_id, original_name, stored_name, mime_type) 
    VALUES(?, 'cliente', ?, ?, ?, ?)
");
$q->execute([
    $cid, 
    $_SESSION['client_id'], 
    basename($f['name']), 
    $stored, 
    $mime
]);

// 7. Redirigir de vuelta al historial médico de la mascota
header('Location: ' . url('cliente/historial.php?pet_id=' . (int)$c['pet_id']));
exit;