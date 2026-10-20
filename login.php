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
$identity = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identity = trim($_POST['identity'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = !empty($_POST['remember']);

    if ($identity === '' || $password === '') {
        $error = 'Por favor completá tu correo o usuario y tu contraseña.';
    } else {
        try {
            $authenticated = false;

            // 1. Intentar autenticar como Veterinario (vet_users por email o username)
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
                $authenticated = true;
            }

            // 2. Si no es veterinario, intentar autenticar como Administrador (admins por email)
            if (!$authenticated && !$error) {
                $q = db()->prepare("SELECT * FROM admins WHERE LOWER(email) = LOWER(?) LIMIT 1");
                $q->execute([$identity]);
                $a = $q->fetch();

                if ($a && password_verify($password, $a['password_hash'])) {
                    session_regenerate_id(true);
                    $_SESSION['admin_id'] = (int)$a['id'];

                    if ($remember) {
                        create_remember_cookie('admin', (int)$a['id']);
                    }
                    header('Location: ' . url('admin/veterinarias.php'));
                    exit;
                }
            }

            // 3. Si no es admin, intentar autenticar como Tutor / Cliente (clients por email o DNI)
            if (!$authenticated && !$error) {
                $q = db()->prepare("SELECT * FROM clients WHERE LOWER(email) = LOWER(?) OR dni = ? LIMIT 1");
                $q->execute([$identity, $identity]);
                $c = $q->fetch();

                if ($c) {
                    $clientValid = false;
                    if (!empty($c['password_hash']) && password_verify($password, $c['password_hash'])) {
                        $clientValid = true;
                    } elseif ($password === $c['dni'] || strtolower($password) === strtolower($c['email'])) {
                        $clientValid = true;
                        if (empty($c['password_hash'])) {
                            $up = db()->prepare("UPDATE clients SET password_hash = ? WHERE id = ?");
                            $up->execute([password_hash($password, PASSWORD_DEFAULT), $c['id']]);
                        }
                    }

                    if ($clientValid) {
                        session_regenerate_id(true);
                        $_SESSION['client_id']   = (int)$c['id'];
                        $_SESSION['client_name'] = $c['full_name'];

                        if ($remember) {
                            create_remember_cookie('client', (int)$c['id']);
                        }
                        header('Location: ' . url('cliente/mascotas.php'));
                        exit;
                    }
                }
            }

            if (!$authenticated && !$error) {
                $error = 'Los datos ingresados no coinciden con ninguna cuenta activa.';
            }

        } catch (Throwable $e) {
            $error = 'Error de conexión con la base de datos. Verificá que el servicio MySQL esté activo.';
        }
    }
}

$pageTitle = 'Iniciar sesión | VetCompartido';
require __DIR__ . '/includes/header.php';
?>

<div class="auth-wrapper">
    <div class="auth-card">
        <div class="auth-header">
            <div class="auth-badge">
                <span class="auth-paw">✚</span>
            </div>
            <h2>Iniciar sesión</h2>
            <p class="auth-subtitle">Accedé a tu cuenta en VetCompartido</p>
        </div>

        <?php if ($error): ?>
            <div class="notice error">
                <span class="notice-icon">⚠️</span>
                <span><?= e($error) ?></span>
            </div>
        <?php endif; ?>

        <form method="post" action="<?= url('login.php') ?>" class="auth-form" autocomplete="on">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

            <div class="form-group">
                <label for="identity">
                    <span>Correo electrónico o usuario</span>
                </label>
                <div class="input-icon-wrapper">
                    <span class="field-icon">✉️</span>
                    <input id="identity" type="text" name="identity" value="<?= e($identity) ?>" required autocomplete="username" placeholder="doctor@veterinaria.com, usuario o DNI" autofocus>
                </div>
            </div>

            <div class="form-group" style="margin-top: 14px;">
                <label for="password">
                    <span>Contraseña</span>
                </label>
                <div class="input-icon-wrapper">
                    <span class="field-icon">🔒</span>
                    <input id="password" type="password" name="password" required autocomplete="current-password" placeholder="Ingresá tu contraseña">
                    <button type="button" class="btn-toggle-password" id="togglePasswordBtn" aria-label="Mostrar u ocultar contraseña" title="Mostrar/ocultar contraseña">
                        👁️
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
                <span>Iniciar sesión</span>
                <span class="btn-arrow">→</span>
            </button>
        </form>

        <div class="auth-footer-help">
            <p class="form-note">
                ℹ️ Acceso unificado para veterinarias, tutores de mascotas y administración.
            </p>
        </div>
        
        <div class="auth-security-badge">
            <span>🛡️ Conexión cifrada y registros médicos protegidos</span>
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
                toggleBtn.textContent = isPassword ? '🙈' : '👁️';
            });
        }
    });
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>