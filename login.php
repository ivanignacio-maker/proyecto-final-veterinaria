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

if (!empty($_SESSION['google_auth_error'])) {
    $error = (string)$_SESSION['google_auth_error'];
    unset($_SESSION['google_auth_error']);
}

if (!empty($_SESSION['google_pending']['created_at']) && time() - (int)$_SESSION['google_pending']['created_at'] > 900) {
    unset($_SESSION['google_pending']);
}
$googlePending = $_SESSION['google_pending'] ?? null;
$googleRegistration = $tipo === 'cliente' && is_array($googlePending);

$identity = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tipo = ($_POST['tipo'] ?? 'veterinaria') === 'cliente' ? 'cliente' : 'veterinaria';
    $remember = !empty($_POST['remember']);

    if ($tipo === 'cliente') {
        // El tutor entra con su DNI o su correo, más la contraseña
        $identity = trim($_POST['identity'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($identity === '' || $password === '') {
            $error = 'Por favor ingresá tu DNI o correo y tu contraseña.';
        } else {
            try {
                if (strpos($identity, '@') !== false) {
                    $q = db()->prepare("SELECT * FROM clients WHERE LOWER(email) = LOWER(?)");
                    $q->execute([$identity]);
                } else {
                    // Acepta el DNI con o sin puntos
                    $q = db()->prepare("SELECT * FROM clients WHERE dni IN (?, ?)");
                    $q->execute([$identity, preg_replace('/\D+/', '', $identity)]);
                }

                $c = null;
                foreach ($q->fetchAll() as $row) {
                    if (!empty($row['password_hash']) && password_verify($password, $row['password_hash'])) {
                        $c = $row;
                        break;
                    }
                }

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

                $error = 'El DNI o correo y la contraseña no coinciden. Si creaste tu cuenta con Google, usá "Continuar con Google".';
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
$bodyClass = 'login-page';
require __DIR__ . '/includes/header.php';
?>

<link rel="stylesheet" href="<?= url('assets/login.css') ?>?v=21">

<div class="tc-wrap">
    <div class="tc-card">
        <div class="tc-map">
            <canvas id="tc-canvas"></canvas>
            <div class="tc-map-text">
                <div class="tc-logo"><i class="fa-solid fa-paw"></i></div>
                <h2>VetCare</h2>
                <p>La red de veterinarias conectadas: historias clínicas, turnos y libreta sanitaria en un solo lugar.</p>
            </div>
        </div>

        <div class="tc-form">
            <h1>Bienvenido de nuevo</h1>
            <p class="tc-sub">
                <?= $tipo === 'cliente'
                    ? 'Consultá la libreta sanitaria digital y turnos de tus mascotas'
                    : 'Ingresá al panel médico de tu veterinaria' ?>
            </p>

            <div class="tc-tabs">
                <a class="<?= $tipo === 'veterinaria' ? 'active' : '' ?>" href="<?= url('login.php?tipo=veterinaria') ?>"><i class="fa-solid fa-hospital"></i> Veterinaria</a>
                <a class="<?= $tipo === 'cliente' ? 'active' : '' ?>" href="<?= url('login.php?tipo=cliente') ?>"><i class="fa-solid fa-paw"></i> Portal Tutores</a>
            </div>

            <?php if ($error): ?>
                <p class="tc-error" role="alert"><i class="fa-solid fa-triangle-exclamation"></i> <?= e($error) ?></p>
            <?php endif; ?>

            <form method="post" action="<?= url('login.php?tipo=' . e($tipo)) ?>" autocomplete="on">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="tipo" value="<?= e($tipo) ?>">

                <?php if ($tipo === 'veterinaria'): ?>
                    <label class="tc-l" for="identity">Correo o usuario <span>*</span></label>
                    <div class="tc-field">
                        <input id="identity" name="identity" value="<?= e($identity) ?>" required autocomplete="username" placeholder="doctor@veterinaria.com" autofocus>
                    </div>

                    <label class="tc-l" for="tc-password">Contraseña <span>*</span></label>
                    <div class="tc-field">
                        <input id="tc-password" type="password" name="password" required autocomplete="current-password" placeholder="Ingresá tu contraseña">
                        <button type="button" class="tc-eye" id="tc-eye" aria-label="Mostrar contraseña"><i class="fa-solid fa-eye"></i></button>
                    </div>
                <?php else: ?>
                    <label class="tc-l" for="client-identity">DNI o correo <span>*</span></label>
                    <div class="tc-field">
                        <input id="client-identity" name="identity" value="<?= e($identity) ?>" required autocomplete="username" placeholder="Número de documento o tutor@correo.com" autofocus>
                    </div>

                    <label class="tc-l" for="tc-password">Contraseña <span>*</span></label>
                    <div class="tc-field">
                        <input id="tc-password" type="password" name="password" required autocomplete="current-password" placeholder="Ingresá tu contraseña">
                        <button type="button" class="tc-eye" id="tc-eye" aria-label="Mostrar contraseña"><i class="fa-solid fa-eye"></i></button>
                    </div>
                    <p class="tc-note"><i class="fa-solid fa-circle-info"></i> Podés ingresar con tu DNI o con el correo registrado en tu veterinaria.</p>
                <?php endif; ?>

                <label class="tc-remember">
                    <input type="checkbox" name="remember" value="1" checked> Recordarme
                </label>

                <button class="tc-btn" type="submit">
                    <?= $tipo === 'cliente' ? 'Ver libreta de mis mascotas' : 'Ingresar al panel médico' ?>
                    <i class="fa-solid fa-arrow-right"></i>
                </button>
            </form>

            <?php if ($tipo === 'cliente'): ?>
                <div class="tc-google-auth">
                    <div class="tc-divider"><span>o</span></div>
                    <a class="tc-google-button" id="tc-google-login" href="<?= url('google_login.php') ?>">
                        <i class="fa-brands fa-google" aria-hidden="true"></i>
                        <span>Continuar con Google</span>
                    </a>
                </div>

                <?php if ($googleRegistration): ?>
                    <form class="tc-google-register" method="post" action="<?= url('google_login.php') ?>">
                        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="complete_registration">
                        <p class="tc-google-profile">
                            Google verificó <strong><?= e($googlePending['email'] ?? '') ?></strong>.
                            Completá tu DNI para crear la cuenta de tutor.
                        </p>
                        <label class="tc-l" for="google-dni">DNI del tutor <span>*</span></label>
                        <div class="tc-field">
                            <input id="google-dni" name="dni" required inputmode="numeric" autocomplete="off" placeholder="Número de documento">
                        </div>
                        <label class="tc-l" for="google-phone">Teléfono o celular</label>
                        <div class="tc-field">
                            <input id="google-phone" name="phone" autocomplete="tel" placeholder="Opcional">
                        </div>
                        <button class="tc-btn" type="submit">Completar registro <i class="fa-solid fa-arrow-right"></i></button>
                    </form>
                <?php endif; ?>

                <p class="tc-foot">¿No tenés cuenta? <a href="<?= url('registro_cliente.php') ?>">Registrate gratis</a></p>
            <?php else: ?>
                <p class="tc-foot"><i class="fa-solid fa-shield-halved"></i> Conexión cifrada y registros médicos protegidos</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="<?= url('assets/login.js') ?>?v=17"></script>

<?php require __DIR__ . '/includes/footer.php'; ?>
