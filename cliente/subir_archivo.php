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

$redirectToHistory = static function (string $error) use ($c): void {
    $query = http_build_query([
        'pet_id' => (int)$c['pet_id'],
        'upload_error' => $error,
        'consultation_id' => (int)$c['id']
    ]);
    header('Location: ' . url('cliente/historial.php?' . $query));
    exit;
};

// 3. Validar el archivo enviado (existencia, errores en la subida y tamaño máximo de 5 MB)
$f = $_FILES['attachment'] ?? null;

if (!$f || $f['error'] !== UPLOAD_ERR_OK || $f['size'] > 5 * 1024 * 1024) {
    $redirectToHistory('invalid');
}

// 4. Validar que el tipo MIME sea seguro y permitido
$mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
$allowedMimes = [
    'application/pdf',
    'image/jpeg',
    'image/png',
    'image/webp'
];

if (!in_array($mime, $allowedMimes, true)) {
    $redirectToHistory('invalid');
}

// 5. Guardar archivo: si es imagen, convertir a WebP automáticamente
$dir = ensure_upload_dir();
$isImage = strpos($mime, 'image/') === 0;
$saveExt = $isImage ? 'webp' : 'pdf';
$stored = bin2hex(random_bytes(24)) . '.' . $saveExt;
$destPath = $dir . DIRECTORY_SEPARATOR . $stored;

$saved = false;
if ($isImage) {
    $saved = convert_image_to_webp($f['tmp_name'], $destPath, 85);
    $mime = 'image/webp';
} else {
    $saved = move_uploaded_file($f['tmp_name'], $destPath);
}

if (!$saved) {
    $redirectToHistory('save');
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
header('Location: ' . url('cliente/historial.php?' . http_build_query([
    'pet_id' => (int)$c['pet_id'],
    'upload_success' => 1,
    'consultation_id' => (int)$c['id']
])));
exit;
