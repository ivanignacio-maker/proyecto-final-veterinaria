<?php
require_once __DIR__ . '/../config/config.php';
require_vet();

$id = (int)($_GET['id'] ?? 0);

// 1. Obtener los datos de la mascota y su dueño
$q = db()->prepare("
    SELECT p.*, c.full_name, c.dni, c.id client_id 
    FROM pets p 
    JOIN clients c ON c.id = p.client_id 
    WHERE p.id = ?
");
$q->execute([$id]);
$p = $q->fetch();

if (!$p) {
    not_found();
}

// 2. Obtener el historial clínico (consultas en todas las clínicas vinculadas)
$q = db()->prepare("
    SELECT co.*, cl.name clinic_name, vu.username 
    FROM consultations co 
    JOIN clinics cl ON cl.id = co.clinic_id 
    JOIN vet_users vu ON vu.id = co.vet_user_id 
    WHERE co.pet_id = ? 
    ORDER BY co.created_at DESC
");
$q->execute([$id]);
$consults = $q->fetchAll();

$pageTitle = 'Historial de mascota';
require __DIR__ . '/../includes/header.php';
?>

<!-- Ficha de la mascota: foto a la mitad del bloque y los datos apilados al costado -->
<div class="panel">
    <div class="pet-profile-header is-photo-75">
        <div class="pet-profile-photo-frame">
            <img class="pet-photo" src="<?= pet_photo_url($p) ?>" alt="Foto de <?= e_name($p['name']) ?>">
        </div>

        <div class="pet-profile-info">
            <div class="pet-profile-heading">
                <div class="pet-profile-tags">
                    <span class="pill" style="font-family: monospace; font-weight: 700;">ID de mascota: #<?= (int)$p['id'] ?></span>
                    <span class="badge" style="background: var(--mint); color: var(--green); font-weight: 700;">
                        <i class="fa-solid fa-book-medical" style="margin-right: 4px;"></i> Historial clínico
                    </span>
                </div>
                <h1 class="name-titlecase"><?= e_name($p['name']) ?></h1>
            </div>

            <div class="pet-shelves">
                <div class="pet-shelf">
                    <span class="pet-shelf-label"><i class="fa-solid fa-paw"></i> Especie</span>
                    <span class="pet-shelf-value"><?= e_name($p['animal_type']) ?></span>
                </div>
                <div class="pet-shelf">
                    <span class="pet-shelf-label"><i class="fa-solid <?= ($p['sexo'] ?? '') === 'Hembra' ? 'fa-venus' : 'fa-mars' ?>"></i> Sexo</span>
                    <span class="pet-shelf-value"><?= e($p['sexo'] ?: 'No registrado') ?></span>
                </div>
                <div class="pet-shelf">
                    <span class="pet-shelf-label"><i class="fa-solid fa-dna"></i> Raza</span>
                    <span class="pet-shelf-value"><?= e_name($p['breed'] ?: 'Mestizo') ?></span>
                </div>
                <div class="pet-shelf">
                    <span class="pet-shelf-label"><i class="fa-solid fa-weight-scale"></i> Peso</span>
                    <span class="pet-shelf-value"><?= $p['weight_kg'] ? e((string)$p['weight_kg']) . ' kg' : 'No registrado' ?></span>
                </div>
                <div class="pet-shelf">
                    <span class="pet-shelf-label"><i class="fa-solid fa-ruler-vertical"></i> Altura</span>
                    <span class="pet-shelf-value"><?= $p['height_cm'] ? e((string)$p['height_cm']) . ' cm' : 'No registrada' ?></span>
                </div>
                <div class="pet-shelf">
                    <span class="pet-shelf-label"><i class="fa-solid fa-cake-candles"></i> Nacimiento</span>
                    <span class="pet-shelf-value"><?= $p['birth_date'] ? date('d/m/Y', strtotime($p['birth_date'])) : 'No especificada' ?></span>
                </div>
                <div class="pet-shelf pet-shelf-stack">
                    <span class="pet-shelf-label"><i class="fa-solid fa-user"></i> Dueño</span>
                    <div class="pet-shelf-value">
                        <span class="pet-shelf-owner">
                            <span class="name-titlecase"><?= e_name($p['full_name']) ?></span>
                            <small>DNI <?= e($p['dni']) ?></small>
                        </span>
                    </div>
                </div>
            </div>

            <div class="pet-profile-actions">
                <a class="btn" href="<?= url('consultas/nueva.php?pet_id=' . $id) ?>">Agregar consulta</a>
                <a class="btn secondary" href="<?= url('clientes/ver.php?id=' . (int)$p['client_id']) ?>">Volver al cliente</a>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <h2>Historial clínico</h2>
    
    <?php if (!$consults): ?>
        <p>No hay consultas registradas.</p>
    <?php else: ?>
        <?php foreach ($consults as $c): ?>
            <div class="panel">
                <div class="actions">
                    <span class="pill"><?= e($c['status']) ?></span>
                    <span class="muted"><?= e($c['created_at']) ?> · <?= e($c['clinic_name']) ?></span>
                </div>
                
                <h3><?= e($c['reason']) ?></h3>
                
                <p><strong>Motivo / notas:</strong> <?= nl2br(e($c['notes'])) ?></p>
                
                <?php if ($c['diagnosis']): ?>
                    <p><strong>Diagnóstico:</strong> <?= nl2br(e($c['diagnosis'])) ?></p>
                <?php endif; ?>
                
                <?php if ($c['treatment']): ?>
                    <p><strong>Tratamiento:</strong> <?= nl2br(e($c['treatment'])) ?></p>
                <?php endif; ?>
                
                <?php if ($c['vaccine_expiry']): ?>
                    <p>
                        Vencimiento de vacuna: <?= e($c['vaccine_expiry']) ?> · 
                        Próxima dosis: <?= e($c['next_dose']) ?>
                    </p>
                <?php endif; ?>
                
                <a class="btn secondary" href="<?= url('consultas/ver.php?id=' . (int)$c['id']) ?>">Ver consulta y archivos</a>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>