<?php
require_once __DIR__ . '/../config/config.php';
require_vet();
csrf_check();

$cid = (int)$_SESSION['clinic_id'];
$error = '';

// 1. Procesar la actualización del estado de un turno existente
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_status'])) {
    $aid = (int)($_POST['appointment_id'] ?? 0);
    $status = $_POST['status'] ?? '';

    if (in_array($status, ['Solicitado', 'Confirmado', 'Cancelado', 'Atendido'], true)) {
        $q = db()->prepare("UPDATE appointments SET status = ? WHERE id = ? AND clinic_id = ?");
        $q->execute([$status, $aid, $cid]);
        
        flash('Estado del turno actualizado.');
        header('Location: ' . url('turnos/index.php'));
        exit;
    }
}

// 2. Procesar la asignación de un nuevo turno
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['change_status'])) {
    $dni = trim($_POST['dni'] ?? '');
    $pet = (int)($_POST['pet_id'] ?? 0);
    $dt = str_replace('T', ' ', $_POST['appointment_at'] ?? '');
    $reason = trim($_POST['reason'] ?? '');

    // Validar existencia del cliente
    $q = db()->prepare("SELECT id FROM clients WHERE dni = ?");
    $q->execute([$dni]);
    $client = (int)$q->fetchColumn();

    // Validar existencia de la mascota y que pertenezca al cliente
    $q = db()->prepare("SELECT id FROM pets WHERE id = ? AND client_id = ?");
    $q->execute([$pet, $client]);

    if (!$client || !$q->fetch() || !$dt || strtotime($dt) <= time()) {
        $error = 'Verificá el DNI, la mascota y que la fecha sea futura.';
    } else {
        // Registrar o actualizar la relación entre la clínica y el cliente
        $q = db()->prepare("INSERT INTO clinic_clients(clinic_id, client_id) VALUES(?, ?) ON DUPLICATE KEY UPDATE last_seen_at = CURRENT_TIMESTAMP");
        $q->execute([$cid, $client]);

        // Guardar el nuevo turno
        $q = db()->prepare("INSERT INTO appointments(clinic_id, client_id, pet_id, requested_by, appointment_at, reason, status) VALUES(?, ?, ?, 'veterinaria', ?, ?, 'Confirmado')");
        $q->execute([$cid, $client, $pet, $dt, $reason ?: null]);

        flash('Turno asignado.');
        header('Location: ' . url('turnos/index.php'));
        exit;
    }
}

// 3. Obtener el listado de turnos de la clínica (últimos 100)
$q = db()->prepare("
    SELECT a.*, c.full_name, c.dni, p.name pet_name 
    FROM appointments a 
    JOIN clients c ON c.id = a.client_id 
    JOIN pets p ON p.id = a.pet_id 
    WHERE a.clinic_id = ? 
    ORDER BY a.appointment_at DESC 
    LIMIT 100
");
$q->execute([$cid]);
$rows = $q->fetchAll();

$pageTitle = 'Turnos | ' . APP_NAME;
require __DIR__ . '/../includes/header.php';
?>

<div class="panel">
    <h1><i class="fa-solid fa-calendar-plus" style="color: var(--green); margin-right: 8px;"></i>Asignar nuevo turno</h1>
    <p class="muted">Programá una consulta presencial indicando el DNI del titular y el ID de su mascota.</p>
    
    <?php if ($error): ?>
        <div class="notice error">
            <span class="notice-icon"><i class="fa-solid fa-triangle-exclamation"></i></span>
            <span><?= e($error) ?></span>
        </div>
    <?php endif; ?>
    
    <form method="post">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
        
        <div class="form-grid">
            <div>
                <label>DNI del cliente</label>
                <input name="dni" placeholder="Ej. 30123456" required>
            </div>
            <div>
                <label>ID de mascota</label>
                <input type="number" name="pet_id" placeholder="Ej. 1" required>
            </div>
            <div>
                <label>Fecha y hora</label>
                <input type="datetime-local" name="appointment_at" required>
            </div>
            <div>
                <label>Motivo de la consulta (opcional)</label>
                <input name="reason" placeholder="Ej. Vacunación anual, control">
            </div>
        </div>
        
        <div class="actions">
            <button class="btn primary" type="submit">
                <i class="fa-solid fa-calendar-check" style="margin-right: 6px;"></i> Asignar turno
            </button>
        </div>
    </form>
</div>

<div class="card" style="margin-top: 28px;">
    <h2><i class="fa-solid fa-calendar-days" style="color: var(--green); margin-right: 8px;"></i>Agenda de turnos de la veterinaria</h2>
    <p class="muted">Lista cronológica de turnos confirmados y solicitudes recibidas.</p>
    
    <?php if (!$rows): ?>
        <p style="padding: 20px 0; color: var(--muted);">No hay turnos registrados todavía en la clínica.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Fecha y hora</th>
                    <th>Cliente / Tutor</th>
                    <th>Mascota</th>
                    <th>Motivo</th>
                    <th>Estado</th>
                    <th>Acción</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $r): ?>
                    <?php 
                        $statusClass = 'badge-info';
                        if ($r['status'] === 'Confirmado') $statusClass = 'badge-success';
                        elseif ($r['status'] === 'Solicitado') $statusClass = 'badge-warning';
                        elseif ($r['status'] === 'Cancelado') $statusClass = 'badge-danger';
                    ?>
                    <tr>
                        <td>
                            <strong><?= date('d/m/Y', strtotime($r['appointment_at'])) ?></strong><br>
                            <span class="muted"><?= date('H:i', strtotime($r['appointment_at'])) ?> hs</span>
                        </td>
                        <td>
                            <strong class="name-titlecase"><?= e_name($r['full_name']) ?></strong><br>
                            <span class="muted">DNI <?= e($r['dni']) ?></span>
                        </td>
                        <td>
                            <span class="pill name-titlecase"><i class="fa-solid fa-paw" style="color: var(--green); margin-right: 4px;"></i> <?= e_name($r['pet_name']) ?></span>
                        </td>
                        <td><?= e($r['reason'] ?: 'Consulta veterinaria') ?></td>
                        <td>
                            <span class="badge <?= $statusClass ?>">
                                <?= e($r['status']) ?>
                            </span>
                        </td>
                        <td>
                            <form method="post" style="display: flex; gap: 6px; align-items: center;">
                                <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                                <input type="hidden" name="change_status" value="1">
                                <input type="hidden" name="appointment_id" value="<?= (int)$r['id'] ?>">
                                
                                <select name="status" style="margin: 0; padding: 6px 8px; font-size: 13px;">
                                    <?php foreach (['Solicitado', 'Confirmado', 'Cancelado', 'Atendido'] as $st): ?>
                                        <option value="<?= e($st) ?>" <?= $r['status'] === $st ? 'selected' : '' ?>>
                                            <?= e($st) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                
                                <button type="submit" class="btn secondary btn-sm" style="padding: 6px 12px;">OK</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
