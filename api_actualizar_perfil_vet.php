<?php
/**
 * API: Actualizar perfil del usuario veterinario
 * 
 * Recibe datos vía POST (JSON) y actualiza username y email
 * del vet_user logueado. La contraseña se puede cambiar opcionalmente.
 * 
 * Responde siempre en JSON.
 */
require_once __DIR__ . '/config/config.php';

header('Content-Type: application/json; charset=utf-8');

// Verificar que el veterinario esté autenticado
if (empty($_SESSION['vet_user_id']) || empty($_SESSION['clinic_id'])) {
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

$vetUserId = (int)$_SESSION['vet_user_id'];
$clinicId  = (int)$_SESSION['clinic_id'];

// Leer el cuerpo JSON
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'JSON inválido.']);
    exit;
}

$username    = trim($input['username'] ?? '');
$email       = trim($input['email'] ?? '');
$newPassword = $input['new_password'] ?? '';

// Validaciones
if ($username === '' || $email === '') {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Nombre de usuario y correo electrónico son obligatorios.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'El correo electrónico no tiene un formato válido.']);
    exit;
}

if ($newPassword !== '' && strlen($newPassword) < 6) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'La nueva contraseña debe tener al menos 6 caracteres.']);
    exit;
}

try {
    $pdo = db();

    // Verificar que el usuario exista y pertenezca a la clínica actual
    $q = $pdo->prepare("SELECT id FROM vet_users WHERE id = ? AND clinic_id = ?");
    $q->execute([$vetUserId, $clinicId]);
    if (!$q->fetch()) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Usuario no encontrado.']);
        exit;
    }

    // Verificar que el email no esté duplicado en la misma clínica (excluyendo al usuario actual)
    $q = $pdo->prepare("SELECT id FROM vet_users WHERE clinic_id = ? AND LOWER(email) = LOWER(?) AND id != ?");
    $q->execute([$clinicId, $email, $vetUserId]);
    if ($q->fetch()) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'Ese correo electrónico ya está en uso por otro usuario de la clínica.']);
        exit;
    }

    // Actualizar datos
    if ($newPassword !== '') {
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $q = $pdo->prepare("UPDATE vet_users SET username = ?, email = ?, password_hash = ? WHERE id = ? AND clinic_id = ?");
        $q->execute([$username, strtolower($email), $hash, $vetUserId, $clinicId]);
    } else {
        $q = $pdo->prepare("UPDATE vet_users SET username = ?, email = ? WHERE id = ? AND clinic_id = ?");
        $q->execute([$username, strtolower($email), $vetUserId, $clinicId]);
    }

    // Actualizar nombre en sesión para el header
    $_SESSION['vet_username'] = $username;

    // Devolver datos actualizados
    $q = $pdo->prepare("SELECT id, username, email FROM vet_users WHERE id = ?");
    $q->execute([$vetUserId]);
    $updated = $q->fetch();

    echo json_encode([
        'ok'   => true,
        'data' => $updated
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    error_log('Error actualizando perfil veterinario: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Error interno del servidor.']);
}
