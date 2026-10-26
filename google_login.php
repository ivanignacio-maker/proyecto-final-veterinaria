<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';

function google_login_error(string $message, bool $registration = false): void {
    $_SESSION['google_auth_error'] = $message;
    $destination = 'login.php?tipo=cliente' . ($registration ? '&google_register=1' : '');
    header('Location: ' . url($destination));
    exit;
}

function google_request_json(string $url, ?array $postFields = null, ?string $accessToken = null): ?array {
    if (!function_exists('curl_init')) {
        return null;
    }

    $curl = curl_init($url);
    if ($curl === false) {
        return null;
    }

    $headers = ['Accept: application/json'];
    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_HTTPHEADER => $headers
    ];

    if ($postFields !== null) {
        $options[CURLOPT_POST] = true;
        $options[CURLOPT_POSTFIELDS] = http_build_query($postFields);
        $options[CURLOPT_HTTPHEADER] = [
            'Accept: application/json',
            'Content-Type: application/x-www-form-urlencoded'
        ];
    }

    if ($accessToken !== null) {
        $options[CURLOPT_HTTPHEADER] = ['Accept: application/json', 'Authorization: Bearer ' . $accessToken];
    }

    curl_setopt_array($curl, $options);
    $response = curl_exec($curl);
    $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curlNumber = curl_errno($curl);
    $curlMessage = curl_error($curl);
    $host = (string)(parse_url($url, PHP_URL_HOST) ?: 'unknown');
    curl_close($curl);

    if (!is_string($response)) {
        error_log('Google OAuth connection failed at ' . $host . ' (cURL ' . $curlNumber . '): ' . substr($curlMessage, 0, 180));
        return null;
    }

    $decoded = json_decode($response, true);
    if ($status < 200 || $status >= 300) {
        $providerCode = is_array($decoded) ? (string)($decoded['error'] ?? 'unknown') : 'invalid_response';
        $providerCode = preg_replace('/[^a-zA-Z0-9_.-]/', '', $providerCode);
        error_log('Google OAuth request rejected by ' . $host . ' (HTTP ' . $status . ', code ' . $providerCode . ').');
        return null;
    }

    return is_array($decoded) ? $decoded : null;
}

function google_client_matches(string $email): array {
    $query = db()->prepare(
        'SELECT id, dni, full_name, email FROM clients WHERE LOWER(email) = LOWER(?) ORDER BY id'
    );
    $query->execute([$email]);
    return $query->fetchAll();
}

