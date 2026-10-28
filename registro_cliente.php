<?php
require_once __DIR__ . '/config/config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dni      = trim($_POST['dni'] ?? '');
    $fullName = format_name(trim($_POST['full_name'] ?? ''));
    $email    = trim($_POST['email'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    // Validaciones de campos obligatorios y formato
    if ($dni === '' || $fullName === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Por favor, completá el DNI, nombre y un correo electrónico válido.';
    } elseif (strlen($password) < 8) {
        $error = 'La contraseña debe tener al menos 8 caracteres.';
    } elseif ($password !== $confirm) {
        $error = 'Las contraseñas no coinciden.';
    } else {
        try {
            // Verificar si el correo o el DNI ya se encuentran registrados en la base de datos
            $check = db()->prepare("SELECT id FROM clients WHERE email = ? OR dni = ?");
            $check->execute([$email, $dni]);
            if ($check->fetch()) {
                $error = 'El correo electrónico o el DNI ya se encuentran registrados en el sistema.';
            } else {
                // Registrar el nuevo cliente en la tabla 'clients'
                $stmt = db()->prepare("
                    INSERT INTO clients (dni, full_name, email, phone, password_hash, created_at, updated_at) 
                    VALUES (?, ?, ?, ?, ?, NOW(), NOW())
                ");
                $stmt->execute([
                    $dni,
                    $fullName,
                    $email,
                    $phone,
                    password_hash($password, PASSWORD_DEFAULT)
                ]);

                flash('¡Cuenta creada con éxito! Ya podés iniciar sesión con tus datos.');
                header('Location: ' . url('login_cliente.php'));
                exit;
            }
        } catch (Throwable $ex) {
            error_log('Error en registro de cliente: ' . $ex->getMessage());
            $error = 'Ocurrió un error al procesar el registro. Intentalo de nuevo más tarde.';
        }
    }
}

$pageTitle = 'Crear cuenta de cliente | Portal Tutores';
require __DIR__ . '/includes/header.php';
?>

<div class="panel" style="max-width: 500px; margin: 40px auto; background: #ffffff; border: 1px solid var(--line); border-radius: 20px; padding: 32px; box-shadow: 0 4px 20px rgba(24,59,53,0.04);">
    <div style="text-align: center; margin-bottom: 24px;">
        <span style="font-size: 36px; color: var(--green); display: inline-block; margin-bottom: 8px;">
            <i class="fa-solid fa-user-plus"></i>
        </span>
        <h1 style="margin: 0; font-size: 24px; color: var(--ink);">Crear mi cuenta</h1>
        <p class="muted" style="margin: 6px 0 0; font-size: 14px;">Registrate para administrar el perfil e historial médico de tus mascotas.</p>
    </div>

    <?php if ($error): ?>
        <div class="notice error" style="margin-bottom: 20px;">
            <span class="notice-icon"><i class="fa-solid fa-triangle-exclamation"></i></span>
            <span><?= e($error) ?></span>
        </div>
    <?php endif; ?>

    <form method="post">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">

        <div class="form-group" style="margin-bottom: 16px;">
            <label style="display: block; font-weight: 700; font-size: 13px; margin-bottom: 6px; color: var(--ink);">DNI *</label>
            <input type="text" name="dni" class="form-control" required placeholder="Número de documento" value="<?= e($_POST['dni'] ?? '') ?>" style="width: 100%; padding: 10px 14px; border: 1px solid var(--line); border-radius: 10px; font-size: 14px;">
        </div>

        <div class="form-group" style="margin-bottom: 16px;">
            <label style="display: block; font-weight: 700; font-size: 13px; margin-bottom: 6px; color: var(--ink);">Nombre y Apellido *</label>
            <input type="text" name="full_name" class="form-control" required placeholder="Ej. Juan Pérez" value="<?= e($_POST['full_name'] ?? '') ?>" style="width: 100%; padding: 10px 14px; border: 1px solid var(--line); border-radius: 10px; font-size: 14px;">
        </div>

        <div class="form-group" style="margin-bottom: 16px;">
            <label style="display: block; font-weight: 700; font-size: 13px; margin-bottom: 6px; color: var(--ink);">Correo electrónico *</label>
            <input type="email" name="email" class="form-control" required placeholder="tutor@correo.com" value="<?= e($_POST['email'] ?? '') ?>" style="width: 100%; padding: 10px 14px; border: 1px solid var(--line); border-radius: 10px; font-size: 14px;">
        </div>

        <div class="form-group" style="margin-bottom: 16px;">
            <label style="display: block; font-weight: 700; font-size: 13px; margin-bottom: 6px; color: var(--ink);">Teléfono de contacto</label>
            <input type="text" name="phone" class="form-control" placeholder="Ej. 11 1234 5678" value="<?= e($_POST['phone'] ?? '') ?>" style="width: 100%; padding: 10px 14px; border: 1px solid var(--line); border-radius: 10px; font-size: 14px;">
        </div>

        <div class="form-group" style="margin-bottom: 16px;">
            <label style="display: block; font-weight: 700; font-size: 13px; margin-bottom: 6px; color: var(--ink);">Contraseña *</label>
            <input type="password" name="password" class="form-control" minlength="8" required placeholder="Mínimo 8 caracteres" style="width: 100%; padding: 10px 14px; border: 1px solid var(--line); border-radius: 10px; font-size: 14px;">
        </div>

        <div class="form-group" style="margin-bottom: 24px;">
            <label style="display: block; font-weight: 700; font-size: 13px; margin-bottom: 6px; color: var(--ink);">Confirmar contraseña *</label>
            <input type="password" name="confirm_password" class="form-control" minlength="8" required placeholder="Repetir contraseña" style="width: 100%; padding: 10px 14px; border: 1px solid var(--line); border-radius: 10px; font-size: 14px;">
        </div>

        <button type="submit" class="btn primary" style="width: 100%; justify-content: center; padding: 12px; font-size: 15px;">
            Registrarme
        </button>

        <p class="muted" style="text-align: center; margin-top: 20px; font-size: 14px;">
            ¿Ya tenés una cuenta? <a href="<?= url('login_cliente.php') ?>" style="color: var(--green); font-weight: 600;">Iniciá sesión acá</a>
        </p>
    </form>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>