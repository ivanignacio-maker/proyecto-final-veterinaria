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

$pageTitle = 'Panel de Gestión Médica';
require __DIR__ . '/includes/header.php';
?>

<section class="dashboard-hero">
    <div class="badge-pulse-wrapper" style="margin-bottom: 14px;">
        <span class="badge-pulse-dot"></span>
        <span>Clínica Activa · <?= e_name($_SESSION['clinic_name']) ?></span>
    </div>

    <h1>Hola, <?= e_name($_SESSION['vet_username']) ?></h1>
    <p>
        Bienvenido a tu panel de gestión clínica en <?= e_name($_SESSION['clinic_name']) ?>. Accedé al registro unificado de historias clínicas, turnos y control de medicamentos en stock.
    </p>
    
    <div class="actions">
        <a class="btn primary" href="<?= url('clientes/buscar.php') ?>">
            <i class="fa-solid fa-magnifying-glass" style="margin-right: 6px;"></i>
            <span>Buscar cliente por DNI</span>
            <span style="margin-left: 4px;">→</span>
        </a>
        <a class="btn secondary" href="<?= url('clientes/nuevo.php') ?>">
            <i class="fa-solid fa-user-plus" style="margin-right: 6px;"></i>
            <span>Nuevo cliente</span>
        </a>
        <a class="btn secondary" href="<?= url('turnos/index.php') ?>">
            <i class="fa-solid fa-calendar-check" style="margin-right: 6px;"></i>
            <span>Gestionar turnos</span>
            <span style="margin-left: 4px;">→</span>
        </a>
    </div>
</section>

<div class="grid">
    <div class="card" style="border-top-color: var(--green);">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <p class="muted" style="margin: 0;">Clientes vinculados</p>
            <i class="fa-solid fa-users" style="color: var(--green); font-size: 18px;"></i>
        </div>
        <h2 style="margin: 8px 0 0;"><?= (int)$clients ?></h2>
    </div>
    
    <div class="card" style="border-top-color: #2b7bc4;">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <p class="muted" style="margin: 0;">Turnos próximos</p>
            <i class="fa-solid fa-calendar-days" style="color: #2b7bc4; font-size: 18px;"></i>
        </div>
        <h2 style="margin: 8px 0 0;"><?= (int)$turnos ?></h2>
    </div>
    
    <div class="card" style="border-top-color: var(--gold);">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <p class="muted" style="margin: 0;">Consultas en proceso</p>
            <i class="fa-solid fa-stethoscope" style="color: var(--gold); font-size: 18px;"></i>
        </div>
        <h2 style="margin: 8px 0 0;"><?= (int)$open ?></h2>
    </div>
</div>

<div class="card">
    <h2><i class="fa-solid fa-cubes-stacked" style="color: var(--green); margin-right: 8px;"></i>Módulos y Operaciones Clínicas</h2>
    <p class="muted" style="margin-bottom: 16px;">Accesos directos al historial general y farmacia de tu veterinaria.</p>
    <div class="actions">
        <a class="btn secondary" href="<?= url('historial/recientes.php') ?>">
            <i class="fa-solid fa-clock-rotate-left" style="margin-right: 6px;"></i>
            <span>Pacientes y consultas recientes</span>
            <span style="margin-left: 4px;">→</span>
        </a>
        <a class="btn secondary" href="<?= url('stock/index.php') ?>">
            <i class="fa-solid fa-boxes-stacked" style="margin-right: 6px;"></i>
            <span>Medicamentos en stock</span>
            <span style="margin-left: 4px;">→</span>
        </a>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
