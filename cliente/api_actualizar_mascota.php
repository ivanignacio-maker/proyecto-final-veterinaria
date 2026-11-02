<?php
/**
 * API: Actualizar datos de una mascota (desde el portal del tutor)
 * 
 * Recibe datos vía POST (FormData o JSON) y actualiza nombre, especie, sexo, raza,
 * peso, altura, fecha de nacimiento y foto de perfil de una mascota del cliente logueado.
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

// Mensaje claro según por qué falló la foto (tipo no aceptado, tamaño o error de subida).
function photo_upload_error(array $f, string $mime): ?string {
    if ($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE || $f['size'] > 5 * 1024 * 1024) {
        return 'La foto pesa demasiado. El máximo es 5 MB.';
    }
    if ($f['error'] !== UPLOAD_ERR_OK) {
        return 'No se pudo recibir la foto. Volvé a intentarlo.';
    }
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
        return 'Ese tipo de archivo no es aceptado. Usá una foto JPG, PNG o WEBP.';
    }
    return null;
}

// Si el archivo supera post_max_size, PHP descarta TODO el formulario (queda vacío).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && empty($_FILES) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0
    && strpos($_SERVER['CONTENT_TYPE'] ?? '', 'multipart/form-data') !== false) {
    http_response_code(413);
    echo json_encode(['ok' => false, 'error' => 'El archivo es demasiado grande. Elegí una foto de hasta 3 MB.']);
    exit;
}

// Leer datos tanto desde $_POST (FormData) como desde JSON
$input = !empty($_POST) ? $_POST : (json_decode(file_get_contents('php://input'), true) ?: []);

$petId      = (int)($input['id'] ?? 0);
$name       = trim($input['name'] ?? '');
$animalType = trim($input['animal_type'] ?? '');
$sexo       = trim($input['sexo'] ?? '');
$breed      = trim($input['breed'] ?? '');
$weightKg   = ($input['weight_kg'] ?? '') !== '' ? (float)$input['weight_kg'] : null;
$heightCm   = ($input['height_cm'] ?? '') !== '' ? (float)$input['height_cm'] : null;
$birthDate  = trim($input['birth_date'] ?? '');

if (!in_array($sexo, ['Macho', 'Hembra'], true)) {
    $sexo = null;
}

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

    // Procesar subida de foto si se envió
    $newPhotoRel = null;
    if (!empty($_FILES['photo']['name'])) {
        $f = $_FILES['photo'];
        $mime = $f['error'] === UPLOAD_ERR_OK ? (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']) : '';
        $allowed = ['image/jpeg', 'image/png', 'image/webp'];

        $photoError = photo_upload_error($f, $mime);
        if ($photoError !== null) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'error' => $photoError], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $saved = save_uploaded_image_as_webp($f, 'pets', bin2hex(random_bytes(20)));
        if ($saved !== null) {
            $newPhotoRel = $saved;
        } else {
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => 'No se pudo procesar la foto en formato WebP.']);
            exit;
        }
    }

    // Actualizar los datos de la mascota
    if ($newPhotoRel !== null) {
        $q = $pdo->prepare("
            UPDATE pets 
            SET name = ?, animal_type = ?, sexo = ?, breed = ?, weight_kg = ?, height_cm = ?, birth_date = ?, photo_path = ?
            WHERE id = ? AND client_id = ?
        ");
        $q->execute([
            format_name($name),
            format_name($animalType),
            $sexo,
            $breed ? format_name($breed) : null,
            $weightKg,
            $heightCm,
            $birthDate ?: null,
            $newPhotoRel,
            $petId,
            $clientId
        ]);
    } else {
        $q = $pdo->prepare("
            UPDATE pets 
            SET name = ?, animal_type = ?, sexo = ?, breed = ?, weight_kg = ?, height_cm = ?, birth_date = ?
            WHERE id = ? AND client_id = ?
        ");
        $q->execute([
            format_name($name),
            format_name($animalType),
            $sexo,
            $breed ? format_name($breed) : null,
            $weightKg,
            $heightCm,
            $birthDate ?: null,
            $petId,
            $clientId
        ]);
    }

    // Devolver datos actualizados
    $q = $pdo->prepare("SELECT id, name, animal_type, sexo, breed, weight_kg, height_cm, birth_date, photo_path FROM pets WHERE id = ?");
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
