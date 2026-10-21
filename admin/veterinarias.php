<?php
require_once __DIR__ . '/../config/config.php';
require_admin();
csrf_check();

$error = '';

// 1. Procesar la creación de una nueva veterinaria y su primer usuario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = format_name(trim($_POST['name'] ?? ''));
    $clinicEmail = trim($_POST['clinic_email'] ?? '');
    $username = format_name(trim($_POST['username'] ?? ''));
    $email = trim($_POST['user_email'] ?? '');
    $pass = $_POST['password'] ?? '';

    // Validar formato de correos, campos vacíos y longitud de contraseña
    if (!$name || !filter_var($clinicEmail, FILTER_VALIDATE_EMAIL) || !$username || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($pass) < 10) {
        $error = 'Completá los datos y usá una contraseña de al menos 10 caracteres.';
    } else {
        try {
            $pdo = db();
            $pdo->beginTransaction();

            // Insertar la nueva clínica
            $q = $pdo->prepare("INSERT INTO clinics(name, email) VALUES(?, ?)");
            $q->execute([$name, $clinicEmail]);
            $id = (int)$pdo->lastInsertId();

            // Insertar el usuario administrador de esa clínica
            $q = $pdo->prepare("INSERT INTO vet_users(clinic_id, username, email, password_hash) VALUES(?, ?, ?, ?)");
            $q->execute([$id, $username, $email, password_hash($pass, PASSWORD_DEFAULT)]);

            // Confirmar ambos registros
            $pdo->commit();
            
            flash('Veterinaria y usuario creados con éxito.');
            header('Location: ' . url('admin/veterinarias.php'));
            exit;
            
        } catch (Throwable $ex) {
            // Si algo falla, revertir los cambios para no dejar datos a medias
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = 'No se pudo crear. Revisá que el nombre y correo no estén repetidos.';
        }
    }
}

// 2. Procesar la activación/desactivación de una clínica
if (isset($_GET['toggle'])) {
    $q = db()->prepare("UPDATE clinics SET active = 1 - active WHERE id = ?");
    $q->execute([(int)$_GET['toggle']]);
    
    flash('Estado de la veterinaria modificado.');
    header('Location: ' . url('admin/veterinarias.php'));
    exit;
}

// 3. Obtener el listado de todas las veterinarias y la cantidad de usuarios que tienen
$rows = db()->query("
    SELECT c.*, COUNT(u.id) users 
    FROM clinics c 
    LEFT JOIN vet_users u ON u.clinic_id = c.id 
    GROUP BY c.id 
    ORDER BY c.name
")->fetchAll();

$pageTitle = 'Administrar veterinarias';
require __DIR__ . '/../includes/header.php';
?>

<div class="panel">
    <h1>Administración de Veterinarias</h1>
    <p class="muted">Las clínicas no se registran solas en la plataforma: el superadministrador crea la clínica y habilita su usuario inicial.</p>
    
    <?php if ($error): ?>
        <div class="notice error">
            <span class="notice-icon">⚠️</span>
            <span><?= e($error) ?></span>
        </div>
    <?php endif; ?>
    
    <form method="post">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
        <h2 style="font-size: 20px; margin-bottom: 16px;">Alta de nueva clínica veterinaria</h2>
        
        <div class="form-grid">
            <div>
                <label>Nombre de la veterinaria</label>
                <input name="name" placeholder="Ej. Veterinaria del Parque" required>
            </div>
            
            <div>
                <label>Correo oficial de la veterinaria</label>
                <input type="email" name="clinic_email" placeholder="contacto@veterinaria.com" required>
            </div>
            
            <div>
                <label>Nombre de usuario del veterinario</label>
                <input name="username" placeholder="Ej. Dr. Martínez" required>
            </div>
            
            <div>
                <label>Correo de acceso (login)</label>
                <input type="email" name="user_email" placeholder="martinez@veterinaria.com" required>
            </div>
            
            <div>
                <label>Contraseña inicial (mín. 10 caracteres)</label>
                <input type="password" name="password" minlength="10" placeholder="••••••••••••" required>
            </div>
        </div>
        
        <div class="actions">
            <button class="btn primary" type="submit">🏢 Crear veterinaria y usuario</button>
        </div>
    </form>
</div>

<div class="card" style="margin-top: 28px;">
    <h2>Clínicas veterinarias registradas</h2>
    <p class="muted">Listado del ecosistema de centros y consultorios activos en la plataforma.</p>
    
    <table>
        <thead>
            <tr>
                <th>Veterinaria</th>
                <th>Correo de contacto</th>
                <th>Veterinarios</th>
                <th>Estado</th>
                <th>Acción</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $r): ?>
                <?php $isActive = (bool)$r['active']; ?>
                <tr>
                    <td>
                        <strong class="name-titlecase">🏥 <?= e_name($r['name']) ?></strong>
                    </td>
                    <td><?= e($r['email']) ?></td>
                    <td>
                        <span class="pill">👨‍⚕️ <?= (int)$r['users'] ?> usuario<?= (int)$r['users'] === 1 ? '' : 's' ?></span>
                    </td>
                    <td>
                        <span class="badge <?= $isActive ? 'badge-success' : 'badge-danger' ?>">
                            <?= $isActive ? 'Activa' : 'Suspendida' ?>
                        </span>
                    </td>
                    <td>
                        <a class="btn secondary btn-sm" href="<?= url('admin/veterinarias.php?toggle=' . (int)$r['id']) ?>" data-confirm="¿Cambiar estado de esta veterinaria?">
                            <?= $isActive ? 'Suspender' : 'Activar' ?>
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>