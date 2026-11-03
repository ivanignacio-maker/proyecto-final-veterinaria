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
    .stock-actions-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 18px; }
    .stock-action-card { min-width: 0; }
    .stock-action-card h2 { margin-top: 0; }
    .stock-action-card .btn { margin-top: 14px; }
    .stock-table-wrap { overflow-x: auto; }
    .stock-table-wrap table { min-width: 900px; }
    .stock-delete-form { display: flex; align-items: center; gap: 10px; min-width: 250px; }
    .stock-delete-form select { min-width: 0; }
    .stock-delete-form button { flex: 0 0 auto; }
    .stock-edit-details { min-width: 170px; }
    .stock-edit-details summary { cursor: pointer; list-style: none; }
    .stock-edit-details summary::-webkit-details-marker { display: none; }
    .stock-edit-details[open] summary { margin-bottom: 12px; }
    .stock-edit-form { display: grid; gap: 8px; min-width: 220px; }
    .stock-edit-form label { margin-top: 4px; }
    .stock-edit-form textarea { min-height: 76px; resize: vertical; }
    .stock-status { white-space: nowrap; }
    .stock-status-empty, .stock-status-low { color: var(--red); border-color: var(--red); background: transparent; }
    .stock-status-medium { color: var(--gold); border-color: var(--gold); background: transparent; }
    .stock-status-high { color: var(--green); border-color: var(--green); background: transparent; }
    @media (max-width: 900px) { .stock-actions-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
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
                <button class="btn primary" type="submit"><i class="fa-solid fa-plus" aria-hidden="true"></i>Agregar medicamento</button>
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
                <button class="btn primary" type="submit" <?= !$medications ? 'disabled' : '' ?>><i class="fa-solid fa-arrow-up" aria-hidden="true"></i>Sumar al stock</button>
                <?php if (!$medications): ?><p class="muted">Primero agregá un medicamento al inventario.</p><?php endif; ?>
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
                <button class="btn danger" type="submit" <?= !$medications ? 'disabled' : '' ?>><i class="fa-solid fa-trash-can" aria-hidden="true"></i>Eliminar</button>
                <?php if (!$medications): ?><p class="muted">No hay medicamentos para eliminar.</p><?php endif; ?>
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
                        <tr>
                            <td><?= e($medication['name']) ?></td>
                            <td><?= trim((string)($medication['brand'] ?? '')) !== '' ? e($medication['brand']) : '—' ?></td>
                            <td><?= trim((string)($medication['purpose'] ?? '')) !== '' ? e($medication['purpose']) : '—' ?></td>
                            <td><?= $quantity ?></td>
                            <td><span class="pill stock-status <?= e($stockClass) ?>"><?= e($stockStatus) ?></span></td>
                            <td>
                                <details class="stock-edit-details" <?= $showEditValues ? 'open' : '' ?>>
                                    <summary class="btn secondary"><i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>Editar medicamento</summary>
                                    <form method="post" class="stock-edit-form">
                                        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                                        <input type="hidden" name="action" value="update_details">
                                        <input type="hidden" name="edit_medication_id" value="<?= (int)$medication['id'] ?>">
                                        <label for="brand-<?= (int)$medication['id'] ?>">Marca</label>
                                        <input id="brand-<?= (int)$medication['id'] ?>" name="brand" maxlength="180" value="<?= e($showEditValues ? $oldBrand : ($medication['brand'] ?? '')) ?>">
                                        <label for="purpose-<?= (int)$medication['id'] ?>">Utilidad (para qué se usa)</label>
                                        <textarea id="purpose-<?= (int)$medication['id'] ?>" name="purpose" maxlength="2000"><?= e($showEditValues ? $oldPurpose : ($medication['purpose'] ?? '')) ?></textarea>
                                        <button class="btn primary" type="submit"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>Guardar</button>
                                    </form>
                                </details>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

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
});
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
