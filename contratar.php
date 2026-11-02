<?php
/**
 * contratar.php — Contratación del plan (página pública).
 *
 * Qué hace:
 *   1. La veterinaria completa un formulario con TODOS sus datos (clínica, dirección, responsable).
 *   2. Guardamos la solicitud en la tabla plan_requests (la verá el administrador).
 *   3. Creamos el cobro en Mercado Pago y mandamos a la persona a pagar.
 *   4. Cuando el pago se aprueba (mp_retorno.php / mp_webhook.php), el administrador registra
 *      la veterinaria desde admin/solicitudes.php.
 *
 * El precio NO viaja en el formulario: lo decide el servidor con plan_catalog().
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/geocoding.php';
require_once __DIR__ . '/includes/mercadopago.php';

$catalog  = plan_catalog();
$planCode = 'clinicas';              // hoy hay un solo plan; el código queda listo para sumar más
$plan     = $catalog[$planCode];

$daysOptions = [
    'Lunes a Viernes', 'Lunes a Sábados', 'Lunes a Domingos', 'Lunes, Miércoles y Viernes',
    'Martes, Jueves y Sábados', 'Fines de semana (Sábados y Domingos)',
    'Todos los días (incluye feriados)', 'Guardia 24 hs',
];

/** Crea la preferencia de pago, la guarda y redirige a Mercado Pago. Si falla, vuelve a mp_retorno con aviso. */
function start_payment(array $req): void {
    try {
        [$prefId, $payUrl] = mp_create_preference($req);
        db()->prepare('UPDATE plan_requests SET mp_preference_id = ? WHERE id = ?')->execute([$prefId, (int)$req['id']]);
        header('Location: ' . $payUrl);
        exit;
    } catch (Throwable $e) {
        error_log('Mercado Pago (crear preferencia, solicitud ' . $req['id'] . '): ' . $e->getMessage());
        header('Location: ' . url('mp_retorno.php?r=' . (int)$req['id'] . '&t=' . $req['token'] . '&err=1'));
        exit;
    }
}

// ---- Reintentar el pago de una solicitud ya guardada (botón de mp_retorno.php) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['retry'])) {
    csrf_check();
    $q = db()->prepare("SELECT * FROM plan_requests WHERE id = ? AND token = ? AND payment_status <> 'aprobado'");
    $q->execute([(int)$_POST['retry'], (string)($_POST['t'] ?? '')]);
    $req = $q->fetch();
    if ($req && mp_enabled()) {
        start_payment($req);
    }
    header('Location: ' . url('contratar.php'));
    exit;
}

