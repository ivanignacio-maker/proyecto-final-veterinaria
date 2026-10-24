<?php
require_once __DIR__ . '/../config/config.php';
require_admin();
csrf_check();

$clinicId = (int)($_GET['id'] ?? $_POST['clinic_id'] ?? 0);
$clinicQuery = db()->prepare('SELECT * FROM clinics WHERE id = ?');
$clinicQuery->execute([$clinicId]);
$clinic = $clinicQuery->fetch();
if (!$clinic) {
    http_response_code(404);
    exit('La veterinaria no existe.');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    try {
        if ($action === 'update_client') {
            $clientId = (int)($_POST['client_id'] ?? 0);
            $dni = trim((string)($_POST['dni'] ?? ''));
            $name = format_name(trim((string)($_POST['full_name'] ?? '')));
            $email = strtolower(trim((string)($_POST['email'] ?? '')));
            $phone = trim((string)($_POST['phone'] ?? ''));
            $membership = db()->prepare('SELECT 1 FROM clinic_clients WHERE clinic_id = ? AND client_id = ?');
            $membership->execute([$clinicId, $clientId]);
            if (!$membership->fetchColumn()) {
                throw new RuntimeException('El tutor no pertenece a esta veterinaria.');
            }
            if ($dni === '' || $name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Completá DNI, nombre y un correo válido.');
            }
            $save = db()->prepare('UPDATE clients SET dni = ?, full_name = ?, email = ?, phone = ? WHERE id = ?');
            $save->execute([$dni, $name, $email, $phone, $clientId]);
            flash('Datos del tutor actualizados.');
        } elseif ($action === 'update_pet') {
            $petId = (int)($_POST['pet_id'] ?? 0);
            $name = format_name(trim((string)($_POST['name'] ?? '')));
            $type = format_name(trim((string)($_POST['animal_type'] ?? '')));
            $breed = trim((string)($_POST['breed'] ?? ''));
            $weight = trim((string)($_POST['weight_kg'] ?? ''));
            $height = trim((string)($_POST['height_cm'] ?? ''));
            $birthDate = trim((string)($_POST['birth_date'] ?? ''));
            $belongs = db()->prepare('SELECT p.id FROM pets p JOIN clinic_clients cc ON cc.client_id = p.client_id WHERE p.id = ? AND cc.clinic_id = ?');
            $belongs->execute([$petId, $clinicId]);
            if (!$belongs->fetchColumn()) {
                throw new RuntimeException('La mascota no está vinculada a esta veterinaria.');
            }
            if ($name === '' || $type === '' || ($weight !== '' && (!is_numeric($weight) || (float)$weight < 0)) || ($height !== '' && (!is_numeric($height) || (float)$height < 0)) || ($birthDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $birthDate))) {
                throw new RuntimeException('Revisá los datos de la mascota.');
            }
            $save = db()->prepare('UPDATE pets SET name = ?, animal_type = ?, breed = ?, weight_kg = ?, height_cm = ?, birth_date = ? WHERE id = ?');
            $save->execute([$name, $type, $breed ?: null, $weight !== '' ? $weight : null, $height !== '' ? $height : null, $birthDate !== '' ? $birthDate : null, $petId]);
            flash('Datos de la mascota actualizados.');
        } elseif ($action === 'update_stock') {
            $medicationId = (int)($_POST['medication_id'] ?? 0);
            $name = trim((string)($_POST['name'] ?? ''));
            $quantity = filter_var($_POST['quantity'] ?? null, FILTER_VALIDATE_INT);
            if ($name === '' || mb_strlen($name) > 180 || $quantity === false || $quantity < 0) {
                throw new RuntimeException('Indicá un nombre y una cantidad válida para el stock.');
            }
            $save = db()->prepare('UPDATE medications SET name = ?, quantity = ? WHERE id = ? AND clinic_id = ?');
            $save->execute([$name, $quantity, $medicationId, $clinicId]);
            if (!$save->rowCount()) {
                $exists = db()->prepare('SELECT 1 FROM medications WHERE id = ? AND clinic_id = ?');
                $exists->execute([$medicationId, $clinicId]);
                if (!$exists->fetchColumn()) throw new RuntimeException('El insumo no pertenece a esta veterinaria.');
            }
            flash('Stock actualizado.');
        } elseif ($action === 'delete_stock') {
            $medicationId = (int)($_POST['medication_id'] ?? 0);
            db()->beginTransaction();
            $exists = db()->prepare('SELECT id FROM medications WHERE id = ? AND clinic_id = ? FOR UPDATE');
            $exists->execute([$medicationId, $clinicId]);
            if (!$exists->fetchColumn()) throw new RuntimeException('El insumo no pertenece a esta veterinaria.');
            // Conserva las consultas clínicas y elimina solamente su vínculo al insumo borrado.
            $unlink = db()->prepare('UPDATE consultation_medications cm JOIN consultations c ON c.id = cm.consultation_id SET cm.medication_id = NULL WHERE cm.medication_id = ? AND c.clinic_id = ?');
            $unlink->execute([$medicationId, $clinicId]);
            $delete = db()->prepare('DELETE FROM medications WHERE id = ? AND clinic_id = ?');
            $delete->execute([$medicationId, $clinicId]);
            db()->commit();
            flash('Insumo eliminado del inventario. El historial clínico se conservó.');
        } else {
            throw new RuntimeException('Acción no reconocida.');
        }
        header('Location: ' . url('admin/soporte_veterinaria.php?id=' . $clinicId));
        exit;
    } catch (Throwable $exception) {
        if (db()->inTransaction()) db()->rollBack();
        $error = $exception instanceof RuntimeException ? $exception->getMessage() : 'No se pudieron guardar los cambios. Verificá los datos e intentá nuevamente.';
        if (!$exception instanceof RuntimeException) error_log('Soporte admin veterinaria: ' . $exception->getMessage());
    }
}

