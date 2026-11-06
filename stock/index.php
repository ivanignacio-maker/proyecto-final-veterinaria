<?php
require_once __DIR__ . '/../config/config.php';
require_vet();
csrf_check();

$pdo = db();
$clinicId = (int)$_SESSION['clinic_id'];
$error = '';
$oldName = '';
$oldInitialQuantity = '0';
$oldRestockId = '';
$oldRestockQuantity = '';
$oldSubtractId = '';
$oldSubtractQuantity = '';
$oldDeleteId = '';
$oldEditId = '';
$oldBrand = '';
$oldPurpose = '';

$postedString = static function (string $key): string {
    $value = $_POST[$key] ?? '';
    return is_string($value) ? trim($value) : '';
};
$postedInteger = static function (string $key, int $minimum): int|false {
    $value = $_POST[$key] ?? null;
    if (!is_string($value) && !is_int($value)) return false;
    return filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => $minimum]]);
};

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = $postedString('action');
    $oldName = $postedString('name');
    $oldInitialQuantity = $postedString('initial_quantity');
    $oldRestockId = $postedString('restock_medication_id');
    $oldRestockQuantity = $postedString('restock_quantity');
    $oldSubtractId = $postedString('subtract_medication_id');
    $oldSubtractQuantity = $postedString('subtract_quantity');
    $oldDeleteId = $postedString('delete_medication_id');
    $oldEditId = $postedString('edit_medication_id');
    $oldBrand = $postedString('brand');
    $oldPurpose = $postedString('purpose');

    try {
        if ($action === 'create') {
            $name = trim((string)preg_replace('/\s+/u', ' ', $oldName));
            $quantity = $postedInteger('initial_quantity', 0);
            if ($name === '' || mb_strlen($name) > 180 || $quantity === false || $quantity > 2147483647) {
                throw new InvalidArgumentException('Ingresá un nombre de hasta 180 caracteres y una cantidad inicial válida.');
            }

            $exists = $pdo->prepare('SELECT id FROM medications WHERE clinic_id = ? AND name = ? LIMIT 1');
            $exists->execute([$clinicId, $name]);
            if ($exists->fetchColumn()) {
                throw new InvalidArgumentException('Ese medicamento ya existe. Elegilo en “Agregar unidades” para aumentar su stock.');
            }

            $insert = $pdo->prepare('INSERT INTO medications (clinic_id, name, quantity, last_restock_quantity) VALUES (?, ?, ?, ?)');
            $insert->execute([$clinicId, $name, $quantity, $quantity]);
            $medicationId = (int)$pdo->lastInsertId();
            audit_log('medication', $medicationId, 'created', ['name' => $name, 'initial_quantity' => $quantity]);
            flash('Medicamento agregado al inventario.');
        } elseif ($action === 'restock') {
            $medicationId = $postedInteger('restock_medication_id', 1);
            $quantityToAdd = $postedInteger('restock_quantity', 1);
            if ($medicationId === false || $quantityToAdd === false) {
                throw new InvalidArgumentException('Elegí un medicamento e indicá cuántas unidades querés agregar.');
            }

            $pdo->beginTransaction();
            $find = $pdo->prepare('SELECT id, name, quantity FROM medications WHERE id = ? AND clinic_id = ? FOR UPDATE');
            $find->execute([$medicationId, $clinicId]);
            $medication = $find->fetch();
            if (!$medication) throw new InvalidArgumentException('El medicamento seleccionado no pertenece al stock de esta veterinaria.');
            $previousQuantity = (int)$medication['quantity'];
            if ($quantityToAdd > 2147483647 - $previousQuantity) {
                throw new InvalidArgumentException('La cantidad supera el máximo que puede guardar el inventario.');
            }

            $update = $pdo->prepare('UPDATE medications SET quantity = quantity + ?, last_restock_quantity = ? WHERE id = ? AND clinic_id = ?');
            $update->execute([$quantityToAdd, $quantityToAdd, $medicationId, $clinicId]);
            $newQuantity = $previousQuantity + $quantityToAdd;
            $pdo->commit();
            audit_log('medication', (int)$medicationId, 'restocked', [
                'name' => $medication['name'],
                'previous_quantity' => $previousQuantity,
                'added_quantity' => $quantityToAdd,
                'new_quantity' => $newQuantity,
            ]);
            flash('Se agregaron ' . $quantityToAdd . ' unidades de ' . $medication['name'] . '.');
        } elseif ($action === 'subtract') {
            $medicationId = $postedInteger('subtract_medication_id', 1);
            $quantityToSubtract = $postedInteger('subtract_quantity', 1);
            if ($medicationId === false || $quantityToSubtract === false) {
                throw new InvalidArgumentException('Elegí un medicamento e indicá cuántas unidades querés restar.');
            }

            $pdo->beginTransaction();
            $find = $pdo->prepare('SELECT id, name, quantity FROM medications WHERE id = ? AND clinic_id = ? FOR UPDATE');
            $find->execute([$medicationId, $clinicId]);
            $medication = $find->fetch();
            if (!$medication) throw new InvalidArgumentException('El medicamento seleccionado no pertenece al stock de esta veterinaria.');
            $previousQuantity = (int)$medication['quantity'];
            if ($quantityToSubtract > $previousQuantity) {
                throw new InvalidArgumentException('No podés restar más unidades de las que hay disponibles (' . $previousQuantity . ').');
            }

            // Solo baja las unidades disponibles. La referencia de la última reposición se mantiene intacta para calcular el estado.
            $update = $pdo->prepare('UPDATE medications SET quantity = quantity - ? WHERE id = ? AND clinic_id = ?');
            $update->execute([$quantityToSubtract, $medicationId, $clinicId]);
            $newQuantity = $previousQuantity - $quantityToSubtract;
            $pdo->commit();
            audit_log('medication', (int)$medicationId, 'stock_reduced', [
                'name' => $medication['name'],
                'previous_quantity' => $previousQuantity,
                'subtracted_quantity' => $quantityToSubtract,
                'new_quantity' => $newQuantity,
            ]);
            flash('Se restaron ' . $quantityToSubtract . ' unidades de ' . $medication['name'] . '.');
        } elseif ($action === 'update_details') {
            $medicationId = $postedInteger('edit_medication_id', 1);
            $brand = trim((string)preg_replace('/\s+/u', ' ', $oldBrand));
            $purpose = trim($oldPurpose);
            if ($medicationId === false || mb_strlen($brand) > 180 || mb_strlen($purpose) > 2000) {
                throw new InvalidArgumentException('Revisá los datos: la marca admite hasta 180 caracteres y la utilidad hasta 2000.');
            }

            $find = $pdo->prepare('SELECT id, name FROM medications WHERE id = ? AND clinic_id = ? LIMIT 1');
            $find->execute([$medicationId, $clinicId]);
            $medication = $find->fetch();
            if (!$medication) throw new InvalidArgumentException('El medicamento seleccionado no pertenece al stock de esta veterinaria.');

            $update = $pdo->prepare('UPDATE medications SET brand = ?, purpose = ? WHERE id = ? AND clinic_id = ?');
            $update->execute([$brand !== '' ? $brand : null, $purpose !== '' ? $purpose : null, $medicationId, $clinicId]);
            audit_log('medication', (int)$medicationId, 'details_updated', [
                'name' => $medication['name'],
                'brand' => $brand,
                'purpose' => $purpose,
            ]);
            flash('Se actualizaron los datos de ' . $medication['name'] . '.');
        } elseif ($action === 'delete') {
            $medicationId = $postedInteger('delete_medication_id', 1);
            if ($medicationId === false) throw new InvalidArgumentException('Elegí un medicamento para eliminar.');

            $pdo->beginTransaction();
            $find = $pdo->prepare('SELECT id, name, quantity FROM medications WHERE id = ? AND clinic_id = ? FOR UPDATE');
            $find->execute([$medicationId, $clinicId]);
            $medication = $find->fetch();
            if (!$medication) throw new InvalidArgumentException('El medicamento seleccionado no pertenece al stock de esta veterinaria.');

            // Mantener una copia del nombre en el historial antes de borrar la fila del inventario.
            $preserveHistory = $pdo->prepare(
                'UPDATE consultation_medications cm
                 JOIN consultations c ON c.id = cm.consultation_id
                 SET cm.medication_id = NULL, cm.other_medication = ?, cm.purchase_needed = 0
                 WHERE cm.medication_id = ? AND c.clinic_id = ?'
            );
            $preserveHistory->execute([$medication['name'], $medicationId, $clinicId]);
            $preservedConsultations = $preserveHistory->rowCount();

            $delete = $pdo->prepare('DELETE FROM medications WHERE id = ? AND clinic_id = ?');
            $delete->execute([$medicationId, $clinicId]);
            if ($delete->rowCount() !== 1) throw new InvalidArgumentException('No se pudo eliminar el medicamento del inventario.');
            $pdo->commit();

            audit_log('medication', (int)$medicationId, 'deleted', [
                'name' => $medication['name'],
                'quantity_at_deletion' => (int)$medication['quantity'],
                'consultations_preserved' => $preservedConsultations,
            ]);
            flash('Medicamento eliminado del inventario. Se conservó su nombre en ' . $preservedConsultations . ' consultas anteriores.');
        } else {
            throw new InvalidArgumentException('La acción solicitada no es válida.');
        }

        header('Location: ' . url('stock/index.php'));
        exit;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($exception instanceof InvalidArgumentException) {
            $error = $exception->getMessage();
        } else {
            error_log('Stock de medicamentos: ' . $exception->getMessage());
            $error = 'No se pudo actualizar el inventario. Verificá los datos e intentá nuevamente.';
        }
    }
}

