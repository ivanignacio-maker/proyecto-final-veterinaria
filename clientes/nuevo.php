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
        $error = 'Completá todos los campos correctamente con un correo válido.';
    } else {
        $pdo = db();
        
        // Verificar si el cliente ya existe en el sistema global usando su DNI
        $q = $pdo->prepare("SELECT id FROM clients WHERE dni = ?");
        $q->execute([$dni]);
        $id = $q->fetchColumn();
        
        if ($id) {
            $error = 'Ese DNI ya se encuentra registrado. Podés buscarlo desde "Buscar cliente".';
        } else {
            // Registrar el nuevo cliente globalmente guardando con formato ordenado y contraseña inicial (su DNI)
            $pwHash = password_hash($dni, PASSWORD_DEFAULT);
            $q = $pdo->prepare("INSERT INTO clients(dni, full_name, email, phone, password_hash) VALUES(?, ?, ?, ?, ?)");
            $q->execute([$dni, format_name($name), strtolower($email), $phone, $pwHash]);
            $id = (int)$pdo->lastInsertId();
        }
        
        // Si no hubo errores, vincular el cliente a la clínica actual y redirigir
        if (!$error) {
            $q = $pdo->prepare("INSERT IGNORE INTO clinic_clients(clinic_id, client_id) VALUES(?, ?)");
            $q->execute([$_SESSION['clinic_id'], $id]);
            
            flash('Cliente registrado y vinculado exitosamente.');
            header('Location: ' . url('clientes/ver.php?id=' . $id));
            exit;
        }
    }
}

$pageTitle = 'Nuevo Cliente | ' . APP_NAME;
require __DIR__ . '/../includes/header.php';
?>

<div class="panel">
    <h1><i class="fa-solid fa-user-plus" style="color: var(--green); margin-right: 8px;"></i>Registrar nuevo cliente</h1>
    <p class="muted">El DNI identifica de forma única al tutor en toda la red de clínicas participantes.</p>
    
    <?php if ($error): ?>
        <div class="notice error">
            <span class="notice-icon"><i class="fa-solid fa-triangle-exclamation"></i></span>
            <span><?= e($error) ?></span>
        </div>
    <?php endif; ?>
    
    <form method="post" style="margin-top: 18px;">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
        
        <div class="form-grid">
            <div>
                <label>DNI del tutor</label>
                <input name="dni" required inputmode="numeric" placeholder="Número de documento">
            </div>
            
            <div>
                <label>Nombre y apellido completo</label>
                <input name="full_name" required placeholder="Ej. Juan Pérez">
            </div>
            
            <div>
                <label>Correo electrónico</label>
                <input type="email" name="email" required placeholder="correo@ejemplo.com">
            </div>
            
            <div>
                <label>Teléfono o celular</label>
                <input name="phone" required placeholder="Ej. 11 4455-6677">
            </div>
        </div>
        
        <div class="actions">
            <button type="submit" class="btn primary">
                <span>Guardar cliente</span>
                <i class="fa-solid fa-arrow-right" style="margin-left: 6px;"></i>
            </button>
            <a class="btn secondary" href="<?= url('clientes/buscar.php') ?>">Cancelar</a>
        </div>
    </form>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
