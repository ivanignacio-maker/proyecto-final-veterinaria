<?php
require_once __DIR__ . '/config/config.php';

$petId = (int)($_GET['id'] ?? 0);

// Buscar la información de la mascota en la base de datos
$q = db()->prepare("SELECT photo_path, client_id FROM pets WHERE id = ?");
$q->execute([$petId]);
$p = $q->fetch();

// Verificar si la mascota existe
if (!$p) {
    http_response_code(404);
    exit;
}

// Validar permisos: debe ser un veterinario logueado o el dueño de la mascota
$allowed = !empty($_SESSION['vet_user_id']) || 
           (!empty($_SESSION['client_id']) && (int)$_SESSION['client_id'] === (int)$p['client_id']);

if (!$allowed) {
    http_response_code(403);
    exit;
}

// Construir la ruta física del archivo
$path = ensure_upload_dir() . DIRECTORY_SEPARATOR . ($p['photo_path'] ?? '');

// Verificar que la ruta exista en la base de datos y el archivo físico esté en el servidor
if (!$p['photo_path'] || !is_file($path)) {
    http_response_code(404);
    exit;
}

// Verificar que el tipo MIME sea seguro (solo imágenes permitidas)
$mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);

if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
    http_response_code(415);
    exit;
}

// Enviar el archivo al navegador con las cabeceras correspondientes
header('Content-Type: ' . $mime);
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=3600');
readfile($path);