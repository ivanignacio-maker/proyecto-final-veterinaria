<?php
/**
 * API: Mascotas de un cliente por DNI (para asignar turnos)
 *
 * Recibe por POST el DNI del cliente y devuelve su nombre y todas sus
 * mascotas: las propias y las que comparte como co-titular.
 *
 * Responde siempre en JSON.
 */
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function json_fail(int $code, string $message): never {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

// Solo veterinarios autenticados
if (empty($_SESSION['vet_user_id']) || empty($_SESSION['clinic_id'])) {
    json_fail(401, 'Tu sesión expiró. Volvé a iniciar sesión.');
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_fail(405, 'Método no permitido.');
}

$sent = $_POST['csrf'] ?? '';
if (!is_string($sent) || !hash_equals($_SESSION['csrf'] ?? '', $sent)) {
    json_fail(419, 'La sesión expiró. Recargá la página.');
}

$dni = trim((string)($_POST['dni'] ?? ''));
if ($dni === '') {
    json_fail(422, 'Ingresá el DNI del cliente.');
}

// 1. Buscar al cliente
$q = db()->prepare("SELECT id, full_name FROM clients WHERE dni = ?");
$q->execute([$dni]);
$client = $q->fetch();

if (!$client) {
    json_fail(404, 'No hay ningún cliente registrado con el DNI ' . $dni . '.');
}

// 2. Mascotas propias + mascotas compartidas como co-titular
$q = db()->prepare("
    SELECT p.id, p.name, p.animal_type, p.breed, 0 AS co_owner
    FROM pets p
    WHERE p.client_id = ?
    UNION
    SELECT p.id, p.name, p.animal_type, p.breed, 1 AS co_owner
    FROM pets p
    JOIN pet_co_owners po ON po.pet_id = p.id
    WHERE po.client_id = ? AND p.client_id <> ?
    ORDER BY name ASC
");
$q->execute([$client['id'], $client['id'], $client['id']]);

$pets = array_map(fn($p) => [
    'id'          => (int)$p['id'],
    'name'        => format_name($p['name']),
    'animal_type' => $p['animal_type'],
    'breed'       => $p['breed'],
    'co_owner'    => (bool)$p['co_owner'],
], $q->fetchAll());

echo json_encode([
    'ok'     => true,
    'client' => ['name' => format_name($client['full_name'])],
    'pets'   => $pets,
], JSON_UNESCAPED_UNICODE);
