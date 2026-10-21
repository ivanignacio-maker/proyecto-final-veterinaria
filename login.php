<?php
require_once __DIR__ . '/config/config.php';

// Si ya hay una sesión activa, redirigir al panel correspondiente
if (!empty($_SESSION['clinic_id'])) {
    header('Location: ' . url('dashboard.php'));
    exit;
}
if (!empty($_SESSION['client_id'])) {
    header('Location: ' . url('cliente/mascotas.php'));
    exit;
}
if (!empty($_SESSION['admin_id'])) {
    header('Location: ' . url('admin/veterinarias.php'));
    exit;
}

csrf_check();

$error = '';
$tipo = ($_GET['tipo'] ?? 'veterinaria') === 'cliente' ? 'cliente' : 'veterinaria';

$identity = '';
$dni = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tipo = ($_POST['tipo'] ?? 'veterinaria') === 'cliente' ? 'cliente' : 'veterinaria';
    $remember = !empty($_POST['remember']);

    if ($tipo === 'cliente') {
        $dni = trim($_POST['dni'] ?? '');
        $email = trim($_POST['email'] ?? '');

        if ($dni === '' || $email === '') {
            $error = 'Por favor ingresá tu DNI y el correo electrónico registrado.';
        } else {
            try {
                $q = db()->prepare("SELECT * FROM clients WHERE dni = ? AND LOWER(email) = LOWER(?) LIMIT 1");
                $q->execute([$dni, $email]);
                $c = $q->fetch();

                if ($c) {
                    session_regenerate_id(true);
                    $_SESSION['client_id']   = (int)$c['id'];
                    $_SESSION['client_name'] = $c['full_name'];

                    if ($remember) {
                        create_remember_cookie('client', (int)$c['id']);
                    }
                    header('Location: ' . url('cliente/mascotas.php'));
                    exit;
                }

                $error = 'No se encontró ningún tutor registrado con ese DNI y correo electrónico. Verificá los datos con tu clínica veterinaria.';
            } catch (Throwable $e) {
                $error = 'Error de conexión con la base de datos. Verificá que el servicio MySQL esté activo.';
            }
        }
    } else {
        $identity = trim($_POST['identity'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($identity === '' || $password === '') {
            $error = 'Por favor completá tu correo o usuario y tu contraseña.';
        } else {
            try {
                // 1. Intentar autenticar como Veterinario
                $q = db()->prepare("
                    SELECT u.*, c.name AS clinic_name, c.active AS clinic_active 
                    FROM vet_users u 
                    JOIN clinics c ON c.id = u.clinic_id 
                    WHERE (LOWER(u.email) = LOWER(?) OR LOWER(u.username) = LOWER(?)) 
                      AND u.active = 1 
                    LIMIT 1
                ");
                $q->execute([$identity, $identity]);
                $u = $q->fetch();

                if ($u && password_verify($password, $u['password_hash'])) {
                    if (!$u['clinic_active']) {
                        $error = 'La clínica veterinaria asociada se encuentra inactiva o suspendida.';
                    } else {
                        session_regenerate_id(true);
                        $_SESSION['vet_user_id']  = (int)$u['id'];
                        $_SESSION['clinic_id']    = (int)$u['clinic_id'];
                        $_SESSION['vet_username'] = $u['username'];
                        $_SESSION['clinic_name']  = $u['clinic_name'];

                        if ($remember) {
                            create_remember_cookie('vet', (int)$u['id']);
                        }
                        header('Location: ' . url('dashboard.php'));
                        exit;
                    }
                } else {
                    // 2. Si no es veterinario, verificar si es Administrador general
                    $aq = db()->prepare("SELECT * FROM admins WHERE LOWER(email) = LOWER(?) LIMIT 1");
                    $aq->execute([$identity]);
                    $a = $aq->fetch();

                    if ($a && password_verify($password, $a['password_hash'])) {
                        session_regenerate_id(true);
                        $_SESSION['admin_id'] = (int)$a['id'];

                        if ($remember) {
                            create_remember_cookie('admin', (int)$a['id']);
                        }
                        header('Location: ' . url('admin/veterinarias.php'));
                        exit;
                    } else {
                        $error = 'Los datos ingresados no coinciden con una cuenta veterinaria activa o la contraseña es incorrecta.';
                    }
                }
            } catch (Throwable $e) {
                $error = 'Error de conexión con la base de datos. Verificá que el servicio MySQL esté activo.';
            }
        }
    }
}

$pageTitle = ($tipo === 'cliente' ? 'Portal de Tutores' : 'Acceso Veterinarias') . ' | ' . APP_NAME;
require __DIR__ . '/includes/header.php';
?>

<div class="auth-wrapper">
    <div class="auth-card">
        <div class="auth-header">
            <div class="auth-badge">
                <span class="auth-paw"><?= $tipo === 'cliente' ? '<i class="fa-solid fa-paw"></i>' : '<i class="fa-solid fa-hospital"></i>' ?></span>
            </div>
            <h2><?= $tipo === 'cliente' ? 'Portal de Tutores' : 'Acceso Veterinarias' ?></h2>
            <p class="auth-subtitle">
                <?= $tipo === 'cliente' 
                    ? 'Consultá la libreta sanitaria digital y turnos de tus mascotas' 
                    : 'Ingresá al panel médico y gestión clínica de tu veterinaria' ?>
            </p>
        </div>

        <!-- Pestañas de Acceso Diferenciado -->
        <div class="tabs auth-tabs">
            <a class="<?= $tipo === 'veterinaria' ? 'active' : '' ?>" href="<?= url('login.php?tipo=veterinaria') ?>">
                <span class="tab-icon"><i class="fa-solid fa-hospital"></i></span> Veterinaria
            </a>
            <a class="<?= $tipo === 'cliente' ? 'active' : '' ?>" href="<?= url('login.php?tipo=cliente') ?>">
                <span class="tab-icon"><i class="fa-solid fa-paw"></i></span> Portal Tutores
            </a>
        </div>

        <?php if ($error): ?>
            <div class="notice error">
                <span class="notice-icon"><i class="fa-solid fa-triangle-exclamation"></i></span>
                <span><?= e($error) ?></span>
            </div>
        <?php endif; ?>

        <form method="post" action="<?= url('login.php?tipo=' . e($tipo)) ?>" class="auth-form" autocomplete="on">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="tipo" value="<?= e($tipo) ?>">

            <?php if ($tipo === 'veterinaria'): ?>
                <div class="form-group">
                    <label for="identity">
                        <span>Correo electrónico o usuario</span>
                    </label>
                    <div class="input-icon-wrapper">
                        <span class="field-icon"><i class="fa-solid fa-envelope"></i></span>
                        <input id="identity" type="text" name="identity" value="<?= e($identity) ?>" required autocomplete="username" placeholder="doctor@veterinaria.com o usuario" autofocus>
                    </div>
                </div>

                <div class="form-group" style="margin-top: 14px;">
                    <label for="password">
                        <span>Contraseña</span>
                    </label>
                    <div class="input-icon-wrapper">
                        <span class="field-icon"><i class="fa-solid fa-lock"></i></span>
                        <input id="password" type="password" name="password" required autocomplete="current-password" placeholder="Ingresá tu contraseña">
                        <button type="button" class="btn-toggle-password" id="togglePasswordBtn" aria-label="Mostrar u ocultar contraseña" title="Mostrar/ocultar contraseña">
                            <i class="fa-solid fa-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="auth-remember-row">
                    <label class="remember-label" for="remember">
                        <input type="checkbox" name="remember" id="remember" value="1" checked>
                        <span>Recordarme</span>
                    </label>
                </div>

                <button class="btn primary full auth-submit" type="submit">
                    <span>Ingresar al panel médico</span>
                    <span class="btn-arrow"><i class="fa-solid fa-arrow-right"></i></span>
                </button>

            <?php else: ?>
                <div class="form-group">
                    <label for="dni">
                        <span>DNI del tutor</span>
                    </label>
                    <div class="input-icon-wrapper">
                        <span class="field-icon"><i class="fa-solid fa-id-card"></i></span>
                        <input id="dni" name="dni" value="<?= e($dni) ?>" required inputmode="numeric" placeholder="Número de documento" autofocus>
                    </div>
                </div>

                <div class="form-group" style="margin-top: 14px;">
                    <label for="email">
                        <span>Correo electrónico registrado</span>
                    </label>
                    <div class="input-icon-wrapper">
                        <span class="field-icon"><i class="fa-solid fa-envelope"></i></span>
                        <input id="email" type="email" name="email" value="<?= e($email) ?>" required autocomplete="email" placeholder="tutor@correo.com">
                    </div>
                </div>

                <p class="form-note" style="margin-top: 8px; font-size: 12px; color: var(--muted); line-height: 1.5;">
                    <i class="fa-solid fa-circle-info" style="color: var(--green); margin-right: 4px;"></i> Como tutor no requerís contraseña: tu acceso seguro está vinculado al DNI y correo informados en tu veterinaria.
                </p>

                <div class="auth-remember-row">
                    <label class="remember-label" for="remember">
                        <input type="checkbox" name="remember" id="remember" value="1" checked>
                        <span>Recordarme</span>
                    </label>
                </div>

                <button class="btn primary full auth-submit" type="submit">
                    <span>Ver libreta de mis mascotas</span>
                    <span class="btn-arrow"><i class="fa-solid fa-arrow-right"></i></span>
                </button>
            <?php endif; ?>
        </form>

        <div class="auth-footer-help">
            <p class="form-note">
                <?= $tipo === 'veterinaria' 
                    ? '¿Tu clínica no tiene acceso? Las cuentas son creadas por la administración.' 
                    : '¿No encontrás a tu mascota? Pedile a tu veterinaria que verifique tu registro.' ?>
            </p>
            
            <?php if ($tipo === 'veterinaria'): ?>
                <div class="auth-extra-links">
                    <a href="<?= url('admin/login.php') ?>" class="admin-link"><i class="fa-solid fa-gear"></i> Acceso administración</a>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="auth-security-badge">
            <span><i class="fa-solid fa-shield-halved" style="color: var(--green); margin-right: 4px;"></i> Conexión cifrada y registros médicos protegidos</span>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const toggleBtn = document.getElementById('togglePasswordBtn');
        const passInput = document.getElementById('password');
        if (toggleBtn && passInput) {
            toggleBtn.addEventListener('click', () => {
                const isPassword = passInput.getAttribute('type') === 'password';
                passInput.setAttribute('type', isPassword ? 'text' : 'password');
                toggleBtn.innerHTML = isPassword ? '<i class="fa-solid fa-eye-slash"></i>' : '<i class="fa-solid fa-eye"></i>';
            });
        }
    });
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
