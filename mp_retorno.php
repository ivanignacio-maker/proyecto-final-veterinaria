<?php
/**
 * mp_retorno.php — A donde vuelve la persona después de pagar (o de cancelar) en Mercado Pago.
 *
 * Importante: los datos que Mercado Pago agrega a la URL (payment_id, status...) los puede
 * editar cualquiera, así que NO se usan para decidir nada. Solo usamos payment_id para
 * preguntarle a Mercado Pago, con nuestro token, cuál es el estado real del pago.
 * La solicitud se identifica con su número (r) y su clave secreta (t).
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/mercadopago.php';

$id    = (int)($_GET['r'] ?? 0);
$token = (string)($_GET['t'] ?? '');

$q = db()->prepare('SELECT * FROM plan_requests WHERE id = ? AND token = ?');
$q->execute([$id, $token]);
$req = $q->fetch();

if (!$req) {
    http_response_code(404);
    flash('No encontramos esa solicitud. Si pagaste, escribinos desde Contacto con tu comprobante.');
    header('Location: ' . url('contacto.php'));
    exit;
}

// Si volvió con un pago, lo verificamos contra Mercado Pago.
$paymentId = (string)($_GET['payment_id'] ?? $_GET['collection_id'] ?? '');
if ($paymentId !== '' && ctype_digit($paymentId) && mp_enabled()) {
    try {
        $payment = mp_get_payment($paymentId);
        if ((int)($payment['external_reference'] ?? 0) === $id) {   // el pago tiene que ser de ESTA solicitud
            mp_apply_payment($payment);
            $q->execute([$id, $token]);
            $req = $q->fetch();
        }
    } catch (Throwable $e) {
        error_log('mp_retorno: ' . $e->getMessage());
    }
}

$status = $req['payment_status'];
if (!empty($_GET['sinpago']) && $status === 'pendiente') {
    $view = 'sinpago';
} elseif (!empty($_GET['err']) && $status === 'pendiente') {
    $view = 'error';
} else {
    $view = $status;
}

$views = [
    'aprobado'   => ['fa-circle-check',        '#1e6348', '#eaf7ee', '¡Pago aprobado!',
                     'Recibimos tu pago. Nuestro equipo va a registrar tu veterinaria y te enviará los datos de acceso a ' . $req['responsible_email'] . '. Suele tardar menos de un día hábil.'],
    'en_proceso' => ['fa-hourglass-half',      '#8a6a12', '#fff8e5', 'Tu pago está en proceso',
                     'Mercado Pago todavía está acreditando el pago (por ejemplo, si elegiste pagar en efectivo o con transferencia). Apenas se confirme, avisamos a nuestro equipo y te escribimos a ' . $req['responsible_email'] . '. No hace falta que hagas nada más.'],
    'rechazado'  => ['fa-circle-xmark',        '#a63e39', '#fff0ee', 'El pago fue rechazado',
                     'Mercado Pago no pudo procesar el pago. No se te cobró. Podés intentarlo de nuevo con otro medio de pago.'],
    'cancelado'  => ['fa-circle-xmark',        '#a63e39', '#fff0ee', 'El pago fue cancelado',
                     'El pago no se completó. Tus datos quedaron guardados, así que podés reintentar sin volver a cargarlos.'],
    'pendiente'  => ['fa-circle-info',         '#2b5f8a', '#eef5fb', 'Todavía no registramos tu pago',
                     'Tus datos quedaron guardados. Si cerraste la ventana de Mercado Pago antes de terminar, podés retomar el pago.'],
    'error'      => ['fa-triangle-exclamation','#a63e39', '#fff0ee', 'No pudimos iniciar el pago',
                     'Guardamos tus datos, pero Mercado Pago no respondió. No se te cobró nada. Probá de nuevo en un momento; si sigue fallando, escribinos desde Contacto.'],
    'sinpago'    => ['fa-paper-plane',         '#1e6348', '#eaf7ee', '¡Solicitud recibida!',
                     'Guardamos los datos de tu veterinaria. Un asesor te va a contactar a ' . $req['responsible_email'] . ' para coordinar el pago y activar tu cuenta.'],
];
[$icon, $color, $bg, $title, $text] = $views[$view] ?? $views['pendiente'];
$canRetry = mp_enabled() && in_array($view, ['rechazado', 'cancelado', 'pendiente', 'error'], true);

$pageTitle = 'Estado de tu contratación';
$isLanding = true;
$bodyClass = 'page-bg-follow page-bg-contact';
require __DIR__ . '/includes/header.php';
?>

<div class="bg-paper section-padding-sm contact-bg">
    <div class="public-container">
        <div class="contact-card-form" style="max-width: 640px; margin: 0 auto; text-align: center;">
            <span style="font-size: 54px; color: <?= e($color) ?>;"><i class="fa-solid <?= e($icon) ?>"></i></span>
            <h1 style="font-size: 26px; margin: 10px 0 8px;"><?= e($title) ?></h1>
            <p style="color: var(--muted); line-height: 1.6; margin: 0 0 18px;"><?= e($text) ?></p>

            <div style="background: <?= e($bg) ?>; border-radius: 14px; padding: 14px 18px; text-align: left; font-size: 14px; margin-bottom: 20px;">
                <div><strong>Solicitud:</strong> #<?= (int)$req['id'] ?> · <?= e($req['clinic_name']) ?></div>
                <div><strong>Importe:</strong> <?= e(plan_money((float)$req['amount'])) ?> (<?= $req['billing'] === 'anual' ? 'pago anual' : 'pago mensual' ?>)</div>
                <div><strong>Estado del pago:</strong> <?= e(plan_payment_label($status)) ?></div>
                <?php if (!empty($req['mp_payment_id'])): ?>
                    <div><strong>N.º de operación:</strong> <?= e($req['mp_payment_id']) ?></div>
                <?php endif; ?>
            </div>

            <div style="display: flex; gap: 10px; justify-content: center; flex-wrap: wrap;">
                <?php if ($canRetry): ?>
                    <form method="post" action="<?= url('contratar.php') ?>" style="margin: 0;">
                        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="retry" value="<?= (int)$req['id'] ?>">
                        <input type="hidden" name="t" value="<?= e($req['token']) ?>">
                        <button type="submit" class="btn-hero-primary" style="border: none; cursor: pointer;">
                            <span><i class="fa-solid fa-rotate-right" style="margin-right: 8px;"></i>Reintentar el pago</span>
                        </button>
                    </form>
                <?php endif; ?>
                <a href="<?= url('index.php') ?>" class="btn-nav-outline" style="padding: 12px 22px;">Volver al inicio</a>
            </div>
            <p style="font-size: 12px; color: var(--muted); margin-top: 18px;">Guardá el número de solicitud por si necesitás escribirnos.</p>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
