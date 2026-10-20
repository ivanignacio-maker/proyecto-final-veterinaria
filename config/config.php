<?php
declare(strict_types=1);

// ============================================================================
// Configuración de Base de Datos y Aplicación
// ============================================================================
if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
if (!defined('DB_NAME')) define('DB_NAME', 'vetesta');
if (!defined('DB_USER')) define('DB_USER', 'root');
if (!defined('DB_PASS')) define('DB_PASS', '');
if (!defined('APP_NAME')) define('APP_NAME', 'VetCompartido');

// ============================================================================
// Detección automática o configuración de BASE_URL
// ============================================================================
if (!defined('BASE_URL')) {
    $detectedBase = '';
    $scriptFile = str_replace('\\', '/', $_SERVER['SCRIPT_FILENAME'] ?? '');
    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $projectDir = str_replace('\\', '/', dirname(__DIR__));

    if ($scriptFile !== '' && $scriptName !== '' && strpos($scriptFile, $projectDir) === 0) {
        $relPath = substr($scriptFile, strlen($projectDir));
        if ($relPath !== '' && substr($scriptName, -strlen($relPath)) === $relPath) {
            $detectedBase = rtrim(substr($scriptName, 0, strlen($scriptName) - strlen($relPath)), '/');
        }
    }
    define('BASE_URL', $detectedBase);
}

// ============================================================================
// Manejo de Sesión Segura
// ============================================================================
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('vetcompartido_session');
    session_set_cookie_params([
        'lifetime' => 2592000,
        'httponly' => true,
        'secure'   => !empty($_SERVER['HTTPS']),
        'samesite' => 'Lax'
    ]);
    session_start();
}

// ============================================================================
// Conexión PDO Singleton
// ============================================================================
function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false
            ]
        );
    }
    return $pdo;
}

// ============================================================================
// Utilidades de Seguridad y Rutas
// ============================================================================
function e(?string $value): string {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function format_name(?string $name): string {
    if ($name === null || $name === '') return '';
    return mb_convert_case(trim($name), MB_CASE_TITLE, 'UTF-8');
}

function e_name(?string $value): string {
    return e(format_name($value));
}

function url(string $path = ''): string {
    return BASE_URL . '/' . ltrim($path, '/');
}

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_check(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $sent = $_POST['csrf'] ?? '';
        if (!is_string($sent) || !hash_equals($_SESSION['csrf'] ?? '', $sent)) {
            http_response_code(419);
            exit('La sesión del formulario expiró. Volvé a intentarlo.');
        }
    }
}

// Alias para compatibilidad con páginas de front
function verify_csrf(): void {
    csrf_check();
}

// ============================================================================
// Guardias de Acceso por Roles (Redirigen al Login Unificado)
// ============================================================================
function require_vet(): void {
    if (empty($_SESSION['vet_user_id']) || empty($_SESSION['clinic_id'])) {
        header('Location: ' . url('login.php?tipo=veterinaria'));
        exit;
    }
}

function require_admin(): void {
    if (empty($_SESSION['admin_id'])) {
        header('Location: ' . url('admin/login.php'));
        exit;
    }
}

function require_client(): void {
    if (empty($_SESSION['client_id'])) {
        header('Location: ' . url('login.php?tipo=cliente'));
        exit;
    }
}

// ============================================================================
// Mensajes Flash y Alertas
// ============================================================================
function flash(string $message): void {
    $_SESSION['flash'] = $message;
}

function show_flash(): void {
    if (!empty($_SESSION['flash'])) {
        echo '<div class="notice">' . e($_SESSION['flash']) . '</div>';
        unset($_SESSION['flash']);
    }
}

// ============================================================================
// Auditoría y Subida de Archivos
// ============================================================================
function audit_log(string $entityType, int $entityId, string $action, array $details = []): void {
    try {
        $q = db()->prepare("INSERT INTO audit_log(clinic_id, vet_user_id, entity_type, entity_id, action, details) VALUES(?,?,?,?,?,?)");
        $q->execute([
            $_SESSION['clinic_id'] ?? null,
            $_SESSION['vet_user_id'] ?? null,
            $entityType,
            $entityId,
            $action,
            json_encode($details, JSON_UNESCAPED_UNICODE)
        ]);
    } catch (Throwable $e) {
        error_log('Audit log failed: ' . $e->getMessage());
    }
}

function ensure_upload_dir(): string {
    $dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads';
    if (!is_dir($dir)) {
        mkdir($dir, 0750, true);
    }
    return $dir;
}

