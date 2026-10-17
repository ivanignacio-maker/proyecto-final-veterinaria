<?php
require_once __DIR__ . '/config/config.php';
csrf_check();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $q = db()->prepare("SELECT u.*, c.name clinic_name, c.active clinic_active FROM vet_users u JOIN clinics c ON c.id=u.clinic_id WHERE c.name=? AND u.email=? AND u.active=1 LIMIT 1");
    $q->execute([trim($_POST['clinic_name'] ?? ''), trim($_POST['email'] ?? '')]);
    $u = $q->fetch();

    if ($u && $u['clinic_active'] && password_verify($_POST['password'] ?? '', $u['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['vet_user_id'] = $u['id'];
        $_SESSION['clinic_id'] = $u['clinic_id'];
        $_SESSION['vet_username'] = $u['username'];
        $_SESSION['clinic_name'] = $u['clinic_name'];

        if (!empty($_POST['remember'])) {
            $selector = bin2hex(random_bytes(12));
            $validator = bin2hex(random_bytes(32));
            
            $q = db()->prepare("INSERT INTO remember_tokens(vet_user_id, selector, validator_hash, expires_at) VALUES(?, ?, ?, DATE_ADD(NOW(), INTERVAL 30 DAY))");
            $q->execute([$u['id'], $selector, hash('sha256', $validator)]);
            
            setcookie('vet_remember', $selector . ':' . $validator, [
                'expires' => time() + 2592000,
                'path' => '/',
                'secure' => !empty($_SERVER['HTTPS']),
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
        }
        
        header('Location: ' . url('dashboard.php'));
        exit;
    }
    
    $error = 'Los datos ingresados no coinciden o la cuenta está desactivada.';
}

$pageTitle = 'Iniciar sesión';
require __DIR__ . '/includes/header.php';
?>

<div class="panel" style="max-width:520px;margin:45px auto">
    <h1>Ingreso de veterinaria</h1>
    <p class="muted">Acceso para clínicas registradas por el administrador.</p>
    
    <?php if ($error): ?>
        <p role="alert"><?= e($error) ?></p>
    <?php endif; ?>

    <form method="post">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
        
        <label>Nombre de la veterinaria</label>
        <input name="clinic_name" required autocomplete="organization">
        
        <label>Correo</label>
        <input type="email" name="email" required autocomplete="username">
        
        <label>Contraseña</label>
        <input type="password" name="password" required autocomplete="current-password">
        
        <p>
            <label style="font-weight:400">
                <input type="checkbox" name="remember"> Recordarme en este dispositivo
            </label>
        </p>
        
        <button>Iniciar sesión</button>
    </form>
    
    <p>
        <a href="<?= url('cliente/login.php') ?>">Soy cliente</a>
    </p>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>