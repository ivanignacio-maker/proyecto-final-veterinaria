<?php
/**
 * API: Gestión de Co-tutores / Asignación de otros dueños por DNI
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

$action = trim($input['action'] ?? 'add');
$petId  = (int)($input['pet_id'] ?? 0);

if ($petId <= 0 || !is_client_pet_owner($currentClientId, $petId)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'No tenés permisos sobre esta mascota.']);
    exit;
}

// 1. Agregar nuevo dueño por DNI
if ($action === 'add') {
    $dni = trim($input['dni'] ?? '');
    if ($dni === '') {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'Ingresá el número de DNI del nuevo dueño.']);
        exit;
    }

    try {
        $pdo = db();
        // Buscar cliente por DNI
        $q = $pdo->prepare("SELECT id, full_name, dni FROM clients WHERE dni = ? LIMIT 1");
        $q->execute([$dni]);
        $target = $q->fetch();

        if (!$target) {
            http_response_code(404);
            echo json_encode([
                'ok' => false, 
                'error' => 'No se encontró ningún usuario registrado con el DNI "' . $dni . '". El tutor debe registrarse primero en la plataforma.'
            ]);
            exit;
        }

        $targetId = (int)$target['id'];

        if ($targetId === $currentClientId) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'error' => 'Ya sos dueño de esta mascota.']);
            exit;
        }

        // Verificar si ya es dueño principal o co-dueño
        if (is_client_pet_owner($targetId, $petId)) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'error' => e_name($target['full_name']) . ' ya figura como dueño de esta mascota.']);
            exit;
        }

        // Insertar co-dueño
        $ins = $pdo->prepare("INSERT INTO pet_co_owners (pet_id, client_id) VALUES (?, ?)");
        $ins->execute([$petId, $targetId]);

        echo json_encode([
            'ok' => true,
            'message' => '¡' . format_name($target['full_name']) . ' ahora también es dueño/a de tu mascota!',
            'owners' => get_pet_owners($petId)
        ], JSON_UNESCAPED_UNICODE);
        exit;

    } catch (Throwable $e) {
        error_log('Error asignando co-dueño: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Error al asignar el dueño.']);
        exit;
    }
}

// 2. Eliminar co-dueño
if ($action === 'remove') {
    $removeId = (int)($input['client_id'] ?? 0);
    if ($removeId <= 0) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'Cliente a remover inválido.']);
        exit;
    }

    try {
        $del = db()->prepare("DELETE FROM pet_co_owners WHERE pet_id = ? AND client_id = ?");
        $del->execute([$petId, $removeId]);

        echo json_encode([
            'ok' => true,
            'message' => 'Se removió la cotutoría correctamente.',
            'owners' => get_pet_owners($petId)
        ], JSON_UNESCAPED_UNICODE);
        exit;
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'No se pudo remover la cotutoría.']);
        exit;
    }
}

// 3. Listar dueños
if ($action === 'list') {
    echo json_encode([
        'ok' => true,
        'owners' => get_pet_owners($petId)
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

http_response_code(400);
echo json_encode(['ok' => false, 'error' => 'Acción no válida.']);

