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

$pageTitle = e_name($c['full_name']) . ' | Ficha de Cliente';
require __DIR__ . '/../includes/header.php';
?>

<div class="panel">
    <div style="display: flex; gap: 22px; align-items: center; flex-wrap: wrap;">
        <!-- Avatar Profesional para el Perfil del Dueño -->
        <div style="width: 68px; height: 68px; border-radius: 50%; background: #eaf5ef; border: 2px solid var(--green); display: grid; place-items: center; color: var(--green); box-shadow: 0 4px 14px rgba(35, 119, 101, 0.15); flex-shrink: 0; font-size: 28px;">
            <i class="fa-solid fa-user"></i>
        </div>
        
        <div style="flex: 1; min-width: 240px;">
            <div style="display: flex; gap: 10px; align-items: center; margin-bottom: 4px;">
                <span class="pill"><i class="fa-solid fa-id-badge" style="margin-right: 4px;"></i> Tutor Registrado</span>
                <span class="muted" style="font-size: 13px;">ID #<?= (int)$c['id'] ?></span>
            </div>
            <h1 style="margin: 0 0 6px; font-size: 28px;"><?= e_name($c['full_name']) ?></h1>
            <p class="muted" style="margin: 0; font-size: 14.5px;">
                DNI: <strong><?= e($c['dni']) ?></strong> · 
                <?= e($c['email']) ?> · 
                Tel: <?= e($c['phone']) ?>
            </p>
        </div>
    </div>
    
    <div class="actions" style="margin-top: 24px; padding-top: 18px; border-top: 1px solid var(--line);">
        <a class="btn primary" href="<?= url('mascotas/nueva.php?client_id=' . $id) ?>">
            <i class="fa-solid fa-plus" style="margin-right: 6px;"></i> Registrar mascota
        </a>
        <a class="btn secondary" href="<?= url('clientes/buscar.php') ?>">
            <i class="fa-solid fa-magnifying-glass" style="margin-right: 6px;"></i> Buscar cliente
        </a>
        <a class="btn secondary" href="<?= url('dashboard.php') ?>">
            <i class="fa-solid fa-arrow-left" style="margin-right: 6px;"></i> Panel principal
        </a>
    </div>
</div>

<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 10px;">
        <h2 style="margin: 0;"><i class="fa-solid fa-paw" style="color: var(--green); margin-right: 8px;"></i>Mascotas a cargo (<?= count($pets) ?>)</h2>
        <a class="btn secondary" href="<?= url('mascotas/nueva.php?client_id=' . $id) ?>" style="font-size: 13px; padding: 8px 14px;">
            <i class="fa-solid fa-plus" style="margin-right: 4px;"></i> Nueva mascota
        </a>
    </div>
    
    <?php if (!$pets): ?>
        <div style="text-align: center; padding: 35px 20px; background: var(--paper); border-radius: 14px; border: 1.5px dashed var(--line);">
            <span style="font-size: 38px; color: var(--green); display: block; margin-bottom: 10px;"><i class="fa-solid fa-paw"></i></span>
            <p style="margin: 0 0 14px; color: var(--muted); font-size: 15px;">Este tutor todavía no tiene mascotas registradas.</p>
            <a class="btn" href="<?= url('mascotas/nueva.php?client_id=' . $id) ?>">
                <i class="fa-solid fa-plus" style="margin-right: 6px;"></i> Agregar primera mascota
            </a>
        </div>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Foto</th>
                    <th>Nombre</th>
                    <th>Especie</th>
                    <th>Raza</th>
                    <th>Peso / Altura</th>
                    <th>Acción</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pets as $p): ?>
                    <tr>
                        <td style="width: 60px;">
                            <?php if ($p['photo_path']): ?>
                                <img src="<?= url('archivo_mascota.php?id=' . (int)$p['id']) ?>" alt="Foto" style="width: 44px; height: 44px; border-radius: 10px; object-fit: cover; display: block;">
                            <?php else: ?>
                                <div style="width: 44px; height: 44px; border-radius: 10px; background: #eaf5ef; display: grid; place-items: center; font-size: 18px; color: var(--green);">
                                    <i class="fa-solid fa-paw"></i>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong style="font-size: 15px; color: var(--ink);"><?= e_name($p['name']) ?></strong>
                            <div class="muted" style="font-size: 12px;">ID #<?= (int)$p['id'] ?></div>
                        </td>
                        <td><span class="pill"><?= e_name($p['animal_type']) ?></span></td>
                        <td><?= e_name($p['breed'] ?: 'Mestizo / No especificado') ?></td>
                        <td>
                            <?= $p['weight_kg'] ? e((string)$p['weight_kg']) . ' kg' : '-' ?> · 
                            <?= $p['height_cm'] ? e((string)$p['height_cm']) . ' cm' : '-' ?>
                        </td>
                        <td>
                            <a class="btn secondary" href="<?= url('mascotas/ver.php?id=' . (int)$p['id']) ?>" style="font-size: 13px; padding: 8px 14px;">
                                <span>Abrir historial clínico</span>
                                <i class="fa-solid fa-arrow-right" style="margin-left: 6px;"></i>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
