<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/geocoding.php';
require_admin();

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

$q = db()->prepare("SELECT * FROM clinics WHERE id = ?");
$q->execute([$id]);
$clinic = $q->fetch();

if (!$clinic) {
    flash('La veterinaria no existe.');
    header('Location: ' . url('admin/veterinarias.php'));
    exit;
}

$error = '';

// Procesamiento de formularios POST de forma segura
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sent = $_POST['csrf'] ?? '';
    if (!is_string($sent) || !hash_equals($_SESSION['csrf'] ?? '', $sent)) {
        $error = 'La sesión expiró o el token de seguridad es inválido. Recargá la página.';
    } else {
        // 1. Crear nuevo usuario desde el modal
        if (isset($_POST['action']) && $_POST['action'] === 'crear_usuario') {
            $newUsername = format_name(trim($_POST['new_username'] ?? ''));
            $newEmail    = trim($_POST['new_email'] ?? '');
            $newPassword = $_POST['new_password'] ?? '';

            if ($newUsername === '' || !filter_var($newEmail, FILTER_VALIDATE_EMAIL) || strlen($newPassword) < 10) {
                $error = 'Para el nuevo usuario completá nombre, correo válido y una contraseña de al menos 10 caracteres.';
            } else {
                try {
                    $stmtUser = db()->prepare("INSERT INTO vet_users (clinic_id, username, email, password_hash, active) VALUES (?, ?, ?, ?, 1)");
                    $stmtUser->execute([$id, $newUsername, $newEmail, password_hash($newPassword, PASSWORD_DEFAULT)]);
                    
                    flash('Usuario agregado correctamente a la veterinaria.');
                    header('Location: ' . url('admin/editar_veterinaria.php?id=' . $id));
                    exit;
                } catch (Throwable $ex) {
                    error_log('Error creando usuario de veterinaria: ' . $ex->getMessage());
                    $error = 'No se pudo crear el usuario. Verificá que el correo no esté repetido.';
                }
            }
        } 
        // 2. Guardar cambios generales de la veterinaria y sus usuarios
        else {
            $form = [
                'name'           => format_name(trim($_POST['name'] ?? '')),
                'email'          => trim($_POST['email'] ?? ''),
                'cuit'           => trim($_POST['cuit'] ?? ''),
                'phone'          => trim($_POST['phone'] ?? ''),
                'available_days' => trim($_POST['available_days'] ?? ''),
                'opening_time'   => ($_POST['opening_time'] ?? '') !== '' ? $_POST['opening_time'] : null,
                'closing_time'   => ($_POST['closing_time'] ?? '') !== '' ? $_POST['closing_time'] : null,
                'street'         => trim($_POST['street'] ?? ''),
                'city'           => format_name(trim($_POST['city'] ?? '')),
                'province'       => trim($_POST['province'] ?? ''),
                'active'         => !empty($_POST['active']) ? 1 : 0,
            ];

            // Obtener usuarios actuales para actualizar
            $qUsers = db()->prepare("SELECT id, username, email, active FROM vet_users WHERE clinic_id = ? ORDER BY id");
            $qUsers->execute([$id]);
            $currentUsers = $qUsers->fetchAll();
            
            $formUsers = [];
            $posted = is_array($_POST['users'] ?? null) ? $_POST['users'] : [];
            foreach ($currentUsers as $u) {
                $uid = (int)$u['id'];
                $in = is_array($posted[$uid] ?? null) ? $posted[$uid] : [];
                $formUsers[$uid] = [
                    'username' => format_name(trim((string)($in['username'] ?? ''))),
                    'email'    => trim((string)($in['email'] ?? '')),
                    'active'   => !empty($in['active']) ? 1 : 0, 
                    'password' => (string)($in['password'] ?? ''),
                ];
            }

            if ($form['name'] === '' || !filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
                $error = 'Completá el nombre y un correo válido de la veterinaria.';
            } elseif ($form['street'] === '' || $form['city'] === '' || !in_array($form['province'], ar_provinces(), true)
                      || mb_strlen($form['street']) > 180 || mb_strlen($form['city']) > 120) {
                $error = 'Completá correctamente la dirección: provincia, localidad y calle.';
            } else {
                foreach ($formUsers as $fu) {
                    if ($fu['username'] === '' || !filter_var($fu['email'], FILTER_VALIDATE_EMAIL)) {
                        $error = 'Cada usuario necesita un nombre y un correo válido.';
                        break;
                    }
                    if ($fu['password'] !== '' && strlen($fu['password']) < 10) {
                        $error = 'Las contraseñas nuevas deben tener al menos 10 caracteres.';
                        break;
                    }
                }
            }

            if ($error === '') {
                try {
                    $pdo = db();
                    $pdo->beginTransaction();

                    $q = $pdo->prepare("UPDATE clinics SET name = ?, email = ?, cuit = ?, phone = ?, available_days = ?, opening_time = ?, closing_time = ?, street = ?, city = ?, province = ?, active = ? WHERE id = ?");
                    $q->execute([
                        $form['name'], $form['email'], 
                        $form['cuit'], $form['phone'], 
                        $form['available_days'], $form['opening_time'], 
                        $form['closing_time'], $form['street'], 
                        $form['city'], $form['province'], 
                        $form['active'], $id
                    ]);

                    foreach ($formUsers as $uid => $fu) {
                        if ($fu['password'] !== '') {
                            $q = $pdo->prepare("UPDATE vet_users SET username = ?, email = ?, active = ?, password_hash = ? WHERE id = ? AND clinic_id = ?");
                            $q->execute([$fu['username'], $fu['email'], $fu['active'], password_hash($fu['password'], PASSWORD_DEFAULT), $uid, $id]);
                        } else {
                            $q = $pdo->prepare("UPDATE vet_users SET username = ?, email = ?, active = ? WHERE id = ? AND clinic_id = ?");
                            $q->execute([$fu['username'], $fu['email'], $fu['active'], $uid, $id]);
                        }
                    }

                    $pdo->commit();

                    flash('Los datos de la veterinaria se guardaron correctamente.');
                    header('Location: ' . url('admin/veterinarias.php'));
                    exit;

                } catch (Throwable $ex) {
                    if (isset($pdo) && $pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    error_log('Edición de veterinaria fallida: ' . $ex->getMessage());
                    $error = 'No se pudo guardar. Revisá que el nombre o los correos no estén repetidos.';
                }
            }
        }
    }
}

