<?php
require_once __DIR__ . '/config/config.php';

$fileId = (int)($_GET['id'] ?? 0);

// Buscar la información del archivo y sus relaciones (consulta, mascota, cliente y clínica)
$q = db()->prepare("
    SELECT f.*, co.pet_id, p.client_id, co.clinic_id 
    FROM consultation_files f 
    JOIN consultations co ON co.id = f.consultation_id 
    JOIN pets p ON p.id = co.pet_id 
    WHERE f.id = ?
");
$q->execute([$fileId]);
$f = $q->fetch();

// Verificar si el registro del archivo existe en la base de datos
if (!$f) {
    http_response_code(404);
    exit;
}

// Validar permisos: el usuario debe ser el cliente dueño de la mascota o pertenecer a la clínica
$allowed = (!empty($_SESSION['client_id']) && (int)$_SESSION['client_id'] === (int)$f['client_id']) || 
           (!empty($_SESSION['clinic_id']) && (int)$_SESSION['clinic_id'] === (int)$f['clinic_id']);

if (!$allowed) {
    http_response_code(403);
    exit('No autorizado.');
}

// Construir la ruta física del archivo en el servidor
$path = ensure_upload_dir() . DIRECTORY_SEPARATOR . $f['stored_name'];

// Verificar que el archivo exista físicamente
if (!is_file($path)) {
    http_response_code(404);
    exit;
}

// Verificar que el tipo MIME sea seguro (solo PDF, JPEG y PNG permitidos)
$mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);

if (!in_array($mime, ['application/pdf', 'image/jpeg', 'image/png'], true)) {
    http_response_code(415);
    exit;
}

// Configurar cabeceras HTTP para forzar la descarga del archivo de forma segura
header('Content-Type: ' . $mime);
header('X-Content-Type-Options: nosniff');
header('Content-Disposition: attachment; filename="' . str_replace('"', '', basename($f['original_name'])) . '"');

// Enviar el contenido del archivo
readfile($path);