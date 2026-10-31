<?php
declare(strict_types=1);

// ============================================================================
// Configuración de Base de Datos y Aplicación
// ============================================================================
if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
if (!defined('DB_NAME')) define('DB_NAME', 'BDD_Veterinaria');
if (!defined('DB_USER')) define('DB_USER', 'root');
if (!defined('DB_PASS')) define('DB_PASS', '');
if (!defined('APP_NAME')) define('APP_NAME', 'VetCare');

// Hora de Argentina: el php.ini de XAMPP trae Europe/Berlin (4 h adelante) y
// MySQL ya usa la hora del sistema. Sin esto, "ahora" en PHP no coincide con la base.
date_default_timezone_set('America/Argentina/Buenos_Aires');

// Motivos habituales de un turno. "Otro" permite escribir uno a mano.
const APPOINTMENT_REASONS = [
    'Consulta general', 'Control', 'Vacunación', 'Desparasitación', 'Castración',
    'Cirugía', 'Análisis clínicos', 'Dermatología', 'Odontología', 'Urgencia',
];
const APPOINTMENT_REASON_OTHER = 'Otro';
const APPOINTMENT_REASON_MAX = 120;

// Lee el motivo elegido en el formulario (includes/motivo_turno.php).
// Devuelve el texto a guardar, o null si falta o no es válido.
function appointment_reason_from_post(): ?string {
    $option = (string)($_POST['reason_option'] ?? '');
    if (in_array($option, APPOINTMENT_REASONS, true)) {
        return $option;
    }
    if ($option === APPOINTMENT_REASON_OTHER) {
        $text = trim((string)preg_replace('/\s+/u', ' ', (string)($_POST['reason_other'] ?? '')));
        if ($text !== '' && mb_strlen($text) <= APPOINTMENT_REASON_MAX) {
            return $text;
        }
    }
    return null;
}

// ============================================================================
// Correo saliente (recordatorios de vacunas)
// Los datos reales van en config/mail.local.php (no se sube al repositorio).
// Sin MAIL_HOST o MAIL_PASS el sistema funciona en "modo prueba": guarda cada correo como
// archivo .eml en storage/correos/ en lugar de enviarlo.
// ============================================================================
if (is_file(__DIR__ . '/mail.local.php')) require __DIR__ . '/mail.local.php';
if (!defined('MAIL_HOST')) define('MAIL_HOST', '');            // Ej.: 'smtp.gmail.com'
if (!defined('MAIL_PORT')) define('MAIL_PORT', 587);
if (!defined('MAIL_USER')) define('MAIL_USER', '');            // Ej.: 'vetcare987@gmail.com'
if (!defined('MAIL_PASS')) define('MAIL_PASS', '');            // En Gmail: "contraseña de aplicación"
if (!defined('MAIL_FROM')) define('MAIL_FROM', MAIL_USER ?: 'no-responder@vetcare.local');
if (!defined('MAIL_FROM_NAME')) define('MAIL_FROM_NAME', 'VetCare');
// Solo se envía de verdad con servidor y contraseña cargados; si no, modo prueba.
define('MAIL_ENABLED', MAIL_HOST !== '' && MAIL_PASS !== '');

// Credenciales OAuth 2.0 para el acceso de tutores con Google.
if (!defined('GOOGLE_CLIENT_ID')) define('GOOGLE_CLIENT_ID', getenv('VETCARE_GOOGLE_CLIENT_ID') ?: '');
if (!defined('GOOGLE_CLIENT_SECRET')) define('GOOGLE_CLIENT_SECRET', getenv('VETCARE_GOOGLE_CLIENT_SECRET') ?: '');
if (!defined('GOOGLE_REDIRECT_URI')) define('GOOGLE_REDIRECT_URI', getenv('VETCARE_GOOGLE_REDIRECT_URI') ?: '');

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

