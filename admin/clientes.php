<?php
require_once __DIR__ . '/../config/config.php';
require_admin();
csrf_check();

$error = '';

$old = [
    'dni'       => '',
    'full_name' => '',
    'email'     => '',
    'phone'     => '',
    'password'  => '',
];

// Procesamiento de alta de nuevo tutor
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dni      = trim($_POST['dni'] ?? '');
    $fullName = format_name(trim($_POST['full_name'] ?? ''));
    $email    = strtolower(trim($_POST['email'] ?? ''));
    $phone    = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';

    $old = [
        'dni'       => $dni,
        'full_name' => $fullName,
        'email'     => $email,
        'phone'     => $phone,
        'password'  => $password,
    ];

    if ($dni === '' || $fullName === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Completá DNI, nombre y un correo electrónico válido.';
    } else {
        try {
            $pdo = db();
            $chk = $pdo->prepare("SELECT id FROM clients WHERE dni = ?");
            $chk->execute([$dni]);
            if ($chk->fetch()) {
                $error = 'El DNI ingresado ya está registrado para otro tutor.';
            } else {
                $pwHash = password_hash($password !== '' ? $password : $dni, PASSWORD_DEFAULT);
                $q = $pdo->prepare("INSERT INTO clients(dni, full_name, email, phone, password_hash) VALUES(?, ?, ?, ?, ?)");
                $q->execute([$dni, $fullName, $email, $phone, $pwHash]);
                $newClientId = (int)$pdo->lastInsertId();

                audit_log('client', $newClientId, 'admin_created', ['dni' => $dni, 'full_name' => $fullName]);

                flash('Tutor / Dueño registrado con éxito.');
                header('Location: ' . url('admin/editar_cliente.php?id=' . $newClientId));
                exit;
            }
        } catch (Throwable $ex) {
            error_log('Error creando cliente desde admin: ' . $ex->getMessage());
            $error = 'No se pudo registrar el tutor. Verificá que el correo o DNI no estén repetidos.';
        }
    }
}

// Búsqueda de tutores: selector exclusivo entre DNI, ID o Nombre (no ambos a la vez)
$searchType = trim($_GET['tipo'] ?? 'dni');
if (!in_array($searchType, ['dni', 'id', 'nombre'], true)) {
    $searchType = 'dni';
}
$search = trim($_GET['q'] ?? '');

if ($search !== '') {
    if ($searchType === 'id') {
        $sql = "
            SELECT c.*, 
                   (SELECT COUNT(*) FROM pets p WHERE p.client_id = c.id) AS pets_count
            FROM clients c
            WHERE c.id = ?
            ORDER BY c.id DESC
        ";
        $q = db()->prepare($sql);
        $q->execute([is_numeric($search) ? (int)$search : 0]);
    } elseif ($searchType === 'dni') {
        $sql = "
            SELECT c.*, 
                   (SELECT COUNT(*) FROM pets p WHERE p.client_id = c.id) AS pets_count
            FROM clients c
            WHERE c.dni LIKE ?
            ORDER BY c.id DESC
        ";
        $q = db()->prepare($sql);
        $q->execute(["%{$search}%"]);
    } else { // nombre
        $sql = "
            SELECT c.*, 
                   (SELECT COUNT(*) FROM pets p WHERE p.client_id = c.id) AS pets_count
            FROM clients c
            WHERE c.full_name LIKE ?
            ORDER BY c.id DESC
        ";
        $q = db()->prepare($sql);
        $q->execute(["%{$search}%"]);
    }
    $clients = $q->fetchAll();
} else {
    $sql = "
        SELECT c.*, 
               (SELECT COUNT(*) FROM pets p WHERE p.client_id = c.id) AS pets_count
        FROM clients c
        ORDER BY c.id DESC
    ";
    $clients = db()->query($sql)->fetchAll();
}

$pageTitle = 'Administrar dueños y tutores';
require __DIR__ . '/../includes/header.php';
?>

