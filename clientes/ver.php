<?php
require_once __DIR__ . '/../config/config.php';
require_vet();

$id = (int)($_GET['id'] ?? 0);

// 1. Obtener la información del cliente
$q = db()->prepare("SELECT * FROM clients WHERE id = ?");
$q->execute([$id]);
$c = $q->fetch();

if (!$c) {
    http_response_code(404);
    exit('Cliente no encontrado');
}

// 2. Registrar o actualizar la última visita del cliente a esta clínica específica
$q = db()->prepare("
    INSERT INTO clinic_clients(clinic_id, client_id) 
    VALUES(?, ?) 
    ON DUPLICATE KEY UPDATE last_seen_at = CURRENT_TIMESTAMP
");
$q->execute([$_SESSION['clinic_id'], $id]);

// 3. Obtener el listado de mascotas asociadas a este cliente
$q = db()->prepare("SELECT * FROM pets WHERE client_id = ? ORDER BY name");
$q->execute([$id]);
$pets = $q->fetchAll();

$pageTitle = 'Cliente';
require __DIR__ . '/../includes/header.php';
?>

<div class="panel">
    <h1><?= e($c['full_name']) ?></h1>
    <p>
        DNI: <?= e($c['dni']) ?> · 
        <?= e($c['email']) ?> · 
        <?= e($c['phone']) ?>
    </p>
    
    <div class="actions">
        <a class="btn" href="<?= url('mascotas/nueva.php?client_id=' . $id) ?>">Agregar mascota</a>
        <a class="btn secondary" href="<?= url('clientes/buscar.php') ?>">Volver a búsqueda</a>
    </div>
</div>

<div class="card">
    <h2>Mascotas</h2>
    
    <?php if (!$pets): ?>
        <p>Este cliente todavía no tiene mascotas cargadas.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>Tipo</th>
                    <th>Raza</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pets as $p): ?>
                    <tr>
                        <td><?= (int)$p['id'] ?></td>
                        <td><?= e($p['name']) ?></td>
                        <td><?= e($p['animal_type']) ?></td>
                        <td><?= e($p['breed']) ?></td>
                        <td>
                            <a class="btn" href="<?= url('mascotas/ver.php?id=' . (int)$p['id']) ?>">Abrir historial</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>