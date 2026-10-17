<?php
declare(strict_types=1);

const DB_HOST = 'localhost';
const DB_NAME = 'vetesta';
const DB_USER = 'root';
const DB_PASS = '';
const APP_NAME = 'vetesta';
const BASE_URL = '/vetcare-v8'; // Ej.: '' si está en la raíz, o '/vetcare-v8' si está en subcarpeta.

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'httponly' => true, 'secure' => !empty($_SERVER['HTTPS']),
        'samesite' => 'Lax'
    ]);
    session_start();
}
function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(
            'mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4',
            DB_USER, DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
             PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
             PDO::ATTR_EMULATE_PREPARES => false]
        );
    }
    return $pdo;
}
function e(?string $value): string { return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8'); }
function url(string $path=''): string { return BASE_URL . '/' . ltrim($path, '/'); }
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_check(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $sent = $_POST['csrf'] ?? '';
        if (!is_string($sent) || !hash_equals($_SESSION['csrf'] ?? '', $sent)) {
            http_response_code(419); exit('La sesión del formulario expiró. Volvé a intentarlo.');
        }
    }
}
function require_vet(): void {
    if (empty($_SESSION['vet_user_id']) || empty($_SESSION['clinic_id'])) {
        header('Location: '.url('login.php')); exit;
    }
}
function require_admin(): void {
    if (empty($_SESSION['admin_id'])) { header('Location: '.url('admin/login.php')); exit; }
}
function require_client(): void {
    if (empty($_SESSION['client_id'])) { header('Location: '.url('cliente/login.php')); exit; }
}
function flash(string $message): void { $_SESSION['flash'] = $message; }
function show_flash(): void {
    if (!empty($_SESSION['flash'])) {
        echo '<div class="notice">'.e($_SESSION['flash']).'</div>';
        unset($_SESSION['flash']);
    }
}

function audit_log(string $entityType, int $entityId, string $action, array $details=[]): void {
    try {
        $q = db()->prepare("INSERT INTO audit_log(clinic_id,vet_user_id,entity_type,entity_id,action,details) VALUES(?,?,?,?,?,?)");
        $q->execute([$_SESSION['clinic_id'] ?? null, $_SESSION['vet_user_id'] ?? null, $entityType, $entityId, $action, json_encode($details, JSON_UNESCAPED_UNICODE)]);
    } catch (Throwable $e) { error_log('Audit log failed: '.$e->getMessage()); }
}
function ensure_upload_dir(): string {
    $dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads';
    if (!is_dir($dir)) mkdir($dir, 0750, true);
    return $dir;
}
if (empty($_SESSION['vet_user_id']) && !empty($_COOKIE['vet_remember'])) {
    $parts = explode(':', (string)$_COOKIE['vet_remember'], 2);
    if (count($parts) === 2 && preg_match('/^[a-f0-9]{24}$/', $parts[0]) && preg_match('/^[a-f0-9]{64}$/', $parts[1])) {
        try {
            $q = db()->prepare("SELECT t.*,u.clinic_id,u.username,u.active,c.name clinic_name,c.active clinic_active FROM remember_tokens t JOIN vet_users u ON u.id=t.vet_user_id JOIN clinics c ON c.id=u.clinic_id WHERE t.selector=? AND t.expires_at>NOW() LIMIT 1");
            $q->execute([$parts[0]]); $t = $q->fetch();
            if ($t && $t['active'] && $t['clinic_active'] && hash_equals($t['validator_hash'], hash('sha256', $parts[1]))) {
                session_regenerate_id(true);
                $_SESSION['vet_user_id']=$t['vet_user_id']; $_SESSION['clinic_id']=$t['clinic_id']; $_SESSION['vet_username']=$t['username']; $_SESSION['clinic_name']=$t['clinic_name'];
                $q=db()->prepare("DELETE FROM remember_tokens WHERE selector=?"); $q->execute([$parts[0]]);
                $selector=bin2hex(random_bytes(12)); $validator=bin2hex(random_bytes(32));
                $q=db()->prepare("INSERT INTO remember_tokens(vet_user_id,selector,validator_hash,expires_at) VALUES(?,?,?,DATE_ADD(NOW(),INTERVAL 30 DAY))");
                $q->execute([$t['vet_user_id'],$selector,hash('sha256',$validator)]);
                setcookie('vet_remember',$selector.':'.$validator,['expires'=>time()+2592000,'path'=>'/','secure'=>!empty($_SERVER['HTTPS']),'httponly'=>true,'samesite'=>'Lax']);
            } else {
                $q=db()->prepare("DELETE FROM remember_tokens WHERE selector=?"); $q->execute([$parts[0]]);
                setcookie('vet_remember','',['expires'=>time()-3600,'path'=>'/','httponly'=>true,'samesite'=>'Lax']);
            }
        } catch (Throwable $e) { error_log('Remember-me lookup failed: '.$e->getMessage()); }
    }
}
