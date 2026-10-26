<?php
/**
 * API: Generar y consultar código de acceso temporal para la veterinaria
 */
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['client_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'No autenticado.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Método no permitido.']);
    exit;
}

$currentClientId = (int)$_SESSION['client_id'];
$input = !empty($_POST) ? $_POST : (json_decode(file_get_contents('php://input'), true) ?: []);

$petId  = (int)($input['pet_id'] ?? 0);
$action = trim($input['action'] ?? 'generate');

if ($petId <= 0 || !is_client_pet_owner($currentClientId, $petId)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'No tenés permisos sobre esta mascota.']);
    exit;
}

try {
    // Si solicitan generar uno nuevo (o si consultan y no hay ninguno activo)
    $code = generate_pet_access_code($petId, $currentClientId);

    echo json_encode([
        'ok' => true,
        'code' => $code,
        'formatted' => substr($code, 0, 3) . ' ' . substr($code, 3, 3),
        'expires_in_minutes' => 30,
        'message' => 'Código de acceso generado correctamente.'
    ], JSON_UNESCAPED_UNICODE);
    exit;

} catch (Throwable $e) {
    error_log('Error generando código de acceso: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Error al generar el código de acceso.']);
}

