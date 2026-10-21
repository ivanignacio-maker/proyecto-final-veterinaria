<?php
require_once __DIR__ . '/../config/config.php';
require_client();

$id = (int)$_SESSION['client_id'];

// 1. Obtener la información del cliente
$q = db()->prepare("SELECT * FROM clients WHERE id = ?");
$q->execute([$id]);
$c = $q->fetch();

// 2. Obtener el listado de mascotas del cliente
$q = db()->prepare("SELECT * FROM pets WHERE client_id = ? ORDER BY name");
$q->execute([$id]);
$pets = $q->fetchAll();

$pageTitle = 'Mis mascotas';
require __DIR__ . '/../includes/header.php';
?>

<div class="panel">
    <div style="display: flex; align-items: center; gap: 18px; flex-wrap: wrap;">
        <div style="width: 58px; height: 58px; border-radius: 50%; background: var(--mint); color: var(--green); display: grid; place-items: center; font-size: 28px; border: 1.5px solid rgba(35, 119, 101, 0.2);">
            👤
        </div>
        <div>
            <h1 style="margin: 0;" class="name-titlecase">Hola, <?= e_name($c['full_name']) ?></h1>
            <p class="muted" style="margin: 4px 0 0;">DNI <?= e($c['dni']) ?> · Libreta sanitaria y seguimiento médico de tus mascotas.</p>
        </div>
    </div>
    
    <div class="actions" style="margin-top: 24px;">
        <a class="btn primary" href="<?= url('cliente/agregar_mascota.php') ?>">+ Registrar mascota</a>
        <a class="btn secondary" href="<?= url('cliente/turnos.php') ?>">📅 Solicitar turno</a>
    </div>
</div>

<div class="grid" style="margin-top: 28px;">
    <?php foreach ($pets as $p): ?>
        <div class="card" style="display: flex; flex-direction: column; justify-content: space-between;">
            <div>
                <?php if ($p['photo_path']): ?>
                    <img class="pet-photo" src="<?= url('archivo_mascota.php?id=' . (int)$p['id']) ?>" alt="Foto de <?= e_name($p['name']) ?>">
                <?php else: ?>
                    <div style="width: 64px; height: 64px; border-radius: 16px; background: var(--mint); color: var(--green); display: grid; place-items: center; font-size: 28px; margin-bottom: 14px;">
                        🐾
                    </div>
                <?php endif; ?>
                
                <span class="pill">ID #<?= (int)$p['id'] ?></span>
                <h2 class="name-titlecase" style="margin: 8px 0 4px;"><?= e_name($p['name']) ?></h2>
                
                <p class="name-titlecase" style="font-weight: 600; color: var(--green); margin: 0 0 8px;">
                    <?= e_name($p['animal_type']) ?> <?= $p['breed'] ? '· ' . e_name($p['breed']) : '' ?>
                </p>
                
                <div style="font-size: 13px; color: var(--muted); line-height: 1.6; margin-bottom: 16px;">
                    <div>⚖️ Peso: <strong><?= e((string)($p['weight_kg'] ?: 'No registrado')) ?></strong> <?= $p['weight_kg'] ? 'kg' : '' ?></div>
                    <div>📏 Altura: <strong><?= e((string)($p['height_cm'] ?: 'No registrada')) ?></strong> <?= $p['height_cm'] ? 'cm' : '' ?></div>
                </div>
            </div>
            
            <a class="btn secondary full" href="<?= url('cliente/historial.php?pet_id=' . (int)$p['id']) ?>">
                Ver historial médico →
            </a>
        </div>
    <?php endforeach; ?>
</div>

<?php if (!$pets): ?>
    <div class="card" style="text-align: center; padding: 40px 20px;">
        <span style="font-size: 40px; display: block; margin-bottom: 12px;">🐾</span>
        <h3>Todavía no registraste ninguna mascota</h3>
        <p class="muted">Hacé clic en el botón superior para dar de alta a tu primer animal de compañía.</p>
        <div style="margin-top: 18px;">
            <a class="btn primary" href="<?= url('cliente/agregar_mascota.php') ?>">+ Registrar mi mascota</a>
        </div>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>