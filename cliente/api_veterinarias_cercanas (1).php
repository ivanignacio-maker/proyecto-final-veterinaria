<?php
/**
 * API: veterinarias más cercanas a un punto (lat/lng del tutor).
 * Las coordenadas de cada clínica NO se guardan en la base: se calculan a partir de su
 * dirección con geocode_address() (que guarda el resultado en caché 30 días).
 */
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
if ($lat === false || $lng === false || abs($lat) > 90 || abs($lng) > 180) {
    json_fail(422, 'Ubicación inválida.');
}

// La primera vez hay que geocodificar cada clínica (1 por segundo); liberamos la sesión
// y ampliamos el tiempo para no bloquear al resto de las páginas mientras tanto.
session_write_close();
set_time_limit(120);

try {
    // Se leen TODAS las clínicas activas: si se cortara a 10 antes de medir distancias,
    // podría quedar afuera la más cercana.
    $rows = db()->query("SELECT id, name, email, street, city, province FROM clinics WHERE active = 1")->fetchAll();
} catch (Throwable $ex) {
    error_log('Veterinarias cercanas error: ' . $ex->getMessage());
    json_fail(500, 'No se pudo consultar las veterinarias. Probá de nuevo.');
}

$clinics = [];
foreach ($rows as $r) {
    $address = clinic_full_address($r);
    $point = geocode_address($address);
    if ($point === null) {
        continue; // sin ubicación en el mapa no se puede medir la distancia
    }

    // Fórmula de Haversine: distancia en km entre dos puntos de la Tierra
    $dLat = deg2rad($point['lat'] - $lat);
    $dLng = deg2rad($point['lng'] - $lng);
    $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat)) * cos(deg2rad($point['lat'])) * sin($dLng / 2) ** 2;
    $distance = round(6371 * 2 * atan2(sqrt($a), sqrt(1 - $a)), 1);

    $clinics[] = [
        'id'          => (int)$r['id'],
        'name'        => format_name($r['name']),
        'email'       => $r['email'],
        'address'     => $address,
        'lat'         => $point['lat'],
        'lng'         => $point['lng'],
        'distance_km' => $distance,
    ];
}

usort($clinics, fn($a, $b) => $a['distance_km'] <=> $b['distance_km']);

echo json_encode(['ok' => true, 'clinics' => array_slice($clinics, 0, NEAREST_LIMIT)], JSON_UNESCAPED_UNICODE);
