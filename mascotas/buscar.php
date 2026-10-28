<?php
require_once __DIR__ . '/../config/config.php';
require_vet();

const BUSCAR_MASCOTA_MAX = 50;

$results = [];
$term = trim($_GET['q'] ?? '');
$byId = $term !== '' && ctype_digit($term);

// Buscar por ID exacto (si es un número) o por nombre (coincidencia parcial)
if ($term !== '') {
    $where = $byId ? 'p.id = ?' : 'p.name LIKE ?';
    $param = $byId ? (int)$term : '%' . addcslashes($term, '%_\\') . '%';
    $q = db()->prepare("
        SELECT p.*, c.id owner_id, c.full_name owner_name, c.dni owner_dni,
               (SELECT GROUP_CONCAT(CONCAT(cc.full_name, ' (DNI ', cc.dni, ')') ORDER BY pco.id SEPARATOR ', ')
                FROM pet_co_owners pco JOIN clients cc ON cc.id = pco.client_id
                WHERE pco.pet_id = p.id AND cc.id <> p.client_id) co_owners
        FROM pets p
        JOIN clients c ON c.id = p.client_id
        WHERE $where
        ORDER BY p.name, p.id
        LIMIT " . BUSCAR_MASCOTA_MAX);
    $q->execute([$param]);
    $results = $q->fetchAll();
}

$pageTitle = 'Buscar Mascota | ' . APP_NAME;
require __DIR__ . '/../includes/header.php';
?>

<div class="panel">
    <h1><i class="fa-solid fa-paw" style="color: var(--green); margin-right: 8px;"></i>Buscar mascota</h1>
    <p class="muted">Ingresá el nombre o el ID de la mascota para abrir su ficha clínica.</p>

    <form method="get" style="margin-top: 18px;">
        <label for="pet_search_input">Nombre o ID de la mascota</label>
        <div style="display: flex; gap: 12px; max-width: 520px; align-items: center;">
            <input id="pet_search_input" name="q" value="<?= e($term) ?>" required maxlength="100" placeholder="Ej. Apolo o 2" style="margin-top: 0; font-size: 15px;">
            <button type="submit" style="flex-shrink: 0;">
                <i class="fa-solid fa-magnifying-glass" style="margin-right: 4px;"></i> Buscar
            </button>
        </div>

        <div class="actions" style="margin-top: 20px;">
            <a class="btn secondary" href="<?= url('clientes/buscar.php') ?>">
                <i class="fa-solid fa-user" style="margin-right: 6px;"></i> Buscar cliente
            </a>
            <a class="btn secondary" href="<?= url('dashboard.php') ?>">
                <i class="fa-solid fa-arrow-left" style="margin-right: 6px;"></i> Volver al panel
            </a>
        </div>
    </form>
</div>

<?php if ($term !== ''): ?>
    <div class="card">
        <h2>Resultados de búsqueda</h2>

        <?php if (!$results): ?>
            <div style="padding: 16px; background: #fffbf0; border: 1px solid #f6e0b5; border-radius: 12px; color: #8f6208;">
                <p style="margin: 0;"><i class="fa-solid fa-triangle-exclamation" style="margin-right: 6px;"></i>
                    <?= $byId ? 'No hay ninguna mascota con el ID' : 'No se encontró ninguna mascota con el nombre' ?> <strong><?= e($term) ?></strong>.
                </p>
            </div>
        <?php else: ?>
            <?php if (count($results) === BUSCAR_MASCOTA_MAX): ?>
                <p class="muted">Se muestran las primeras <?= BUSCAR_MASCOTA_MAX ?> coincidencias; escribí más del nombre para afinar.</p>
            <?php endif; ?>
            <?php foreach ($results as $pet): ?>
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 18px 20px; background: var(--paper); border: 1px solid var(--line); border-radius: 14px; margin-bottom: 12px; flex-wrap: wrap; gap: 14px;">
                    <div style="display: flex; gap: 14px; align-items: center; min-width: 0;">
                        <img src="<?= pet_photo_url($pet) ?>" alt="Foto de <?= e_name($pet['name']) ?>" style="width: 52px; height: 52px; border-radius: 12px; object-fit: cover; flex-shrink: 0;">
                        <div style="min-width: 0;">
                            <div style="font-size: 17px; font-weight: 800; font-family: 'Manrope', sans-serif; color: var(--ink);">
                                <?= e_name($pet['name']) ?> <span class="muted" style="font-size: 13px; font-weight: 600;">ID #<?= (int)$pet['id'] ?></span>
                            </div>
                            <div class="muted" style="font-size: 13.5px; margin-top: 4px;">
                                <?= e_name($pet['animal_type']) ?> · <?= e_name($pet['breed'] ?: 'Mestizo / No especificado') ?>
                            </div>
                            <div class="muted" style="font-size: 13.5px; margin-top: 2px;">
                                Tutor: <strong><?= e_name($pet['owner_name']) ?></strong> (DNI <?= e($pet['owner_dni']) ?>)
                                <?php if ($pet['co_owners']): ?>
                                    · Co-titular: <?= e($pet['co_owners']) ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <a class="btn" href="<?= url('mascotas/ver.php?id=' . (int)$pet['id']) ?>">
                        <span>Abrir ficha clínica</span>
                        <i class="fa-solid fa-arrow-right" style="margin-left: 6px;"></i>
                    </a>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
