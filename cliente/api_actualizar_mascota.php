<?php
/**
 * API: Actualizar datos de una mascota (desde el portal del tutor)
 * 
 * Recibe datos vía POST (JSON) y actualiza nombre, especie, raza, peso y altura
 * de una mascota que pertenezca al cliente logueado.
 * 
 * Responde siempre en JSON.
 */
require_once __DIR__ . '/../config/config.php';

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

// Leer el cuerpo JSON
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'JSON inválido.']);
    exit;
}

$petId      = (int)($input['id'] ?? 0);
$name       = trim($input['name'] ?? '');
$animalType = trim($input['animal_type'] ?? '');
$breed      = trim($input['breed'] ?? '');
$weightKg   = ($input['weight_kg'] ?? '') !== '' ? (float)$input['weight_kg'] : null;
$heightCm   = ($input['height_cm'] ?? '') !== '' ? (float)$input['height_cm'] : null;
$birthDate  = trim($input['birth_date'] ?? '');

// Validaciones
if ($petId <= 0) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'ID de mascota inválido.']);
    exit;
}

if ($name === '' || $animalType === '') {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Nombre y especie son obligatorios.']);
    exit;
}

try {
    $pdo = db();

    // Verificar que la mascota pertenezca al cliente logueado
    $q = $pdo->prepare("SELECT id FROM pets WHERE id = ? AND client_id = ?");
    $q->execute([$petId, $clientId]);
    if (!$q->fetch()) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'No tenés permiso para editar esta mascota.']);
        exit;
    }

    // Actualizar los datos de la mascota
    $q = $pdo->prepare("
        UPDATE pets 
        SET name = ?, animal_type = ?, breed = ?, weight_kg = ?, height_cm = ?, birth_date = ?
        WHERE id = ? AND client_id = ?
    ");
    $q->execute([
        format_name($name),
        format_name($animalType),
        $breed ? format_name($breed) : null,
        $weightKg,
        $heightCm,
        $birthDate ?: null,
        $petId,
        $clientId
    ]);

    // Devolver datos actualizados
    $q = $pdo->prepare("SELECT id, name, animal_type, breed, weight_kg, height_cm, birth_date FROM pets WHERE id = ?");
    $q->execute([$petId]);
    $updated = $q->fetch();

    echo json_encode([
        'ok'   => true,
        'data' => $updated
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    error_log('Error actualizando mascota: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Error interno del servidor.']);
}