// Cargar datos actuales para mostrar en la vista
$q = db()->prepare("SELECT * FROM clinics WHERE id = ?");
$q->execute([$id]);
$clinic = $q->fetch();

$form = [
    'name'           => (string)$clinic['name'],
    'email'          => (string)$clinic['email'],
    'cuit'           => (string)($clinic['cuit'] ?? ''),
    'phone'          => (string)($clinic['phone'] ?? ''),
    'available_days' => (string)($clinic['available_days'] ?? ''),
    'opening_time'   => (string)($clinic['opening_time'] ?? ''),
    'closing_time'   => (string)($clinic['closing_time'] ?? ''),
    'street'         => (string)($clinic['street'] ?? ''),
    'city'           => (string)($clinic['city'] ?? ''),
    'province'       => (string)($clinic['province'] ?? ''),
    'active'         => (int)$clinic['active'],
];

$qUsers = db()->prepare("SELECT id, username, email, active FROM vet_users WHERE clinic_id = ? ORDER BY id");
$qUsers->execute([$id]);
$users = $qUsers->fetchAll();

$formUsers = [];
foreach ($users as $u) {
    $formUsers[(int)$u['id']] = [
        'username' => (string)$u['username'],
        'email'    => (string)$u['email'],
        'active'   => (int)$u['active'],
        'password' => '',
    ];
}

$provincesCities = ar_provinces_cities();

require __DIR__ . '/../includes/header.php';
?>

