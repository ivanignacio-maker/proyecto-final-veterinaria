<?php
require_once __DIR__ . '/../config/config.php';
require_vet();

// Obtener las últimas 50 consultas de la clínica actual junto con los datos de la mascota y el cliente
$q = db()->prepare("
    SELECT 
        co.id consultation_id, 
        co.created_at, 
        co.reason, 
        co.status, 
        p.id pet_id, 
        p.name pet_name, 
        c.full_name, 
        c.dni 
    FROM consultations co 
    JOIN pets p ON p.id = co.pet_id 
    JOIN clients c ON c.id = p.client_id 
    WHERE co.clinic_id = ? 
    ORDER BY co.created_at DESC 
    LIMIT 50
");
$q->execute([$_SESSION['clinic_id']]);
$rows = $q->fetchAll();

$pageTitle = 'Atendidos recientemente';
require __DIR__ . '/../includes/header.php';
?>

<div class="card">
    <h1>Clientes y mascotas atendidos recientemente</h1>
    
    <?php if (!$rows): ?>
        <p>Todavía no hay consultas registradas por esta veterinaria.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Cliente</th>
                    <th>Mascota</th>
                    <th>Consulta</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><?= e($r['created_at']) ?></td>
                        <td>
                            <?= e($r['full_name']) ?><br>
                            <span class="muted">DNI <?= e($r['dni']) ?></span>
                        </td>
                        <td><?= e($r['pet_name']) ?> (#<?= (int)$r['pet_id'] ?>)</td>
                        <td>
                            <?= e($r['reason']) ?><br>
                            <a href="<?= url('consultas/ver.php?id=' . (int)$r['consultation_id']) ?>">Ver consulta</a>
                        </td>
                        <td><?= e($r['status']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>