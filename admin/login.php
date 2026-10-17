<?php
require_once __DIR__ . '/../config/config.php';
csrf_check();

$error = '';

// Procesar el formulario de inicio de sesión del administrador
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Buscar al administrador por su correo electrónico
    $q = db()->prepare("SELECT * FROM admins WHERE email = ?");
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

<div class="panel" style="max-width:500px;margin:40px auto">
    <h1>Administración general</h1>
    
    <?php if ($error): ?>
        <p><?= e($error) ?></p>
    <?php endif; ?>
    
    <form method="post">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
        
        <label>Correo administrador</label>
        <input type="email" name="email" required>
        
        <label>Contraseña</label>
        <input type="password" name="password" required>
        
        <button>Ingresar</button>
    </form>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>