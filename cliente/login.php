<?php
require_once __DIR__ . '/../config/config.php';
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

<div class="panel" style="max-width:520px;margin:45px auto">
    <h1>Portal del cliente</h1>
    <p>Ingresá con tu DNI y correo registrado.</p>
    
    <?php if ($error): ?>
        <p><?= e($error) ?></p>
    <?php endif; ?>
    
    <form method="post">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
        
        <label>DNI</label>
        <input name="dni" required>
        
        <label>Correo</label>
        <input type="email" name="email" required>
        
        <button>Ingresar</button>
    </form>
    
    <p>
        <a href="<?= url('login.php') ?>">Acceso veterinaria</a>
    </p>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>