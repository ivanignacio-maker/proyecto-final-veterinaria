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

// Mensaje claro según por qué falló la foto (tipo no aceptado, tamaño o error de subida).
function photo_upload_error(array $f, string $mime): ?string {
    if ($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE || $f['size'] > 5 * 1024 * 1024) {
        return 'La foto pesa demasiado. El máximo es 5 MB.';
    }
    if ($f['error'] !== UPLOAD_ERR_OK) {
        return 'No se pudo recibir la foto. Volvé a intentarlo.';
    }
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
        return 'Ese tipo de archivo no es aceptado. Usá una foto JPG, PNG o WEBP.';
    }
    return null;
}

// Si el archivo supera post_max_size, PHP descarta TODO el formulario (queda vacío).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && empty($_FILES) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0
    && strpos($_SERVER['CONTENT_TYPE'] ?? '', 'multipart/form-data') !== false) {
    http_response_code(413);
    echo json_encode(['ok' => false, 'error' => 'El archivo es demasiado grande. Elegí una foto de hasta 3 MB.']);
    exit;
}

// Detectar si viene como JSON o como form-data (para subida de avatar)
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';

if (strpos($contentType, 'application/json') !== false) {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'JSON inválido.']);
        exit;
    }
    $nombre   = trim($input['nombre'] ?? ($input['first_name'] ?? ''));
    $apellido = trim($input['apellido'] ?? ($input['last_name'] ?? ''));
    $fullName = trim($input['full_name'] ?? '');
    if ($nombre === '' && $apellido === '' && $fullName !== '') {
        $parts = explode(' ', $fullName, 2);
        $nombre = $parts[0] ?? '';
        $apellido = $parts[1] ?? '';
    }
    if ($fullName === '' && ($nombre !== '' || $apellido !== '')) {
        $fullName = trim($nombre . ' ' . $apellido);
    }
    $email    = trim($input['email'] ?? '');
    $phone    = trim($input['phone'] ?? '');
    $currentPassword = is_string($input['current_password'] ?? null) ? $input['current_password'] : '';
    $newPassword = is_string($input['new_password'] ?? null) ? $input['new_password'] : '';
    $confirmPassword = is_string($input['confirm_password'] ?? null) ? $input['confirm_password'] : '';
    $hasFile  = false;
} else {
    // multipart/form-data (con posible avatar)
    $nombre   = trim($_POST['nombre'] ?? ($_POST['first_name'] ?? ''));
    $apellido = trim($_POST['apellido'] ?? ($_POST['last_name'] ?? ''));
    $fullName = trim($_POST['full_name'] ?? '');
    if ($nombre === '' && $apellido === '' && $fullName !== '') {
        $parts = explode(' ', $fullName, 2);
        $nombre = $parts[0] ?? '';
        $apellido = $parts[1] ?? '';
    }
    if ($fullName === '' && ($nombre !== '' || $apellido !== '')) {
        $fullName = trim($nombre . ' ' . $apellido);
    }
    $email    = trim($_POST['email'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $currentPassword = is_string($_POST['current_password'] ?? null) ? $_POST['current_password'] : '';
    $newPassword = is_string($_POST['new_password'] ?? null) ? $_POST['new_password'] : '';
    $confirmPassword = is_string($_POST['confirm_password'] ?? null) ? $_POST['confirm_password'] : '';
    $hasFile  = !empty($_FILES['avatar']['name']);
}
$changingPassword = $currentPassword !== '' || $newPassword !== '' || $confirmPassword !== '';

// Validaciones
if ($nombre === '' || $apellido === '' || $email === '') {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Nombre, apellido y correo electrónico son obligatorios.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'El correo electrónico no tiene un formato válido.']);
    exit;
}

