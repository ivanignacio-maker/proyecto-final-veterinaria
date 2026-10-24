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
    http_response_code(404);
    exit('Mascota no encontrada');
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

$pageTitle = e_name($p['name']) . ' · Historial Clínico | ' . APP_NAME;
require __DIR__ . '/../includes/header.php';
?>

<div class="panel">
    <div style="display: flex; gap: 26px; align-items: flex-start; flex-wrap: wrap;">
        <!-- Foto de Mascota -->
        <div style="flex-shrink: 0;">
            <img class="pet-photo" src="<?= !empty($p['photo_path']) ? url('archivo_mascota.php?id=' . (int)$p['id']) : url('assets/perro_generico.png') ?>" alt="Foto de <?= e_name($p['name']) ?>" style="width: 140px; height: 140px; border-radius: 20px; object-fit: cover;">
        </div>
        
        <div style="flex: 1; min-width: 270px;">
            <div style="display: flex; gap: 8px; align-items: center; margin-bottom: 6px;">
                <span class="pill">Ficha Clínica #<?= (int)$p['id'] ?></span>
            </div>

            <!-- Nombre de la mascota -->
            <h1 style="margin: 0 0 6px; font-size: 30px;"><?= e_name($p['name']) ?></h1>
            
            <!-- Dueño responsable situado inmediatamente debajo del nombre -->
            <p style="margin: 0 0 18px; font-size: 15px; color: var(--ink);">
                <strong><i class="fa-solid fa-user" style="color: var(--green); margin-right: 4px;"></i> Tutor responsable:</strong> 
                <a href="<?= url('clientes/ver.php?id=' . (int)$p['client_id']) ?>" style="color: var(--green); text-decoration: underline; font-weight: 700;">
                    <?= e_name($p['full_name']) ?>
                </a> 
                (DNI: <strong><?= e($p['dni']) ?></strong>)
            </p>
            
            <!-- Datos del animal situados uno arriba del otro -->
            <div style="background: var(--paper); border: 1px solid var(--line); border-radius: 16px; padding: 16px 20px; max-width: 480px; display: flex; flex-direction: column; gap: 10px;">
                <div style="display: flex; justify-content: space-between; font-size: 14px; border-bottom: 1px dashed var(--line); padding-bottom: 6px;">
                    <span class="muted">Especie:</span>
                    <strong style="color: var(--ink);"><?= e_name($p['animal_type']) ?></strong>
                </div>
                <div style="display: flex; justify-content: space-between; font-size: 14px; border-bottom: 1px dashed var(--line); padding-bottom: 6px;">
                    <span class="muted">Raza:</span>
                    <strong style="color: var(--ink);"><?= e_name($p['breed'] ?: 'Mestizo / No especificado') ?></strong>
                </div>
                <div style="display: flex; justify-content: space-between; font-size: 14px; border-bottom: 1px dashed var(--line); padding-bottom: 6px;">
                    <span class="muted">Peso actual:</span>
                    <strong style="color: var(--ink);"><?= $p['weight_kg'] ? e((string)$p['weight_kg']) . ' kg' : 'No registrado' ?></strong>
                </div>
                <div style="display: flex; justify-content: space-between; font-size: 14px; border-bottom: 1px dashed var(--line); padding-bottom: 6px;">
                    <span class="muted">Altura:</span>
                    <strong style="color: var(--ink);"><?= $p['height_cm'] ? e((string)$p['height_cm']) . ' cm' : 'No registrada' ?></strong>
                </div>
                <div style="display: flex; justify-content: space-between; font-size: 14px;">
                    <span class="muted">Fecha de nacimiento:</span>
                    <strong style="color: var(--ink);"><?= $p['birth_date'] ? e($p['birth_date']) : 'No especificada' ?></strong>
                </div>
            </div>
        </div>
    </div>
    
    <div class="actions" style="margin-top: 24px; padding-top: 18px; border-top: 1px solid var(--line);">
        <a class="btn primary" href="<?= url('consultas/nueva.php?pet_id=' . $id) ?>">
            <i class="fa-solid fa-plus" style="margin-right: 6px;"></i> Agregar consulta médica
        </a>
        <a class="btn secondary" href="<?= url('clientes/ver.php?id=' . (int)$p['client_id']) ?>">
            <i class="fa-solid fa-arrow-left" style="margin-right: 6px;"></i> Volver a la ficha del tutor
        </a>
        <a class="btn secondary" href="<?= url('dashboard.php') ?>">
            <i class="fa-solid fa-house" style="margin-right: 6px;"></i> Panel principal
        </a>
    </div>
