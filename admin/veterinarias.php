<?php
require_once __DIR__ . '/../config/config.php';
require_admin();
csrf_check();

$error = '';

// 1. Procesar la creación de una nueva veterinaria y su primer usuario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $clinicEmail = trim($_POST['clinic_email'] ?? '');
    $username = trim($_POST['username'] ?? '');
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
            
            flash('Veterinaria y usuario creados.');
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
    <h1>Administrar veterinarias</h1>
    <p>Las clínicas no se registran por su cuenta. El administrador crea la clínica y su primer usuario.</p>
    
    <?php if ($error): ?>
        <p role="alert"><?= e($error) ?></p>
    <?php endif; ?>
    
    <form method="post">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
        <h2>Nueva veterinaria</h2>
        
        <div class="form-grid">
            <div>
                <label>Nombre de veterinaria</label>
                <input name="name" required>
            </div>
            
            <div>
                <label>Correo de la veterinaria</label>
                <input type="email" name="clinic_email" required>
            </div>
            
            <div>
                <label>Nombre de usuario</label>
                <input name="username" required>
            </div>
            
            <div>
                <label>Correo de inicio de sesión</label>
                <input type="email" name="user_email" required>
            </div>
            
            <div>
                <label>Contraseña inicial (mínimo 10 caracteres)</label>
                <input type="password" name="password" minlength="10" required>
            </div>
        </div>
        
        <div class="actions">
            <button>Crear veterinaria</button>
        </div>
    </form>
</div>

<div class="card">
    <h2>Veterinarias existentes</h2>
    
    <table>
        <thead>
            <tr>
                <th>Veterinaria</th>
                <th>Correo</th>
                <th>Usuarios</th>
                <th>Estado</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?= e($r['name']) ?></td>
                    <td><?= e($r['email']) ?></td>
                    <td><?= (int)$r['users'] ?></td>
                    <td><?= $r['active'] ? 'Activa' : 'Suspendida' ?></td>
                    <td>
                        <a class="btn secondary" href="<?= url('admin/veterinarias.php?toggle=' . (int)$r['id']) ?>" data-confirm="¿Cambiar estado de esta veterinaria?">Cambiar estado</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>