$query = $pdo->prepare('SELECT id, name, brand, purpose, quantity, last_restock_quantity FROM medications WHERE clinic_id = ? ORDER BY name');
$query->execute([$clinicId]);
$medications = $query->fetchAll();

$pageTitle = 'Stock de medicamentos';
require __DIR__ . '/../includes/header.php';
?>

<style>
    .stock-actions-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 18px; align-items: stretch; }
    .stock-action-card { min-width: 0; display: flex; flex-direction: column; }
    .stock-action-card h2 { box-sizing: border-box; height: 2.8em; margin: 0 0 14px; line-height: 1.35; }
    .stock-action-card > p { box-sizing: border-box; height: 4.65em; min-height: 4.65em; margin: 0 0 12px; line-height: 1.55; }
    .stock-action-card > form { display: flex; flex: 1; flex-direction: column; }
    .stock-action-card > form > label { margin: 8px 0 4px; }
    .stock-action-card > form > input,
    .stock-action-card > form > select { width: 100%; min-height: 42px; box-sizing: border-box; }
    .stock-action-card > form > input[type="number"] { margin-bottom: 12px; }
    .stock-action-card > form > button { align-self: stretch; width: 100%; justify-content: center; min-height: 48px; margin-top: auto; white-space: nowrap; }
    .stock-table-wrap { overflow-x: auto; }
    .stock-table-wrap table { min-width: 900px; }
    .stock-delete-form { display: flex; align-items: center; gap: 10px; min-width: 250px; }
    .stock-delete-form select { min-width: 0; }
    .stock-delete-form button { flex: 0 0 auto; }
    .stock-search { max-width: 520px; margin: 0 0 16px; }
    .stock-search-row { display: flex; align-items: center; gap: 10px; }
    .stock-search-row input { flex: 1; }
    .stock-search-count { margin: 8px 0 0; font-size: 13px; }
    .stock-empty-search { padding: 18px; text-align: center; }
    .stock-edit-dialog { width: min(520px, calc(100% - 32px)); max-width: 520px; max-height: min(90vh, 680px); padding: 0; border: 1px solid var(--line); border-radius: 18px; color: var(--ink); background: var(--white); box-shadow: 0 24px 70px rgba(15, 45, 38, .28); }
    .stock-edit-dialog[open] { animation: stock-dialog-in 180ms ease-out both; }
    @keyframes stock-dialog-in { from { opacity: 0; transform: translateY(9px) scale(.985); } to { opacity: 1; transform: translateY(0) scale(1); } }
    @media (prefers-reduced-motion: reduce) { .stock-edit-dialog[open] { animation: none; } }
    .stock-edit-dialog::backdrop { background: rgba(8, 24, 20, .48); backdrop-filter: blur(2px); }
    .stock-edit-dialog-inner { padding: 26px; }
    .stock-edit-dialog-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; margin-bottom: 8px; }
    .stock-edit-dialog-header h3 { margin: 0; }
    .stock-edit-form { display: grid; gap: 10px; }
    .stock-edit-form label { margin-top: 6px; }
    .stock-edit-form textarea { min-height: 100px; resize: vertical; }
    .stock-edit-dialog-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 10px; }
    .stock-status { white-space: nowrap; }
    .stock-status-empty, .stock-status-low { color: var(--red); border-color: var(--red); background: transparent; }
    .stock-status-medium { color: var(--gold); border-color: var(--gold); background: transparent; }
    .stock-status-high { color: var(--green); border-color: var(--green); background: transparent; }
    @media (max-width: 1250px) { .stock-actions-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    @media (max-width: 620px) {
        .stock-actions-grid { grid-template-columns: 1fr; }
        .stock-action-card { padding: 20px; }
        .stock-delete-form { align-items: stretch; flex-direction: column; }
    }
</style>

<?php if ($error !== ''): ?>
    <div class="notice error" role="alert"><?= e($error) ?></div>
<?php endif; ?>

<section class="panel">
    <h1><i class="fa-solid fa-pills" style="color: var(--green); margin-right: 8px;" aria-hidden="true"></i>Stock de medicamentos</h1>
    <p class="muted">Agregá medicamentos, reponé unidades, editá sus datos o quitá productos del inventario de tu veterinaria.</p>

    <div class="stock-actions-grid">
        <article class="card stock-action-card">
            <h2><i class="fa-solid fa-circle-plus" style="color: var(--green); margin-right: 7px;" aria-hidden="true"></i>Nuevo medicamento</h2>
            <p class="muted">Registrá un medicamento y su cantidad inicial.</p>
            <form method="post">
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="create">
                <label for="new-medication-name">Nombre del medicamento</label>
                <input id="new-medication-name" name="name" maxlength="180" value="<?= e($oldName) ?>" required>
                <label for="initial-quantity">Cantidad inicial</label>
                <input id="initial-quantity" type="number" name="initial_quantity" min="0" max="2147483647" value="<?= e($oldInitialQuantity !== '' ? $oldInitialQuantity : '0') ?>" required>
                <button class="btn primary" type="submit"><i class="fa-solid fa-plus" aria-hidden="true"></i>Agregar</button>
            </form>
        </article>

        <article class="card stock-action-card">
            <h2><i class="fa-solid fa-boxes-stacked" style="color: var(--green); margin-right: 7px;" aria-hidden="true"></i>Agregar unidades</h2>
            <p class="muted">La cantidad se suma a las unidades que ya hay.</p>
            <form method="post">
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="restock">
                <label for="restock-medication">Medicamento</label>
                <select id="restock-medication" name="restock_medication_id" required <?= !$medications ? 'disabled' : '' ?>>
                    <option value="">Elegí un medicamento</option>
                    <?php foreach ($medications as $medication): ?>
                        <option value="<?= (int)$medication['id'] ?>" <?= $oldRestockId === (string)$medication['id'] ? 'selected' : '' ?>><?= e($medication['name']) ?> · stock: <?= (int)$medication['quantity'] ?></option>
                    <?php endforeach; ?>
                </select>
                <label for="restock-quantity">Unidades a agregar</label>
                <input id="restock-quantity" type="number" name="restock_quantity" min="1" max="2147483647" value="<?= e($oldRestockQuantity) ?>" required <?= !$medications ? 'disabled' : '' ?>>
                <?php if (!$medications): ?><p class="muted">Primero agregá un medicamento al inventario.</p><?php endif; ?>
                <button class="btn primary" type="submit" <?= !$medications ? 'disabled' : '' ?>><i class="fa-solid fa-arrow-up" aria-hidden="true"></i>Sumar</button>
            </form>
        </article>

        <article class="card stock-action-card">
            <h2><i class="fa-solid fa-boxes-stacked" style="color: var(--gold); margin-right: 7px;" aria-hidden="true"></i>Restar unidades</h2>
            <p class="muted">Descontá unidades usadas sin cambiar la referencia de la última reposición.</p>
            <form method="post">
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="subtract">
                <label for="subtract-medication">Medicamento</label>
                <select id="subtract-medication" name="subtract_medication_id" required <?= !$medications ? 'disabled' : '' ?>>
                    <option value="">Elegí un medicamento</option>
                    <?php foreach ($medications as $medication): ?>
                        <option value="<?= (int)$medication['id'] ?>" <?= $oldSubtractId === (string)$medication['id'] ? 'selected' : '' ?>><?= e($medication['name']) ?> · stock: <?= (int)$medication['quantity'] ?></option>
                    <?php endforeach; ?>
                </select>
                <label for="subtract-quantity">Unidades a restar</label>
                <input id="subtract-quantity" type="number" name="subtract_quantity" min="1" max="2147483647" value="<?= e($oldSubtractQuantity) ?>" required <?= !$medications ? 'disabled' : '' ?>>
                <?php if (!$medications): ?><p class="muted">Primero agregá un medicamento al inventario.</p><?php endif; ?>
                <button class="btn secondary" type="submit" <?= !$medications ? 'disabled' : '' ?>><i class="fa-solid fa-arrow-down" aria-hidden="true"></i>Restar</button>
            </form>
        </article>

        <article class="card stock-action-card">
            <h2><i class="fa-solid fa-trash-can" style="color: var(--red); margin-right: 7px;" aria-hidden="true"></i>Eliminar medicamento</h2>
            <p class="muted">La confirmación evita quitar un producto por error. Las consultas conservarán su nombre.</p>
            <form method="post" id="delete-medication-form">
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="delete">
                <label for="delete-medication">Medicamento</label>
                <select id="delete-medication" name="delete_medication_id" required <?= !$medications ? 'disabled' : '' ?>>
                    <option value="">Elegí un medicamento</option>
                    <?php foreach ($medications as $medication): ?>
                        <option value="<?= (int)$medication['id'] ?>" <?= $oldDeleteId === (string)$medication['id'] ? 'selected' : '' ?>><?= e($medication['name']) ?> · <?= (int)$medication['quantity'] ?> unidades</option>
                    <?php endforeach; ?>
                </select>
                <?php if (!$medications): ?><p class="muted">No hay medicamentos para eliminar.</p><?php endif; ?>
                <button class="btn danger" type="submit" <?= !$medications ? 'disabled' : '' ?>><i class="fa-solid fa-trash-can" aria-hidden="true"></i>Eliminar</button>
            </form>
        </article>
    </div>
</section>

<section class="card">
    <h2><i class="fa-solid fa-list-check" style="color: var(--green); margin-right: 7px;" aria-hidden="true"></i>Inventario</h2>
    <p class="muted">El estado compara las unidades disponibles con la cantidad de la última reposición.</p>
    <?php if (!$medications): ?>
        <p class="muted">Todavía no hay medicamentos registrados.</p>
    <?php else: ?>
        <div class="stock-search">
            <label for="medication-search">Buscar medicamento</label>
            <div class="stock-search-row">
                <input id="medication-search" type="search" placeholder="Nombre, marca o utilidad…" autocomplete="off" aria-describedby="medication-search-count">
                <button class="btn secondary" id="clear-medication-search" type="button" aria-label="Limpiar búsqueda"><i class="fa-solid fa-xmark" aria-hidden="true"></i>Limpiar</button>
            </div>
            <p id="medication-search-count" class="muted stock-search-count" aria-live="polite"></p>
        </div>
        <div class="stock-table-wrap">
            <table>
                <thead><tr><th>Medicamento</th><th>Marca</th><th>Utilidad</th><th>Unidades</th><th>Estado</th><th>Acciones</th></tr></thead>
                <tbody>
                    <?php foreach ($medications as $medication): ?>
                        <?php $quantity = (int)$medication['quantity']; ?>
                        <?php
                            $lastRestockQuantity = (int)$medication['last_restock_quantity'];
                            $referenceQuantity = max(1, $lastRestockQuantity);
                            if ($quantity === 0) {
                                $stockStatus = 'Inexistente';
                                $stockClass = 'stock-status-empty';
                            } elseif ($quantity * 4 <= $referenceQuantity) {
                                $stockStatus = 'Stock bajo';
                                $stockClass = 'stock-status-low';
                            } elseif ($quantity * 2 <= $referenceQuantity) {
                                $stockStatus = 'Stock medio';
                                $stockClass = 'stock-status-medium';
                            } else {
                                $stockStatus = 'Stock alto';
                                $stockClass = 'stock-status-high';
                            }
                            $editId = (string)$medication['id'];
                            $showEditValues = $oldEditId === $editId;
                        ?>
                        <tr class="medication-row" data-search="<?= e($medication['name'] . ' ' . (string)($medication['brand'] ?? '') . ' ' . (string)($medication['purpose'] ?? '')) ?>">
                            <td><?= e($medication['name']) ?></td>
                            <td><?= trim((string)($medication['brand'] ?? '')) !== '' ? e($medication['brand']) : '—' ?></td>
                            <td><?= trim((string)($medication['purpose'] ?? '')) !== '' ? e($medication['purpose']) : '—' ?></td>
                            <td><?= $quantity ?></td>
                            <td><span class="pill stock-status <?= e($stockClass) ?>"><?= e($stockStatus) ?></span></td>
                            <td>
                                <button
                                    type="button"
                                    class="btn secondary medication-edit-button"
                                    data-medication-id="<?= (int)$medication['id'] ?>"
                                    data-medication-name="<?= e($medication['name']) ?>"
                                    data-brand="<?= e((string)($medication['brand'] ?? '')) ?>"
                                    data-purpose="<?= e((string)($medication['purpose'] ?? '')) ?>"
                                ><i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>Editar medicamento</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p id="no-medication-results" class="muted stock-empty-search" hidden>No se encontraron medicamentos con esa búsqueda.</p>
    <?php endif; ?>
</section>

<dialog class="stock-edit-dialog" id="edit-medication-dialog" aria-labelledby="edit-medication-title">
    <div class="stock-edit-dialog-inner">
        <div class="stock-edit-dialog-header">
            <div>
                <h3 id="edit-medication-title"><i class="fa-solid fa-pen-to-square" style="color: var(--green);" aria-hidden="true"></i> Editar medicamento</h3>
                <p id="edit-medication-name" class="muted" style="margin: 6px 0 0;"></p>
            </div>
            <button type="button" class="btn secondary" id="close-edit-medication" aria-label="Cerrar"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
        </div>
        <p class="muted">Actualizá la marca y para qué se utiliza este medicamento.</p>
        <form method="post" class="stock-edit-form" id="edit-medication-form">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="update_details">
            <input type="hidden" name="edit_medication_id" id="edit-medication-id" value="<?= e($oldEditId) ?>">
            <label for="edit-medication-brand">Marca</label>
            <input id="edit-medication-brand" name="brand" maxlength="180" value="<?= e($oldBrand) ?>">
            <label for="edit-medication-purpose">Utilidad (para qué se usa)</label>
            <textarea id="edit-medication-purpose" name="purpose" maxlength="2000"><?= e($oldPurpose) ?></textarea>
            <div class="stock-edit-dialog-actions">
                <button class="btn secondary" type="button" id="cancel-edit-medication">Cancelar</button>
                <button class="btn primary" type="submit"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>Guardar cambios</button>
            </div>
        </form>
    </div>
</dialog>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('delete-medication-form');
    const select = document.getElementById('delete-medication');
    if (!form || !select) return;

    form.addEventListener('submit', function (event) {
        const name = select.options[select.selectedIndex]?.text || 'este medicamento';
        if (!select.value || !window.confirm('¿Eliminar ' + name + ' del inventario? Las consultas anteriores conservarán el nombre del medicamento.')) {
            event.preventDefault();
        }
    });

    const search = document.getElementById('medication-search');
    const clearSearch = document.getElementById('clear-medication-search');
    const rows = Array.from(document.querySelectorAll('.medication-row'));
    const count = document.getElementById('medication-search-count');
    const noResults = document.getElementById('no-medication-results');
    const normalize = value => value.toLocaleLowerCase('es').normalize('NFD').replace(/[\u0300-\u036f]/g, '').trim();
    const applySearch = () => {
        if (!search) return;
        const term = normalize(search.value);
        let visible = 0;
        rows.forEach(row => {
            const matches = normalize(row.dataset.search || '').includes(term);
            row.hidden = !matches;
            if (matches) visible++;
        });
        if (count) count.textContent = term ? visible + ' de ' + rows.length + ' medicamentos' : rows.length + ' medicamentos';
        if (noResults) noResults.hidden = visible !== 0;
    };
    search?.addEventListener('input', applySearch);
    clearSearch?.addEventListener('click', function () {
        if (!search) return;
        search.value = '';
        applySearch();
        search.focus();
    });
    applySearch();

    const editDialog = document.getElementById('edit-medication-dialog');
    const editForm = document.getElementById('edit-medication-form');
    const idInput = document.getElementById('edit-medication-id');
    const brandInput = document.getElementById('edit-medication-brand');
    const purposeInput = document.getElementById('edit-medication-purpose');
    const nameLabel = document.getElementById('edit-medication-name');
    const closeEdit = () => editDialog?.close();
    document.querySelectorAll('.medication-edit-button').forEach(button => {
        button.addEventListener('click', function () {
            if (!editDialog || !idInput || !brandInput || !purposeInput || !nameLabel) return;
            idInput.value = button.dataset.medicationId || '';
            brandInput.value = button.dataset.brand || '';
            purposeInput.value = button.dataset.purpose || '';
            nameLabel.textContent = button.dataset.medicationName || '';
            editDialog.showModal();
            brandInput.focus();
        });
    });
    document.getElementById('close-edit-medication')?.addEventListener('click', closeEdit);
    document.getElementById('cancel-edit-medication')?.addEventListener('click', closeEdit);
    editDialog?.addEventListener('click', function (event) {
        if (event.target === editDialog) closeEdit();
    });
    if (editDialog && editForm && idInput?.value) {
        const failedRow = document.querySelector('.medication-edit-button[data-medication-id="' + CSS.escape(idInput.value) + '"]');
        if (failedRow) {
            nameLabel.textContent = failedRow.dataset.medicationName || '';
            editDialog.showModal();
        }
    }
});
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
