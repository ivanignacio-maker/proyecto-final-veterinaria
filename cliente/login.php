<?php
require_once __DIR__ . '/../config/config.php';
header('Location: ' . url('login.php?tipo=cliente'));
exit;

csrf_check();

$error = '';

// Procesar el formulario de inicio de sesión del cliente
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dni = trim($_POST['dni'] ?? '');
    $email = trim($_POST['email'] ?? '');
    
    // Buscar al cliente por DNI y correo electrónico (ignorando mayúsculas/minúsculas en el correo)
    $q = db()->prepare("SELECT * FROM clients WHERE dni = ? AND LOWER(email) = LOWER(?)");
    $q->execute([$dni, $email]);
    $c = $q->fetch();
    
    if ($c) {
        // Generar un nuevo ID de sesión por seguridad e iniciar la sesión del cliente
        session_regenerate_id(true);
        $_SESSION['client_id'] = $c['id'];
        
        header('Location: ' . url('cliente/mascotas.php'));
        exit;
    }
    
    $error = 'No se encontró un cliente con ese DNI y correo.';
}

$pageTitle = 'Portal del cliente';
require __DIR__ . '/../includes/header.php';
?>

<div class="auth-wrapper" style="padding: 40px 15px;">
    <div class="auth-card" style="max-width: 480px; margin: 0 auto;">
        <div class="auth-header">
            <div class="auth-badge">
                <span class="auth-paw">🐾</span>
            </div>
            <h2>Portal de Tutores</h2>
            <p class="auth-subtitle">Accedé a las historias clínicas y libreta sanitaria digital de tus mascotas</p>
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
                <label>DNI del tutor</label>
                <input name="dni" required placeholder="Número de documento">
            </div>
            
            <div class="form-group" style="margin-top: 14px;">
                <label>Correo electrónico registrado</label>
                <input type="email" name="email" required placeholder="tutor@correo.com">
            </div>
            
            <p class="form-note" style="margin: 12px 0 18px; text-align: left; font-size: 13px; color: var(--muted);">
                ℹ️ No requerís contraseña: tu acceso está vinculado al DNI y email informados en la clínica.
            </p>
            
            <button class="btn primary full" type="submit" style="padding: 13px;">
                <span>Ver libreta de mis mascotas</span>
                <span style="margin-left: 6px;">→</span>
            </button>
        </form>
        
        <div style="text-align: center; margin-top: 20px; padding-top: 18px; border-top: 1px solid var(--line);">
            <a href="<?= url('login.php?tipo=veterinaria') ?>" style="font-size: 13px; font-weight: 700; color: var(--green);">
                🏥 ¿Sos veterinaria? Ingresá aquí
            </a>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>