<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/geocoding.php';
require_admin();
csrf_check();

$clinicId = (int)($_GET['id'] ?? $_POST['clinic_id'] ?? 0);
$clinicQuery = db()->prepare('SELECT * FROM clinics WHERE id = ?');
$clinicQuery->execute([$clinicId]);
$clinic = $clinicQuery->fetch();
if (!$clinic) {
    flash('La veterinaria no existe.');
    header('Location: ' . url('admin/veterinarias.php'));
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    try {
        if ($action === 'update_stock') {
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
            
            // Conserva las consultas clínicas y elimina solamente su vínculo al insumo borrado
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

// Usuarios veterinarios de la clínica
$usersQuery = db()->prepare('SELECT id, username, email, active FROM vet_users WHERE clinic_id = ? ORDER BY id');
$usersQuery->execute([$clinicId]);
$users = $usersQuery->fetchAll();

// Stock de medicamentos
$stockQuery = db()->prepare('SELECT * FROM medications WHERE clinic_id = ? ORDER BY name');
$stockQuery->execute([$clinicId]);
$stock = $stockQuery->fetchAll();

// Archivos adjuntos de consultas de esta clínica
$filesQuery = db()->prepare('
    SELECT f.id, f.uploaded_by, f.original_name, f.mime_type, f.created_at, 
           p.name AS pet_name, cl.full_name AS client_name, cl.dni AS client_dni, co.id AS consultation_id 
    FROM consultation_files f 
    JOIN consultations co ON co.id = f.consultation_id 
    JOIN pets p ON p.id = co.pet_id 
    JOIN clients cl ON cl.id = p.client_id 
    WHERE co.clinic_id = ? 
    ORDER BY f.created_at DESC
');
$filesQuery->execute([$clinicId]);
$files = $filesQuery->fetchAll();

$isActive = (bool)$clinic['active'];
$fullAddress = clinic_full_address($clinic);

$pageTitle = 'Información de Veterinaria | ' . $clinic['name'];
require __DIR__ . '/../includes/header.php';
?>

<div class="panel">
    <div style="display: flex; justify-content: space-between; align-items: center; gap: 16px; flex-wrap: wrap;">
        <div>
            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 6px;">
                <span class="pill" style="font-family: monospace; font-weight: 700;">#<?= (int)$clinic['id'] ?></span>
                <span class="badge <?= $isActive ? 'badge-success' : 'badge-danger' ?>">
                    <i class="fa-solid <?= $isActive ? 'fa-check' : 'fa-ban' ?>" style="margin-right: 4px;"></i>
                    <?= $isActive ? 'Clínica Activa' : 'Suspendida' ?>
                </span>
            </div>
            <h1 style="margin: 0; font-size: 28px;" class="name-titlecase">
                <i class="fa-solid fa-hospital" style="color: var(--green); margin-right: 8px;"></i><?= e_name($clinic['name']) ?>
            </h1>
        </div>
        <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
            <a class="btn primary" href="<?= url('admin/editar_veterinaria.php?id=' . (int)$clinic['id']) ?>">
                <i class="fa-solid fa-pen-to-square" style="margin-right: 6px;"></i> Editar veterinaria
            </a>
            <a class="btn secondary" href="<?= url('admin/veterinarias.php') ?>">
                <i class="fa-solid fa-arrow-left" style="margin-right: 6px;"></i> Volver al listado
            </a>
        </div>
    </div>
</div>

<?php if ($error): ?>
    <div class="notice error" style="margin-top: 18px;"><?= e($error) ?></div>
<?php endif; ?>

<!-- 1. Todos los Datos Generales de la Veterinaria -->
<div class="card" style="margin-top: 24px;">
    <h2 style="margin-top: 0; margin-bottom: 16px; font-size: 20px;">
        <i class="fa-solid fa-circle-info" style="color: var(--green); margin-right: 8px;"></i>Datos generales de la clínica
    </h2>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 18px;">
        <div style="background: var(--paper); padding: 14px 16px; border-radius: 12px; border: 1px solid var(--line);">
            <span class="muted" style="font-size: 12px; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px;">Nombre comercial</span>
            <div style="font-size: 16px; font-weight: 700; color: var(--ink); margin-top: 4px;"><?= e_name($clinic['name']) ?></div>
        </div>

        <div style="background: var(--paper); padding: 14px 16px; border-radius: 12px; border: 1px solid var(--line);">
            <span class="muted" style="font-size: 12px; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px;">Identificador del sistema</span>
            <div style="font-size: 16px; font-weight: 700; font-family: monospace; color: var(--ink); margin-top: 4px;">ID #<?= (int)$clinic['id'] ?></div>
        </div>

        <div style="background: var(--paper); padding: 14px 16px; border-radius: 12px; border: 1px solid var(--line);">
            <span class="muted" style="font-size: 12px; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px;">CUIT</span>
            <div style="font-size: 16px; font-weight: 600; color: var(--ink); margin-top: 4px;"><?= e($clinic['cuit'] ?: 'No registrado') ?></div>
        </div>

        <div style="background: var(--paper); padding: 14px 16px; border-radius: 12px; border: 1px solid var(--line);">
            <span class="muted" style="font-size: 12px; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px;">Correo electrónico de contacto</span>
            <div style="font-size: 15px; font-weight: 600; color: var(--ink); margin-top: 4px;">
                <a href="mailto:<?= e($clinic['email']) ?>" style="color: var(--green); text-decoration: underline;"><?= e($clinic['email']) ?></a>
            </div>
        </div>

        <div style="background: var(--paper); padding: 14px 16px; border-radius: 12px; border: 1px solid var(--line);">
            <span class="muted" style="font-size: 12px; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px;">Teléfono de contacto</span>
            <div style="font-size: 16px; font-weight: 600; color: var(--ink); margin-top: 4px;"><?= e($clinic['phone'] ?: 'No registrado') ?></div>
        </div>

        <div style="background: var(--paper); padding: 14px 16px; border-radius: 12px; border: 1px solid var(--line);">
            <span class="muted" style="font-size: 12px; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px;">Días de atención</span>
            <div style="font-size: 15px; font-weight: 600; color: var(--ink); margin-top: 4px;"><?= e($clinic['available_days'] ?: 'No especificados') ?></div>
        </div>

        <div style="background: var(--paper); padding: 14px 16px; border-radius: 12px; border: 1px solid var(--line);">
            <span class="muted" style="font-size: 12px; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px;">Horario de atención</span>
            <div style="font-size: 15px; font-weight: 600; color: var(--ink); margin-top: 4px;">
                <?= $clinic['opening_time'] ? substr($clinic['opening_time'], 0, 5) : '--:--' ?> a <?= $clinic['closing_time'] ? substr($clinic['closing_time'], 0, 5) : '--:--' ?>
            </div>
        </div>

        <div style="background: var(--paper); padding: 14px 16px; border-radius: 12px; border: 1px solid var(--line); grid-column: 1 / -1;">
            <span class="muted" style="font-size: 12px; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px;">Dirección completa</span>
            <div style="font-size: 16px; font-weight: 600; color: var(--ink); margin-top: 4px;">
                <i class="fa-solid fa-location-dot" style="color: var(--green); margin-right: 6px;"></i>
                <?= $fullAddress !== '' ? e($fullAddress) : '<span class="muted">Sin dirección especificada</span>' ?>
            </div>
            <div style="font-size: 13px; color: var(--muted); margin-top: 4px;">
                Provincia: <strong><?= e($clinic['province']) ?></strong> · Ciudad: <strong><?= e($clinic['city']) ?></strong> · Calle: <strong><?= e($clinic['street']) ?></strong>
            </div>
        </div>
    </div>
</div>

<!-- 2. Personal Veterinario y Usuarios de la Clínica -->
<div class="card" style="margin-top: 24px;">
    <div style="display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; margin-bottom: 16px;">
        <h2 style="margin: 0; font-size: 20px;">
            <i class="fa-solid fa-user-doctor" style="color: var(--green); margin-right: 8px;"></i>Usuarios veterinarios asignados (<?= count($users) ?>)
        </h2>
        <a class="btn secondary btn-sm" href="<?= url('admin/editar_veterinaria.php?id=' . (int)$clinic['id']) ?>">
            <i class="fa-solid fa-user-gear" style="margin-right: 4px;"></i> Administrar usuarios
        </a>
    </div>

    <?php if (!$users): ?>
        <p class="muted">Esta veterinaria aún no tiene usuarios veterinarios asignados.</p>
    <?php else: ?>
        <div style="overflow-x: auto;">
            <table>
                <thead>
                    <tr>
                        <th style="width: 70px;">ID</th>
                        <th>Nombre de usuario</th>
                        <th>Correo de login</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td><span class="pill" style="font-family: monospace; font-weight: 700;">#<?= (int)$u['id'] ?></span></td>
                            <td><strong><?= e_name($u['username']) ?></strong></td>
                            <td><?= e($u['email']) ?></td>
                            <td>
                                <span class="badge <?= (bool)$u['active'] ? 'badge-success' : 'badge-danger' ?>">
                                    <?= (bool)$u['active'] ? 'Activo' : 'Inactivo' ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- 3. Stock e Insumos Propios de la Veterinaria -->
<div class="card" style="margin-top: 24px;">
    <div style="display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap;">
        <h2><i class="fa-solid fa-boxes-stacked" style="color: var(--green); margin-right: 8px;"></i>Stock e insumos de la clínica (<?= count($stock) ?>)</h2>
        <input type="search" class="support-search" data-target="stock-table" placeholder="Buscar por nombre de insumo..." aria-label="Buscar insumos por nombre" style="max-width: 320px;">
    </div>
    <?php if (!$stock): ?>
        <p class="muted" style="margin-top: 12px;">No hay insumos cargados en el inventario de esta clínica.</p>
    <?php else: ?>
        <div style="overflow-x: auto; margin-top: 14px;">
            <table id="stock-table" style="min-width: 720px;">
                <thead>
                    <tr>
                        <th style="width: 80px;">ID</th>
                        <th>Insumo / Medicamento</th>
                        <th style="width: 140px;">Cantidad</th>
                        <th style="width: 110px;">Guardar</th>
                        <th style="width: 110px;">Eliminar</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stock as $item): ?>
                        <tr data-search="<?= e(mb_strtolower($item['name'])) ?>">
                            <td>#<?= (int)$item['id'] ?></td>
                            <td>
                                <form id="stock-<?= (int)$item['id'] ?>" method="post" style="display: none;">
                                    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                                    <input type="hidden" name="clinic_id" value="<?= $clinicId ?>">
                                    <input type="hidden" name="medication_id" value="<?= (int)$item['id'] ?>">
                                    <input type="hidden" name="action" value="update_stock">
                                </form>
                                <input form="stock-<?= (int)$item['id'] ?>" name="name" value="<?= e($item['name']) ?>" maxlength="180" required style="width: 100%; min-width: 0; margin: 0;">
                            </td>
                            <td>
                                <input form="stock-<?= (int)$item['id'] ?>" type="number" min="0" name="quantity" value="<?= (int)$item['quantity'] ?>" required style="width: 100%; min-width: 0; margin: 0;">
                            </td>
                            <td>
                                <button form="stock-<?= (int)$item['id'] ?>" class="btn primary btn-sm" type="submit">
                                    <i class="fa-solid fa-floppy-disk"></i> Guardar
                                </button>
                            </td>
                            <td>
                                <form method="post" onsubmit="return confirm('¿Eliminar este insumo del inventario? El historial clínico se conservará.');">
                                    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                                    <input type="hidden" name="clinic_id" value="<?= $clinicId ?>">
                                    <input type="hidden" name="medication_id" value="<?= (int)$item['id'] ?>">
                                    <input type="hidden" name="action" value="delete_stock">
                                    <button class="btn secondary btn-sm" type="submit">
                                        <i class="fa-solid fa-trash"></i> Eliminar
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- 4. Archivos Adjuntos a Consultas en esta Clínica -->
<div class="card" style="margin-top: 24px;">
    <div style="display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap;">
        <div>
            <h2 style="margin: 0;"><i class="fa-solid fa-paperclip" style="color: var(--green); margin-right: 8px;"></i>Archivos adjuntos en consultas (<?= count($files) ?>)</h2>
            <p class="muted" style="margin: 4px 0 0;">Documentos y estudios cargados en las consultas atendidas en esta clínica.</p>
        </div>
        <input type="search" class="support-search" data-target="files-table" placeholder="Buscar por DNI o paciente..." aria-label="Buscar archivos" style="max-width: 320px;">
    </div>

    <?php if (!$files): ?>
        <p class="muted" style="margin-top: 14px;">Todavía no hay archivos adjuntos en las consultas de esta veterinaria.</p>
    <?php else: ?>
        <div style="overflow-x: auto; margin-top: 14px;">
            <table id="files-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Archivo</th>
                        <th>Tutor</th>
                        <th>Mascota</th>
                        <th>Consulta</th>
                        <th>Subido por</th>
                        <th>Fecha</th>
                        <th>Acceso</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($files as $file): ?>
                        <tr data-search="<?= e(mb_strtolower($file['client_dni'] . ' ' . $file['client_name'] . ' ' . $file['pet_name'])) ?>">
                            <td>#<?= (int)$file['id'] ?></td>
                            <td>
                                <i class="fa-solid <?= $file['mime_type'] === 'application/pdf' ? 'fa-file-pdf' : 'fa-file-image' ?>" style="color: var(--green); margin-right: 4px;"></i>
                                <?= e($file['original_name']) ?>
                                <div class="muted" style="font-size: 12px;"><?= e($file['mime_type']) ?></div>
                            </td>
                            <td><?= e_name($file['client_name']) ?> (<?= e($file['client_dni']) ?>)</td>
                            <td><?= e_name($file['pet_name']) ?></td>
                            <td>#<?= (int)$file['consultation_id'] ?></td>
                            <td><?= $file['uploaded_by'] === 'cliente' ? 'Tutor' : 'Veterinaria' ?></td>
                            <td><?= e($file['created_at']) ?></td>
                            <td>
                                <a class="btn secondary btn-sm" href="<?= url('archivo_consulta.php?id=' . (int)$file['id']) ?>" target="_blank" rel="noopener">
                                    <i class="fa-solid fa-download"></i> Abrir
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
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