<div class="panel">
    <h1><i class="fa-solid fa-pen-to-square" style="color: var(--green); margin-right: 8px;"></i>Editar veterinaria</h1>
    <p class="muted">Modificá los datos de la clínica, su dirección y sus usuarios de acceso.</p>

    <?php if ($error): ?>
        <div class="notice error">
            <span class="notice-icon"><i class="fa-solid fa-triangle-exclamation"></i></span>
            <span><?= e($error) ?></span>
        </div>
    <?php endif; ?>

    <form method="post">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
        <input type="hidden" name="id" value="<?= (int)$id ?>">

        <h2 style="font-size: 20px; margin-bottom: 16px;">Datos de la veterinaria</h2>
        <div class="form-grid">
            <div>
                <label>Nombre de la veterinaria *</label>
                <input type="text" name="name" maxlength="150" value="<?= e($form['name'] ?? '') ?>" required>
            </div>
            
            <div>
                <label>CUIT *</label>
                <input type="text" name="cuit" maxlength="20" value="<?= e($form['cuit'] ?? '') ?>" required>
            </div>

            <div>
                <label>Correo electrónico *</label>
                <input type="email" name="email" maxlength="150" value="<?= e($form['email'] ?? '') ?>" required>
            </div>

            <div>
                <label>Teléfono de contacto</label>
                <input type="text" name="phone" maxlength="50" value="<?= e($form['phone'] ?? '') ?>">
            </div>

            <div>
                <label>Días disponibles</label>
                <input type="text" name="available_days" maxlength="100" value="<?= e($form['available_days'] ?? '') ?>">
            </div>

            <div>
                <label>Hora de apertura</label>
                <input type="time" name="opening_time" value="<?= e($form['opening_time'] ?? '') ?>">
            </div>
            <div>
                <label>Hora de cierre</label>
                <input type="time" name="closing_time" value="<?= e($form['closing_time'] ?? '') ?>">
            </div>
        </div>

        <h2 style="font-size: 20px; margin: 28px 0 16px;">Dirección</h2>
        <div class="form-grid">
            <div>
                <label>Provincia</label>
                <select name="province" id="provinceSelect" required>
                    <option value="">Elegí una provincia</option>
                    <?php foreach (ar_provinces() as $prov): ?>
                        <option value="<?= e($prov) ?>" <?= $form['province'] === $prov ? 'selected' : '' ?>><?= e($prov) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>Localidad / Ciudad</label>
                <select name="city" id="citySelect" required>
                    <option value="">Elegí una localidad</option>
                </select>
            </div>
            <div>
                <label>Calle y número</label>
                <input name="street" maxlength="180" placeholder="Ej. Av. Rivadavia 1234" value="<?= e($form['street']) ?>" required>
            </div>
        </div>

        <h2 style="font-size: 20px; margin: 28px 0 16px;">Estado</h2>
        <label style="display: flex; align-items: center; gap: 8px; font-weight: 600;">
            <input type="checkbox" name="active" value="1" <?= $form['active'] ? 'checked' : '' ?> style="width: auto;">
            Veterinaria activa (si se suspende, no aparece en el sistema)
        </label>

        <div style="display: flex; justify-content: space-between; align-items: center; margin: 28px 0 16px; flex-wrap: wrap; gap: 10px;">
            <h2 style="font-size: 20px; margin: 0;">Usuarios de la veterinaria</h2>
            <button type="button" class="btn secondary btn-sm" onclick="openModal('modalNuevoUsuario')">
                <i class="fa-solid fa-user-plus" style="margin-right: 6px;"></i> Agregar nuevo usuario
            </button>
        </div>

        <?php if (!$formUsers): ?>
            <p class="muted">Esta veterinaria no tiene usuarios cargados.</p>
        <?php endif; ?>

        <?php foreach ($formUsers as $uid => $fu): ?>
            <div style="border: 1px solid var(--line); border-radius: 14px; padding: 16px; margin-bottom: 14px;">
                <div class="form-grid">
                    <div>
                        <label>Nombre de usuario</label>
                        <input name="users[<?= (int)$uid ?>][username]" value="<?= e($fu['username']) ?>" required>
                    </div>
                    <div>
                        <label>Correo de acceso (login)</label>
                        <input type="email" name="users[<?= (int)$uid ?>][email]" value="<?= e($fu['email']) ?>" required>
                    </div>
                    <div>
                        <label>Nueva contraseña (opcional)</label>
                        <input type="password" name="users[<?= (int)$uid ?>][password]" minlength="10" placeholder="Dejala vacía para no cambiarla" autocomplete="new-password">
                    </div>
                </div>
                <label style="display: flex; align-items: center; gap: 8px; margin-top: 10px;">
                    <input type="checkbox" name="users[<?= (int)$uid ?>][active]" value="1" <?= $fu['active'] ? 'checked' : '' ?> style="width: auto;">
                    Usuario activo
                </label>
            </div>
        <?php endforeach; ?>

        <div class="actions">
            <button class="btn primary" type="submit">
                <i class="fa-solid fa-floppy-disk" style="margin-right: 6px;"></i> Guardar cambios
            </button>
            <a class="btn secondary" href="<?= url('admin/veterinarias.php') ?>">Cancelar</a>
        </div>
    </form>
