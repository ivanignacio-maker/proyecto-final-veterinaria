<?php
/**
 * includes/mercadopago.php
 *
 * Integración con Mercado Pago "Checkout Pro" usando su API REST (sin librerías):
 *   1. contratar.php crea una "preferencia" (el pedido de cobro) y manda al cliente a pagar.
 *   2. Mercado Pago devuelve al cliente a mp_retorno.php.
 *   3. Mercado Pago avisa a mp_webhook.php cada vez que cambia el estado del pago.
 *
 * REGLA DE ORO: nunca se confía en lo que dice el navegador ("pagué"). El estado del pago
 * siempre se consulta a Mercado Pago con nuestro Access Token (mp_get_payment).
 *
 * Datos secretos: config/mercadopago.local.php (no se sube al repositorio).
 * Ver config/mercadopago.local.example.php.
 */

if (is_file(__DIR__ . '/../config/mercadopago.local.php')) require __DIR__ . '/../config/mercadopago.local.php';
if (!defined('MP_ACCESS_TOKEN'))    define('MP_ACCESS_TOKEN', getenv('VETCARE_MP_ACCESS_TOKEN') ?: '');
if (!defined('MP_WEBHOOK_SECRET'))  define('MP_WEBHOOK_SECRET', getenv('VETCARE_MP_WEBHOOK_SECRET') ?: '');
// URL pública del sitio (ej. https://abc123.ngrok-free.app/proyectofinalv5). Mercado Pago NO puede
// avisarle a "localhost": para probar el webhook en tu PC hace falta una URL pública (ngrok).
if (!defined('MP_PUBLIC_BASE_URL')) define('MP_PUBLIC_BASE_URL', getenv('VETCARE_PUBLIC_URL') ?: '');
// Opcional: ruta al archivo cacert.pem si cURL falla con "SSL certificate problem" en XAMPP.
if (!defined('MP_CA_BUNDLE'))       define('MP_CA_BUNDLE', '');

const MP_API = 'https://api.mercadopago.com';

function mp_enabled(): bool {
    return MP_ACCESS_TOKEN !== '';
}

/** Base absoluta del sitio, sin barra final. */
function mp_base_url(): string {
    if (MP_PUBLIC_BASE_URL !== '') {
        return rtrim(MP_PUBLIC_BASE_URL, '/');
    }
    return rtrim(defined('APP_URL') ? APP_URL : '', '/');
}

function mp_is_https(string $u): bool {
    return stripos($u, 'https://') === 0;
}