// ============================================================================
// Helpers para Gestión de Cookie y Token "Recordarme"
// ============================================================================
function create_remember_cookie(string $userType, int $userId): void {
    try {
        $selector  = bin2hex(random_bytes(12));
        $validator = bin2hex(random_bytes(32));
        $vetUserId = ($userType === 'vet') ? $userId : null;

        $q = db()->prepare("
            INSERT INTO remember_tokens(user_type, user_id, vet_user_id, selector, validator_hash, expires_at) 
            VALUES(?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 60 DAY))
        ");
        $q->execute([$userType, $userId, $vetUserId, $selector, hash('sha256', $validator)]);

        setcookie('vet_remember', $selector . ':' . $validator, [
            'expires'  => time() + (86400 * 60),
            'path'     => '/',
            'secure'   => !empty($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    } catch (Throwable $e) {
        error_log('Failed creating remember cookie: ' . $e->getMessage());
    }
}

function clear_remember_cookie(): void {
    if (!empty($_COOKIE['vet_remember'])) {
        $parts = explode(':', (string)$_COOKIE['vet_remember'], 2);
        if (!empty($parts[0])) {
            try {
                $q = db()->prepare("DELETE FROM remember_tokens WHERE selector = ?");
                $q->execute([$parts[0]]);
            } catch (Throwable $e) {
                // ignore
            }
        }
    }
    setcookie('vet_remember', '', [
        'expires'  => time() - 3600,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}

// ============================================================================
// Validación Automática de Cookie "Recordarme" (Cualquier Rol)
// ============================================================================
if (empty($_SESSION['vet_user_id']) && empty($_SESSION['client_id']) && empty($_SESSION['admin_id']) && !empty($_COOKIE['vet_remember'])) {
    $parts = explode(':', (string)$_COOKIE['vet_remember'], 2);
    if (count($parts) === 2 && preg_match('/^[a-f0-9]{24}$/', $parts[0]) && preg_match('/^[a-f0-9]{64}$/', $parts[1])) {
        try {
            $q = db()->prepare("SELECT * FROM remember_tokens WHERE selector = ? AND expires_at > NOW() LIMIT 1");
            $q->execute([$parts[0]]);
            $t = $q->fetch();

            if ($t && hash_equals($t['validator_hash'], hash('sha256', $parts[1]))) {
                $uType = $t['user_type'] ?? 'vet';
                $uId   = (int)($t['user_id'] ?: $t['vet_user_id']);

                if ($uType === 'vet') {
                    $vq = db()->prepare("
                        SELECT u.*, c.name AS clinic_name, c.active AS clinic_active 
                        FROM vet_users u 
                        JOIN clinics c ON c.id = u.clinic_id 
                        WHERE u.id = ? AND u.active = 1 
                        LIMIT 1
                    ");
                    $vq->execute([$uId]);
                    $vu = $vq->fetch();
                    if ($vu && $vu['clinic_active']) {
                        session_regenerate_id(true);
                        $_SESSION['vet_user_id']  = (int)$vu['id'];
                        $_SESSION['clinic_id']    = (int)$vu['clinic_id'];
                        $_SESSION['vet_username'] = $vu['username'];
                        $_SESSION['clinic_name']  = $vu['clinic_name'];

                        $del = db()->prepare("DELETE FROM remember_tokens WHERE selector = ?");
                        $del->execute([$parts[0]]);
                        create_remember_cookie('vet', (int)$vu['id']);
                    } else {
                        clear_remember_cookie();
                    }
                } elseif ($uType === 'client') {
                    $cq = db()->prepare("SELECT * FROM clients WHERE id = ? LIMIT 1");
                    $cq->execute([$uId]);
                    $cu = $cq->fetch();
                    if ($cu) {
                        session_regenerate_id(true);
                        $_SESSION['client_id']   = (int)$cu['id'];
                        $_SESSION['client_name'] = $cu['full_name'];

                        $del = db()->prepare("DELETE FROM remember_tokens WHERE selector = ?");
                        $del->execute([$parts[0]]);
                        create_remember_cookie('client', (int)$cu['id']);
                    } else {
                        clear_remember_cookie();
                    }
                } elseif ($uType === 'admin') {
                    $aq = db()->prepare("SELECT * FROM admins WHERE id = ? LIMIT 1");
                    $aq->execute([$uId]);
                    $au = $aq->fetch();
                    if ($au) {
                        session_regenerate_id(true);
                        $_SESSION['admin_id'] = (int)$au['id'];

                        $del = db()->prepare("DELETE FROM remember_tokens WHERE selector = ?");
                        $del->execute([$parts[0]]);
                        create_remember_cookie('admin', (int)$au['id']);
                    } else {
                        clear_remember_cookie();
                    }
                }
            } else {
                clear_remember_cookie();
            }
        } catch (Throwable $e) {
            error_log('Remember-me lookup failed: ' . $e->getMessage());
        }
    }
}