// URL completa del sitio, para los links dentro de los correos.
// Desde la consola (tarea programada) no hay dominio: se arma con la carpeta dentro de htdocs.
if (!defined('APP_URL')) {
    if (!empty($_SERVER['HTTP_HOST'])) {
        define('APP_URL', (!empty($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . BASE_URL);
    } else {
        $projectDir = str_replace('\\', '/', dirname(__DIR__));
        $pos = stripos($projectDir, '/htdocs/');
        define('APP_URL', 'http://localhost' . ($pos !== false ? substr($projectDir, $pos + 7) : ''));
    }
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

/**
 * Devuelve el nombre del archivo de imagen generica segun el tipo de animal.
 * Si no se selecciona o no coincide, queda en "otro".
 */
function get_pet_generic_avatar(?string $animalType, ?string $breed = null): string {
    $typeNorm = mb_strtolower(trim($animalType ?? ''), 'UTF-8');
    $typeNorm = strtr($typeNorm, ['á'=>'a', 'é'=>'e', 'í'=>'i', 'ó'=>'o', 'ú'=>'u', 'ñ'=>'n']);

    switch ($typeNorm) {
        case 'perro':
            return 'perro_generico.webp';
        case 'gato':
            return 'gato_generico.webp';
        case 'ave':
            return 'ave_generico.webp';
        case 'roedor':
            return 'roedor_generico.webp';
        case 'reptil':
            return 'reptil_generico.webp';
        case 'otro':
        default:
            return 'otro_generico.webp';
    }
}

/**
 * Devuelve la URL para mostrar la foto de la mascota o su ilustracion generica segun su tipo.
 */
function pet_photo_url($pet): string {
    if (!$pet) {
        return url('assets/otro_generico.webp');
    }
    $photoPath = is_array($pet) ? ($pet['photo_path'] ?? null) : ($pet->photo_path ?? null);
    $id = is_array($pet) ? ($pet['id'] ?? null) : ($pet->id ?? null);
    $type = is_array($pet) ? ($pet['animal_type'] ?? null) : ($pet->animal_type ?? null);

    if (!empty($photoPath) && !empty($id)) {
        return url('archivo_mascota.php?id=' . (int)$id);
    }
    return url('assets/' . get_pet_generic_avatar($type));
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

function ensure_pet_sexo_column(): void {
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        $cols = db()->query("SHOW COLUMNS FROM pets LIKE 'sexo'")->fetchAll();
        if (empty($cols)) {
            db()->exec("ALTER TABLE pets ADD COLUMN sexo ENUM('Macho', 'Hembra') DEFAULT NULL AFTER animal_type");
        }
    } catch (Throwable $e) {
        error_log('Schema ensure pet sexo: ' . $e->getMessage());
    }
}
ensure_pet_sexo_column();

function ensure_tables_auto_increment(): void {
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        $tables = ['pets', 'consultation_files', 'medications', 'remember_tokens'];
        foreach ($tables as $t) {
            $col = db()->query("SHOW COLUMNS FROM `$t` LIKE 'id'")->fetch();
            if ($col && strpos(strtolower($col['Extra'] ?? ''), 'auto_increment') === false) {
                $qMax = db()->query("SELECT COALESCE(MAX(id), 0) as m FROM `$t` WHERE id > 0");
                $maxId = (int)($qMax->fetch()['m'] ?? 0);
                db()->exec("UPDATE `$t` SET id = " . ($maxId + 1) . " WHERE id = 0");
                db()->exec("ALTER TABLE `$t` MODIFY id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT");
                db()->exec("ALTER TABLE `$t` AUTO_INCREMENT = " . ($maxId + 2));
            }
        }
    } catch (Throwable $e) {
        error_log('Schema ensure auto_increment: ' . $e->getMessage());
    }
}
ensure_tables_auto_increment();

// ============================================================================
// Asegurar tablas de Co-tutores y Códigos de Acceso Clínico
// ============================================================================
function ensure_pet_co_owners_and_codes_tables(): void {
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        db()->exec("
            CREATE TABLE IF NOT EXISTS pet_co_owners (
                id INT AUTO_INCREMENT PRIMARY KEY,
                pet_id INT NOT NULL,
                client_id INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_pet_client (pet_id, client_id),
                INDEX (pet_id),
                INDEX (client_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        db()->exec("
            CREATE TABLE IF NOT EXISTS pet_access_codes (
                id INT AUTO_INCREMENT PRIMARY KEY,
                pet_id INT NOT NULL,
                code VARCHAR(10) NOT NULL,
                action_type VARCHAR(50) DEFAULT 'modify_history',
                created_by_client_id INT NOT NULL,
                used_by_vet_user_id INT DEFAULT NULL,
                used_at DATETIME DEFAULT NULL,
                expires_at DATETIME NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX (pet_id, code),
                INDEX (expires_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    } catch (Throwable $e) {
        error_log('Schema ensure co_owners/codes: ' . $e->getMessage());
    }
}
ensure_pet_co_owners_and_codes_tables();

// Comprueba si un cliente es dueño o co-dueño de una mascota
function is_client_pet_owner(int $clientId, int $petId): bool {
    try {
        $q = db()->prepare("
            SELECT 1 FROM pets WHERE id = ? AND client_id = ?
            UNION
            SELECT 1 FROM pet_co_owners WHERE pet_id = ? AND client_id = ?
            LIMIT 1
        ");
        $q->execute([$petId, $clientId, $petId, $clientId]);
        return (bool)$q->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

// Obtiene todos los dueños (principal y co-dueños) de una mascota
function get_pet_owners(int $petId): array {
    try {
        $q = db()->prepare("
            SELECT c.id, c.full_name, c.dni, c.email, c.phone, 1 AS is_primary
            FROM clients c
            JOIN pets p ON p.client_id = c.id
            WHERE p.id = ?
            UNION
            SELECT c.id, c.full_name, c.dni, c.email, c.phone, 0 AS is_primary
            FROM clients c
            JOIN pet_co_owners pco ON pco.client_id = c.id
            WHERE pco.pet_id = ?
            ORDER BY is_primary DESC, full_name ASC
        ");
        $q->execute([$petId, $petId]);
        return $q->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

// Genera un código de acceso aleatorio de 6 dígitos para una mascota
function generate_pet_access_code(int $petId, int $clientId): string {
    // Un código nuevo reemplaza a todos los códigos aún activos de la mascota.
    $invalidate = db()->prepare("
        UPDATE pet_access_codes
        SET used_at = NOW()
        WHERE pet_id = ?
          AND used_at IS NULL
          AND expires_at >= NOW()
    ");
    $invalidate->execute([$petId]);

    // Código numérico aleatorio de 6 dígitos (100000 - 999999)
    $code = (string)random_int(100000, 999999);
    $expires = date('Y-m-d H:i:s', time() + (30 * 60)); // 30 minutos de validez

    $q = db()->prepare("
        INSERT INTO pet_access_codes (pet_id, code, action_type, created_by_client_id, expires_at)
        VALUES (?, ?, 'modify_history', ?, ?)
    ");
    $q->execute([$petId, $code, $clientId, $expires]);

    return $code;
}

// Devuelve el código vigente más reciente sin consumir para una mascota.
function get_active_pet_access_code(int $petId): ?string {
    $q = db()->prepare("
        SELECT code FROM pet_access_codes
        WHERE pet_id = ?
          AND used_at IS NULL
          AND expires_at >= NOW()
        ORDER BY id DESC
        LIMIT 1
    ");
    $q->execute([$petId]);
    $code = $q->fetchColumn();
    return $code === false ? null : (string)$code;
}

// Verifica y consume un código de acceso otorgado por el tutor
function verify_and_use_pet_access_code(int $petId, string $code, int $vetUserId): bool {
    $cleanCode = preg_replace('/\s+/', '', trim($code));
    if ($cleanCode === '') return false;

    $q = db()->prepare("
        SELECT id FROM pet_access_codes
        WHERE pet_id = ? 
          AND code = ? 
          AND used_at IS NULL 
          AND expires_at >= NOW()
        ORDER BY id DESC
        LIMIT 1
    ");
    $q->execute([$petId, $cleanCode]);
    $codeId = $q->fetchColumn();

    if (!$codeId) {
        return false;
    }

    // Marcar como consumido por este veterinario
    $up = db()->prepare("UPDATE pet_access_codes SET used_at = NOW(), used_by_vet_user_id = ? WHERE id = ?");
    $up->execute([$vetUserId, $codeId]);

    return true;
}

function ensure_upload_dir(): string {
    $dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads';
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }
    if (!is_dir($dir . DIRECTORY_SEPARATOR . 'pets')) {
        @mkdir($dir . DIRECTORY_SEPARATOR . 'pets', 0750, true);
    }
    if (!is_dir($dir . DIRECTORY_SEPARATOR . 'avatars')) {
        @mkdir($dir . DIRECTORY_SEPARATOR . 'avatars', 0750, true);
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
