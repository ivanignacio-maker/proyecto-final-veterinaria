<?php
/**
 * mp_webhook.php — Aviso automático de Mercado Pago ("Webhook").
 *
 * Mercado Pago llama a esta dirección cada vez que cambia el estado de un pago, aunque la
 * persona haya cerrado el navegador. NO tiene sesión ni formulario: es una máquina hablando
 * con otra, por eso no usa header.php ni CSRF.
 *
 * Seguridad: del aviso solo tomamos el número de pago; después le preguntamos a la API de
 * Mercado Pago (con nuestro token) cuál es el estado real. Un aviso falso no aprueba nada.
 *
 * En tu PC (localhost) Mercado Pago NO puede llegar hasta acá: ver MP_PUBLIC_BASE_URL en
 * config/mercadopago.local.example.php. Igual, al volver del pago, mp_retorno.php verifica el pago.
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/mercadopago.php';

header('Content-Type: application/json; charset=utf-8');

// Los avisos llegan por POST (JSON) o, en el formato viejo, por GET/POST con ?topic=payment&id=...
$body = json_decode((string)file_get_contents('php://input'), true);
$body = is_array($body) ? $body : [];

$type = (string)($body['type'] ?? $_GET['type'] ?? $_GET['topic'] ?? '');
$dataId = (string)($body['data']['id'] ?? $_GET['data.id'] ?? $_GET['id'] ?? '');

// Solo nos interesan los pagos. Al resto se responde 200 para que Mercado Pago no reintente.
if ($type !== 'payment' || !ctype_digit($dataId)) {
    echo json_encode(['ok' => true, 'ignored' => true]);
    exit;
}

if (!mp_verify_signature($dataId)) {
    http_response_code(401);
    error_log('mp_webhook: firma inválida para el pago ' . $dataId);
    echo json_encode(['ok' => false, 'error' => 'firma inválida']);
    exit;
}

try {
    $payment = mp_get_payment($dataId);
    mp_apply_payment($payment);
    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    // 500 = Mercado Pago vuelve a intentarlo más tarde.
    error_log('mp_webhook: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false]);
}
