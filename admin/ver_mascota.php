<?php
/**
 * admin/ver_mascota.php
 *
 * Historia médica de una mascota, SOLO LECTURA, para el administrador.
 * (mascotas/ver.php es de las veterinarias: exige sesión de veterinaria y por eso
 * al admin lo mandaba de vuelta a admin/veterinarias.php.)
 */
require_once __DIR__ . '/../config/config.php';
require_admin();

$id = (int)($_GET['id'] ?? 0);

// 1. Datos de la mascota y su tutor principal
$q = db()->prepare("
    SELECT p.*, c.full_name AS owner_name, c.dni AS owner_dni
    FROM pets p
    JOIN clients c ON c.id = p.client_id
    WHERE p.id = ?
");
$q->execute([$id]);
$p = $q->fetch();

if (!$p) {
    flash('La mascota no existe o fue eliminada.');
    header('Location: ' . url('admin/clientes.php'));
    exit;
}

// 2. Todos los tutores (principal + co-tutores)
$owners = get_pet_owners($id);

// 3. Consultas de la mascota, de todas las clínicas
$q = db()->prepare("
    SELECT co.*, cl.name AS clinic_name, vu.username AS vet_name
    FROM consultations co
    JOIN clinics cl ON cl.id = co.clinic_id
    LEFT JOIN vet_users vu ON vu.id = co.vet_user_id
    WHERE co.pet_id = ?
    ORDER BY co.created_at DESC
");
$q->execute([$id]);
$consults = $q->fetchAll();

// Medicación y adjuntos de cada consulta (se consultan una vez por consulta)
$medQ = db()->prepare("
    SELECT cm.other_medication, cm.purchase_needed, m.name AS medication_name
    FROM consultation_medications cm
    LEFT JOIN medications m ON m.id = cm.medication_id
    WHERE cm.consultation_id = ?
");
$fileQ = db()->prepare("
    SELECT id, original_name, mime_type, created_at
    FROM consultation_files
    WHERE consultation_id = ?
    ORDER BY created_at DESC
");

$pageTitle = 'Historia médica de ' . $p['name'];
require __DIR__ . '/../includes/header.php';
?>

<div class="panel">
    <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 20px; flex-wrap: wrap;">
        <div style="display: flex; align-items: center; gap: 20px; flex-wrap: wrap;">
            <img src="<?= pet_photo_url($p) ?>" alt="Foto de <?= e_name($p['name']) ?>"
                 style="width: 86px; height: 86px; border-radius: 20px; object-fit: cover; border: 2.5px solid var(--green); display: block;">
            <div>
                <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap; margin-bottom: 6px;">
                    <span class="pill" style="font-family: monospace; font-weight: 700;">#<?= (int)$p['id'] ?></span>
                    <span class="badge" style="background: var(--mint); color: var(--green); font-weight: 700;">
                        <i class="fa-solid fa-paw" style="margin-right: 4px;"></i> <?= e_name($p['animal_type']) ?>
                    </span>
                    <?php if (!empty($p['sexo'])): ?>
                        <span class="badge" style="background: var(--paper); border: 1px solid var(--line); color: var(--ink);">
                            <i class="fa-solid <?= $p['sexo'] === 'Hembra' ? 'fa-venus' : 'fa-mars' ?>" style="color: var(--green); margin-right: 4px;"></i>
                            <?= e($p['sexo']) ?>
                        </span>
                    <?php endif; ?>
                </div>
                <h1 class="name-titlecase" style="margin: 0 0 6px; font-size: 30px;"><?= e_name($p['name']) ?></h1>
                <p class="muted" style="margin: 0; font-size: 14px;">
                    <?= $p['breed'] ? 'Raza: <strong>' . e_name($p['breed']) . '</strong>' : 'Raza: <em>Mestizo / No especificada</em>' ?>
                    · Peso: <strong><?= $p['weight_kg'] ? e((string)$p['weight_kg']) . ' kg' : 'No registrado' ?></strong>
                    · Altura: <strong><?= $p['height_cm'] ? e((string)$p['height_cm']) . ' cm' : 'No registrada' ?></strong>
                    · Nacimiento: <strong><?= $p['birth_date'] ? date('d/m/Y', strtotime($p['birth_date'])) : 'No especificado' ?></strong>
                </p>
            </div>
        </div>

        <a class="btn secondary" href="<?= url('admin/editar_cliente.php?id=' . (int)$p['client_id']) ?>">
            <i class="fa-solid fa-arrow-left" style="margin-right: 6px;"></i> Volver al tutor
        </a>
    </div>

    <div style="margin-top: 16px; padding-top: 14px; border-top: 1px solid var(--line);">
        <span class="muted" style="font-size: 12px; text-transform: uppercase; font-weight: 700; display: block; margin-bottom: 8px;">
            <i class="fa-solid fa-users" style="color: var(--green); margin-right: 4px;"></i> Tutores responsables
        </span>
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <?php foreach ($owners as $ow): ?>
                <span class="pill" style="font-size: 13px; padding: 6px 12px;">
                    <i class="fa-solid fa-user" style="color: var(--green); margin-right: 4px;"></i>
                    <?= e_name($ow['full_name']) ?> (DNI: <?= e($ow['dni']) ?>)
                    <?= (int)$ow['is_primary'] === 1 ? '<strong style="color: var(--green); font-size: 11px;">(Principal)</strong>' : '<span class="muted" style="font-size: 11px;">(Co-tutor)</span>' ?>
                </span>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div style="margin-top: 24px;">
    <h2 style="font-size: 22px; color: var(--ink); margin-bottom: 16px;">
        <i class="fa-solid fa-book-medical" style="color: var(--green); margin-right: 8px;"></i>Historial médico y atenciones (<?= count($consults) ?>)
    </h2>

    <?php if (!$consults): ?>
        <div class="card" style="text-align: center; padding: 40px 20px;">
            <span style="font-size: 40px; color: var(--green); display: block; margin-bottom: 12px;"><i class="fa-solid fa-clipboard-list"></i></span>
            <h3>Esta mascota todavía no tiene consultas registradas</h3>
        </div>
    <?php endif; ?>

    <?php foreach ($consults as $c): ?>
        <?php
            $medQ->execute([$c['id']]);
            $meds = $medQ->fetchAll();
            $fileQ->execute([$c['id']]);
            $files = $fileQ->fetchAll();
            $statusClass = $c['status'] === 'Dado de Alta' ? 'badge-success' : 'badge-warning';
        ?>
        <div class="card" style="margin-bottom: 20px; padding: 24px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; flex-wrap: wrap; gap: 8px;">
                <span class="badge <?= $statusClass ?>"><?= e($c['status']) ?></span>
                <span class="muted" style="font-size: 13px;">
                    <i class="fa-solid fa-calendar-day" style="margin-right: 4px;"></i><?= date('d/m/Y H:i', strtotime($c['created_at'])) ?> hs
                </span>
            </div>

            <h3 style="margin: 4px 0 8px; font-size: 20px;"><?= e($c['reason']) ?></h3>
            <p class="muted name-titlecase" style="margin-bottom: 16px; font-size: 14px;">
                <i class="fa-solid fa-hospital" style="color: var(--green); margin-right: 4px;"></i>
                Atendido en <strong><?= e_name($c['clinic_name']) ?></strong>
                <?php if (!empty($c['vet_name'])): ?> por <strong><?= e_name($c['vet_name']) ?></strong><?php endif; ?>
            </p>

            <div style="background: #fcfdfc; border: 1px solid var(--line); border-radius: 12px; padding: 18px; margin-bottom: 14px;">
                <p style="margin: 0 0 12px;"><strong>Evolución y notas:</strong><br><?= nl2br(e($c['notes'])) ?></p>
                <?php if ($c['diagnosis']): ?>
                    <p style="margin: 0 0 12px;"><strong>Diagnóstico:</strong><br><?= nl2br(e($c['diagnosis'])) ?></p>
                <?php endif; ?>
                <?php if ($c['treatment']): ?>
                    <p style="margin: 0;"><strong>Indicaciones y tratamiento:</strong><br><?= nl2br(e($c['treatment'])) ?></p>
                <?php endif; ?>
            </div>

            <?php if ($c['vaccine_expiry']): ?>
                <div class="notice" style="margin-bottom: 14px;">
                    <i class="fa-solid fa-syringe" style="margin-right: 6px;"></i><strong>Vacuna aplicada</strong>
                    · Vencimiento: <?= date('d/m/Y', strtotime($c['vaccine_expiry'])) ?>
                    <?= $c['next_dose'] ? '· Próxima dosis: ' . date('d/m/Y', strtotime($c['next_dose'])) : '' ?>
                </div>
            <?php endif; ?>

            <?php foreach ($meds as $m): ?>
                <p class="muted" style="margin: 0 0 6px; font-size: 13.5px;">
                    <i class="fa-solid fa-pills" style="color: var(--green); margin-right: 6px;"></i>
                    Medicación: <strong><?= e($m['medication_name'] ?: $m['other_medication'] ?: 'Sin especificar') ?></strong>
                    <?= (int)$m['purchase_needed'] === 1 ? '(el tutor debe comprarla)' : '' ?>
                </p>
            <?php endforeach; ?>

            <?php if ($files): ?>
                <div style="display: flex; gap: 10px; flex-wrap: wrap; margin-top: 12px;">
                    <?php foreach ($files as $f): ?>
                        <a class="btn secondary btn-sm" target="_blank" rel="noopener"
                           href="<?= url('archivo_consulta.php?id=' . (int)$f['id'] . '&preview=1') ?>">
                            <i class="fa-solid <?= $f['mime_type'] === 'application/pdf' ? 'fa-file-pdf' : 'fa-file-image' ?>" style="color: var(--green); margin-right: 4px;"></i>
                            <?= e($f['original_name']) ?>
                            <span class="muted" style="font-size: 11px;">(<?= date('d/m/y', strtotime($f['created_at'])) ?>)</span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