$error = '';
$f = [
    'billing' => in_array($_GET['facturacion'] ?? '', ['mensual', 'anual'], true) ? $_GET['facturacion'] : 'anual',
    'clinic_name' => '', 'cuit' => '', 'clinic_email' => '', 'phone' => '',
    'available_days' => '', 'opening_time' => '', 'closing_time' => '',
    'province' => '', 'city' => '', 'street' => '',
    'responsible_name' => '', 'responsible_email' => '', 'responsible_phone' => '',
    'staff_count' => '', 'notes' => '', 'terms' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    foreach ($f as $k => $_) {
        $f[$k] = trim((string)($_POST[$k] ?? ''));
    }
    $f['clinic_name']       = format_name($f['clinic_name']);
    $f['responsible_name']  = format_name($f['responsible_name']);
    $f['city']              = format_name($f['city']);
    $f['clinic_email']      = strtolower($f['clinic_email']);
    $f['responsible_email'] = strtolower($f['responsible_email']);
    if (!isset($plan['billing'][$f['billing']])) {
        $f['billing'] = 'anual';
    }

    $cuitDigits = preg_replace('/\D/', '', $f['cuit']);
    $staff = $f['staff_count'] === '' ? null : (int)$f['staff_count'];

    if ($f['clinic_name'] === '' || $f['responsible_name'] === '') {
        $error = 'Completá el nombre de la veterinaria y el del responsable.';
    } elseif (strlen($cuitDigits) !== 11) {
        $error = 'El CUIT debe tener 11 dígitos (ej. 20-12345678-9).';
    } elseif (!filter_var($f['clinic_email'], FILTER_VALIDATE_EMAIL) || !filter_var($f['responsible_email'], FILTER_VALIDATE_EMAIL)) {
        $error = 'Ingresá correos electrónicos válidos (de la veterinaria y del responsable).';
    } elseif ($f['street'] === '' || $f['city'] === '' || !in_array($f['province'], ar_provinces(), true)
              || mb_strlen($f['street']) > 180 || mb_strlen($f['city']) > 120) {
        $error = 'Completá la dirección: provincia, localidad y calle con número.';
    } elseif ($staff !== null && ($staff < 1 || $staff > 8)) {
        $error = 'El plan incluye hasta 8 usuarios. Indicá una cantidad entre 1 y 8 o dejá el campo vacío.';
    } elseif ($f['terms'] !== '1') {
        $error = 'Para continuar tenés que aceptar los términos y condiciones.';
    } else {
        try {
            $pdo = db();

            // ¿Ya hay una veterinaria registrada con ese CUIT o correo?
            $dup = $pdo->prepare('SELECT 1 FROM clinics WHERE email = ? OR REPLACE(REPLACE(cuit, "-", ""), " ", "") = ? LIMIT 1');
            $dup->execute([$f['clinic_email'], $cuitDigits]);
            if ($dup->fetchColumn()) {
                $error = 'Ya existe una veterinaria registrada con ese CUIT o correo. Si es la tuya, escribinos desde Contacto.';
            } else {
                $amount = (float)$plan['billing'][$f['billing']]['amount'];

                // Si la misma veterinaria ya había iniciado una solicitud sin pagar, la reutilizamos (evita duplicados).
                $ex = $pdo->prepare("SELECT id, token FROM plan_requests WHERE REPLACE(cuit, '-', '') = ? AND status = 'nueva' AND payment_status <> 'aprobado' ORDER BY id DESC LIMIT 1");
                $ex->execute([$cuitDigits]);
                $existing = $ex->fetch();

                $values = [
                    $planCode, $f['billing'], $amount,
                    $f['clinic_name'], $f['cuit'], $f['clinic_email'], $f['phone'] ?: null,
                    $f['available_days'] ?: null, $f['opening_time'] ?: null, $f['closing_time'] ?: null,
                    $f['province'], $f['city'], $f['street'],
                    $f['responsible_name'], $f['responsible_email'], $f['responsible_phone'] ?: null,
                    $staff, $f['notes'] !== '' ? mb_substr($f['notes'], 0, 2000) : null,
                ];

                if ($existing) {
                    $pdo->prepare("UPDATE plan_requests SET plan_code=?, billing=?, amount=?, clinic_name=?, cuit=?, clinic_email=?, phone=?,
                        available_days=?, opening_time=?, closing_time=?, province=?, city=?, street=?,
                        responsible_name=?, responsible_email=?, responsible_phone=?, staff_count=?, notes=?, payment_status='pendiente'
                        WHERE id = ?")->execute(array_merge($values, [(int)$existing['id']]));
                    $requestId = (int)$existing['id'];
                    $token = $existing['token'];
                } else {
                    $token = bin2hex(random_bytes(16));
                    $pdo->prepare("INSERT INTO plan_requests (token, plan_code, billing, amount, clinic_name, cuit, clinic_email, phone,
                        available_days, opening_time, closing_time, province, city, street,
                        responsible_name, responsible_email, responsible_phone, staff_count, notes)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
                        ->execute(array_merge([$token], $values));
                    $requestId = (int)$pdo->lastInsertId();

                    notify_support_team(
                        'Nueva solicitud de alta: ' . $f['clinic_name'],
                        '<p>Llegó una solicitud de alta de <strong>' . e($f['clinic_name']) . '</strong> (' . e($plan['name']) . ', ' . e($f['billing']) . ').</p><p>Revisala en Administración → Solicitudes de alta.</p>',
                        'Llegó una solicitud de alta de ' . $f['clinic_name'] . ". Revisala en Administración > Solicitudes de alta."
                    );
                }

                $req = ['id' => $requestId, 'token' => $token, 'plan_code' => $planCode, 'billing' => $f['billing'],
                        'amount' => $amount, 'clinic_name' => $f['clinic_name'],
                        'responsible_name' => $f['responsible_name'], 'responsible_email' => $f['responsible_email']];

                if (mp_enabled()) {
                    start_payment($req);          // redirige y termina
                }
                // Sin Mercado Pago configurado: la solicitud queda guardada y se coordina el pago a mano.
                header('Location: ' . url('mp_retorno.php?r=' . $requestId . '&t=' . $token . '&sinpago=1'));
                exit;
            }
        } catch (Throwable $ex) {
            error_log('contratar.php: ' . $ex->getMessage());
            $error = 'No pudimos guardar tu solicitud. Probá de nuevo en unos minutos o escribinos desde Contacto.';
        }
    }
}

$pageTitle = 'Contratar ' . $plan['name'];
$pageDescription = 'Contratá el ' . $plan['name'] . ' de ' . APP_NAME . ': completá los datos de tu veterinaria y pagá de forma segura con Mercado Pago.';
$isLanding = true;
$bodyClass = 'page-bg-follow page-bg-contact';
require __DIR__ . '/includes/header.php';

$provincesCities = ar_provinces_cities();
?>

<style>
    .hire-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 16px; margin-top: 14px; }
    .hire-section { font: 800 17px 'Manrope', sans-serif; color: var(--ink); margin: 26px 0 0; padding-top: 18px; border-top: 1px solid var(--line); }
    .hire-section:first-of-type { border-top: 0; margin-top: 0; padding-top: 0; }
    .hire-plans { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 12px; margin-top: 12px; }
    .hire-plan { display: block; border: 2px solid var(--line); border-radius: 14px; padding: 14px 16px; cursor: pointer; background: var(--white); }
    .hire-plan input { position: absolute; opacity: 0; pointer-events: none; }
    .hire-plan.selected { border-color: var(--green); background: #f3faf5; }
    .hire-plan strong { display: block; font-size: 15px; color: var(--ink); }
    .hire-plan .price { font: 800 22px 'Manrope', sans-serif; color: var(--green); margin: 4px 0 2px; }
    .hire-plan small { color: var(--muted); }
    .hire-form select, .hire-form textarea { width: 100%; border-radius: 12px; padding: 12px 14px; border: 1px solid #dbe6de; background: #fbfdfb; font-family: inherit; font-size: 14px; color: var(--ink); }
    .hire-form select { padding-right: 10px; }
</style>

<div class="bg-paper section-padding-sm contact-bg">
    <div class="public-container">
        <div class="page-intro-header">
            <span class="section-eyebrow">Contratación</span>
            <h1>Contratá el <?= e($plan['name']) ?></h1>
            <p>Completá los datos de tu veterinaria y pagá de forma segura con Mercado Pago. Con tu pago aprobado, nuestro equipo registra tu clínica y te envía el acceso por correo.</p>
        </div>

        <div class="contact-card-form" style="max-width: 860px; margin: 0 auto;">
            <?php if ($error): ?>
                <div class="notice error" style="padding: 16px 20px; border-radius: 14px; margin-bottom: 24px; display: flex; align-items: center; gap: 14px; background: #fff0ee; color: #a63e39; border: 1px solid #fbdcd9;">
                    <span style="font-size: 22px;"><i class="fa-solid fa-triangle-exclamation"></i></span>
                    <div><strong style="display: block; font-size: 15px; margin-bottom: 2px;">Atención</strong><span style="font-size: 13px;"><?= e($error) ?></span></div>
                </div>
            <?php endif; ?>

            <form method="post" action="<?= url('contratar.php') ?>" class="auth-form hire-form" id="hireForm" autocomplete="on">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

                <h2 class="hire-section">1. Elegí cómo pagar</h2>
                <div class="hire-plans">
                    <?php foreach ($plan['billing'] as $code => $opt): ?>
                        <label class="hire-plan <?= $f['billing'] === $code ? 'selected' : '' ?>">
                            <input type="radio" name="billing" value="<?= e($code) ?>" <?= $f['billing'] === $code ? 'checked' : '' ?>>
                            <strong><?= e($opt['label']) ?></strong>
                            <div class="price"><?= e(plan_money((float)$opt['amount'])) ?></div>
                            <small><?= e($opt['detail']) ?></small>
                        </label>
                    <?php endforeach; ?>
                </div>

                <h2 class="hire-section">2. Datos de la veterinaria</h2>
                <div class="hire-grid">
                    <div class="form-group">
                        <label for="clinic_name"><span>Nombre de la veterinaria *</span></label>
                        <input id="clinic_name" name="clinic_name" maxlength="150" value="<?= e($f['clinic_name']) ?>" required placeholder="Ej. Veterinaria San Roque">
                    </div>
                    <div class="form-group">
                        <label for="cuit"><span>CUIT *</span></label>
                        <input id="cuit" name="cuit" maxlength="20" value="<?= e($f['cuit']) ?>" required placeholder="Ej. 20-12345678-9" inputmode="numeric">
                    </div>
                    <div class="form-group">
                        <label for="clinic_email"><span>Correo de la veterinaria *</span></label>
                        <input id="clinic_email" type="email" name="clinic_email" maxlength="190" value="<?= e($f['clinic_email']) ?>" required placeholder="contacto@veterinaria.com">
                    </div>
                    <div class="form-group">
                        <label for="phone"><span>Teléfono de la veterinaria</span><span class="label-hint">(Opcional)</span></label>
                        <input id="phone" type="tel" name="phone" maxlength="50" value="<?= e($f['phone']) ?>" placeholder="Ej. 11 1234-5678">
                    </div>
                    <div class="form-group">
                        <label for="available_days"><span>Días de atención</span></label>
                        <select id="available_days" name="available_days">
                            <option value="">Elegí los días de atención</option>
                            <?php foreach ($daysOptions as $d): ?>
                                <option value="<?= e($d) ?>" <?= $f['available_days'] === $d ? 'selected' : '' ?>><?= e($d) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                        <div>
                            <label for="opening_time"><span>Apertura</span></label>
                            <input id="opening_time" type="time" name="opening_time" value="<?= e($f['opening_time']) ?>">
                        </div>
                        <div>
                            <label for="closing_time"><span>Cierre</span></label>
                            <input id="closing_time" type="time" name="closing_time" value="<?= e($f['closing_time']) ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="provinceSelect"><span>Provincia *</span></label>
                        <select name="province" id="provinceSelect" required>
                            <option value="">Elegí una provincia</option>
                            <?php foreach (ar_provinces() as $prov): ?>
                                <option value="<?= e($prov) ?>" <?= $f['province'] === $prov ? 'selected' : '' ?>><?= e($prov) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="citySelect"><span>Localidad / Ciudad *</span></label>
                        <select name="city" id="citySelect" required>
                            <option value="">Primero elegí una provincia</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="street"><span>Calle y número *</span></label>
                        <input id="street" name="street" maxlength="180" value="<?= e($f['street']) ?>" required placeholder="Ej. Av. Rivadavia 1234">
                    </div>
                    <div class="form-group">
                        <label for="staff_count"><span>Cantidad de usuarios</span><span class="label-hint">(Opcional, hasta 8)</span></label>
                        <input id="staff_count" type="number" name="staff_count" min="1" max="8" value="<?= e($f['staff_count']) ?>" placeholder="Ej. 4">
                    </div>
                </div>

                <h2 class="hire-section">3. Responsable de la cuenta</h2>
                <p class="muted" style="font-size: 13px; margin: 6px 0 0;">Será el usuario con rol <strong>Jefe</strong> de la clínica. Le enviaremos el acceso a este correo.</p>
                <div class="hire-grid">
                    <div class="form-group">
                        <label for="responsible_name"><span>Nombre y apellido *</span></label>
                        <input id="responsible_name" name="responsible_name" maxlength="120" value="<?= e($f['responsible_name']) ?>" required placeholder="Ej. Dra. Valeria Rossi" autocomplete="name">
                    </div>
                    <div class="form-group">
                        <label for="responsible_email"><span>Correo de acceso *</span></label>
                        <input id="responsible_email" type="email" name="responsible_email" maxlength="190" value="<?= e($f['responsible_email']) ?>" required placeholder="valeria@veterinaria.com" autocomplete="email">
                    </div>
                    <div class="form-group">
                        <label for="responsible_phone"><span>Teléfono / WhatsApp</span><span class="label-hint">(Opcional)</span></label>
                        <input id="responsible_phone" type="tel" name="responsible_phone" maxlength="50" value="<?= e($f['responsible_phone']) ?>" placeholder="+54 9 11 2345-6789" autocomplete="tel">
                    </div>
                </div>

                <div class="form-group" style="margin-top: 14px;">
                    <label for="notes"><span>Comentarios</span><span class="label-hint">(Opcional: software actual, cantidad de pacientes a migrar, etc.)</span></label>
                    <textarea id="notes" name="notes" rows="3" maxlength="2000"><?= e($f['notes']) ?></textarea>
                </div>

                <label style="display: flex; gap: 10px; align-items: flex-start; margin-top: 18px; font-size: 13px; cursor: pointer;">
                    <input type="checkbox" name="terms" value="1" required <?= $f['terms'] === '1' ? 'checked' : '' ?> style="margin-top: 3px;">
                    <span>Acepto los <a href="<?= url('terminos.php') ?>" target="_blank" rel="noopener" style="color: var(--green); font-weight: 700;">términos y condiciones</a>.</span>
                </label>

                <div style="margin-top: 22px;">
                    <button type="submit" id="hireBtn" class="btn-hero-primary" style="width: 100%; justify-content: center; border: none; cursor: pointer; padding: 16px;">
                        <span><i class="fa-solid fa-lock" style="margin-right: 8px;"></i><?= mp_enabled() ? 'Continuar al pago con Mercado Pago' : 'Enviar solicitud' ?></span>
                    </button>
                </div>
                <div style="text-align: center; margin-top: 14px; font-size: 12px; color: var(--muted);">
                    <?php if (mp_enabled()): ?>
                        Vas a pagar en el sitio seguro de Mercado Pago. Nunca vemos ni guardamos los datos de tu tarjeta.
                    <?php else: ?>
                        El pago online todavía no está habilitado: guardamos tu solicitud y un asesor te contacta para coordinar el pago.
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Provincia → localidades (mismos datos que usa el alta de veterinarias del administrador).
const provincesData = <?= json_encode($provincesCities, JSON_UNESCAPED_UNICODE) ?>;
const provinceSelect = document.getElementById('provinceSelect');
const citySelect = document.getElementById('citySelect');
const oldCity = <?= json_encode($f['city'], JSON_UNESCAPED_UNICODE) ?>;

function updateCities(province, selectedCity) {
    citySelect.innerHTML = '<option value="">Elegí una localidad</option>';
    (provincesData[province] || []).forEach(function (city) {
        const opt = document.createElement('option');
        opt.value = city; opt.textContent = city;
        if (city === selectedCity) opt.selected = true;
        citySelect.appendChild(opt);
    });
}
provinceSelect.addEventListener('change', function () { updateCities(this.value, ''); });
if (provinceSelect.value) updateCities(provinceSelect.value, oldCity);

// Resalta la opción de pago elegida.
document.querySelectorAll('.hire-plan input').forEach(function (radio) {
    radio.addEventListener('change', function () {
        document.querySelectorAll('.hire-plan').forEach(function (l) { l.classList.remove('selected'); });
        radio.closest('.hire-plan').classList.add('selected');
    });
});

// Evita enviar dos veces (cada envío crearía un cobro).
document.getElementById('hireForm').addEventListener('submit', function () {
    const b = document.getElementById('hireBtn');
    setTimeout(function () { b.disabled = true; b.style.opacity = '.7'; }, 0);
});
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
