<?php
require_once __DIR__ . '/../config/config.php';
require_client();
csrf_check();

$clientId = (int)$_SESSION['client_id'];
$error = '';

// 1. Obtener las clínicas donde el cliente ya tiene consultas registradas (solo puede pedir turno en estas)
$q = db()->prepare("
    SELECT DISTINCT cl.id, cl.name 
    FROM clinics cl 
    JOIN consultations co ON co.clinic_id = cl.id 
    JOIN pets p ON p.id = co.pet_id 
    WHERE p.client_id = ? AND cl.active = 1 
    ORDER BY cl.name
");
$q->execute([$clientId]);
$clinics = $q->fetchAll();

// 2. Obtener el listado de mascotas del cliente
$q = db()->prepare("SELECT * FROM pets WHERE client_id = ? ORDER BY name");
$q->execute([$clientId]);
$pets = $q->fetchAll();

// 3. Procesar la solicitud de un nuevo turno
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $clinic = (int)($_POST['clinic_id'] ?? 0);
    $pet = (int)($_POST['pet_id'] ?? 0);
    $dt = str_replace('T', ' ', $_POST['appointment_at'] ?? '');
    $reason = trim($_POST['reason'] ?? '');
    
    // Validar que el cliente haya visitado esa clínica previamente
    $q = db()->prepare("
        SELECT 1 
        FROM consultations co 
        JOIN pets p ON p.id = co.pet_id 
        WHERE p.client_id = ? AND co.clinic_id = ? 
        LIMIT 1
    ");
    $q->execute([$clientId, $clinic]);
    $visited = $q->fetchColumn();
    
    // Validar que la mascota le pertenezca al cliente
    $q = db()->prepare("SELECT id FROM pets WHERE id = ? AND client_id = ?");
    $q->execute([$pet, $clientId]);
    $owned = $q->fetchColumn();
    
    // Validar permisos y que la fecha sea futura
    if (!$visited || !$owned || !$dt || strtotime($dt) <= time()) {
        $error = 'Elegí una veterinaria visitada, una mascota propia y una fecha futura.';
    } else {
        // Insertar el turno con estado "Solicitado"
        $q = db()->prepare("
            INSERT INTO appointments(clinic_id, client_id, pet_id, requested_by, appointment_at, reason, status) 
            VALUES(?, ?, ?, 'cliente', ?, ?, 'Solicitado')
        ");
        $q->execute([$clinic, $clientId, $pet, $dt, $reason ?: null]);
        
        flash('Solicitud enviada. La veterinaria debe confirmarla.');
        header('Location: ' . url('cliente/turnos.php'));
        exit;
    }
}

// 4. Obtener el historial de turnos y solicitudes del cliente
$q = db()->prepare("
    SELECT a.*, cl.name clinic_name, p.name pet_name 
    FROM appointments a 
    JOIN clinics cl ON cl.id = a.clinic_id 
    JOIN pets p ON p.id = a.pet_id 
    WHERE a.client_id = ? 
    ORDER BY a.appointment_at DESC
");
$q->execute([$clientId]);
$appointments = $q->fetchAll();

$pageTitle = 'Solicitar turno';
require __DIR__ . '/../includes/header.php';
?>

<div class="panel">
    <h1>Solicitar turno</h1>
    <p class="muted">Solo aparecen veterinarias que ya atendieron a alguna de tus mascotas.</p>
    
    <?php if ($error): ?>
        <p role="alert"><?= e($error) ?></p>
    <?php endif; ?>
    
    <?php if (!$clinics || !$pets): ?>
        <p>Para solicitar un turno, necesitás tener una mascota y haber sido atendido por una veterinaria participante.</p>
    <?php else: ?>
        <form method="post">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
            
            <div class="form-grid">
                <div>
                    <label>Veterinaria</label>
                    <select name="clinic_id" required>
                        <?php foreach ($clinics as $cl): ?>
                            <option value="<?= (int)$cl['id'] ?>"><?= e($cl['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label>Mascota</label>
                    <select name="pet_id" required>
                        <?php foreach ($pets as $pet): ?>
                            <option value="<?= (int)$pet['id'] ?>">
                                <?= e($pet['name']) ?> (ID #<?= (int)$pet['id'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label>Fecha y hora deseada</label>
                    <input type="datetime-local" name="appointment_at" required>
                </div>
                
                <div>
                    <label>Motivo</label>
                    <input name="reason" placeholder="Ej.: control anual">
                </div>
            </div>
            
            <button>Enviar solicitud</button>
        </form>
    <?php endif; ?>
</div>

<div class="card">
    <h2>Mis solicitudes y turnos</h2>
    
    <?php if (!$appointments): ?>
        <p>Todavía no tenés turnos.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Veterinaria</th>
                    <th>Mascota</th>
                    <th>Motivo</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($appointments as $a): ?>
                    <tr>
                        <td><?= e($a['appointment_at']) ?></td>
                        <td><?= e($a['clinic_name']) ?></td>
                        <td><?= e($a['pet_name']) ?></td>
                        <td><?= e($a['reason']) ?></td>
                        <td><?= e($a['status']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>