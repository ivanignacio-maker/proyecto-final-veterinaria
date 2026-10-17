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

$pageTitle = 'Stock';
require __DIR__ . '/../includes/header.php';
?>

<div class="panel">
    <h1>Stock de medicamentos</h1>
    
    <form method="post">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
        
        <div class="form-grid">
            <div>
                <label>Medicamento</label>
                <input name="name" required>
            </div>
            <div>
                <label>Cantidad disponible</label>
                <input type="number" min="0" name="quantity" required value="0">
            </div>
        </div>
        
        <div class="actions">
            <button>Agregar / actualizar stock</button>
        </div>
    </form>
</div>

<div class="card">
    <h2>Inventario</h2>
    
    <table>
        <thead>
            <tr>
                <th>Medicamento</th>
                <th>Unidades</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?= e($r['name']) ?></td>
                    <td><?= (int)$r['quantity'] ?></td>
                    <td>
                        <?= $r['quantity'] < 1 ? 'Sin stock' : ($r['quantity'] < 5 ? 'Stock bajo' : 'Disponible') ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>