<?php
require_once __DIR__ . '/../config/config.php';
require_vet();
csrf_check();

$error = '';

// 1. Procesar el formulario de registro de un nuevo cliente
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dni = trim($_POST['dni'] ?? '');
    $name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    
    // Validar campos obligatorios y formato de correo electrónico
    if (!$dni || !$name || !filter_var($email, FILTER_VALIDATE_EMAIL) || !$phone) {
        $error = 'Completá todos los campos correctamente.';
    } else {
        $pdo = db();
        
        // Verificar si el cliente ya existe en el sistema global usando su DNI
        $q = $pdo->prepare("SELECT id FROM clients WHERE dni = ?");
        $q->execute([$dni]);
        $id = $q->fetchColumn();
        
        if ($id) {
            $error = 'Ese DNI ya existe. Usá Buscar cliente para acceder al registro compartido.';
        } else {
            // Registrar el nuevo cliente globalmente
            $q = $pdo->prepare("INSERT INTO clients(dni, full_name, email, phone) VALUES(?, ?, ?, ?)");
            $q->execute([$dni, $name, $email, $phone]);
            $id = (int)$pdo->lastInsertId();
        }
        
        // Si no hubo errores, vincular el cliente a la clínica actual y redirigir
        if (!$error) {
            $q = $pdo->prepare("INSERT IGNORE INTO clinic_clients(clinic_id, client_id) VALUES(?, ?)");
            $q->execute([$_SESSION['clinic_id'], $id]);
            
            header('Location: ' . url('clientes/ver.php?id=' . $id));
            exit;
        }
    }
}

$pageTitle = 'Nuevo cliente';
require __DIR__ . '/../includes/header.php';
?>

<div class="panel">
    <h1>Agregar cliente</h1>
    <p class="muted">El DNI identifica al cliente en todas las veterinarias participantes.</p>
    
    <?php if ($error): ?>
        <p role="alert"><?= e($error) ?></p>
    <?php endif; ?>
    
    <form method="post">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
        
        <div class="form-grid">
            <div>
                <label>DNI</label>
                <input name="dni" required>
            </div>
            
            <div>
                <label>Nombre y apellido</label>
                <input name="full_name" required>
            </div>
            
            <div>
                <label>Correo</label>
                <input type="email" name="email" required>
            </div>
            
            <div>
                <label>Celular</label>
                <input name="phone" required>
            </div>
        </div>
        
        <div class="actions">
            <button>Guardar cliente</button>
            <a class="btn secondary" href="<?= url('clientes/buscar.php') ?>">Cancelar</a>
        </div>
    </form>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>