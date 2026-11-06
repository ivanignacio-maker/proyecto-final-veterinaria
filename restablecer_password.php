<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/password_policy.php';

$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$tokenHash = hash('sha256', $token);
$error = '';
$validToken = false;
$resetRecord = null;
$userDisplay = '';

if ($token !== '') {
    $q = db()->prepare("SELECT * FROM password_resets WHERE token_hash = ? AND expires_at > NOW() LIMIT 1");
    $q->execute([$tokenHash]);
    $resetRecord = $q->fetch();
    if ($resetRecord) {
        $validToken = true;
        if ($resetRecord['user_type'] === 'vet') {
            $uQ = db()->prepare("SELECT username, email FROM vet_users WHERE id = ?");
            $uQ->execute([(int)$resetRecord['user_id']]);
            $u = $uQ->fetch();
            $userDisplay = $u ? $u['username'] . ' (' . $u['email'] . ')' : '';
        } else {
            $uQ = db()->prepare("SELECT full_name, email FROM clients WHERE id = ?");
            $uQ->execute([(int)$resetRecord['user_id']]);
            $u = $uQ->fetch();
            $userDisplay = $u ? $u['full_name'] . ' (' . $u['email'] . ')' : '';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $validToken) {
    csrf_check();
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($newPassword === '' || $confirmPassword === '') {
        $error = 'Completá la nueva contraseña y su confirmación.';
    } elseif (!hash_equals($newPassword, $confirmPassword)) {
        $error = 'La nueva contraseña y su confirmación no coinciden.';
    } else {
        $policyError = password_policy_error($newPassword);
        if ($policyError !== null) {
            $error = $policyError;
        } else {
            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $pdo = db();
            if ($resetRecord['user_type'] === 'vet') {
                $upd = $pdo->prepare("UPDATE vet_users SET password_hash = ? WHERE id = ?");
                $upd->execute([$newHash, (int)$resetRecord['user_id']]);
                $loginTipo = 'veterinaria';
            } else {
                $upd = $pdo->prepare("UPDATE clients SET password_hash = ? WHERE id = ?");
                $upd->execute([$newHash, (int)$resetRecord['user_id']]);
                $loginTipo = 'cliente';
            }

            // Eliminar token usado
            $pdo->prepare("DELETE FROM password_resets WHERE id = ?")->execute([(int)$resetRecord['id']]);

            header('Location: ' . url('login.php?tipo=' . $loginTipo . '&reset_success=1'));
            exit;
        }
    }
}

$pageTitle = 'Crear nueva contraseña | ' . APP_NAME;
$bodyClass = 'login-page';
require __DIR__ . '/includes/header.php';
?>

<link rel="stylesheet" href="<?= url('assets/login.css') ?>?v=23">

<div class="tc-wrap">
    <div class="tc-card">
        <div class="tc-map">
            <canvas id="tc-canvas"></canvas>
            <div class="tc-map-text">
                <div class="tc-logo"><i class="fa-solid fa-lock"></i></div>
                <h2>Nueva contraseña</h2>
                <p>Establecé una clave segura para proteger el acceso a tus datos y registros médicos.</p>
            </div>
        </div>

        <div class="tc-form">
            <?php if (!$validToken): ?>
                <h1>Enlace no válido</h1>
                <p class="tc-sub">
                    El enlace para restablecer tu contraseña es inválido o ya ha expirado (su validez es de 1 hora).
                </p>
                <div style="margin-top: 24px;">
                    <a href="<?= url('olvide_password.php') ?>" class="tc-btn" style="text-decoration: none;">
                        Solicitar un nuevo enlace <i class="fa-solid fa-arrow-right"></i>
                    </a>
                </div>
                <div style="margin-top: 18px; text-align: center;">
                    <a href="<?= url('login.php') ?>" style="color: var(--green); text-decoration: none; font-weight: 700; font-size: 13.5px;">
                        <i class="fa-solid fa-arrow-left"></i> Volver al inicio de sesión
                    </a>
                </div>
            <?php else: ?>
                <h1>Restablecer clave</h1>
                <p class="tc-sub">
                    Cuenta: <strong><?= e($userDisplay) ?></strong>
                </p>

                <?php if ($error): ?>
                    <p class="tc-error" role="alert"><i class="fa-solid fa-triangle-exclamation"></i> <?= e($error) ?></p>
                <?php endif; ?>

                <form method="post" action="<?= url('restablecer_password.php') ?>">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="token" value="<?= e($token) ?>">

                    <label class="tc-l" for="tc-new-password">Nueva contraseña <span>*</span></label>
                    <div class="tc-field" style="margin-bottom: 8px;">
                        <input id="tc-new-password" type="password" name="new_password" required autocomplete="new-password" placeholder="Creá tu nueva contraseña" data-strength-input autofocus>
                        <button type="button" class="tc-eye" id="tc-eye-1" aria-label="Mostrar contraseña"><i class="fa-solid fa-eye"></i></button>
                    </div>

                    <div data-password-strength class="password-strength-widget" style="margin-bottom: 18px;">
                        <div class="password-strength-meter" aria-hidden="true">
                            <span class="password-strength-segment"></span><span class="password-strength-segment"></span>
                            <span class="password-strength-segment"></span><span class="password-strength-segment"></span>
                        </div>
                        <span class="password-strength-label" data-strength-label>La seguridad aparecerá mientras escribís</span>
                        <ul class="password-strength-requirements">
                            <li data-password-rule="length"><span class="password-rule-mark">○</span> 8 caracteres</li>
                            <li data-password-rule="case"><span class="password-rule-mark">○</span> Mayúscula y minúscula</li>
                            <li data-password-rule="number"><span class="password-rule-mark">○</span> Un número</li>
                            <li data-password-rule="symbol"><span class="password-rule-mark">○</span> Un símbolo</li>
                            <li data-password-rule="uncommon"><span class="password-rule-mark">○</span> Sin secuencias comunes</li>
                        </ul>
                    </div>

                    <label class="tc-l" for="tc-confirm-password">Confirmar nueva contraseña <span>*</span></label>
                    <div class="tc-field" style="margin-bottom: 20px;">
                        <input id="tc-confirm-password" type="password" name="confirm_password" required autocomplete="new-password" placeholder="Repetí tu nueva contraseña">
                        <button type="button" class="tc-eye" id="tc-eye-2" aria-label="Mostrar contraseña"><i class="fa-solid fa-eye"></i></button>
                    </div>

                    <button class="tc-btn" type="submit">
                        Guardar nueva contraseña <i class="fa-solid fa-check"></i>
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="<?= url('assets/login.js') ?>?v=13"></script>
<script src="<?= url('assets/password-strength.js') ?>?v=1" defer></script>
<script>
document.querySelectorAll('.tc-eye').forEach(function(btn) {
    btn.addEventListener('click', function() {
        const input = btn.previousElementSibling;
        if (input && input.tagName === 'INPUT') {
            const isPassword = input.type === 'password';
            input.type = isPassword ? 'text' : 'password';
            btn.innerHTML = isPassword ? '<i class="fa-solid fa-eye-slash"></i>' : '<i class="fa-solid fa-eye"></i>';
        }
    });
});
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>