<div class="panel">
    <h1><i class="fa-solid fa-users" style="color: var(--green); margin-right: 8px;"></i>Administración de Dueños y Tutores</h1>
    <p class="muted">Gestión global de los responsables de mascotas y sus libretas sanitarias digitales.</p>

    <?php if ($error): ?>
        <div class="notice error">
            <span class="notice-icon"><i class="fa-solid fa-triangle-exclamation"></i></span>
            <span><?= e($error) ?></span>
        </div>
    <?php endif; ?>

    <form method="post">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
        <h2 style="font-size: 20px; margin-bottom: 16px;">Alta de nuevo tutor / responsable</h2>

        <div class="form-grid">
            <div>
                <label>DNI del tutor *</label>
                <input type="text" name="dni" maxlength="20" placeholder="Ej. 35849201" value="<?= e($old['dni']) ?>" required inputmode="numeric">
            </div>

            <div>
                <label>Nombre y apellido completo *</label>
                <input type="text" name="full_name" maxlength="150" placeholder="Ej. Martín Gómez" value="<?= e($old['full_name']) ?>" required>
            </div>

            <div>
                <label>Correo electrónico *</label>
                <input type="email" name="email" maxlength="190" placeholder="martin@ejemplo.com" value="<?= e($old['email']) ?>" required>
            </div>

            <div>
                <label>Teléfono o celular de contacto</label>
                <input type="text" name="phone" maxlength="50" placeholder="Ej. 11 4455-6677" value="<?= e($old['phone']) ?>">
            </div>

            <div>
                <label>Contraseña de acceso (opcional)</label>
                <input type="password" name="password" minlength="6" placeholder="Por defecto será su DNI" autocomplete="new-password">
            </div>
        </div>

        <div class="actions" style="margin-top: 20px;">
            <button class="btn primary" type="submit">
                <i class="fa-solid fa-user-plus" style="margin-right: 6px;"></i> Registrar tutor
            </button>
        </div>
    </form>
</div>

