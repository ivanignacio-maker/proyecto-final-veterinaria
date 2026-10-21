<?php 
require_once __DIR__ . '/../config/config.php'; 
$currentScript = basename($_SERVER['PHP_SELF'] ?? '');
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle ?? 'Plataforma de Gestión Veterinaria') ?> | <?= APP_NAME ?></title>
    <meta name="description" content="<?= e($pageDescription ?? 'Plataforma médica veterinaria integral. Historias clínicas unificadas, gestión de stock, libreta sanitaria digital para tutores y migración asistida sin costo.') ?>">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400..700;1,9..40,400..700&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer">
    
    <link rel="stylesheet" href="<?= url('assets/style.css') ?>?v=4">
    <link rel="stylesheet" href="<?= url('assets/public.css') ?>?v=7">
    <link rel="icon" href="<?= url('assets/logo.jpg') ?>" type="image/jpeg">
</head>
<body class="<?= !empty($bodyClass) ? e($bodyClass) : '' ?>">
    <header class="topbar public-topbar">
        <div class="topbar-inner">
            <?php 
            $brandUrl = url('index.php');
            if (!empty($_SESSION['clinic_id'])) $brandUrl = url('dashboard.php');
            elseif (!empty($_SESSION['client_id'])) $brandUrl = url('cliente/mascotas.php');
            elseif (!empty($_SESSION['admin_id'])) $brandUrl = url('admin/veterinarias.php');
            ?>
            <a class="brand" href="<?= $brandUrl ?>" title="<?= APP_NAME ?> - Gestión Veterinaria">
                <img src="<?= url('assets/logo.jpg') ?>" alt="<?= APP_NAME ?>" class="brand-icon-img">
                <div class="brand-text-group">
                    <span class="brand-name"><?= APP_NAME ?></span>
                    <span class="brand-tagline">
                        <?php if (!empty($_SESSION['clinic_name'])): ?>
                            <?= e_name($_SESSION['clinic_name']) ?>
                        <?php elseif (!empty($_SESSION['admin_id'])): ?>
                            Panel Administrador
                        <?php elseif (!empty($_SESSION['client_id'])): ?>
                            Portal Tutores
                        <?php else: ?>
                            Gestión Clínica &amp; Tutores
                        <?php endif; ?>
                    </span>
                </div>
            </a>

            <!-- Navegación de Escritorio -->
            <nav class="desktop-nav">
                <?php if (!empty($_SESSION['clinic_id'])): ?>
                    <!-- Menú Veterinarias -->
                    <a href="<?= url('dashboard.php') ?>" class="<?= $currentScript === 'dashboard.php' ? 'active-nav' : '' ?>">Inicio</a>
                    <a href="<?= url('clientes/buscar.php') ?>" class="<?= $currentScript === 'buscar.php' ? 'active-nav' : '' ?>">Buscar cliente</a>
                    <a href="<?= url('clientes/nuevo.php') ?>" class="<?= $currentScript === 'nuevo.php' ? 'active-nav' : '' ?>">+ Nuevo cliente</a>
                    <a href="<?= url('historial/recientes.php') ?>" class="<?= $currentScript === 'recientes.php' ? 'active-nav' : '' ?>">Recientes</a>
                    <a href="<?= url('turnos/index.php') ?>" class="<?= $currentScript === 'index.php' && strpos($_SERVER['SCRIPT_NAME'] ?? '', '/turnos/') !== false ? 'active-nav' : '' ?>">Turnos</a>
                    <a href="<?= url('stock/index.php') ?>" class="<?= $currentScript === 'index.php' && strpos($_SERVER['SCRIPT_NAME'] ?? '', '/stock/') !== false ? 'active-nav' : '' ?>">Stock</a>
                    <span class="nav-divider"></span>
                    <div class="user-profile-header">
                        <div class="user-avatar-initial">
                            <?= strtoupper(substr(trim($_SESSION['vet_username'] ?? 'V'), 0, 1)) ?>
                        </div>
                        <div class="user-meta-header">
                            <span class="user-meta-name"><?= e_name($_SESSION['vet_username'] ?? 'Veterinario') ?></span>
                            <span class="user-meta-role"><?= e_name($_SESSION['clinic_name'] ?? 'Clínica') ?></span>
                        </div>
                    </div>
                    <a href="<?= url('logout.php') ?>" class="btn-nav-outline" title="Cerrar sesión segura">Cerrar sesión</a>

                <?php elseif (!empty($_SESSION['client_id'])): ?>
                    <!-- Menú Clientes / Dueños de Mascotas -->
                    <a href="<?= url('cliente/mascotas.php') ?>" class="<?= $currentScript === 'mascotas.php' ? 'active-nav' : '' ?>">Mis mascotas</a>
                    <a href="<?= url('cliente/turnos.php') ?>" class="<?= $currentScript === 'turnos.php' ? 'active-nav' : '' ?>">Turnos</a>
                    <span class="nav-divider"></span>
                    <div class="user-profile-header">
                        <div class="user-avatar-initial" style="background: #2b7bc4;">
                            <?= strtoupper(substr(trim($_SESSION['client_name'] ?? 'T'), 0, 1)) ?>
                        </div>
                        <div class="user-meta-header">
                            <span class="user-meta-name"><?= e_name($_SESSION['client_name'] ?? 'Tutor') ?></span>
                            <span class="user-meta-role">Libreta Digital</span>
                        </div>
                    </div>
                    <a href="<?= url('cliente/logout.php') ?>" class="btn-nav-outline">Cerrar sesión</a>

                <?php elseif (!empty($_SESSION['admin_id'])): ?>
                    <!-- Menú Superadministrador -->
                    <a href="<?= url('admin/veterinarias.php') ?>" class="<?= $currentScript === 'veterinarias.php' ? 'active-nav' : '' ?>">Veterinarias</a>
                    <span class="nav-divider"></span>
                    <div class="user-profile-header">
                        <div class="user-avatar-initial" style="background: #6366f1;">
                            A
                        </div>
                        <div class="user-meta-header">
                            <span class="user-meta-name">Admin</span>
                            <span class="user-meta-role">Superadministrador</span>
                        </div>
                    </div>
                    <a href="<?= url('admin/logout.php') ?>" class="btn-nav-outline">Cerrar sesión</a>

                <?php else: ?>
                    <!-- Menú Público (Visitantes) -->
                    <a href="<?= url('index.php') ?>" class="<?= $currentScript === 'index.php' ? 'active-nav' : '' ?>">Inicio</a>
                    <a href="<?= url('migracion.php') ?>" class="nav-link-badge <?= $currentScript === 'migracion.php' ? 'active-nav' : '' ?>">
                        Migración asistida <span class="badge-pill-micro">Gratis</span>
                    </a>
                    <a href="<?= url('planes.php') ?>" class="<?= $currentScript === 'planes.php' ? 'active-nav' : '' ?>">Planes</a>
                    <a href="<?= url('nosotros.php') ?>" class="<?= $currentScript === 'nosotros.php' ? 'active-nav' : '' ?>">Quiénes somos</a>
                    <a href="<?= url('contacto.php') ?>" class="<?= $currentScript === 'contacto.php' ? 'active-nav' : '' ?>">Contacto</a>
                    
                    <div class="nav-actions">
                        <a href="<?= url('login.php?tipo=cliente') ?>" class="btn-nav-tutor">
                            <i class="fa-solid fa-paw"></i> Portal Tutores
                        </a>
                        <a href="<?= url('login.php?tipo=veterinaria') ?>" class="btn-nav-login">
                            <span class="login-dot"></span> Iniciar sesión
                        </a>
                    </div>
                <?php endif; ?>
            </nav>

            <!-- Botón Menú Móvil -->
            <button type="button" class="mobile-menu-toggle" id="mobileMenuBtn" aria-label="Abrir menú de navegación">
                <span class="hamburger-bar"></span>
                <span class="hamburger-bar"></span>
                <span class="hamburger-bar"></span>
            </button>
        </div>

        <!-- Menú Móvil Desplegable -->
        <div class="mobile-nav-drawer" id="mobileNavDrawer">
            <div class="mobile-nav-content">
                <?php if (!empty($_SESSION['clinic_id'])): ?>
                    <div style="padding: 10px 14px; background: rgba(35, 119, 101, 0.08); border-radius: 10px; margin-bottom: 8px;">
                        <strong><i class="fa-solid fa-hospital" style="color: var(--green);"></i> <?= e($_SESSION['clinic_name'] ?? 'Clínica') ?></strong>
                        <div style="font-size: 12px; color: var(--muted);">Usuario: <?= e($_SESSION['vet_username'] ?? '') ?></div>
                    </div>
                    <a href="<?= url('dashboard.php') ?>"><i class="fa-solid fa-chart-line"></i> Panel principal</a>
                    <a href="<?= url('clientes/buscar.php') ?>"><i class="fa-solid fa-magnifying-glass"></i> Buscar cliente</a>
                    <a href="<?= url('clientes/nuevo.php') ?>"><i class="fa-solid fa-user-plus"></i> Nuevo cliente</a>
                    <a href="<?= url('historial/recientes.php') ?>"><i class="fa-solid fa-clock-rotate-left"></i> Consultas recientes</a>
                    <a href="<?= url('turnos/index.php') ?>"><i class="fa-solid fa-calendar-check"></i> Gestión de turnos</a>
                    <a href="<?= url('stock/index.php') ?>"><i class="fa-solid fa-boxes-stacked"></i> Control de stock</a>
                    <a href="<?= url('logout.php') ?>" class="mobile-nav-logout"><i class="fa-solid fa-right-from-bracket"></i> Cerrar sesión</a>

                <?php elseif (!empty($_SESSION['client_id'])): ?>
                    <a href="<?= url('cliente/mascotas.php') ?>"><i class="fa-solid fa-paw"></i> Mis mascotas</a>
                    <a href="<?= url('cliente/turnos.php') ?>"><i class="fa-solid fa-calendar-check"></i> Mis turnos</a>
                    <a href="<?= url('cliente/logout.php') ?>" class="mobile-nav-logout"><i class="fa-solid fa-right-from-bracket"></i> Cerrar sesión</a>

                <?php elseif (!empty($_SESSION['admin_id'])): ?>
                    <a href="<?= url('admin/veterinarias.php') ?>"><i class="fa-solid fa-building"></i> Gestión de veterinarias</a>
                    <a href="<?= url('admin/logout.php') ?>" class="mobile-nav-logout"><i class="fa-solid fa-right-from-bracket"></i> Cerrar sesión</a>

                <?php else: ?>
                    <a href="<?= url('index.php') ?>" class="<?= $currentScript === 'index.php' ? 'active' : '' ?>"><i class="fa-solid fa-house"></i> Inicio</a>
                    <a href="<?= url('migracion.php') ?>" class="mobile-highlight <?= $currentScript === 'migracion.php' ? 'active' : '' ?>"><i class="fa-solid fa-truck-ramp-box"></i> Migración asistida sin costo</a>
                    <a href="<?= url('planes.php') ?>" class="<?= $currentScript === 'planes.php' ? 'active' : '' ?>"><i class="fa-solid fa-credit-card"></i> Planes y Precios</a>
                    <a href="<?= url('nosotros.php') ?>" class="<?= $currentScript === 'nosotros.php' ? 'active' : '' ?>"><i class="fa-solid fa-users"></i> Quiénes somos</a>
                    <a href="<?= url('contacto.php') ?>" class="<?= $currentScript === 'contacto.php' ? 'active' : '' ?>"><i class="fa-solid fa-envelope"></i> Contacto y Soporte</a>
                    <a href="<?= url('terminos.php') ?>" class="<?= $currentScript === 'terminos.php' ? 'active' : '' ?>"><i class="fa-solid fa-file-contract"></i> Términos para veterinarias</a>
                    <div class="mobile-nav-buttons">
                        <a href="<?= url('login.php?tipo=veterinaria') ?>" class="btn-mobile-login"><i class="fa-solid fa-hospital"></i> Acceso Veterinarias</a>
                        <a href="<?= url('login.php?tipo=cliente') ?>" class="btn-nav-tutor" style="width: 100%; justify-content: center; margin-top: 8px;"><i class="fa-solid fa-paw"></i> Portal Tutores</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </header>
    
    <main class="<?= !empty($isLanding) ? 'main-landing' : 'container' ?>">
        <?php show_flash(); ?>
