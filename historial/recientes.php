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

<div class="panel">
    <h1>Pacientes y clientes atendidos recientemente</h1>
    <p class="muted">Últimas 50 consultas clínicas registradas en el sistema para tu veterinaria.</p>
    
    <?php if (!$rows): ?>
        <p style="padding: 24px 0; color: var(--muted);">Todavía no hay consultas registradas por esta veterinaria.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Fecha de atención</th>
                    <th>Tutor / Cliente</th>
                    <th>Paciente (Mascota)</th>
                    <th>Motivo de consulta</th>
                    <th>Estado</th>
                    <th>Ficha médica</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $r): ?>
                    <?php
                        $statusClass = $r['status'] === 'Dado de Alta' ? 'badge-success' : 'badge-warning';
                    ?>
                    <tr>
                        <td>
                            <strong><?= date('d/m/Y', strtotime($r['created_at'])) ?></strong><br>
                            <span class="muted"><?= date('H:i', strtotime($r['created_at'])) ?> hs</span>
                        </td>
                        <td>
                            <strong class="name-titlecase"><?= e_name($r['full_name']) ?></strong><br>
                            <span class="muted">DNI <?= e($r['dni']) ?></span>
                        </td>
                        <td>
                            <span class="pill name-titlecase">🐾 <?= e_name($r['pet_name']) ?></span>
                            <span class="muted" style="font-size: 11px; margin-left: 4px;">#<?= (int)$r['pet_id'] ?></span>
                        </td>
                        <td>
                            <strong><?= e($r['reason']) ?></strong>
                        </td>
                        <td>
                            <span class="badge <?= $statusClass ?>">
                                <?= e($r['status']) ?>
                            </span>
                        </td>
                        <td>
                            <a href="<?= url('consultas/ver.php?id=' . (int)$r['consultation_id']) ?>" class="btn secondary btn-sm">
                                Ver consulta →
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>