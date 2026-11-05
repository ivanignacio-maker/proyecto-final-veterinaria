<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/geocoding.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function json_fail(int $code, string $message): never {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

if (empty($_SESSION['client_id'])) {
    json_fail(401, 'Tu sesiÃ³n expirÃ³. VolvÃ© a iniciar sesiÃ³n.');
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_fail(405, 'MÃ©todo no permitido.');
}

$sent = $_POST['csrf'] ?? '';
if (!is_string($sent) || !hash_equals($_SESSION['csrf'] ?? '', $sent)) {
    json_fail(419, 'La sesiÃ³n expirÃ³. RecargÃ¡ la pÃ¡gina.');
}

$text = trim((string)($_POST['q'] ?? ''));
if (mb_strlen($text) < 2 || mb_strlen($text) > 200) {
    json_fail(422, 'EscribÃ­ al menos 2 letras de la localidad o provincia.');
}

try {
    $point = geocode_address($text);
    if ($point === null) {
        json_fail(422, 'No encontramos esa dirección. Probá agregando localidad y provincia.');
    }
    echo json_encode(['ok' => true, 'lat' => $point['lat'], 'lng' => $point['lng'], 'label' => $text], JSON_UNESCAPED_UNICODE);
} catch (Throwable $ex) {
    error_log('Error en geocodificación por texto: ' . $ex->getMessage());
    json_fail(500, 'Error interno al procesar la ubicación.');
}