</div>

<!-- Modal para agregar un nuevo usuario -->
<div id="modalNuevoUsuario" class="modal-overlay" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 9999; place-items: center; padding: 20px;">
    <div class="modal-window" style="background: #ffffff; border-radius: 20px; max-width: 480px; width: 100%; padding: 28px; box-shadow: 0 10px 40px rgba(0,0,0,0.2);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3 style="margin: 0; font-size: 18px; color: var(--ink);">
                <i class="fa-solid fa-user-plus" style="color: var(--green); margin-right: 6px;"></i>Nuevo usuario para la clínica
            </h3>
            <button type="button" onclick="closeModal('modalNuevoUsuario')" style="background: none; border: none; font-size: 22px; cursor: pointer; color: var(--muted);">&times;</button>
        </div>

        <form method="post">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="crear_usuario">
            <input type="hidden" name="id" value="<?= (int)$id ?>">

            <div class="form-group" style="margin-bottom: 14px;">
                <label style="display: block; font-size: 13px; font-weight: 700; margin-bottom: 6px;">Nombre de usuario</label>
                <input type="text" name="new_username" class="form-control" required style="width: 100%; padding: 10px; border: 1px solid var(--line); border-radius: 8px;" placeholder="Ej. Ana Pérez">
            </div>

            <div class="form-group" style="margin-bottom: 14px;">
                <label style="display: block; font-size: 13px; font-weight: 700; margin-bottom: 6px;">Correo de acceso (login)</label>
                <input type="email" name="new_email" class="form-control" required style="width: 100%; padding: 10px; border: 1px solid var(--line); border-radius: 8px;" placeholder="ana@correo.com">
            </div>

            <div class="form-group" style="margin-bottom: 20px;">
                <label style="display: block; font-size: 13px; font-weight: 700; margin-bottom: 6px;">Contraseña inicial (mín. 10 caracteres)</label>
                <input type="password" name="new_password" class="form-control" minlength="10" required style="width: 100%; padding: 10px; border: 1px solid var(--line); border-radius: 8px;" placeholder="Mínimo 10 caracteres">
            </div>

            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" class="btn secondary" onclick="closeModal('modalNuevoUsuario')">Cancelar</button>
                <button type="submit" class="btn primary">Crear usuario</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(id) {
    const el = document.getElementById(id);
    if (el) el.style.display = 'grid';
}

function closeModal(id) {
    const el = document.getElementById(id);
    if (el) el.style.display = 'none';
}

window.addEventListener('click', function(e) {
    const modal = document.getElementById('modalNuevoUsuario');
    if (e.target === modal) closeModal('modalNuevoUsuario');
});

const provincesData = <?= json_encode($provincesCities, JSON_UNESCAPED_UNICODE) ?>;
const provinceSelect = document.getElementById('provinceSelect');
const citySelect = document.getElementById('citySelect');
const currentCity = <?= json_encode($form['city']) ?>;

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
    updateCities(provinceSelect.value, currentCity);
}
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>