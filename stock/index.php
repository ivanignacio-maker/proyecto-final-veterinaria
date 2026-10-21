<?php
require_once __DIR__ . '/../config/config.php';
require_vet();
csrf_check();

// 1. Procesar la carga o actualización de stock de un medicamento
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $qty = max(0, (int)($_POST['quantity'] ?? 0));
    
    if ($name !== '') {
        $q = db()->prepare("INSERT INTO medications(clinic_id, name, quantity) VALUES(?, ?, ?) ON DUPLICATE KEY UPDATE quantity = VALUES(quantity)");
        $q->execute([$_SESSION['clinic_id'], $name, $qty]);
        
        flash('Medicamento guardado.');
        header('Location: ' . url('stock/index.php'));
        exit;
    }
}

// 2. Obtener el inventario de medicamentos de la clínica actual
$q = db()->prepare("SELECT * FROM medications WHERE clinic_id = ? ORDER BY name");
$q->execute([$_SESSION['clinic_id']]);
$rows = $q->fetchAll();

$pageTitle = 'Stock de medicamentos | ' . APP_NAME;
require __DIR__ . '/../includes/header.php';
?>

<div class="panel">
    <h1><i class="fa-solid fa-boxes-stacked" style="color: var(--green); margin-right: 8px;"></i>Stock de medicamentos</h1>
    <p class="muted">Gestión de insumos médicos y farmacia clínica para tu veterinaria.</p>
    
    <form method="post">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
        
        <div class="form-grid">
            <div>
                <label>Nombre del medicamento o insumo</label>
                <input name="name" placeholder="Ej. Amoxicilina 500mg" required>
            </div>
            <div>
                <label>Cantidad disponible (unidades)</label>
                <input type="number" min="0" name="quantity" required value="0">
            </div>
        </div>
        
        <div class="actions">
            <button class="btn primary" type="submit">
                <i class="fa-solid fa-plus" style="margin-right: 6px;"></i> Agregar o actualizar stock
            </button>
        </div>
    </form>
</div>

<div class="card" style="margin-top: 28px;">
    <h2><i class="fa-solid fa-pills" style="color: var(--green); margin-right: 8px;"></i>Inventario de farmacia</h2>
    <p class="muted">Control de existencias y alertas de reposición.</p>
    
    <?php if (!$rows): ?>
        <p style="padding: 20px 0; color: var(--muted);">No hay medicamentos registrados en el inventario.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Medicamento</th>
                    <th>Unidades en depósito</th>
                    <th>Estado de stock</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $r): ?>
                    <?php
                        $qty = (int)$r['quantity'];
                        if ($qty < 1) {
                            $badge = '<span class="badge badge-danger">Sin stock</span>';
                        } elseif ($qty < 5) {
                            $badge = '<span class="badge badge-warning">Stock bajo</span>';
                        } else {
                            $badge = '<span class="badge badge-success">Disponible</span>';
                        }
                    ?>
                    <tr>
                        <td><strong class="name-titlecase"><?= e_name($r['name']) ?></strong></td>
                        <td><strong><?= $qty ?></strong> un.</td>
                        <td><?= $badge ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
