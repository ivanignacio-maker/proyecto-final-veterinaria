<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/geocoding.php';
require_admin();
csrf_check();

$error = '';

$old = [
    'name' => '', 'clinic_email' => '', 'cuit' => '', 'phone' => '',
    'available_days' => '', 'opening_time' => '', 'closing_time' => '',
    'street' => '', 'city' => '',
    'province' => '', 'username' => '', 'user_email' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = format_name(trim($_POST['name'] ?? ''));
    $clinicEmail = trim($_POST['clinic_email'] ?? '');
    $cuit = trim($_POST['cuit'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $availableDays = trim($_POST['available_days'] ?? '');
    $openingTime = ($_POST['opening_time'] ?? '') !== '' ? $_POST['opening_time'] : null;
    $closingTime = ($_POST['closing_time'] ?? '') !== '' ? $_POST['closing_time'] : null;
    $street = trim($_POST['street'] ?? '');
    $city = format_name(trim($_POST['city'] ?? ''));
    $province = trim($_POST['province'] ?? '');
    $username = format_name(trim($_POST['username'] ?? ''));
    $email = trim($_POST['user_email'] ?? '');
    $pass = $_POST['password'] ?? '';


    $old = [
        'name' => $name, 'clinic_email' => $clinicEmail, 'cuit' => $cuit, 'phone' => $phone,
        'available_days' => $availableDays, 'opening_time' => $openingTime ?? '',
        'closing_time' => $closingTime ?? '', 'street' => $street, 'city' => $city,
        'province' => $province, 'username' => $username, 'user_email' => $email,
    ];

    if (!$name || !filter_var($clinicEmail, FILTER_VALIDATE_EMAIL) || !$username || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($pass) < 10) {
        $error = 'Completá los datos y usá una contraseña de al menos 10 caracteres.';
    } elseif ($street === '' || $city === '' || !in_array($province, ar_provinces(), true)
              || mb_strlen($street) > 180 || mb_strlen($city) > 120) {
        $error = 'Completá la dirección: provincia, localidad y calle con número.';
    } else {
        try {
            $pdo = db();
            $pdo->beginTransaction();

            $q = $pdo->prepare("INSERT INTO clinics(name, email, cuit, phone, available_days, opening_time, closing_time, street, city, province) VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $q->execute([$name, $clinicEmail, $cuit, $phone ?: null, $availableDays ?: null, $openingTime, $closingTime, $street, $city, $province]);
            $id = (int)$pdo->lastInsertId();

            $q = $pdo->prepare("INSERT INTO vet_users(clinic_id, username, email, password_hash) VALUES(?, ?, ?, ?)");
            $q->execute([$id, $username, $email, password_hash($pass, PASSWORD_DEFAULT)]);

            $pdo->commit();

            flash('Veterinaria y usuario creados con éxito.');
            header('Location: ' . url('admin/veterinarias.php'));
            exit;

        } catch (Throwable $ex) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Alta de veterinaria fallida: ' . $ex->getMessage());
            $error = 'No se pudo crear. Revisá que el nombre y correo no estén repetidos.';
        }
    }
}

if (isset($_GET['toggle'])) {
    $q = db()->prepare("UPDATE clinics SET active = 1 - active WHERE id = ?");
    $q->execute([(int)$_GET['toggle']]);

    flash('Estado de la veterinaria modificado.');
    header('Location: ' . url('admin/veterinarias.php'));
    exit;
}

$rows = db()->query("
    SELECT c.*, COUNT(u.id) users
    FROM clinics c
    LEFT JOIN vet_users u ON u.clinic_id = c.id
    GROUP BY c.id
    ORDER BY c.name
")->fetchAll();

$provincesCities = ar_provinces_cities();

$pageTitle = 'Administrar veterinarias';
require __DIR__ . '/../includes/header.php';
?>

<div class="panel">
    <h1><i class="fa-solid fa-building" style="color: var(--green); margin-right: 8px;"></i>Administración de Veterinarias</h1>
    <p class="muted">El superadministrador crea la clínica, define su dirección y habilita su usuario inicial.</p>

    <?php if ($error): ?>
        <div class="notice error">
            <span class="notice-icon"><i class="fa-solid fa-triangle-exclamation"></i></span>
            <span><?= e($error) ?></span>
        </div>
    <?php endif; ?>

    <form method="post">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
        <h2 style="font-size: 20px; margin-bottom: 16px;">Alta de nueva clínica veterinaria</h2>

        <div class="form-grid">
    <div>
        <label>Nombre de la veterinaria *</label>
        <input type="text" name="name" maxlength="150" value="<?= e($old['name'] ?? '') ?>" required>
    </div>
    
    <div>
        <label>CUIT *</label>
        <input type="text" name="cuit" maxlength="20" placeholder="Ej. 20-12345678-9" value="<?= e($old['cuit'] ?? '') ?>" required>
    </div>

    <div>
        <label>Correo electrónico *</label>
        <input type="email" name="clinic_email" maxlength="190" value="<?= e($old['clinic_email']) ?>" required>
    </div>

    <div>
        <label>Teléfono de contacto</label>
        <input type="text" name="phone" maxlength="50" placeholder="Ej. 11 1234-5678" value="<?= e($old['phone'] ?? '') ?>">
    </div>

    <div>
        <label>Días disponibles</label>
        <input type="text" name="available_days" maxlength="100" placeholder="Ej. Lunes a Sábados" value="<?= e($old['available_days'] ?? '') ?>">
    </div>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
    <div>
        <label>Hora de apertura</label>
        <input type="time" name="opening_time" value="<?= e($old['opening_time'] ?? '') ?>">
    </div>
    <div>
        <label>Hora de cierre</label>
        <input type="time" name="closing_time" value="<?= e($old['closing_time'] ?? '') ?>">
    </div>
</div>
            <div>
                <label>Provincia</label>
                <select name="province" id="provinceSelect" required>
                    <option value="">Elegí una provincia</option>
                    <?php foreach (ar_provinces() as $prov): ?>
                        <option value="<?= e($prov) ?>" <?= $old['province'] === $prov ? 'selected' : '' ?>><?= e($prov) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label>Localidad / Ciudad</label>
                <select name="city" id="citySelect" required>
                    <option value="">Primero elegí una provincia</option>
                </select>
            </div>

            <div>
                <label>Calle y número</label>
                <input name="street" maxlength="180" placeholder="Ej. Av. Rivadavia 1234" value="<?= e($old['street']) ?>" required>
            </div>

            <div>
                <label>Nombre de usuario del veterinario</label>
                <input name="username" placeholder="Ej. Dr. Martínez" value="<?= e($old['username']) ?>" required>
            </div>

            <div>
                <label>Correo de acceso (login)</label>
                <input type="email" name="user_email" placeholder="martinez@veterinaria.com" value="<?= e($old['user_email']) ?>" required>
            </div>

            <div>
                <label>Contraseña inicial (mín. 10 caracteres)</label>
                <input type="password" name="password" minlength="10" placeholder="••••••••••••" required>
            </div>
        </div>

        <div class="actions" style="margin-top: 20px;">
            <button class="btn primary" type="submit">
                <i class="fa-solid fa-building-circle-check" style="margin-right: 6px;"></i> Crear veterinaria y usuario
            </button>
        </div>
    </form>
</div>

<div class="card" style="margin-top: 28px;">
    <h2>Clínicas veterinarias registradas</h2>
    <p class="muted">Listado del ecosistema de centros y consultorios activos en la plataforma.</p>

    <div style="overflow-x: auto;">
    <table>
        <thead>
            <tr>
                <th>Veterinaria</th>
                <th>Correo de contacto</th>
                <th>Dirección</th>
                <th style="min-width: 150px; white-space: nowrap;">Veterinarios</th>
                <th>Estado</th>
                <th style="min-width: 170px; white-space: nowrap;">Acción</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $r): ?>
                <?php
                $isActive = (bool)$r['active'];
                $address = clinic_full_address($r);
                ?>
                <tr>
                    <td>
                        <strong class="name-titlecase"><i class="fa-solid fa-hospital" style="color: var(--green); margin-right: 4px;"></i> <?= e_name($r['name']) ?></strong>
                    </td>
                    <td><?= e($r['email']) ?></td>
                    <td><?= $address !== '' ? e($address) : '<span class="muted">Sin dirección</span>' ?></td>
                    <td style="white-space: nowrap;">
                        <span class="pill pill-users"><i class="fa-solid fa-user-doctor" style="color: var(--green);"></i> <?= (int)$r['users'] ?> usuario<?= (int)$r['users'] === 1 ? '' : 's' ?></span>
                    </td>
                    <td>
                        <span class="badge <?= $isActive ? 'badge-success' : 'badge-danger' ?>">
                            <?= $isActive ? 'Activa' : 'Suspendida' ?>
                        </span>
                    </td>
                    <td>
                        <div style="display: flex; gap: 6px; flex-wrap: wrap;">
                            <a class="btn primary btn-sm" href="<?= url('admin/editar_veterinaria.php?id=' . (int)$r['id']) ?>">
                                <i class="fa-solid fa-pen-to-square"></i> Editar
                            </a>
                            <a class="btn secondary btn-sm" href="<?= url('admin/veterinarias.php?toggle=' . (int)$r['id']) ?>" data-confirm="¿Cambiar estado de esta veterinaria?">
                                <?= $isActive ? 'Suspender' : 'Activar' ?>
                            </a>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>

<script>
const provincesData = <?= json_encode($provincesCities, JSON_UNESCAPED_UNICODE) ?>;
const provinceSelect = document.getElementById('provinceSelect');
const citySelect = document.getElementById('citySelect');
const oldCity = <?= json_encode($old['city']) ?>;

function updateCities(selectedProvince, selectedCity = '') {
    citySelect.innerHTML = '<option value="">Elegí una localidad</option>';
    if (provincesData[selectedProvince]) {
        provincesData[selectedProvince].forEach(city => {
            const opt = document.createElement('option');
            opt.value = city;
            opt.textContent = city;
            if (city === selectedCity) {
                opt.selected = true;
            }
            citySelect.appendChild(opt);
        });
    }
}

provinceSelect.addEventListener('change', function() {
    updateCities(this.value);
});

if (provinceSelect.value) {
    updateCities(provinceSelect.value, oldCity);
}
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
