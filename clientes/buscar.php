<?php
require_once __DIR__ . '/../config/config.php';
require_vet();

$results = [];
$dni = trim($_GET['dni'] ?? '');

// Si se ingresó un DNI, buscar el cliente en la base de datos
if ($dni !== '') {
    $q = db()->prepare("
        SELECT c.*, cc.last_seen_at 
        FROM clients c 
        LEFT JOIN clinic_clients cc ON cc.client_id = c.id AND cc.clinic_id = ? 
        WHERE c.dni = ?
    ");
    $q->execute([$_SESSION['clinic_id'], $dni]);
    $results = $q->fetchAll();
}

$pageTitle = 'Buscar cliente';
require __DIR__ . '/../includes/header.php';
?>

<div class="panel">
    <h1>Buscar cliente</h1>
    <p class="muted">Ingresá el DNI para localizar al dueño y sus mascotas en el registro compartido.</p>
    
    <form method="get">
        <label>DNI</label>
        <input name="dni" value="<?= e($dni) ?>" required>
        
        <div class="actions">
            <button>Buscar</button>
            <a class="btn secondary" href="<?= url('clientes/nuevo.php') ?>">Crear cliente nuevo</a>
        </div>
    </form>
</div>

<?php if ($dni !== ''): ?>
    <div class="card">
        <h2>Resultados</h2>
        
        <?php if (!$results): ?>
            <p>No se encontró un cliente con ese DNI. Podés darlo de alta si verificaste sus datos.</p>
        <?php else: ?>
            <?php foreach ($results as $c): ?>
                <p>
                    <strong><?= e($c['full_name']) ?></strong> · 
                    DNI <?= e($c['dni']) ?> · 
                    <?= e($c['email']) ?>
                </p>
                <a class="btn" href="<?= url('clientes/ver.php?id=' . (int)$c['id']) ?>">Ver cliente y mascotas</a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>