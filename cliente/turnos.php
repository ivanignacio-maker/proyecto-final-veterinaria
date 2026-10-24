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
    <h1><i class="fa-solid fa-calendar-plus" style="color: var(--green); margin-right: 8px;"></i>Solicitar nuevo turno</h1>
    <p class="muted">Elegí la clínica veterinaria que ya atendió a tu mascota, la fecha preferida y el motivo de consulta.</p>
    
    <?php if ($error): ?>
        <div class="notice error">
            <span class="notice-icon"><i class="fa-solid fa-triangle-exclamation"></i></span>
            <span><?= e($error) ?></span>
        </div>
    <?php endif; ?>
    
    <?php if (!$clinics || !$pets): ?>
        <div class="notice" style="background: #f7faf7; border: 1px solid var(--line); color: var(--ink);">
            <p style="margin: 0;"><i class="fa-solid fa-circle-info" style="color: var(--green); margin-right: 6px;"></i>Para solicitar un turno digital, necesitás tener al menos una mascota registrada y haber recibido una primera atención en una veterinaria de la red.</p>
        </div>
    <?php else: ?>
        <form method="post">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
            
            <div class="form-grid">
                <div>
                    <label>Veterinaria</label>
                    <select name="clinic_id" required>
                        <?php foreach ($clinics as $cl): ?>
                            <option value="<?= (int)$cl['id'] ?>"><?= e_name($cl['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label>Mascota</label>
                    <select name="pet_id" required>
                        <?php foreach ($pets as $pet): ?>
                            <option value="<?= (int)$pet['id'] ?>">
                                <?= e_name($pet['name']) ?> (ID #<?= (int)$pet['id'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label>Fecha y horario deseado</label>
                    <input type="datetime-local" name="appointment_at" required>
                </div>
                
                <div>
                    <label>Motivo de la consulta</label>
                    <input name="reason" placeholder="Ej.: control preventivo, vacuna antirrábica">
                </div>
            </div>
            
            <div class="actions">
                <button class="btn primary" type="submit">
                    <i class="fa-solid fa-calendar-check" style="margin-right: 6px;"></i> Enviar solicitud de turno
                </button>
            </div>
        </form>
    <?php endif; ?>
</div>

<div class="card" style="margin-top: 28px;">
    <h2><i class="fa-solid fa-clock-rotate-left" style="color: var(--green); margin-right: 8px;"></i>Mis solicitudes y turnos programados</h2>
    <p class="muted">Historial de citas solicitadas y confirmadas por tus veterinarias.</p>
    
    <?php if (!$appointments): ?>
        <p style="padding: 20px 0; color: var(--muted);">Todavía no tenés turnos programados.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Fecha y hora</th>
                    <th>Veterinaria</th>
                    <th>Mascota</th>
                    <th>Motivo</th>
                    <th>Estado de la cita</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($appointments as $a): ?>
                    <?php 
                        $statusClass = 'badge-info';
                        if ($a['status'] === 'Confirmado') $statusClass = 'badge-success';
                        elseif ($a['status'] === 'Solicitado') $statusClass = 'badge-warning';
                        elseif ($a['status'] === 'Cancelado') $statusClass = 'badge-danger';
                    ?>
                    <tr>
                        <td>
                            <strong><?= date('d/m/Y', strtotime($a['appointment_at'])) ?></strong><br>
                            <span class="muted"><?= date('H:i', strtotime($a['appointment_at'])) ?> hs</span>
                        </td>
                        <td>
                            <strong class="name-titlecase"><i class="fa-solid fa-hospital" style="color: var(--green); margin-right: 4px;"></i> <?= e_name($a['clinic_name']) ?></strong>
                        </td>
                        <td>
                            <span class="pill name-titlecase"><i class="fa-solid fa-paw" style="color: var(--green); margin-right: 4px;"></i> <?= e_name($a['pet_name']) ?></span>
                        </td>
                        <td><?= e($a['reason'] ?: 'Consulta veterinaria') ?></td>
                        <td>
                            <span class="badge <?= $statusClass ?>">
                                <?= e($a['status']) ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