$clientQuery = db()->prepare('SELECT cl.* FROM clients cl JOIN clinic_clients cc ON cc.client_id = cl.id WHERE cc.clinic_id = ? ORDER BY cl.full_name');
$clientQuery->execute([$clinicId]);
$clients = $clientQuery->fetchAll();
$petQuery = db()->prepare('SELECT p.*, cl.full_name AS client_name, cl.dni AS client_dni FROM pets p JOIN clinic_clients cc ON cc.client_id = p.client_id JOIN clients cl ON cl.id = p.client_id WHERE cc.clinic_id = ? ORDER BY cl.full_name, p.name');
$petQuery->execute([$clinicId]);
$pets = $petQuery->fetchAll();
$stockQuery = db()->prepare('SELECT * FROM medications WHERE clinic_id = ? ORDER BY name');
$stockQuery->execute([$clinicId]);
$stock = $stockQuery->fetchAll();
$filesQuery = db()->prepare('SELECT f.id, f.uploaded_by, f.original_name, f.mime_type, f.created_at, p.name AS pet_name, cl.full_name AS client_name, cl.dni AS client_dni, co.id AS consultation_id FROM consultation_files f JOIN consultations co ON co.id = f.consultation_id JOIN pets p ON p.id = co.pet_id JOIN clients cl ON cl.id = p.client_id WHERE co.clinic_id = ? ORDER BY f.created_at DESC');
$filesQuery->execute([$clinicId]);
$files = $filesQuery->fetchAll();

$pageTitle = 'Soporte | ' . $clinic['name'];
require __DIR__ . '/../includes/header.php';
?>

<div class="panel">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap;">
        <div>
            <span class="pill"><i class="fa-solid fa-headset"></i> Vista de soporte</span>
            <h1 style="margin:10px 0 4px;"><?= e_name($clinic['name']) ?></h1>
        </div>
        <a class="btn secondary" href="<?= url('admin/veterinarias.php') ?>"><i class="fa-solid fa-arrow-left"></i> Volver al listado</a>
    </div>
</div>

<?php if ($error): ?><div class="notice error" style="margin-top:18px;"><?= e($error) ?></div><?php endif; ?>

<div class="card" style="margin-top:24px;">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
        <h2><i class="fa-solid fa-users" style="color:var(--green);"></i> Tutores (<?= count($clients) ?>)</h2>
        <input type="search" class="support-search" data-target="clients-table" placeholder="Buscar por DNI o nombre del tutor" aria-label="Buscar tutores por DNI o nombre" style="max-width:360px;">
    </div>
    <?php if (!$clients): ?><p class="muted">Esta veterinaria todavía no tiene tutores asociados.</p><?php else: ?>
    <div style="overflow-x:auto;"><table id="clients-table">
        <thead><tr><th>ID</th><th>DNI</th><th>Nombre</th><th>Correo</th><th>Teléfono</th><th>Guardar cambios</th></tr></thead>
        <tbody><?php foreach ($clients as $client): ?>
        <tr data-search="<?= e(mb_strtolower($client['dni'] . ' ' . $client['full_name'])) ?>">
            <td>#<?= (int)$client['id'] ?></td>
            <td><form id="client-<?= (int)$client['id'] ?>" method="post"><input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="clinic_id" value="<?= $clinicId ?>"><input type="hidden" name="client_id" value="<?= (int)$client['id'] ?>"><input type="hidden" name="action" value="update_client"></form><input form="client-<?= (int)$client['id'] ?>" name="dni" value="<?= e($client['dni']) ?>" required style="min-width:110px;"></td>
            <td><input form="client-<?= (int)$client['id'] ?>" name="full_name" value="<?= e($client['full_name']) ?>" required style="min-width:170px;"></td>
            <td><input form="client-<?= (int)$client['id'] ?>" type="email" name="email" value="<?= e($client['email']) ?>" required style="min-width:190px;"></td>
            <td><input form="client-<?= (int)$client['id'] ?>" name="phone" value="<?= e($client['phone']) ?>" style="min-width:130px;"></td>
            <td><button form="client-<?= (int)$client['id'] ?>" class="btn primary btn-sm" type="submit"><i class="fa-solid fa-floppy-disk"></i> Guardar</button></td>
        </tr><?php endforeach; ?></tbody>
    </table></div><?php endif; ?>