function google_complete_client_login(array $client): void {
    $remember = !empty($_SESSION['google_remember']);
    session_regenerate_id(true);
    $_SESSION['client_id'] = (int)$client['id'];
    $_SESSION['client_name'] = (string)$client['full_name'];
    unset($_SESSION['google_oauth_state'], $_SESSION['google_pending'], $_SESSION['google_remember'], $_SESSION['google_auth_error']);

    if ($remember) {
        create_remember_cookie('client', (int)$client['id']);
    }

    header('Location: ' . url('cliente/mascotas.php'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    if (($_POST['action'] ?? '') !== 'complete_registration') {
        google_login_error('No se pudo completar el acceso con Google.');
    }

    $pending = $_SESSION['google_pending'] ?? null;
    if (
        !is_array($pending) ||
        empty($pending['email']) ||
        empty($pending['created_at']) ||
        time() - (int)$pending['created_at'] > 900
    ) {
        unset($_SESSION['google_pending']);
        google_login_error('La verificación de Google venció. Volvé a intentarlo.');
    }

    $dni = trim((string)($_POST['dni'] ?? ''));
    $phone = trim((string)($_POST['phone'] ?? ''));
    if ($dni === '' || strlen($dni) > 30) {
        google_login_error('Ingresá un DNI válido de hasta 30 caracteres.', true);
    }
    if (strlen($phone) > 50) {
        google_login_error('El teléfono no puede superar los 50 caracteres.', true);
    }

    try {
        $email = strtolower((string)$pending['email']);
        $emailMatches = google_client_matches($email);

        foreach ($emailMatches as $client) {
            if (hash_equals((string)$client['dni'], $dni)) {
                google_complete_client_login($client);
            }
        }

        if ($emailMatches) {
            google_login_error(
                'Ese correo ya está asociado a un tutor. Revisá el DNI ingresado o consultá con tu veterinaria.',
                true
            );
        }

        $dniQuery = db()->prepare('SELECT id, email FROM clients WHERE dni = ? LIMIT 1');
        $dniQuery->execute([$dni]);
        if ($dniQuery->fetch()) {
            google_login_error(
                'Ese DNI ya está registrado con otro correo. Contactá a tu veterinaria para actualizarlo.',
                true
            );
        }

        $name = trim((string)($pending['name'] ?? ''));
        if ($name === '') {
            $name = ucfirst((string)strstr($email, '@', true));
        }
        $name = mb_substr($name, 0, 180, 'UTF-8');

        $insert = db()->prepare(
            'INSERT INTO clients (dni, full_name, email, phone, password_hash, created_at, updated_at)
             VALUES (?, ?, ?, ?, NULL, NOW(), NOW())'
        );
        $insert->execute([$dni, $name, $email, $phone]);

        $query = db()->prepare('SELECT id, dni, full_name, email FROM clients WHERE id = ? LIMIT 1');
        $query->execute([(int)db()->lastInsertId()]);
        $client = $query->fetch();

        if (!$client) {
            google_login_error('La cuenta se creó, pero no pudimos iniciar la sesión. Volvé a ingresar.');
        }

        google_complete_client_login($client);
    } catch (Throwable $exception) {
        error_log('Google tutor registration failed.');
        google_login_error('No pudimos completar el registro. Revisá tus datos e intentá nuevamente.', true);
    }
}

if (!empty($_GET['error'])) {
    google_login_error('No se completó el acceso con Google. Podés volver a intentarlo.');
}

$clientId = (string)GOOGLE_CLIENT_ID;
$clientSecret = (string)GOOGLE_CLIENT_SECRET;
$redirectUri = (string)GOOGLE_REDIRECT_URI;

if ($clientId === '' || $clientSecret === '' || $redirectUri === '') {
    google_login_error('El acceso con Google todavía no está configurado para este sitio.');
}

if (!empty($_GET['code'])) {
    $expectedState = (string)($_SESSION['google_oauth_state'] ?? '');
    $receivedState = (string)($_GET['state'] ?? '');
    unset($_SESSION['google_oauth_state']);

    if ($expectedState === '' || $receivedState === '' || !hash_equals($expectedState, $receivedState)) {
        unset($_SESSION['google_remember']);
        google_login_error('La solicitud de Google venció o no es válida. Volvé a intentarlo.');
    }

    $token = google_request_json('https://oauth2.googleapis.com/token', [
        'code' => (string)$_GET['code'],
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
        'redirect_uri' => $redirectUri,
        'grant_type' => 'authorization_code'
    ]);

    if (!$token || empty($token['access_token'])) {
        unset($_SESSION['google_remember']);
        google_login_error('Google no pudo verificar tu cuenta. Volvé a intentarlo.');
    }

    $profile = google_request_json(
        'https://openidconnect.googleapis.com/v1/userinfo',
        null,
        (string)$token['access_token']
    );

    if (
        !$profile ||
        empty($profile['email']) ||
        ($profile['email_verified'] ?? null) !== true ||
        empty($profile['sub']) ||
        !filter_var($profile['email'], FILTER_VALIDATE_EMAIL)
    ) {
        unset($_SESSION['google_remember']);
        google_login_error('La cuenta de Google no devolvió un correo verificado.');
    }

    try {
        $email = strtolower(trim((string)$profile['email']));
        $matches = google_client_matches($email);

        if (count($matches) === 1) {
            google_complete_client_login($matches[0]);
        }

        $name = trim((string)($profile['name'] ?? ''));
        if ($name === '') {
            $name = ucfirst((string)strstr($email, '@', true));
        }

        $_SESSION['google_pending'] = [
            'email' => $email,
            'name' => mb_substr($name, 0, 180, 'UTF-8'),
            'created_at' => time()
        ];
        header('Location: ' . url('login.php?tipo=cliente&google_register=1'));
        exit;
    } catch (Throwable $exception) {
        error_log('Google tutor sign-in failed.');
        unset($_SESSION['google_remember']);
        google_login_error('No pudimos consultar tu cuenta. Intentá nuevamente más tarde.');
    }
}

$state = bin2hex(random_bytes(32));
$_SESSION['google_oauth_state'] = $state;
$_SESSION['google_remember'] = !empty($_GET['remember']);

$authorizationUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
    'client_id' => $clientId,
    'redirect_uri' => $redirectUri,
    'response_type' => 'code',
    'scope' => 'openid email profile',
    'state' => $state,
    'prompt' => 'select_account'
]);

header('Location: ' . $authorizationUrl);
exit;

