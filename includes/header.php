<?php require_once __DIR__ . '/../config/config.php'; ?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
    <title><?= e($pageTitle ?? APP_NAME) ?> · <?= APP_NAME ?></title>
    
    <link rel="stylesheet" href="<?= url('assets/style.css') ?>">
</head>
<body>
    <header class="topbar">
        <a class="brand" href="<?= url('dashboard.php') ?>">🐾 VetCompartido</a>
        
        <nav>
            <?php if (!empty($_SESSION['clinic_id'])): ?>
                <!-- Menú para Veterinarias -->
                <a href="<?= url('dashboard.php') ?>">Inicio</a>
                <a href="<?= url('clientes/buscar.php') ?>">Buscar cliente</a>
                <a href="<?= url('clientes/nuevo.php') ?>">Nuevo cliente</a>
                <a href="<?= url('historial/recientes.php') ?>">Recientes</a>
                <a href="<?= url('turnos/index.php') ?>">Turnos</a>
                <a href="<?= url('stock/index.php') ?>">Stock</a>
                <a href="<?= url('logout.php') ?>">Salir</a>
                
            <?php elseif (!empty($_SESSION['client_id'])): ?>
                <!-- Menú para Clientes/Dueños -->
                <a href="<?= url('cliente/mascotas.php') ?>">Mis mascotas</a>
                <a href="<?= url('cliente/turnos.php') ?>">Turnos</a>
                <a href="<?= url('cliente/logout.php') ?>">Salir</a>
                
            <?php elseif (!empty($_SESSION['admin_id'])): ?>
                <!-- Menú para el Administrador del sistema -->
                <a href="<?= url('admin/veterinarias.php') ?>">Veterinarias</a>
                <a href="<?= url('admin/logout.php') ?>">Salir</a>
                
            <?php endif; ?>
        </nav>
    </header>

    <main class="container">
        <?php show_flash(); ?>