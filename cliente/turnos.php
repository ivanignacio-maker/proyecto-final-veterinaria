<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/geocoding.php';   // clinic_full_address()
require_client();
csrf_check();

$clientId = (int)$_SESSION['client_id'];
$error = '';

// 1. Veterinarias activas. En la lista aparecen solo las que el tutor ya visitó; el resto se muestra
//    cuando las busca por nombre o por cercanía (se puede pedir turno en cualquiera de las activas).
$clinics = db()->query("
    SELECT id, name, street, city, province
    FROM clinics
    WHERE active = 1
    ORDER BY name
")->fetchAll();
$clinicIds = array_map(fn($c) => (int)$c['id'], $clinics);

// Veterinarias donde alguna mascota del tutor (propia o compartida) ya tuvo una consulta
$q = db()->prepare("
    SELECT DISTINCT co.clinic_id
    FROM consultations co
    JOIN pets p ON p.id = co.pet_id
    WHERE p.client_id = ?
       OR p.id IN (SELECT pet_id FROM pet_co_owners WHERE client_id = ?)
");
$q->execute([$clientId, $clientId]);
$visitedIds = array_map('intval', $q->fetchAll(PDO::FETCH_COLUMN));

// Profesionales (jefe y doctores activos) de cada veterinaria, para elegir quién atiende
$professionalsByClinic = [];
foreach (db()->query("
    SELECT id, clinic_id, username, role
    FROM vet_users
    WHERE active = 1 AND role IN ('jefe', 'doctor')
    ORDER BY username
")->fetchAll() as $pr) {
    $professionalsByClinic[(int)$pr['clinic_id']][] = [
        'id'   => (int)$pr['id'],
        'name' => format_name($pr['username']),
        'role' => vet_role_label($pr['role']),
    ];
}

// 2. Mascotas del tutor (como dueño principal o co-tutor)
$q = db()->prepare("
    SELECT p.*
    FROM pets p
    WHERE p.client_id = ?
       OR p.id IN (SELECT pet_id FROM pet_co_owners WHERE client_id = ?)
    ORDER BY p.name
");
$q->execute([$clientId, $clientId]);
$pets = $q->fetchAll();

// Veterinaria preseleccionada (viene de "Pedir turno" en Veterinarias cercanas)
$selectedClinic = (int)($_POST['clinic_id'] ?? $_GET['clinic_id'] ?? 0);
if (!in_array($selectedClinic, $clinicIds, true)) {
    $selectedClinic = 0;
}

// 3. Procesar la solicitud de un nuevo turno
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $clinic = (int)($_POST['clinic_id'] ?? 0);
    $pet = (int)($_POST['pet_id'] ?? 0);
    $dt = str_replace('T', ' ', $_POST['appointment_at'] ?? '');
    $reason = appointment_reason_from_post();
    $profId = (int)($_POST['vet_user_id'] ?? 0);   // 0 = sin preferencia
    $profIdsOfClinic = array_column($professionalsByClinic[$clinic] ?? [], 'id');

    if (!in_array($clinic, $clinicIds, true)) {
        $error = 'Elegí una veterinaria de la lista.';
    } elseif (!is_client_pet_owner($clientId, $pet)) {
        $error = 'Elegí una de tus mascotas.';
    } elseif (!$dt || strtotime($dt) === false || strtotime($dt) <= time()) {
        $error = 'Elegí una fecha y hora futuras.';
    } elseif ($reason === null) {
        $error = 'Elegí el motivo del turno (si elegís "Otro", escribilo).';
    } elseif ($profId > 0 && !in_array($profId, $profIdsOfClinic, true)) {
        $error = 'El profesional elegido no atiende en esa veterinaria.';
    } else {
        // Insertar el turno con estado "Solicitado"
        $q = db()->prepare("
            INSERT INTO appointments(clinic_id, client_id, pet_id, vet_user_id, requested_by, appointment_at, reason, status)
            VALUES(?, ?, ?, ?, 'cliente', ?, ?, 'Solicitado')
        ");
        $q->execute([$clinic, $clientId, $pet, $profId > 0 ? $profId : null, $dt, $reason]);

        flash('Solicitud enviada. La veterinaria debe confirmarla.');
        header('Location: ' . url('cliente/turnos.php'));
        exit;
    }
}

// 4. Historial de turnos y solicitudes del cliente
$q = db()->prepare("
    SELECT a.*, cl.name clinic_name, p.name pet_name, vu.username AS prof_name
    FROM appointments a
    JOIN clinics cl ON cl.id = a.clinic_id
    JOIN pets p ON p.id = a.pet_id
    LEFT JOIN vet_users vu ON vu.id = a.vet_user_id
    WHERE a.client_id = ?
    ORDER BY a.appointment_at DESC
");
$q->execute([$clientId]);
$appointments = $q->fetchAll();

$pageTitle = 'Solicitar turno';
require __DIR__ . '/../includes/header.php';
?>

<style>
    .tc-search { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; margin-bottom: 10px; }
    .tc-search input[type="search"], .tc-search input[type="text"] { flex: 1; min-width: 220px; margin: 0; }
    .tc-list { max-height: 300px; overflow-y: auto; border: 1px solid var(--line); border-radius: 14px; padding: 6px; background: var(--white); }
    .tc-item { display: flex; align-items: center; gap: 12px; padding: 10px 12px; border-radius: 10px; cursor: pointer; border: 1px solid transparent; }
    .tc-item:hover { background: var(--paper); }
    .tc-item:has(input:checked) { background: var(--mint); border-color: var(--green); }
    .tc-item input[type="radio"] { width: auto; margin: 0; flex-shrink: 0; }
    .tc-item-name { font-weight: 700; color: var(--ink); }
    .tc-item-addr { font-size: 12.5px; color: var(--muted); }
    .tc-dist { margin-left: auto; font-size: 12px; font-weight: 700; color: var(--green); background: var(--mint); padding: 2px 10px; border-radius: 12px; white-space: nowrap; }
    .tc-item[hidden], .tc-search[hidden], .tc-dist[hidden] { display: none; }
    .tc-tag { margin-left: 6px; font-size: 11px; font-weight: 700; color: var(--green); background: var(--mint); padding: 1px 8px; border-radius: 10px; }
    .tc-status { font-size: 13px; margin: 8px 0 0; }
    .tc-status.error { color: #991b1b; }
</style>

<div class="panel">
    <h1><i class="fa-solid fa-calendar-plus" style="color: var(--green); margin-right: 8px;"></i>Solicitar nuevo turno</h1>
    <p class="muted">Elegí una veterinaria que ya visitaste, o buscá otra de la red por nombre o por cercanía. Después elegí tu mascota, el profesional que preferís, la fecha y el motivo.</p>

    <?php if ($error): ?>
        <div class="notice error">
            <span class="notice-icon"><i class="fa-solid fa-triangle-exclamation"></i></span>
            <span><?= e($error) ?></span>
        </div>
    <?php endif; ?>

    <?php if (!$clinics): ?>
        <div class="notice" style="background: #f7faf7; border: 1px solid var(--line); color: var(--ink);">
            <p style="margin: 0;"><i class="fa-solid fa-circle-info" style="color: var(--green); margin-right: 6px;"></i>Todavía no hay veterinarias activas en la red.</p>
        </div>
    <?php elseif (!$pets): ?>
        <div class="notice" style="background: #f7faf7; border: 1px solid var(--line); color: var(--ink);">
            <p style="margin: 0;"><i class="fa-solid fa-circle-info" style="color: var(--green); margin-right: 6px;"></i>Para solicitar un turno necesitás tener al menos una mascota registrada.
                <a href="<?= url('cliente/agregar_mascota.php') ?>" style="color: var(--green); font-weight: 700;">Registrar mascota</a></p>
        </div>
    <?php else: ?>
        <form method="post">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">

            <h2 style="font-size: 17px; margin: 18px 0 10px;">1. Elegí la veterinaria</h2>

            <div class="tc-search">
                <input type="search" id="clinicFilter" placeholder="Buscar por nombre o localidad…" aria-label="Buscar veterinaria por nombre o localidad" autocomplete="off">
                <button type="button" class="btn secondary" id="btnNear">
                    <i class="fa-solid fa-crosshairs" style="margin-right: 6px;"></i> Ordenar por cercanía
                </button>
            </div>
            <div class="tc-search" id="nearAddressRow" hidden>
                <input type="text" id="nearAddress" maxlength="200" placeholder="O escribí tu dirección o localidad (Ej. Ramos Mejía, Buenos Aires)">
                <button type="button" class="btn secondary" id="btnNearAddress">Buscar cerca de esa dirección</button>
            </div>

            <div class="tc-list" id="clinicList" role="radiogroup" aria-label="Veterinarias">
                <?php foreach ($clinics as $cl): ?>
                    <?php
                        $addr = clinic_full_address($cl);
                        $isVisited = in_array((int)$cl['id'], $visitedIds, true);
                        $isSelected = $selectedClinic === (int)$cl['id'];
                    ?>
                    <label class="tc-item" data-id="<?= (int)$cl['id'] ?>" data-visited="<?= $isVisited ? '1' : '0' ?>"
                           data-search="<?= e(mb_strtolower($cl['name'] . ' ' . $addr)) ?>"
                           <?= ($isVisited || $isSelected) ? '' : 'hidden' ?>>
                        <input type="radio" name="clinic_id" value="<?= (int)$cl['id'] ?>" required
                               <?= $selectedClinic === (int)$cl['id'] ? 'checked' : '' ?>>
                        <span>
                            <span class="tc-item-name name-titlecase"><?= e_name($cl['name']) ?></span>
                            <?php if ($isVisited): ?><span class="tc-tag">Ya la visitaste</span><?php endif; ?><br>
                            <span class="tc-item-addr"><i class="fa-solid fa-location-dot"></i> <?= $addr !== '' ? e($addr) : 'Sin dirección cargada' ?></span>
                        </span>
                        <span class="tc-dist" hidden></span>
                    </label>
                <?php endforeach; ?>
            </div>
            <p class="tc-status muted" id="clinicStatus" aria-live="polite"></p>

            <h2 style="font-size: 17px; margin: 22px 0 10px;">2. Datos del turno</h2>
            <div class="form-grid">
                <div>
                    <label>Mascota</label>
                    <select name="pet_id" required>
                        <?php foreach ($pets as $pet): ?>
                            <option value="<?= (int)$pet['id'] ?>" <?= (int)($_POST['pet_id'] ?? 0) === (int)$pet['id'] ? 'selected' : '' ?>>
                                <?= e_name($pet['name']) ?> (ID #<?= (int)$pet['id'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="profSelect">Profesional que querés que atienda</label>
                    <select name="vet_user_id" id="profSelect" data-selected="<?= (int)($_POST['vet_user_id'] ?? 0) ?>">
                        <option value="0">Sin preferencia (lo asigna la veterinaria)</option>
                    </select>
                </div>

                <div>
                    <label>Fecha y horario deseado</label>
                    <input type="datetime-local" name="appointment_at" required value="<?= e($_POST['appointment_at'] ?? '') ?>">
                </div>

                <div>
                    <?php
                        $motivoSel = (string)($_POST['reason_option'] ?? '');
                        $motivoOtro = (string)($_POST['reason_other'] ?? '');
                        require __DIR__ . '/../includes/motivo_turno.php';
                    ?>
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
                    <th>Profesional</th>
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
                        <td><?= !empty($a['prof_name']) ? '<strong class="name-titlecase">' . e_name($a['prof_name']) . '</strong>' : '<span class="muted">Sin preferencia</span>' ?></td>
                        <td>
                            <span class="badge <?= $statusClass ?>"><?= e($a['status']) ?></span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<script>
(function () {
    const list = document.getElementById('clinicList');
    if (!list) { return; }   // no hay formulario (sin mascotas o sin veterinarias)

    const API_NEAR = <?= json_encode(url('cliente/api_veterinarias_cercanas.php')) ?>;
    const API_GEO  = <?= json_encode(url('cliente/api_geocodificar.php')) ?>;
    const CSRF     = <?= json_encode(csrf_token()) ?>;

    const PROFESSIONALS = <?= json_encode($professionalsByClinic, JSON_UNESCAPED_UNICODE) ?>;   // id de clínica → profesionales

    const filterEl = document.getElementById('clinicFilter');
    const statusEl = document.getElementById('clinicStatus');
    const profSel  = document.getElementById('profSelect');
    const items = Array.from(list.querySelectorAll('.tc-item'));
    const originalOrder = items.slice();   // orden alfabético que manda PHP
    let revealed = new Set();               // veterinarias que trajo la búsqueda por cercanía

    function setStatus(msg, isError) {
        statusEl.textContent = msg;
        statusEl.className = 'tc-status ' + (isError ? 'error' : 'muted');
    }

    // Minúsculas y sin tildes, para que "ramos mejia" encuentre "Ramos Mejía"
    function norm(s) {
        return String(s).normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().trim();
    }

    // Qué veterinarias se ven:
    //  - escribiendo en el buscador: todas las que coincidan (visitadas o no)
    //  - sin texto: las visitadas, las que trajo la búsqueda por cercanía y la elegida
    function applyFilter(silent) {
        const term = norm(filterEl.value);
        let visible = 0;
        items.forEach(function (it) {
            const isChecked = it.querySelector('input').checked;
            const show = term !== ''
                ? norm(it.dataset.search).includes(term)
                : (it.dataset.visited === '1' || revealed.has(it.dataset.id) || isChecked);
            it.hidden = !show;
            if (show) { visible++; }
        });
        if (silent) { return; }
        if (term !== '') {
            setStatus(visible === 0 ? 'Ninguna veterinaria coincide con "' + filterEl.value + '".' : visible + ' resultado' + (visible === 1 ? '' : 's') + '.', visible === 0);
        } else if (visible === 0) {
            setStatus('Todavía no visitaste ninguna veterinaria: buscá una por nombre o tocá "Ordenar por cercanía".', false);
        } else if (!list.dataset.sortedByDistance) {
            setStatus('Se muestran las veterinarias que ya visitaste. Buscá por nombre o por cercanía para ver otras.', false);
        }
    }
    filterEl.addEventListener('input', function () { applyFilter(false); });

    // ---- Profesionales de la veterinaria elegida ----
    function refreshProfessionals() {
        const checked = list.querySelector('input:checked');
        const wanted = profSel.dataset.selected || profSel.value;
        profSel.innerHTML = '';
        profSel.add(new Option('Sin preferencia (lo asigna la veterinaria)', '0'));
        if (checked) {
            (PROFESSIONALS[checked.value] || []).forEach(function (p) {
                profSel.add(new Option(p.name + ' · ' + p.role, p.id, false, String(p.id) === String(wanted)));
            });
        }
        profSel.dataset.selected = '';
    }
    list.addEventListener('change', refreshProfessionals);
    refreshProfessionals();
    applyFilter(false);

    // ---- Orden por cercanía ----
    async function post(url, fields) {
        const fd = new FormData();
        fd.append('csrf', CSRF);
        Object.keys(fields).forEach(k => fd.append(k, fields[k]));
        const resp = await fetch(url, { method: 'POST', body: fd, credentials: 'same-origin' });
        let json = null;
        try { json = await resp.json(); } catch (e) { /* no era JSON */ }
        if (!json) { throw new Error('Respuesta inesperada del servidor.'); }
        if (!json.ok) { throw new Error(json.error || 'Error desconocido.'); }
        return json;
    }

    async function sortByDistance(lat, lng) {
        setStatus('Buscando las veterinarias más cercanas… (la primera vez puede tardar unos segundos)', false);
        try {
            const data = await post(API_NEAR, { lat: lat, lng: lng });
            const byId = {};
            data.clinics.forEach(c => { byId[c.id] = c; });

            // Primero las que tienen distancia (de menor a mayor), después el resto por nombre
            const withDist = items.filter(it => byId[it.dataset.id])
                .sort((a, b) => byId[a.dataset.id].distance_km - byId[b.dataset.id].distance_km);
            const rest = originalOrder.filter(it => !byId[it.dataset.id]);

            items.forEach(function (it) {
                const badge = it.querySelector('.tc-dist');
                const c = byId[it.dataset.id];
                badge.hidden = !c;
                badge.textContent = c ? c.distance_km.toLocaleString('es-AR') + ' km' : '';
            });
            withDist.concat(rest).forEach(it => list.appendChild(it));
            revealed = new Set(withDist.map(it => it.dataset.id));   // las cercanas aparecen aunque no se hayan visitado
            filterEl.value = '';
            applyFilter(true);
            list.dataset.sortedByDistance = '1';
            list.scrollTop = 0;
            setStatus(withDist.length
                ? 'Ordenadas de la más cercana a la más lejana.'
                : 'No pudimos ubicar en el mapa a ninguna veterinaria; se muestran por nombre.', withDist.length === 0);
        } catch (err) {
            setStatus(err.message, true);
        }
    }

    function useMyLocation() {
        if (!window.isSecureContext || !('geolocation' in navigator)) {
            setStatus('Tu navegador no permite obtener la ubicación en esta conexión. Escribí tu dirección o localidad.', true);
            document.getElementById('nearAddressRow').hidden = false;
            return;
        }
        setStatus('Pidiendo permiso para usar tu ubicación…', false);
        navigator.geolocation.getCurrentPosition(
            pos => sortByDistance(pos.coords.latitude, pos.coords.longitude),
            function () {
                setStatus('No pudimos usar tu ubicación. Escribí tu dirección o localidad.', true);
                document.getElementById('nearAddressRow').hidden = false;
            },
            { enableHighAccuracy: false, timeout: 10000, maximumAge: 60000 }
        );
    }
    document.getElementById('btnNear').addEventListener('click', useMyLocation);

    document.getElementById('btnNearAddress').addEventListener('click', async function () {
        const text = document.getElementById('nearAddress').value.trim();
        if (text.length < 3) { setStatus('Escribí al menos 3 letras de tu dirección o localidad.', true); return; }
        setStatus('Buscando ese lugar…', false);
        try {
            const geo = await post(API_GEO, { q: text });
            await sortByDistance(geo.lat, geo.lng);
        } catch (err) {
            setStatus(err.message, true);
        }
    });

    // Si llegó con una veterinaria elegida (desde el mapa), la mostramos a la vista
    const checked = list.querySelector('input:checked');
    if (checked) { checked.closest('.tc-item').scrollIntoView({ block: 'nearest' }); }
})();
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