</div>

<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 10px;">
        <h2 style="margin: 0;"><i class="fa-solid fa-notes-medical" style="color: var(--green); margin-right: 8px;"></i>Historial Clínico Unificado (<?= count($consults) ?>)</h2>
        <a class="btn secondary" href="<?= url('consultas/nueva.php?pet_id=' . $id) ?>" style="font-size: 13px; padding: 8px 14px;">
            <i class="fa-solid fa-plus" style="margin-right: 4px;"></i> Nueva consulta
        </a>
    </div>
    
    <?php if (!$consults): ?>
        <div style="text-align: center; padding: 35px 20px; background: var(--paper); border-radius: 14px; border: 1.5px dashed var(--line);">
            <p style="margin: 0 0 12px; color: var(--muted); font-size: 15px;">No hay consultas médicas asentadas para esta mascota.</p>
            <a class="btn primary" href="<?= url('consultas/nueva.php?pet_id=' . $id) ?>">
                <i class="fa-solid fa-plus" style="margin-right: 6px;"></i> Asentar primera consulta
            </a>
        </div>
    <?php else: ?>
        <?php foreach ($consults as $c): ?>
            <div class="panel" style="border-left: 4px solid var(--green); margin-bottom: 18px; padding: 22px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; flex-wrap: wrap; gap: 10px;">
                    <div style="display: flex; gap: 8px; align-items: center;">
                        <span class="pill"><?= e($c['status']) ?></span>
                        <strong style="color: var(--green-dark); font-size: 14px;"><i class="fa-solid fa-hospital" style="color: var(--green); margin-right: 4px;"></i> <?= e_name($c['clinic_name']) ?></strong>
                    </div>
                    <span class="muted" style="font-size: 13px;">
                        <i class="fa-solid fa-calendar-day" style="margin-right: 4px;"></i> <?= e($c['created_at']) ?> · Atendió Dr/a. <?= e_name($c['username']) ?>
                    </span>
                </div>
                
                <h3 style="margin: 0 0 8px; font-size: 18px;"><?= e($c['reason']) ?></h3>
                
                <p style="margin: 0 0 10px; font-size: 14.5px;"><strong>Motivo / notas:</strong> <?= nl2br(e($c['notes'])) ?></p>
                
                <?php if ($c['diagnosis']): ?>
                    <p style="margin: 0 0 10px; font-size: 14.5px;"><strong>Diagnóstico:</strong> <?= nl2br(e($c['diagnosis'])) ?></p>
                <?php endif; ?>
                
                <?php if ($c['treatment']): ?>
                    <p style="margin: 0 0 10px; font-size: 14.5px;"><strong>Tratamiento:</strong> <?= nl2br(e($c['treatment'])) ?></p>
                <?php endif; ?>
                
                <?php if ($c['vaccine_expiry']): ?>
                    <div style="background: var(--paper); border: 1px solid var(--line); border-radius: 10px; padding: 10px 14px; margin: 10px 0; font-size: 13.5px;">
                        <i class="fa-solid fa-syringe" style="color: var(--green); margin-right: 6px;"></i> <strong>Vencimiento de vacuna:</strong> <?= e($c['vaccine_expiry']) ?> · 
                        <strong>Próxima dosis:</strong> <?= e($c['next_dose']) ?>
                    </div>
                <?php endif; ?>
                
                <div style="margin-top: 14px;">
                    <a class="btn secondary" href="<?= url('consultas/ver.php?id=' . (int)$c['id']) ?>" style="font-size: 13px; padding: 8px 14px;">
                        <span>Ver consulta y archivos adjuntos</span>
                        <i class="fa-solid fa-arrow-right" style="margin-left: 6px;"></i>
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
