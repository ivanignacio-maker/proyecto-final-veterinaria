<?php
require_once __DIR__ . '/config/config.php';

$petId = (int)($_GET['id'] ?? 0);

// Buscar la información de la mascota en la base de datos
$q = db()->prepare("SELECT photo_path, client_id, animal_type, breed FROM pets WHERE id = ?");
$q->execute([$petId]);
$p = $q->fetch();

// Verificar si la mascota existe
if (!$p) {
    http_response_code(404);
    exit;
}

// Validar permisos: debe ser un veterinario logueado o el dueño de la mascota
$allowed = !empty($_SESSION['admin_id']) ||
           !empty($_SESSION['vet_user_id']) || 
           (!empty($_SESSION['client_id']) && is_client_pet_owner((int)$_SESSION['client_id'], $petId));

if (!$allowed) {
    http_response_code(403);
    exit;
}

// Usar la imagen genérica según su raza/especie si todavía no se cargó una foto para esta mascota.
$path = !empty($p['photo_path'])
    ? ensure_upload_dir() . DIRECTORY_SEPARATOR . $p['photo_path']
    : '';

if (!$path || !is_file($path)) {
    $generic = get_pet_generic_avatar($p['animal_type'] ?? '', $p['breed'] ?? '');
    $path = __DIR__ . '/assets/' . $generic;
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
header('Cache-Control: private, no-cache, must-revalidate');
readfile($path);