</div>

<div class="card" style="margin-top:24px;">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
        <h2><i class="fa-solid fa-paw" style="color:var(--green);"></i> Mascotas vinculadas (<?= count($pets) ?>)</h2>
        <input type="search" class="support-search" data-target="pets-table" placeholder="Buscar por DNI o nombre del tutor" aria-label="Buscar mascotas por DNI o nombre del tutor" style="max-width:360px;">
    </div>
    <?php if (!$pets): ?><p class="muted">No hay mascotas vinculadas a tutores de esta veterinaria.</p><?php else: ?>
    <div style="overflow-x:auto;"><table id="pets-table">
        <thead><tr><th>ID mascota</th><th>Tutor</th><th>Nombre</th><th>Especie</th><th>Raza</th><th>Peso kg</th><th>Altura cm</th><th>Nacimiento</th><th>Acción</th></tr></thead>
        <tbody><?php foreach ($pets as $pet): ?>
        <tr data-search="<?= e(mb_strtolower($pet['client_dni'] . ' ' . $pet['client_name'])) ?>">
            <td>#<?= (int)$pet['id'] ?></td>
            <td><form id="pet-<?= (int)$pet['id'] ?>" method="post"><input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="clinic_id" value="<?= $clinicId ?>"><input type="hidden" name="pet_id" value="<?= (int)$pet['id'] ?>"><input type="hidden" name="action" value="update_pet"></form><?= e_name($pet['client_name']) ?></td>
            <td><input form="pet-<?= (int)$pet['id'] ?>" name="name" value="<?= e($pet['name']) ?>" required style="min-width:110px;"></td>
            <td><input form="pet-<?= (int)$pet['id'] ?>" name="animal_type" value="<?= e($pet['animal_type']) ?>" required style="min-width:100px;"></td>
            <td><input form="pet-<?= (int)$pet['id'] ?>" name="breed" value="<?= e($pet['breed'] ?? '') ?>" style="min-width:110px;"></td>
            <td><input form="pet-<?= (int)$pet['id'] ?>" type="number" min="0" step="0.01" name="weight_kg" value="<?= e((string)($pet['weight_kg'] ?? '')) ?>" style="width:90px;"></td>
            <td><input form="pet-<?= (int)$pet['id'] ?>" type="number" min="0" step="0.01" name="height_cm" value="<?= e((string)($pet['height_cm'] ?? '')) ?>" style="width:90px;"></td>
            <td><input form="pet-<?= (int)$pet['id'] ?>" type="date" name="birth_date" value="<?= e($pet['birth_date'] ?? '') ?>"></td>
            <td><button form="pet-<?= (int)$pet['id'] ?>" class="btn primary btn-sm" type="submit"><i class="fa-solid fa-floppy-disk"></i> Guardar</button></td>
        </tr><?php endforeach; ?></tbody>
    </table></div><?php endif; ?>
</div>

