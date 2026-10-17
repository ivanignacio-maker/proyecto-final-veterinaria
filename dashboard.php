<?php
require_once __DIR__ . '/config/config.php';
require_vet();

$cid = (int)$_SESSION['clinic_id'];

// Obtener cantidad de clientes vinculados a la clínica
$q = db()->prepare("SELECT COUNT(*) FROM clinic_clients WHERE clinic_id = ?");
$q->execute([$cid]);
$clients = $q->fetchColumn();

// Obtener cantidad de turnos próximos solicitados o confirmados
$q = db()->prepare("SELECT COUNT(*) FROM appointments WHERE clinic_id = ? AND status IN ('Solicitado', 'Confirmado') AND appointment_at >= NOW()");
$q->execute([$cid]);
$turnos = $q->fetchColumn();

// Obtener cantidad de consultas actualmente en proceso
$q = db()->prepare("SELECT COUNT(*) FROM consultations WHERE clinic_id = ? AND status = 'En proceso'");
$q->execute([$cid]);
$open = $q->fetchColumn();

$pageTitle = 'Panel';
require __DIR__ . '/includes/header.php';
?>

<section class="hero">
    <h1>Hola, <?= e($_SESSION['vet_username']) ?> 👋</h1>
    <p>Panel de <?= e($_SESSION['clinic_name']) ?>. Los historiales compartidos permiten continuar la atención en clínicas participantes.</p>
    
    <div class="actions">
        <a class="btn" href="<?= url('clientes/buscar.php') ?>">Buscar cliente por DNI</a>
        <a class="btn secondary" href="<?= url('clientes/nuevo.php') ?>">Agregar cliente</a>
        <a class="btn secondary" href="<?= url('turnos/index.php') ?>">Gestionar turnos</a>
    </div>
</section>

<div class="grid">
    <div class="card">
        <p class="muted">Clientes vinculados</p>
        <h2><?= (int)$clients ?></h2>
    </div>
    
    <div class="card">
        <p class="muted">Turnos próximos</p>
        <h2><?= (int)$turnos ?></h2>
    </div>
    
    <div class="card">
        <p class="muted">Consultas en proceso</p>
        <h2><?= (int)$open ?></h2>
    </div>
</div>

<div class="card">
    <h2>Accesos rápidos</h2>
    <div class="actions">
        <a class="btn secondary" href="<?= url('historial/recientes.php') ?>">Pacientes recientes</a>
        <a class="btn secondary" href="<?= url('stock/index.php') ?>">Medicamentos en stock</a>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>