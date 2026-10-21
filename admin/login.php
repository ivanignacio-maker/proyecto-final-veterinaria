<?php
require_once __DIR__ . '/../config/config.php';
if (!empty($_SESSION['admin_id'])) {
    header('Location: ' . url('admin/veterinarias.php'));
    exit;
}

csrf_check();

$error = '';

// Procesar el formulario de inicio de sesión del administrador
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Buscar al administrador por su correo electrónico (case-insensitive)
    $q = db()->prepare("SELECT * FROM admins WHERE LOWER(email) = LOWER(?) LIMIT 1");
    $q->execute([$email]);
    $a = $q->fetch();
    
    // Verificar si el usuario existe y la contraseña ingresada coincide con el hash
    if ($a && password_verify($password, $a['password_hash'])) {
        // Regenerar el ID de sesión por seguridad e iniciar la sesión
        session_regenerate_id(true);
        $_SESSION['admin_id'] = $a['id'];
        
        // Redirigir al panel principal de administración
        header('Location: ' . url('admin/veterinarias.php'));
        exit;
    }
    
    $error = 'Correo o contraseña incorrectos.';
}

$pageTitle = 'Administración';
require __DIR__ . '/../includes/header.php';
?>

<div class="auth-wrapper" style="padding: 40px 15px;">
    <div class="auth-card" style="max-width: 480px; margin: 0 auto;">
        <div class="auth-header">
            <div class="auth-badge">
                <span class="auth-paw">⚙️</span>
            </div>
            <h2>Administración General</h2>
            <p class="auth-subtitle">Acceso exclusivo para administradores de la plataforma VetCompartido</p>
        </div>
        
        <?php if ($error): ?>
            <div class="notice error">
                <span class="notice-icon">⚠️</span>
                <span><?= e($error) ?></span>
            </div>
        <?php endif; ?>
        
        <form method="post" class="auth-form">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
            
            <div class="form-group">
                <label>Correo del administrador</label>
                <input type="email" name="email" required placeholder="admin@vetcompartido.com">
            </div>
            
            <div class="form-group" style="margin-top: 14px;">
                <label>Contraseña maestra</label>
                <input type="password" name="password" required placeholder="••••••••••••">
            </div>
            
            <button class="btn primary full" type="submit" style="margin-top: 20px; padding: 13px;">
                <span>Ingresar al panel de control</span>
                <span style="margin-left: 6px;">→</span>
            </button>
        </form>
        
        <div style="text-align: center; margin-top: 20px; padding-top: 18px; border-top: 1px solid var(--line);">
            <a href="<?= url('login.php') ?>" style="font-size: 13px; font-weight: 700; color: var(--green);">
                ← Volver al login principal
            </a>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>