<div class="card" style="margin-top:24px;">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
        <h2><i class="fa-solid fa-boxes-stacked" style="color:var(--green);"></i> Stock e insumos (<?= count($stock) ?>)</h2>
        <input type="search" class="support-search" data-target="stock-table" placeholder="Buscar por nombre de insumo" aria-label="Buscar insumos por nombre" style="max-width:320px;">
    </div>
    <?php if (!$stock): ?><p class="muted">No hay insumos cargados en el inventario.</p><?php else: ?>
    <div style="overflow-x:auto;"><table id="stock-table" style="min-width:760px;table-layout:fixed;">
        <colgroup><col style="width:70px;"><col style="width:150px;"><col style="width:120px;"><col style="width:120px;"><col style="width:120px;"></colgroup>
        <thead><tr><th>ID</th><th>Insumo</th><th>Cantidad</th><th>Guardar</th><th>Eliminar</th></tr></thead>
        <tbody><?php foreach ($stock as $item): ?>
        <tr data-search="<?= e(mb_strtolower($item['name'])) ?>">
            <td>#<?= (int)$item['id'] ?></td>
            <td><form id="stock-<?= (int)$item['id'] ?>" method="post" style="display:none;"><input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="clinic_id" value="<?= $clinicId ?>"><input type="hidden" name="medication_id" value="<?= (int)$item['id'] ?>"><input type="hidden" name="action" value="update_stock"></form><input form="stock-<?= (int)$item['id'] ?>" name="name" value="<?= e($item['name']) ?>" maxlength="180" required style="width:100%;min-width:0;margin:0;"></td>
            <td><input form="stock-<?= (int)$item['id'] ?>" type="number" min="0" name="quantity" value="<?= (int)$item['quantity'] ?>" required style="width:100%;min-width:0;margin:0;"></td>
            <td><button form="stock-<?= (int)$item['id'] ?>" class="btn primary btn-sm" type="submit"><i class="fa-solid fa-floppy-disk"></i> Guardar</button></td>
            <td><form method="post" onsubmit="return confirm('¿Eliminar este insumo del inventario? El historial clínico se conservará.');"><input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="clinic_id" value="<?= $clinicId ?>"><input type="hidden" name="medication_id" value="<?= (int)$item['id'] ?>"><input type="hidden" name="action" value="delete_stock"><button class="btn secondary btn-sm" type="submit"><i class="fa-solid fa-trash"></i> Eliminar</button></form></td>
        </tr><?php endforeach; ?></tbody>
    </table></div><?php endif; ?>
</div>

<div class="card" style="margin-top:24px;">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
        <h2><i class="fa-solid fa-paperclip" style="color:var(--green);"></i> Archivos adjuntos (<?= count($files) ?>)</h2>
        <input type="search" class="support-search" data-target="files-table" placeholder="Buscar por DNI o nombre del tutor" aria-label="Buscar archivos por DNI o nombre del tutor" style="max-width:360px;">
    </div>
    <p class="muted">Archivos subidos por tutores o por la veterinaria a las consultas clínicas.</p>
    <?php if (!$files): ?><p class="muted">Todavía no hay archivos adjuntos en las consultas de esta veterinaria.</p><?php else: ?>
    <div style="overflow-x:auto;"><table id="files-table">
        <thead><tr><th>ID archivo</th><th>Archivo</th><th>Tutor</th><th>Mascota</th><th>Consulta</th><th>Subido por</th><th>Fecha</th><th>Acceso</th></tr></thead>
        <tbody><?php foreach ($files as $file): ?>
        <tr data-search="<?= e(mb_strtolower($file['client_dni'] . ' ' . $file['client_name'])) ?>">
            <td>#<?= (int)$file['id'] ?></td>
            <td><i class="fa-solid <?= $file['mime_type'] === 'application/pdf' ? 'fa-file-pdf' : 'fa-file-image' ?>" style="color:var(--green);"></i> <?= e($file['original_name']) ?><div class="muted" style="font-size:12px;"><?= e($file['mime_type']) ?></div></td>
            <td><?= e_name($file['client_name']) ?></td>
            <td><?= e_name($file['pet_name']) ?></td>
            <td>#<?= (int)$file['consultation_id'] ?></td>
            <td><?= $file['uploaded_by'] === 'cliente' ? 'Tutor' : 'Veterinaria' ?></td>
            <td><?= e($file['created_at']) ?></td>
            <td><a class="btn secondary btn-sm" href="<?= url('archivo_consulta.php?id=' . (int)$file['id']) ?>" target="_blank" rel="noopener"><i class="fa-solid fa-download"></i> Abrir</a></td>
        </tr><?php endforeach; ?></tbody>
    </table></div><?php endif; ?>
</div>

<script>
    function normalizeSupportSearch(value) {
        return String(value)
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .toLocaleLowerCase()
            .replace(/\s+/g, ' ')
            .trim();
    }

    document.querySelectorAll('.support-search').forEach(function (input) {
        const table = document.getElementById(input.dataset.target);
        if (!table) return;

        function filterRows() {
            const query = normalizeSupportSearch(input.value);
            table.querySelectorAll('tbody tr').forEach(function (row) {
                const searchableText = normalizeSupportSearch(row.dataset.search || row.textContent);
                row.style.display = searchableText.includes(query) ? '' : 'none';
            });
        }

        input.addEventListener('input', filterRows);
        input.addEventListener('search', filterRows);
    });
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
