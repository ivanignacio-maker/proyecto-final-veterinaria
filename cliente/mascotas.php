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
    <h1>Hola, <?= e($c['full_name']) ?></h1>
    <p>DNI <?= e($c['dni']) ?> · Tus mascotas y sus historiales clínicos.</p>
    
    <div class="actions">
        <a class="btn" href="<?= url('cliente/agregar_mascota.php') ?>">Agregar mascota</a>
        <a class="btn secondary" href="<?= url('cliente/turnos.php') ?>">Solicitar turno</a>
    </div>
</div>

<div class="grid">
    <?php foreach ($pets as $p): ?>
        <div class="card">
            <?php if ($p['photo_path']): ?>
                <img class="pet-photo" src="<?= url('archivo_mascota.php?id=' . (int)$p['id']) ?>" alt="Foto de mascota">
            <?php endif; ?>
            
            <span class="pill">ID #<?= (int)$p['id'] ?></span>
            <h2><?= e($p['name']) ?></h2>
            
            <p><?= e($p['animal_type']) ?> · <?= e($p['breed']) ?></p>
            <p>Peso: <?= e((string)$p['weight_kg']) ?> kg · Altura: <?= e((string)$p['height_cm']) ?> cm</p>
            
            <a class="btn" href="<?= url('cliente/historial.php?pet_id=' . (int)$p['id']) ?>">Ver historial</a>
        </div>
    <?php endforeach; ?>
</div>

<?php if (!$pets): ?>
    <div class="card">
        <p>Todavía no agregaste mascotas.</p>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>