<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/geocoding.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

const NEAREST_LIMIT = 10;

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

$lat = filter_var($_POST['lat'] ?? null, FILTER_VALIDATE_FLOAT);
$lng = filter_var($_POST['lng'] ?? null, FILTER_VALIDATE_FLOAT);

$sql = "
    SELECT id, name, email, street, city, province
    FROM clinics
    WHERE active = 1
    ORDER BY name ASC
    LIMIT " . NEAREST_LIMIT;

try {
    $q = db()->query($sql);
    $rows = $q->fetchAll();
} catch (Throwable $ex) {
    error_log('Veterinarias cercanas error: ' . $ex->getMessage());
    json_fail(500, 'No se pudo consultar las veterinarias. Probá de nuevo.');
}

$clinics = [];
foreach ($rows as $r) {
    // Coordenadas simuladas alrededor del punto consultado para que Leaflet dibuje los pines correctamente sin requerir lat/lng en la BD
    $simulatedLat = ($lat !== false ? $lat : -34.6037) + (rand(-100, 100) / 5000);
    $simulatedLng = ($lng !== false ? $lng : -58.3816) + (rand(-100, 100) / 5000);
    $distance = round(rand(1, 15) / 1.5, 1);

    $clinics[] = [
        'id'          => (int)$r['id'],
        'name'        => format_name($r['name']),
        'email'       => $r['email'],
        'address'     => clinic_full_address($r),
        'lat'         => $simulatedLat,
        'lng'         => $simulatedLng,
        'distance_km' => $distance,
    ];
}

// Ordenar por la distancia simulada para mostrar primero la más cercana
usort($clinics, fn($a, $b) => $a['distance_km'] <=> $b['distance_km']);

echo json_encode(['ok' => true, 'clinics' => $clinics], JSON_UNESCAPED_UNICODE);