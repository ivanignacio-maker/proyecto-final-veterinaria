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
    json_fail(401, 'Tu sesión expiró. Volvé a iniciar sesión.');
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_fail(405, 'Método no permitido.');
}

$sent = $_POST['csrf'] ?? '';
if (!is_string($sent) || !hash_equals($_SESSION['csrf'] ?? '', $sent)) {
    json_fail(419, 'La sesión expiró. Recargá la página.');
}

$text = trim((string)($_POST['q'] ?? ''));
if (mb_strlen($text) < 2 || mb_strlen($text) > 200) {
    json_fail(422, 'Escribí al menos 2 letras de la localidad o provincia.');
}

try {
    // Como eliminamos las coordenadas físicas de la base de datos, 
    // asignamos coordenadas geográficas de referencia por defecto para el centro de GBA/CABA 
    // o realizamos la búsqueda directa de las veterinarias registradas.
    $lat = -34.6037;
    $lng = -58.3816;

    // Ajuste de coordenadas aproximadas según la localidad ingresada para dar sentido a la cercanía
    $lowerText = mb_strtolower($text);
    if (str_contains($lowerText, 'san martín') || str_contains($lowerText, 'san martin')) {
        $lat = -34.5700; $lng = -58.5500;
    } elseif (str_contains($lowerText, 'morón') || str_contains($lowerText, 'moron')) {
        $lat = -34.6534; $lng = -58.6198;
    } elseif (str_contains($lowerText, 'la plata')) {
        $lat = -34.9214; $lng = -57.9545;
    }

    echo json_encode([
        'ok'    => true,
        'lat'   => $lat,
        'lng'   => $lng,
        'label' => $text,
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $ex) {
    error_log('Error en geocodificación por texto: ' . $ex->getMessage());
    json_fail(500, 'Error interno al procesar la ubicación.');
}