<?php
require_once __DIR__ . '/../config/config.php';
require_vet();
csrf_check();

$cid = (int)$_SESSION['clinic_id'];
$error = '';

// Valores del formulario de nuevo turno (se conservan si hay un error)
$form = ['dni' => '', 'pet_id' => 0, 'appointment_at' => '', 'reason_option' => '', 'reason_other' => ''];

// Fecha y hora mínima para un turno: el minuto actual
$minDateTime = date('Y-m-d\TH:i');

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
    $rawDt = (string)($_POST['appointment_at'] ?? '');
    $reason = appointment_reason_from_post();
    $form = [
        'dni' => $dni, 'pet_id' => $pet, 'appointment_at' => $rawDt,
        'reason_option' => (string)($_POST['reason_option'] ?? ''), 'reason_other' => (string)($_POST['reason_other'] ?? ''),
    ];

    // Validar existencia del cliente
    $q = db()->prepare("SELECT id FROM clients WHERE dni = ?");
    $q->execute([$dni]);
    $client = (int)$q->fetchColumn();

    // Validar que la mascota sea del cliente (propia o como co-titular)
    $q = db()->prepare("
        SELECT p.id FROM pets p
        LEFT JOIN pet_co_owners po ON po.pet_id = p.id AND po.client_id = ?
        WHERE p.id = ? AND (p.client_id = ? OR po.client_id IS NOT NULL)
    ");
    $q->execute([$client, $pet, $client]);
    $petOk = (bool)$q->fetch();

    // Validar fecha y hora: formato del campo datetime-local y no anterior a ahora
    $when = DateTime::createFromFormat('!Y-m-d\TH:i', $rawDt);
    $validDt = $when && $when->format('Y-m-d\TH:i') === $rawDt;
    $dt = $validDt ? $when->format('Y-m-d H:i:00') : '';

    if (!$client) {
        $error = 'No hay ningún cliente registrado con ese DNI.';
    } elseif (!$petOk) {
        $error = 'Seleccioná una de las mascotas del cliente.';
    } elseif (!$validDt) {
        $error = 'Ingresá una fecha y hora válidas.';
    } elseif ($when < new DateTime(date('Y-m-d H:i:00'))) {
        $error = 'El turno no puede ser en una fecha u hora anterior a la actual.';
    } elseif ($reason === null) {
        $error = 'Elegí el motivo del turno (si elegís "Otro", escribilo).';
    } else {
        // Registrar o actualizar la relación entre la clínica y el cliente
        $q = db()->prepare("INSERT INTO clinic_clients(clinic_id, client_id) VALUES(?, ?) ON DUPLICATE KEY UPDATE last_seen_at = CURRENT_TIMESTAMP");
        $q->execute([$cid, $client]);

        // Guardar el nuevo turno
        $q = db()->prepare("INSERT INTO appointments(clinic_id, client_id, pet_id, requested_by, appointment_at, reason, status) VALUES(?, ?, ?, 'veterinaria', ?, ?, 'Confirmado')");
        $q->execute([$cid, $client, $pet, $dt, $reason]);

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
    <p class="muted">Programá una consulta presencial: buscá al titular por DNI y elegí cuál de sus mascotas atender.</p>
    
    <?php if ($error): ?>
        <div class="notice error">
            <span class="notice-icon"><i class="fa-solid fa-triangle-exclamation"></i></span>
            <span><?= e($error) ?></span>
        </div>
    <?php endif; ?>
    
    <form method="post" id="turnoForm">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">

        <div class="form-grid">
            <div>
                <label for="turnoDni">DNI del cliente</label>
                <div class="turno-dni-row">
                    <input id="turnoDni" name="dni" value="<?= e($form['dni']) ?>" placeholder="Ej. 30123456" inputmode="numeric" autocomplete="off" required>
                    <button type="button" class="btn secondary" id="turnoBuscar">
                        <i class="fa-solid fa-magnifying-glass"></i> Buscar
                    </button>
                </div>
                <!-- aria-live: los lectores de pantalla anuncian el resultado de la búsqueda -->
                <div class="turno-dni-status muted" id="turnoDniStatus" aria-live="polite">Escribí el DNI y tocá Buscar para ver sus mascotas.</div>
            </div>
            <div>
                <label for="turnoPet">Mascota</label>
                <select id="turnoPet" name="pet_id" required disabled data-selected="<?= (int)$form['pet_id'] ?>">
                    <option value="">Primero buscá al cliente por DNI</option>
                </select>
            </div>
            <div>
                <label for="turnoFecha">Fecha y hora</label>
                <input type="datetime-local" id="turnoFecha" name="appointment_at" value="<?= e($form['appointment_at']) ?>" min="<?= e($minDateTime) ?>" required>
            </div>
            <div>
                <?php
                    $motivoSel = $form['reason_option'];
                    $motivoOtro = $form['reason_other'];
                    require __DIR__ . '/../includes/motivo_turno.php';
                ?>
            </div>
        </div>

        <div class="actions">
            <button class="btn primary" type="submit">
                <i class="fa-solid fa-calendar-check" style="margin-right: 6px;"></i> Asignar turno
            </button>
        </div>
    </form>
</div>

<style>
    .turno-dni-row { display: flex; gap: 8px; align-items: stretch; margin-top: 7px; }
    .turno-dni-row input { margin-top: 0; flex: 1; min-width: 0; }
    .turno-dni-row .btn { padding: 0 16px; white-space: nowrap; }
    .turno-dni-status { font-size: 12px; margin-top: 6px; min-height: 16px; }
    .turno-dni-status.ok { color: var(--green); font-weight: 700; }
    .turno-dni-status.error { color: var(--red); }
    #turnoPet:disabled { opacity: .6; cursor: not-allowed; }
</style>

<script>
(function () {
    const API_PETS = <?= json_encode(url('turnos/api_mascotas_cliente.php')) ?>;
    const CSRF     = <?= json_encode(csrf_token()) ?>;

    const form     = document.getElementById('turnoForm');
    const dniInput = document.getElementById('turnoDni');
    const btn      = document.getElementById('turnoBuscar');
    const statusEl = document.getElementById('turnoDniStatus');
    const petSel   = document.getElementById('turnoPet');
    const dateIn   = document.getElementById('turnoFecha');
    let lastDni = null;

    function setStatus(msg, kind) {
        statusEl.textContent = msg;
        statusEl.className = 'turno-dni-status ' + (kind || 'muted');
    }

    function resetPets(placeholder) {
        petSel.innerHTML = '';
        petSel.add(new Option(placeholder, ''));
        petSel.disabled = true;
    }

    async function buscarMascotas() {
        const dni = dniInput.value.trim();
        if (!dni) {
            setStatus('Ingresá el DNI del cliente.', 'error');
            dniInput.focus();
            return;
        }
        if (dni === lastDni) { return; }
        lastDni = dni;

        resetPets('Buscando…');
        setStatus('Buscando cliente…');
        btn.disabled = true;

        try {
            const fd = new FormData();
            fd.append('csrf', CSRF);
            fd.append('dni', dni);
            const resp = await fetch(API_PETS, { method: 'POST', body: fd, credentials: 'same-origin' });
            let json = null;
            try { json = await resp.json(); } catch (e) { /* respuesta no JSON */ }
            if (!json) { throw new Error('Respuesta inesperada del servidor.'); }
            if (!json.ok) { throw new Error(json.error || 'Error desconocido.'); }
            if (dniInput.value.trim() !== dni) {   // el DNI cambió mientras buscábamos
                lastDni = null;
                resetPets('Primero buscá al cliente por DNI');
                setStatus('Tocá Buscar para ver las mascotas de este DNI.');
                return;
            }

            if (!json.pets.length) {
                resetPets('El cliente no tiene mascotas');
                setStatus(json.client.name + ' no tiene mascotas registradas.', 'error');
                return;
            }

            const wanted = petSel.dataset.selected;
            petSel.innerHTML = '';
            if (json.pets.length > 1) { petSel.add(new Option('Seleccioná una mascota', '')); }
            json.pets.forEach(function (p) {
                let label = p.name + ' — ' + p.animal_type + (p.breed ? ' (' + p.breed + ')' : '');
                if (p.co_owner) { label += ' · co-titular'; }
                petSel.add(new Option(label, p.id, false, String(p.id) === wanted));
            });
            petSel.dataset.selected = '';
            petSel.disabled = false;

            const n = json.pets.length;
            setStatus('✓ ' + json.client.name + ' · ' + n + ' mascota' + (n === 1 ? '' : 's'), 'ok');
        } catch (err) {
            lastDni = null;
            resetPets('Primero buscá al cliente por DNI');
            setStatus(err.message, 'error');
        } finally {
            btn.disabled = false;
        }
    }

    btn.addEventListener('click', buscarMascotas);
    dniInput.addEventListener('change', buscarMascotas);
    dniInput.addEventListener('keydown', function (ev) {
        // Enter en el DNI busca las mascotas en vez de enviar el formulario.
        if (ev.key === 'Enter') { ev.preventDefault(); buscarMascotas(); }
    });
    dniInput.addEventListener('input', function () {
        if (dniInput.value.trim() !== lastDni && !petSel.disabled) {
            lastDni = null;
            resetPets('Primero buscá al cliente por DNI');
            setStatus('Tocá Buscar para ver las mascotas de este DNI.');
        }
    });

    // Fecha mínima = este minuto, según el reloj de la computadora.
    function pad(n) { return String(n).padStart(2, '0'); }
    function nowLocal() {
        const d = new Date();
        return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) + 'T' + pad(d.getHours()) + ':' + pad(d.getMinutes());
    }
    function checkDate() {
        dateIn.min = nowLocal();
        dateIn.setCustomValidity(dateIn.value && dateIn.value < dateIn.min
            ? 'El turno no puede ser en una fecha u hora anterior a la actual.' : '');
    }
    dateIn.addEventListener('focus', checkDate);
    dateIn.addEventListener('input', checkDate);

    form.addEventListener('submit', function (ev) {
        checkDate();
        if (petSel.disabled) {
            ev.preventDefault();
            setStatus('Buscá al cliente por DNI y elegí una mascota.', 'error');
            dniInput.focus();
            return;
        }
        if (!form.checkValidity()) {
            ev.preventDefault();
            form.reportValidity();
        }
    });

    // Si volvimos con un error, recargar las mascotas del DNI que ya estaba escrito.
    if (dniInput.value.trim()) { buscarMascotas(); }
})();
</script>

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
