<?php
/**
 * veterinaria/api_avatar.php
 * Sube o elimina la foto de perfil de un usuario vet.
 * Solo puede actuar el dueño/jefe sobre cualquier usuario de su clínica,
 * o cualquier usuario sobre SU PROPIA foto.
 */
require_once __DIR__ . '/../config/config.php';
vet_enforce_role(['jefe', 'doctor', 'recepcionista']);

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// ── DELETE: eliminar avatar ──────────────────────────────────────────────────
if ($method === 'POST' && ($_POST['_method'] ?? '') === 'DELETE') {
    $targetId = (int)($_POST['user_id'] ?? 0);
    $myId     = (int)($_SESSION['vet_user_id'] ?? 0);
    $clinicId = (int)($_SESSION['clinic_id'] ?? 0);
    $myRole   = vet_current_role();

    // Solo el propio usuario o el dueño puede eliminar
    if ($targetId !== $myId && !vet_is_owner($myRole)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Sin permisos.']);
        exit;
    }

    // Verificar que el usuario pertenezca a la clínica
    $q = db()->prepare('SELECT avatar FROM vet_users WHERE id = ? AND clinic_id = ? LIMIT 1');
    $q->execute([$targetId, $clinicId]);
    $row = $q->fetch();
    if (!$row) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Usuario no encontrado.']);
        exit;
    }

    // Eliminar archivo
    if ($row['avatar']) {
        $path = __DIR__ . '/../' . ltrim($row['avatar'], '/');
        if (file_exists($path)) {
            @unlink($path);
        }
    }

    db()->prepare('UPDATE vet_users SET avatar = NULL WHERE id = ?')->execute([$targetId]);

    // Actualizar sesión si es el propio usuario
    if ($targetId === $myId) {
        $_SESSION['vet_avatar'] = null;
    }

    echo json_encode(['ok' => true, 'avatar' => null]);
    exit;
}

// ── POST: subir nuevo avatar ─────────────────────────────────────────────────
if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Método no permitido.']);
    exit;
}

$targetId = (int)($_POST['user_id'] ?? 0);
$myId     = (int)($_SESSION['vet_user_id'] ?? 0);
$clinicId = (int)($_SESSION['clinic_id'] ?? 0);
$myRole   = vet_current_role();

// Solo el propio usuario o el dueño puede cambiar la foto
if ($targetId !== $myId && !vet_is_owner($myRole)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Sin permisos para cambiar la foto de otro usuario.']);
    exit;
}

// Verificar que el usuario pertenezca a la clínica
$q = db()->prepare('SELECT avatar FROM vet_users WHERE id = ? AND clinic_id = ? LIMIT 1');
$q->execute([$targetId, $clinicId]);
$row = $q->fetch();
if (!$row) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Usuario no encontrado.']);
    exit;
}

// Validar archivo
if (empty($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
    $errCode = $_FILES['avatar']['error'] ?? -1;
    $msg = match($errCode) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'La imagen supera el tamaño máximo permitido (5 MB).',
        UPLOAD_ERR_NO_FILE  => 'No se recibió ningún archivo.',
        default             => 'Error al subir el archivo (código ' . $errCode . ').',
    };
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => $msg]);
    exit;
}

$file     = $_FILES['avatar'];
$maxBytes = 5 * 1024 * 1024; // 5 MB
$allowed  = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

if ($file['size'] > $maxBytes) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'La imagen no puede superar los 5 MB.']);
    exit;
}

// Verificar MIME real (no confiar solo en el Content-Type del cliente)
$finfo    = finfo_open(FILEINFO_MIME_TYPE);
$mimeReal = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mimeReal, $allowed, true)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Solo se aceptan imágenes JPG, PNG, WebP o GIF.']);
    exit;
}

$ext     = match($mimeReal) {
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
    'image/gif'  => 'gif',
    default      => 'jpg',
};

$uploadDir = __DIR__ . '/../uploads/avatars/vet/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Eliminar avatar anterior si existe
if ($row['avatar']) {
    $oldPath = __DIR__ . '/../' . ltrim($row['avatar'], '/');
    if (file_exists($oldPath)) {
        @unlink($oldPath);
    }
}

$filename  = 'vet_' . $targetId . '_' . time() . '.' . $ext;
$destPath  = $uploadDir . $filename;
$relPath   = 'uploads/avatars/vet/' . $filename;

if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'No se pudo guardar la imagen en el servidor.']);
    exit;
}

// Guardar en BD
db()->prepare('UPDATE vet_users SET avatar = ? WHERE id = ?')->execute([$relPath, $targetId]);

// Actualizar sesión si es el propio usuario
if ($targetId === $myId) {
    $_SESSION['vet_avatar'] = $relPath;
}

echo json_encode([
    'ok'     => true,
    'avatar' => url($relPath),
]);

