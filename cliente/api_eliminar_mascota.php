<?php
/**
 * API: Eliminar / Dar de baja una mascota (portal del tutor)
 *
 * - Si quien llama es el tutor PRINCIPAL: se borra la mascota y todo lo asociado.
 * - Si es un co-tutor: solo se lo desvincula de la mascota.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/pets_delete.php';
require_client();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Método no permitido.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_POST;
}

$petId    = (int)($input['pet_id'] ?? 0);
$clientId = (int)$_SESSION['client_id'];
$inputDni = trim((string)($input['dni'] ?? $input['confirm_dni'] ?? ''));

if ($petId <= 0) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Identificador de mascota inválido.']);
    exit;
}

if ($inputDni === '') {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Debés ingresar tu número de DNI para confirmar la baja.']);
    exit;
}

try {
    $pdo = db();

    // Validar DNI del cliente registrado para evitar bajas accidentales
    $dniQ = $pdo->prepare('SELECT dni FROM clients WHERE id = ?');
    $dniQ->execute([$clientId]);
    $actualDni = trim((string)$dniQ->fetchColumn());

    $cleanInput  = preg_replace('/[^a-zA-Z0-9]/', '', $inputDni);
    $cleanActual = preg_replace('/[^a-zA-Z0-9]/', '', $actualDni);

    if ($cleanInput === '' || strcasecmp($cleanInput, $cleanActual) !== 0) {
        http_response_code(400);
        echo json_encode([
            'ok' => false,
            'error' => 'El DNI ingresado no coincide con el registrado en tu cuenta. Por seguridad, no se procesó la baja.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $q = $pdo->prepare('SELECT id, client_id, name FROM pets WHERE id = ?');
    $q->execute([$petId]);
    $pet = $q->fetch();

    if (!$pet) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'La mascota no existe o ya fue eliminada.']);
        exit;
    }

    if ((int)$pet['client_id'] !== $clientId) {
        // No es el tutor principal: solo puede salir si es co-tutor.
        $coQ = $pdo->prepare('SELECT 1 FROM pet_co_owners WHERE pet_id = ? AND client_id = ?');
        $coQ->execute([$petId, $clientId]);
        if (!$coQ->fetchColumn()) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'No tenés permisos para eliminar o desvincular esta mascota.']);
            exit;
        }

        $pdo->prepare('DELETE FROM pet_co_owners WHERE pet_id = ? AND client_id = ?')->execute([$petId, $clientId]);
        echo json_encode([
            'ok' => true,
            'message' => 'Te desvinculaste como co-tutor de ' . $pet['name'] . ' con éxito.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Tutor principal: borrado completo (transacción incluida en la función).
    $result = delete_pet_cascade($pdo, $petId);
    delete_pet_files($result);

    audit_log('pet', $petId, 'client_deleted', [
        'client_id' => $clientId,
        'reason'    => trim((string)($input['reason'] ?? '')),
    ]);

    echo json_encode([
        'ok' => true,
        'message' => 'La mascota ' . $pet['name'] . ' fue dada de baja correctamente.'
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    // El detalle técnico va al log de errores de PHP; al usuario solo le decimos en qué paso falló.
    error_log('Error al eliminar mascota: ' . $e->getMessage());
    $step = '';
    if (preg_match('/Paso «([^»]+)»/u', $e->getMessage(), $m)) {
        $step = ' (falló en: ' . $m[1] . ')';
    }
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'No se pudo dar de baja la mascota' . $step . '. No se borró nada.'], JSON_UNESCAPED_UNICODE);
}