if ($changingPassword) {
    if ($newPassword === '' || $confirmPassword === '') {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'Completá la nueva contraseña y su confirmación.']);
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
        if ($currentHash !== null && $currentHash !== '') {
            if ($currentPassword === '') {
                http_response_code(422);
                echo json_encode(['ok' => false, 'error' => 'Ingresá tu contraseña actual para confirmar el cambio.']);
                exit;
            }
            if (!password_verify($currentPassword, $currentHash)) {
                http_response_code(422);
                echo json_encode(['ok' => false, 'error' => 'La contraseña actual es incorrecta.']);
                exit;
            }
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
    $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];

    $photoError = photo_upload_error($f, $mime);
    if ($photoError !== null) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => $photoError], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Conservar el avatar actual hasta confirmar que el nuevo se guardó.
    $q = db()->prepare("SELECT avatar_path FROM clients WHERE id = ?");
    $q->execute([$clientId]);
    $oldAvatarPath = $q->fetchColumn() ?: null;

    $saved = save_uploaded_image_as_webp($f, 'avatars', 'client_' . $clientId . '_' . bin2hex(random_bytes(8)));
    if ($saved === null) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'No se pudo procesar la imagen a formato WebP.']);
        exit;
    }

    $avatarPath = $saved;
    $updateAvatar = true;
}

try {
    $pdo = db();

    // Construir query de actualización
    if ($updateAvatar && $changingPassword) {
        $q = $pdo->prepare("UPDATE clients SET nombre = ?, apellido = ?, first_name = ?, last_name = ?, full_name = ?, email = ?, phone = ?, avatar_path = ?, password_hash = ? WHERE id = ?");
        $q->execute([format_name($nombre), format_name($apellido), format_name($nombre), format_name($apellido), format_name($fullName), strtolower($email), $phone, $avatarPath, password_hash($newPassword, PASSWORD_DEFAULT), $clientId]);
        session_regenerate_id(true);
        if ($oldAvatarPath) {
            $oldFile = ensure_upload_dir() . DIRECTORY_SEPARATOR . $oldAvatarPath;
            if (is_file($oldFile)) {
                @unlink($oldFile);
            }
        }
    } elseif ($updateAvatar) {
        $q = $pdo->prepare("UPDATE clients SET nombre = ?, apellido = ?, first_name = ?, last_name = ?, full_name = ?, email = ?, phone = ?, avatar_path = ? WHERE id = ?");
        $q->execute([format_name($nombre), format_name($apellido), format_name($nombre), format_name($apellido), format_name($fullName), strtolower($email), $phone, $avatarPath, $clientId]);
        if ($oldAvatarPath) {
            $oldFile = ensure_upload_dir() . DIRECTORY_SEPARATOR . $oldAvatarPath;
            if (is_file($oldFile)) {
                @unlink($oldFile);
            }
        }
    } elseif ($changingPassword) {
        $q = $pdo->prepare("UPDATE clients SET nombre = ?, apellido = ?, first_name = ?, last_name = ?, full_name = ?, email = ?, phone = ?, password_hash = ? WHERE id = ?");
        $q->execute([format_name($nombre), format_name($apellido), format_name($nombre), format_name($apellido), format_name($fullName), strtolower($email), $phone, password_hash($newPassword, PASSWORD_DEFAULT), $clientId]);
        session_regenerate_id(true);
    } else {
        $q = $pdo->prepare("UPDATE clients SET nombre = ?, apellido = ?, first_name = ?, last_name = ?, full_name = ?, email = ?, phone = ? WHERE id = ?");
        $q->execute([format_name($nombre), format_name($apellido), format_name($nombre), format_name($apellido), format_name($fullName), strtolower($email), $phone, $clientId]);
    }

    // Actualizar nombre en sesión para que se refleje en el header
    $_SESSION['client_name'] = format_name($fullName);

    // Devolver datos actualizados
    $q = $pdo->prepare("SELECT id, nombre, apellido, first_name, last_name, full_name, email, phone, avatar_path FROM clients WHERE id = ?");
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
