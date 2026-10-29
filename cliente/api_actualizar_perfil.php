<?php
/**
 * API: Actualizar perfil del tutor (cliente)
 * 
 * Recibe datos vía POST (JSON o multipart/form-data) y actualiza
 * full_name, email, phone y opcionalmente avatar del cliente logueado.
 * 
 * Responde siempre en JSON.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/password_policy.php';

header('Content-Type: application/json; charset=utf-8');

// Verificar que el cliente esté autenticado
if (empty($_SESSION['client_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'No autenticado.']);
    exit;
}

// Solo aceptar POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Método no permitido.']);
    exit;
}

$clientId = (int)$_SESSION['client_id'];

// Detectar si viene como JSON o como form-data (para subida de avatar)
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';

if (strpos($contentType, 'application/json') !== false) {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'JSON inválido.']);
        exit;
    }
    $fullName = trim($input['full_name'] ?? '');
    $email    = trim($input['email'] ?? '');
    $phone    = trim($input['phone'] ?? '');
    $currentPassword = is_string($input['current_password'] ?? null) ? $input['current_password'] : '';
    $newPassword = is_string($input['new_password'] ?? null) ? $input['new_password'] : '';
    $confirmPassword = is_string($input['confirm_password'] ?? null) ? $input['confirm_password'] : '';
    $hasFile  = false;
} else {
    // multipart/form-data (con posible avatar)
    $fullName = trim($_POST['full_name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $currentPassword = is_string($_POST['current_password'] ?? null) ? $_POST['current_password'] : '';
    $newPassword = is_string($_POST['new_password'] ?? null) ? $_POST['new_password'] : '';
    $confirmPassword = is_string($_POST['confirm_password'] ?? null) ? $_POST['confirm_password'] : '';
    $hasFile  = !empty($_FILES['avatar']['name']);
}
$changingPassword = $currentPassword !== '' || $newPassword !== '' || $confirmPassword !== '';

// Validaciones
if ($fullName === '' || $email === '') {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Nombre y correo electrónico son obligatorios.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'El correo electrónico no tiene un formato válido.']);
    exit;
}

if ($changingPassword) {
    if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'Completá la contraseña actual, la nueva y su confirmación.']);
        exit;
    }
    $passwordError = password_policy_error($newPassword);
    if ($passwordError !== null) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => $passwordError]);
        exit;
    }
    if (!hash_equals($newPassword, $confirmPassword)) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'La nueva contraseña y su confirmación no coinciden.']);
        exit;
    }
    try {
        $passwordQuery = db()->prepare('SELECT password_hash FROM clients WHERE id = ?');
        $passwordQuery->execute([$clientId]);
        $currentHash = $passwordQuery->fetchColumn();
        if (!$currentHash || !password_verify($currentPassword, $currentHash)) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'error' => 'La contraseña actual es incorrecta.']);
            exit;
        }
    } catch (Throwable $e) {
        error_log('Error verificando contraseña del tutor: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'No se pudo verificar la contraseña actual.']);
        exit;
    }
}

$avatarPath = null;
$oldAvatarPath = null;
$updateAvatar = false;

// Procesar avatar si se subió uno
if ($hasFile) {
    $f = $_FILES['avatar'];
    $mime = $f['error'] === UPLOAD_ERR_OK ? (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']) : '';

    $allowedMimes = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp'
    ];

    if ($f['error'] !== UPLOAD_ERR_OK || $f['size'] > 3 * 1024 * 1024 || !isset($allowedMimes[$mime])) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'La foto debe ser JPG, PNG o WEBP y pesar hasta 3 MB.']);
        exit;
    }

    // Crear directorio de avatares si no existe
    $dir = ensure_upload_dir() . DIRECTORY_SEPARATOR . 'avatars';
    if (!is_dir($dir)) {
        mkdir($dir, 0750, true);
    }

    // Conservar el avatar actual hasta confirmar que el nuevo se guardó.
    $q = db()->prepare("SELECT avatar_path FROM clients WHERE id = ?");
    $q->execute([$clientId]);
    $oldAvatarPath = $q->fetchColumn() ?: null;

    // Guardar nuevo avatar
    $ext = $allowedMimes[$mime];
    $avatarPath = 'avatars/client_' . $clientId . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $destPath = ensure_upload_dir() . DIRECTORY_SEPARATOR . $avatarPath;

    if (!move_uploaded_file($f['tmp_name'], $destPath)) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'No se pudo guardar la imagen.']);
        exit;
    }

    $updateAvatar = true;
}

try {
    $pdo = db();

    // Construir query de actualización
    if ($updateAvatar && $changingPassword) {
        $q = $pdo->prepare("UPDATE clients SET full_name = ?, email = ?, phone = ?, avatar_path = ?, password_hash = ? WHERE id = ?");
        $q->execute([format_name($fullName), strtolower($email), $phone, $avatarPath, password_hash($newPassword, PASSWORD_DEFAULT), $clientId]);
        session_regenerate_id(true);
        if ($oldAvatarPath) {
            $oldFile = ensure_upload_dir() . DIRECTORY_SEPARATOR . $oldAvatarPath;
            if (is_file($oldFile)) {
                @unlink($oldFile);
            }
        }
    } elseif ($updateAvatar) {
        $q = $pdo->prepare("UPDATE clients SET full_name = ?, email = ?, phone = ?, avatar_path = ? WHERE id = ?");
        $q->execute([format_name($fullName), strtolower($email), $phone, $avatarPath, $clientId]);
        if ($oldAvatarPath) {
            $oldFile = ensure_upload_dir() . DIRECTORY_SEPARATOR . $oldAvatarPath;
            if (is_file($oldFile)) {
                @unlink($oldFile);
            }
        }
    } elseif ($changingPassword) {
        $q = $pdo->prepare("UPDATE clients SET full_name = ?, email = ?, phone = ?, password_hash = ? WHERE id = ?");
        $q->execute([format_name($fullName), strtolower($email), $phone, password_hash($newPassword, PASSWORD_DEFAULT), $clientId]);
        session_regenerate_id(true);
    } else {
        $q = $pdo->prepare("UPDATE clients SET full_name = ?, email = ?, phone = ? WHERE id = ?");
        $q->execute([format_name($fullName), strtolower($email), $phone, $clientId]);
    }

    // Actualizar nombre en sesión para que se refleje en el header
    $_SESSION['client_name'] = format_name($fullName);

    // Devolver datos actualizados
    $q = $pdo->prepare("SELECT id, full_name, email, phone, avatar_path FROM clients WHERE id = ?");
    $q->execute([$clientId]);
    $updated = $q->fetch();

    echo json_encode([
        'ok'   => true,
        'data' => $updated
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    error_log('Error actualizando perfil de cliente: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Error interno del servidor.']);
}
