<?php
/**
 * Servir avatar del cliente de forma segura.
 * Uso: archivo_avatar.php?id=123
 */
require_once __DIR__ . '/config/config.php';

$clientId = (int)($_GET['id'] ?? 0);

// Buscar avatar del cliente
$q = db()->prepare("SELECT avatar_path FROM clients WHERE id = ?");
$q->execute([$clientId]);
$row = $q->fetch();

if (!$row) {
    http_response_code(404);
    exit;
}

// Validar permisos: el propio cliente, un veterinario logueado, o un admin
$allowed = (!empty($_SESSION['client_id']) && (int)$_SESSION['client_id'] === $clientId)
        || !empty($_SESSION['vet_user_id'])
        || !empty($_SESSION['admin_id']);

if (!$allowed) {
    http_response_code(403);
    exit;
}

$path = !empty($row['avatar_path'])
    ? ensure_upload_dir() . DIRECTORY_SEPARATOR . $row['avatar_path']
    : '';

if (!$path || !is_file($path)) {
    $path = __DIR__ . '/assets/persona_generica.webp';
}

$mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);

if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
    http_response_code(415);
    exit;
}

header('Content-Type: ' . $mime);
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-cache, must-revalidate');
readfile($path);
