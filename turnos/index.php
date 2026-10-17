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

$pageTitle = 'Turnos';
require __DIR__ . '/../includes/header.php';
?>

<div class="panel">
    <h1>Asignar turno</h1>
    
    <?php if ($error): ?>
        <p role="alert"><?= e($error) ?></p>
    <?php endif; ?>
    
    <form method="post">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
        
        <div class="form-grid">
            <div>
                <label>DNI del cliente</label>
                <input name="dni" required>
            </div>
            <div>
                <label>ID de mascota</label>
                <input type="number" name="pet_id" required>
            </div>
            <div>
                <label>Fecha y hora</label>
                <input type="datetime-local" name="appointment_at" required>
            </div>
            <div>
                <label>Motivo (opcional)</label>
                <input name="reason">
            </div>
        </div>
        
        <p class="muted">Ingresá el DNI y el ID de mascota que aparece en el historial.</p>
        <button>Asignar turno</button>
    </form>
</div>

<div class="card">
    <h2>Turnos de la veterinaria</h2>
    
    <?php if (!$rows): ?>
        <p>No hay turnos todavía.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Cliente</th>
                    <th>Mascota</th>
                    <th>Motivo</th>
                    <th>Estado</th>
                    <th>Actualizar</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><?= e($r['appointment_at']) ?></td>
                        <td>
                            <?= e($r['full_name']) ?><br>
                            DNI <?= e($r['dni']) ?>
                        </td>
                        <td><?= e($r['pet_name']) ?></td>
                        <td><?= e($r['reason']) ?></td>
                        <td><?= e($r['status']) ?></td>
                        <td>
                            <form method="post">
                                <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                                <input type="hidden" name="change_status" value="1">
                                <input type="hidden" name="appointment_id" value="<?= (int)$r['id'] ?>">
                                
                                <select name="status">
                                    <?php foreach (['Solicitado', 'Confirmado', 'Cancelado', 'Atendido'] as $st): ?>
                                        <option <?= $r['status'] === $st ? 'selected' : '' ?>>
                                            <?= e($st) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                
                                <button>Guardar</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>