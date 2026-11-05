<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/recordatorios_vacunas.php';

$tipo = ($_GET['tipo'] ?? 'cliente') === 'veterinaria' ? 'veterinaria' : 'cliente';
$error = '';
$success = '';
$localResetUrl = null;
$identity = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $tipo = ($_POST['tipo'] ?? 'cliente') === 'veterinaria' ? 'veterinaria' : 'cliente';
    $identity = trim($_POST['identity'] ?? '');

    if ($identity === '') {
        $error = 'Por favor ingresá tu ' . ($tipo === 'cliente' ? 'DNI o correo electrónico' : 'usuario o correo electrónico') . '.';
    } else {
        try {
            $pdo = db();
            $user = null;
            $userType = $tipo === 'veterinaria' ? 'vet' : 'client';
            $targetEmail = '';
            $targetName = '';
            $userId = 0;

            if ($tipo === 'cliente') {
                if (strpos($identity, '@') !== false) {
                    $q = $pdo->prepare("SELECT id, full_name, email FROM clients WHERE LOWER(email) = LOWER(?) LIMIT 1");
                    $q->execute([$identity]);
                } else {
                    $cleanDni = preg_replace('/\D+/', '', $identity);
                    $q = $pdo->prepare("SELECT id, full_name, email FROM clients WHERE dni IN (?, ?) LIMIT 1");
                    $q->execute([$identity, $cleanDni]);
                }
                $user = $q->fetch();
                if ($user) {
                    $userId = (int)$user['id'];
                    $targetEmail = $user['email'];
                    $targetName = $user['full_name'];
                }
            } else {
                // Veterinaria
                $q = $pdo->prepare("SELECT id, username, email FROM vet_users WHERE LOWER(email) = LOWER(?) OR LOWER(username) = LOWER(?) LIMIT 1");
                $q->execute([$identity, $identity]);
                $user = $q->fetch();
                if ($user) {
                    $userId = (int)$user['id'];
                    $targetEmail = $user['email'];
                    $targetName = $user['username'];
                }
            }

            if ($user && !empty($targetEmail)) {
                // Generar token seguro
                $token = bin2hex(random_bytes(32));
                $tokenHash = hash('sha256', $token);
                $expiresAt = date('Y-m-d H:i:s', time() + 3600); // 1 hora de validez

                // Invalidar tokens previos de este usuario
                $pdo->prepare("DELETE FROM password_resets WHERE user_type = ? AND user_id = ?")->execute([$userType, $userId]);

                // Insertar nuevo token
                $pdo->prepare("INSERT INTO password_resets (user_type, user_id, token_hash, expires_at) VALUES (?, ?, ?, ?)")
                    ->execute([$userType, $userId, $tokenHash, $expiresAt]);

                $resetUrl = APP_URL . '/restablecer_password.php?token=' . urlencode($token);

                $subject = "Restablecé tu contraseña - " . APP_NAME;
                $html = '<div style="font-family: sans-serif; max-width: 560px; margin: 0 auto; padding: 24px; border: 1px solid #e1e7ef; border-radius: 14px;">'
                      . '<h2 style="color: #237765;">' . APP_NAME . '</h2>'
                      . '<p>Hola <strong>' . htmlspecialchars($targetName, ENT_QUOTES, 'UTF-8') . '</strong>,</p>'
                      . '<p>Recibimos una solicitud para restablecer la contraseña de tu cuenta (' . ($tipo === 'cliente' ? 'Portal de Tutores' : 'Panel Veterinario') . ').</p>'
                      . '<p>Hacé clic en el siguiente botón para crear una nueva contraseña. El enlace es válido durante 1 hora:</p>'
                      . '<div style="text-align: center; margin: 26px 0;">'
                      . '<a href="' . htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8') . '" style="background: #237765; color: #ffffff; padding: 12px 24px; border-radius: 10px; text-decoration: none; font-weight: bold; display: inline-block;">Restablecer mi contraseña</a>'
                      . '</div>'
                      . '<p style="color: #667085; font-size: 13px;">Si no solicitaste este cambio, podés ignorar este mensaje; tu contraseña actual continuará siendo la misma.</p>'
                      . '</div>';
                $text = "Hola $targetName,\n\nPara restablecer tu contraseña en " . APP_NAME . ", ingresá al siguiente enlace:\n$resetUrl\n\nEl enlace vence en 1 hora.";

                try {
                    $delivery = send_app_mail($targetEmail, $targetName, $subject, $html, $text);
                    if ($delivery === 'archivo') {
                        $localResetUrl = $resetUrl;
                    }
                } catch (Throwable $mailEx) {
                    // Si falla el transporte, facilitamos el link en entorno local
                    $localResetUrl = $resetUrl;
                }

                $success = 'Si los datos coinciden con una cuenta registrada, enviamos las instrucciones a ' . e($targetEmail) . '.';
            } else {
                // Mensaje genérico para evitar enumeración de usuarios
                $success = 'Si los datos corresponden a una cuenta registrada, te enviaremos las instrucciones para restablecer tu contraseña.';
            }
        } catch (Throwable $e) {
            $error = 'Ocurrió un error al procesar tu solicitud: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Recuperar contraseña | ' . APP_NAME;
$bodyClass = 'login-page';
require __DIR__ . '/includes/header.php';
?>

<link rel="stylesheet" href="<?= url('assets/login.css') ?>?v=23">

<div class="tc-wrap">
    <div class="tc-card">
        <div class="tc-map">
            <canvas id="tc-canvas"></canvas>
            <div class="tc-map-text">
                <div class="tc-logo"><i class="fa-solid fa-key"></i></div>
                <h2>Recuperar acceso</h2>
                <p>Protegemos la seguridad clínica de los pacientes y los datos de cada tutor.</p>
            </div>
        </div>

        <div class="tc-form">
            <h1>¿Olvidaste tu clave?</h1>
            <p class="tc-sub">
                Ingresá tus datos para recibir un enlace seguro de restablecimiento.
            </p>

            <div class="tc-tabs">
                <a class="<?= $tipo === 'veterinaria' ? 'active' : '' ?>" href="<?= url('olvide_password.php?tipo=veterinaria') ?>"><i class="fa-solid fa-hospital"></i> Veterinaria</a>
                <a class="<?= $tipo === 'cliente' ? 'active' : '' ?>" href="<?= url('olvide_password.php?tipo=cliente') ?>"><i class="fa-solid fa-paw"></i> Portal Tutores</a>
            </div>

            <?php if ($success): ?>
                <div style="padding: 14px 16px; background: #eaf7ee; color: #1e6348; border-radius: 12px; font-size: 14px; margin-bottom: 18px; border: 1px solid #c7ebd2; line-height: 1.5;">
                    <i class="fa-solid fa-circle-check" style="margin-right: 6px;"></i> <?= $success ?>
                </div>

                <?php if ($localResetUrl): ?>
                    <div style="padding: 14px 16px; background: #fffbf0; color: #8a5b00; border-radius: 12px; font-size: 13.5px; margin-bottom: 20px; border: 1px solid #f6e0b5; line-height: 1.5;">
                        <strong><i class="fa-solid fa-laptop-code" style="margin-right: 4px;"></i> Modo de prueba local:</strong>
                        <div style="margin-top: 6px;">Podés restablecer tu clave directamente con este botón:</div>
                        <div style="margin-top: 10px;">
                            <a href="<?= e($localResetUrl) ?>" class="tc-btn" style="display: inline-block; width: auto; padding: 10px 18px; text-decoration: none; font-size: 13px;">
                                Restablecer contraseña ahora <i class="fa-solid fa-arrow-right" style="margin-left: 6px;"></i>
                            </a>
                        </div>
                    </div>
                <?php endif; ?>

                <div style="margin-top: 16px;">
                    <a href="<?= url('login.php?tipo=' . e($tipo)) ?>" style="color: var(--green); text-decoration: none; font-weight: 700; font-size: 14px;">
                        <i class="fa-solid fa-arrow-left" style="margin-right: 4px;"></i> Volver a iniciar sesión
                    </a>
                </div>
            <?php else: ?>
                <?php if ($error): ?>
                    <p class="tc-error" role="alert"><i class="fa-solid fa-triangle-exclamation"></i> <?= e($error) ?></p>
                <?php endif; ?>

                <form method="post" action="<?= url('olvide_password.php?tipo=' . e($tipo)) ?>">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="tipo" value="<?= e($tipo) ?>">

                    <?php if ($tipo === 'veterinaria'): ?>
                        <label class="tc-l" for="identity">Correo electrónico o usuario <span>*</span></label>
                        <div class="tc-field">
                            <input id="identity" name="identity" value="<?= e($identity) ?>" required placeholder="doctor@veterinaria.com" autofocus>
                        </div>
                    <?php else: ?>
                        <label class="tc-l" for="identity">DNI o correo del tutor <span>*</span></label>
                        <div class="tc-field">
                            <input id="identity" name="identity" value="<?= e($identity) ?>" required placeholder="Ej: 30123456 o tutor@correo.com" autofocus>
                        </div>
                    <?php endif; ?>

                    <button class="tc-btn" type="submit" style="margin-top: 16px;">
                        Enviar enlace de recuperación <i class="fa-solid fa-paper-plane"></i>
                    </button>
                </form>

                <div style="margin-top: 22px; text-align: center;">
                    <a href="<?= url('login.php?tipo=' . e($tipo)) ?>" style="color: var(--green); text-decoration: none; font-weight: 700; font-size: 13.5px;">
                        <i class="fa-solid fa-arrow-left" style="margin-right: 4px;"></i> Volver a iniciar sesión
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="<?= url('assets/login.js') ?>?v=13"></script>
<?php require __DIR__ . '/includes/footer.php'; ?>