/** Llamada HTTP a la API de Mercado Pago. Devuelve el JSON decodificado o lanza RuntimeException. */
function mp_request(string $method, string $path, ?array $body = null, ?string $idempotencyKey = null): array {
    if (!mp_enabled()) {
        throw new RuntimeException('Mercado Pago no está configurado.');
    }
    if (!function_exists('curl_init')) {
        throw new RuntimeException('Falta la extensión cURL de PHP (activá extension=curl en php.ini).');
    }

    $headers = ['Authorization: Bearer ' . MP_ACCESS_TOKEN, 'Content-Type: application/json', 'Accept: application/json'];
    if ($idempotencyKey !== null) {
        $headers[] = 'X-Idempotency-Key: ' . $idempotencyKey;
    }

    $ch = curl_init(MP_API . $path);
    $opts = [
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_HTTPHEADER     => $headers,
    ];
    if (MP_CA_BUNDLE !== '') {
        $opts[CURLOPT_CAINFO] = MP_CA_BUNDLE;
    }
    if ($body !== null) {
        $opts[CURLOPT_POSTFIELDS] = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    curl_setopt_array($ch, $opts);

    $raw  = curl_exec($ch);
    $err  = curl_error($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($raw === false) {
        throw new RuntimeException('No se pudo conectar con Mercado Pago: ' . $err);
    }
    $data = json_decode((string)$raw, true);
    if (!is_array($data)) {
        throw new RuntimeException('Respuesta inválida de Mercado Pago (HTTP ' . $code . ').');
    }
    if ($code < 200 || $code >= 300) {
        $msg = (string)($data['message'] ?? $data['error'] ?? 'error desconocido');
        throw new RuntimeException('Mercado Pago respondió HTTP ' . $code . ': ' . $msg);
    }
    return $data;
}

/**
 * Crea la preferencia de pago para una solicitud y devuelve [id, url_de_pago].
 * El importe sale de la base de datos (que a su vez salió de plan_catalog()), nunca del navegador.
 */
function mp_create_preference(array $req): array {
    $base   = mp_base_url();
    $plans  = plan_catalog();
    $planNm = $plans[$req['plan_code']]['name'] ?? 'Plan VetCare';
    $billing = $req['billing'] === 'anual' ? 'facturación anual' : 'facturación mensual';

    $return = $base . '/mp_retorno.php?r=' . (int)$req['id'] . '&t=' . $req['token'];

    $pref = [
        'items' => [[
            'id'          => 'plan-' . $req['plan_code'] . '-' . $req['billing'],
            'title'       => $planNm . ' (' . $billing . ')',
            'description' => 'Suscripción a ' . APP_NAME . ' para ' . $req['clinic_name'],
            'quantity'    => 1,
            'currency_id' => 'ARS',
            'unit_price'  => (float)$req['amount'],
        ]],
        'payer' => [
            'name'  => $req['responsible_name'],
            'email' => $req['responsible_email'],
        ],
        // Con esto el pago se asocia a nuestra solicitud cuando llega el aviso.
        'external_reference' => (string)(int)$req['id'],
        'back_urls' => ['success' => $return, 'failure' => $return, 'pending' => $return],
        'statement_descriptor' => 'VETCARE',
    ];
    // "Volver automáticamente" y los avisos solo funcionan con URLs públicas https.
    if (mp_is_https($base)) {
        $pref['auto_return']      = 'approved';
        $pref['notification_url'] = $base . '/mp_webhook.php';
    }

    $res = mp_request('POST', '/checkout/preferences', $pref, 'pref-' . (int)$req['id'] . '-' . substr($req['token'], 0, 8));
    $url = (string)($res['init_point'] ?? '');
    if ($url === '' || empty($res['id'])) {
        throw new RuntimeException('Mercado Pago no devolvió el enlace de pago.');
    }
    return [(string)$res['id'], $url];
}

/** Consulta un pago a Mercado Pago (fuente de verdad del estado). */
function mp_get_payment(string $paymentId): array {
    if (!ctype_digit($paymentId)) {
        throw new RuntimeException('Identificador de pago inválido.');
    }
    return mp_request('GET', '/v1/payments/' . $paymentId);
}

/** Estados de Mercado Pago → estados de nuestra tabla. */
function mp_map_status(string $mpStatus): string {
    switch ($mpStatus) {
        case 'approved':                                  return 'aprobado';
        case 'pending': case 'in_process':
        case 'authorized': case 'in_mediation':           return 'en_proceso';
        case 'rejected':                                  return 'rechazado';
        case 'cancelled': case 'refunded':
        case 'charged_back':                              return 'cancelado';
        default:                                          return 'pendiente';
    }
}

/**
 * Aplica un pago (ya consultado a Mercado Pago) a su solicitud.
 * Es seguro llamarla muchas veces con el mismo pago (el webhook suele repetirse).
 * Devuelve true si el pago quedó aprobado.
 */
function mp_apply_payment(array $payment): bool {
    $requestId = (int)($payment['external_reference'] ?? 0);
    if ($requestId <= 0) {
        return false;
    }
    $pdo = db();
    $q = $pdo->prepare('SELECT * FROM plan_requests WHERE id = ?');
    $q->execute([$requestId]);
    $req = $q->fetch();
    if (!$req) {
        return false;
    }

    $newStatus = mp_map_status((string)($payment['status'] ?? ''));

    // Seguridad: un pago "aprobado" por un monto menor al del plan NO cuenta como aprobado.
    if ($newStatus === 'aprobado' && (float)($payment['transaction_amount'] ?? 0) + 0.01 < (float)$req['amount']) {
        error_log('MP: monto menor al esperado en solicitud ' . $requestId);
        $newStatus = 'en_proceso';
    }
    // Un pago ya aprobado no se "desaprueba" por un aviso viejo que llega tarde.
    if ($req['payment_status'] === 'aprobado' && $newStatus !== 'cancelado') {
        return true;
    }

    $wasApproved = $req['payment_status'] === 'aprobado';
    $upd = $pdo->prepare('UPDATE plan_requests SET payment_status = ?, mp_payment_id = ?, mp_status_detail = ?, paid_at = ? WHERE id = ?');
    $upd->execute([
        $newStatus,
        (string)($payment['id'] ?? ''),
        mb_substr((string)($payment['status_detail'] ?? ''), 0, 80),
        $newStatus === 'aprobado' ? ($req['paid_at'] ?: date('Y-m-d H:i:s')) : $req['paid_at'],
        $requestId,
    ]);

    // Avisos por correo SOLO la primera vez que se aprueba.
    if ($newStatus === 'aprobado' && !$wasApproved) {
        $name = (string)$req['clinic_name'];
        notify_support_team(
            'Pago aprobado: ' . $name,
            '<p>Se aprobó el pago de <strong>' . e($name) . '</strong> (' . e(plan_money((float)$req['amount'])) . ').</p>'
            . '<p>Ya podés registrar la veterinaria desde Administración → Solicitudes de alta.</p>',
            "Se aprobó el pago de $name (" . plan_money((float)$req['amount']) . ").\nRegistrala desde Administración > Solicitudes de alta."
        );
        try {
            require_once __DIR__ . '/recordatorios_vacunas.php';
            send_app_mail(
                (string)$req['responsible_email'], (string)$req['responsible_name'],
                '¡Recibimos tu pago! Estamos preparando tu cuenta en ' . APP_NAME,
                '<p>Hola ' . e($req['responsible_name']) . ',</p>'
                . '<p>Recibimos tu pago del plan para <strong>' . e($name) . '</strong>. Nuestro equipo va a registrar tu veterinaria y te enviará los datos de acceso a este correo a la brevedad.</p>'
                . '<p>Gracias por confiar en ' . e(APP_NAME) . '.</p>',
                "Hola {$req['responsible_name']},\nRecibimos tu pago del plan para $name. Vamos a registrar tu veterinaria y te enviaremos los datos de acceso a este correo.\nGracias por confiar en " . APP_NAME . '.'
            );
        } catch (Throwable $e) {
            error_log('Correo de pago aprobado: ' . $e->getMessage());
        }
    }
    return $newStatus === 'aprobado';
}

/**
 * Verifica la firma "x-signature" que Mercado Pago agrega a cada aviso (si hay clave secreta cargada).
 * Sin MP_WEBHOOK_SECRET no se puede verificar y devuelve true: igual el pago se vuelve a consultar
 * a la API con nuestro token, así que un aviso falso no puede aprobar nada.
 */
function mp_verify_signature(string $dataId): bool {
    if (MP_WEBHOOK_SECRET === '') {
        return true;
    }
    $sig = $_SERVER['HTTP_X_SIGNATURE'] ?? '';
    $reqId = $_SERVER['HTTP_X_REQUEST_ID'] ?? '';
    $ts = $v1 = '';
    foreach (explode(',', $sig) as $part) {
        [$k, $v] = array_pad(explode('=', trim($part), 2), 2, '');
        if ($k === 'ts') $ts = $v;
        if ($k === 'v1') $v1 = $v;
    }
    if ($ts === '' || $v1 === '') {
        return false;
    }
    $manifest = 'id:' . strtolower($dataId) . ';request-id:' . $reqId . ';ts:' . $ts . ';';
    return hash_equals(hash_hmac('sha256', $manifest, MP_WEBHOOK_SECRET), $v1);
}