<div class="card" style="margin-top: 28px;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; margin-bottom: 18px;">
        <div>
            <h2 style="margin: 0 0 4px;">Tutores y dueños registrados (<?= count($clients) ?>)</h2>
            <p class="muted" style="margin: 0;">Padrón unificado de tutores con acceso a historias y libretas digitales.</p>
        </div>

        <!-- Buscador con Selector Exclusivo: Buscar por DNI, ID o Nombre (no ambos a la vez) -->
        <form method="get" action="<?= url('admin/clientes.php') ?>" style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap; margin: 0;">
            <select name="tipo" style="margin: 0; padding: 9px 12px; font-size: 14px; border-radius: 8px; border: 1px solid var(--line); background: var(--card-bg, #fff); font-weight: 600; color: var(--ink);">
                <option value="dni" <?= $searchType === 'dni' ? 'selected' : '' ?>>Buscar por DNI</option>
                <option value="id" <?= $searchType === 'id' ? 'selected' : '' ?>>Buscar por ID</option>
                <option value="nombre" <?= $searchType === 'nombre' ? 'selected' : '' ?>>Buscar por Nombre</option>
            </select>

            <div style="position: relative; width: 220px;">
                <input type="text" name="q" value="<?= e($search) ?>" placeholder="<?= $searchType === 'id' ? 'Ej. 15' : ($searchType === 'dni' ? 'Ej. 35849201' : 'Ej. Juan Pérez') ?>" style="margin: 0; padding-left: 36px; font-size: 14px;">
                <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--muted); pointer-events: none;"></i>
            </div>

            <button type="submit" class="btn primary btn-sm" style="padding: 9px 15px !important;">
                Buscar
            </button>

            <?php if ($search !== ''): ?>
                <a href="<?= url('admin/clientes.php') ?>" class="btn secondary btn-sm" style="padding: 9px 12px !important;" title="Limpiar búsqueda">
                    <i class="fa-solid fa-xmark"></i>
                </a>
            <?php endif; ?>
        </form>
    </div>

    <?php if ($search !== ''): ?>
        <div style="margin-bottom: 16px; padding: 10px 14px; background: rgba(35, 119, 101, 0.08); border: 1px solid rgba(35, 119, 101, 0.2); border-radius: 8px; font-size: 13.5px; display: flex; justify-content: space-between; align-items: center;">
            <span>
                <i class="fa-solid fa-filter" style="color: var(--green); margin-right: 6px;"></i> 
                Filtrado por <strong><?= $searchType === 'id' ? 'ID' : ($searchType === 'dni' ? 'DNI' : 'Nombre') ?></strong>: 
                <strong>"<?= e($search) ?>"</strong> (<?= count($clients) ?> encontrado<?= count($clients) === 1 ? '' : 's' ?>)
            </span>
            <a href="<?= url('admin/clientes.php') ?>" style="color: var(--green); text-decoration: underline; font-weight: 600;">Limpiar filtro</a>
        </div>
    <?php endif; ?>

    <div style="overflow-x: auto;">
        <table>
            <thead>
                <tr>
                    <th style="width: 70px;">ID</th>
                    <th>Tutor / Dueño</th>
                    <th>DNI</th>
                    <th>Contacto</th>
                    <th style="white-space: nowrap;">Mascotas a cargo</th>
                    <th style="min-width: 170px; white-space: nowrap;">Acción</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($clients)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 32px 16px; color: var(--muted);">
                            <i class="fa-solid fa-circle-question" style="font-size: 28px; margin-bottom: 8px; display: block; color: var(--muted);"></i>
                            No se encontraron tutores que coincidan con la búsqueda<?= $search !== '' ? ' "<strong>' . e($search) . '</strong>"' : '' ?>.
                        </td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($clients as $c): ?>
                    <tr>
                        <td>
                            <span class="pill" style="font-family: monospace; font-weight: 700;">#<?= (int)$c['id'] ?></span>
                        </td>
                        <td>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <div style="width: 34px; height: 34px; border-radius: 50%; background: #eaf5ef; border: 1.5px solid var(--green); display: grid; place-items: center; color: var(--green); flex-shrink: 0; font-size: 14px; overflow: hidden;">
                                    <img src="<?= !empty($c['avatar_path']) ? url('archivo_avatar.php?id=' . (int)$c['id']) : url('assets/persona_generica.png') ?>" alt="" style="width: 100%; height: 100%; object-fit: cover;">
                                </div>
                                <strong class="name-titlecase"><?= e_name($c['full_name']) ?></strong>
                            </div>
                        </td>
                        <td>
                            <span style="font-family: monospace; font-weight: 600; color: var(--ink);"><?= e($c['dni']) ?></span>
                        </td>
                        <td>
                            <div style="font-size: 13px; display: flex; flex-direction: column; gap: 2px;">
                                <span><i class="fa-solid fa-envelope" style="color: var(--green); width: 14px; margin-right: 4px;"></i><?= e($c['email']) ?></span>
                                <?php if (!empty($c['phone'])): ?>
                                    <span class="muted"><i class="fa-solid fa-phone" style="width: 14px; margin-right: 4px;"></i><?= e($c['phone']) ?></span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <span class="pill pill-users">
                                <i class="fa-solid fa-paw" style="color: var(--green);"></i> <?= (int)$c['pets_count'] ?> mascota<?= (int)$c['pets_count'] === 1 ? '' : 's' ?>
                            </span>
                        </td>
                        <td>
                            <div style="display: flex; align-items: center; gap: 6px; flex-wrap: nowrap; white-space: nowrap;">
                                <a class="btn primary btn-sm" style="padding: 6px 12px !important; font-size: 12px !important;" href="<?= url('admin/editar_cliente.php?id=' . (int)$c['id']) ?>">
                                    <i class="fa-solid fa-pen-to-square" style="margin-right: 4px;"></i> Editar
                                </a>
                                <a class="btn secondary btn-sm" style="padding: 6px 10px !important; font-size: 12px !important;" href="<?= url('clientes/ver.php?id=' . (int)$c['id']) ?>" title="Ver historial y ficha clínica" target="_blank">
                                    <i class="fa-solid fa-arrow-up-right-from-square"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
