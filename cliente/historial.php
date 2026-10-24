<?php
require_once __DIR__ . '/../config/config.php';
require_client();

$pid = (int)($_GET['pet_id'] ?? 0);
$clientId = (int)$_SESSION['client_id'];

// 1. Obtener la mascota y validar que pertenece al cliente actual (como dueño principal o co-dueño)
$q = db()->prepare("
    SELECT p.* 
    FROM pets p 
    WHERE p.id = ? 
      AND (p.client_id = ? OR p.id IN (SELECT pet_id FROM pet_co_owners WHERE client_id = ?))
");
$q->execute([$pid, $clientId, $clientId]);
$p = $q->fetch();

if (!$p) {
    flash('Mascota no encontrada o no tenés permisos para ver su libreta sanitaria.');
    header('Location: ' . url('cliente/mascotas.php'));
    exit;
}

// 2. Obtener lista de dueños (principal y co-dueños)
$owners = get_pet_owners($pid);

// 3. Obtener todo el historial de consultas de la mascota
$q = db()->prepare("
    SELECT co.*, cl.name clinic_name 
    FROM consultations co 
    JOIN clinics cl ON cl.id = co.clinic_id 
    WHERE co.pet_id = ? 
    ORDER BY co.created_at DESC
");
$q->execute([$pid]);
$rows = $q->fetchAll();

$pageTitle = 'Libreta Sanitaria de ' . $p['name'];
require __DIR__ . '/../includes/header.php';
?>

<!-- Panel Principal con Foto al lado del Nombre y Datos de la Mascota -->
<div class="panel">
    <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 20px; flex-wrap: wrap;">
        <!-- Foto de Perfil al Lado del Nombre -->
        <div style="display: flex; align-items: center; gap: 20px; flex-wrap: wrap;">
            <div style="position: relative; flex-shrink: 0;">
                <img src="<?= !empty($p['photo_path']) ? url('archivo_mascota.php?id=' . (int)$p['id']) : url('assets/perro_generico.png') ?>" 
                     alt="Foto de <?= e_name($p['name']) ?>" 
                     style="width: 86px; height: 86px; border-radius: 20px; object-fit: cover; border: 2.5px solid var(--green); box-shadow: 0 4px 14px rgba(35,119,101,0.15); display: block;">
            </div>

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
                </p>
            </div>
        </div>

        <!-- Botones de Acción: Código para Vet, Asignar Dueño y Volver -->
        <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
            <button type="button" class="btn primary" onclick="openAccessCodeModal()" style="background: #1e5a4d; border-color: #1e5a4d;">
                <i class="fa-solid fa-key" style="margin-right: 6px;"></i> Código para veterinaria
            </button>
            <button type="button" class="btn secondary" onclick="openModal('modalCoOwners')">
                <i class="fa-solid fa-user-plus" style="margin-right: 6px;"></i> Asignar otro dueño
            </button>
            <a class="btn secondary" href="<?= url('cliente/mascotas.php') ?>">
                <i class="fa-solid fa-arrow-left" style="margin-right: 6px;"></i> Volver
            </a>
        </div>
    </div>
</div>

<!-- Tarjeta con Todos los Datos Completos de la Mascota -->
<div class="card" style="margin-top: 20px;">
    <h2 style="font-size: 18px; margin: 0 0 16px; color: var(--ink);">
        <i class="fa-solid fa-id-card-clip" style="color: var(--green); margin-right: 8px;"></i>Ficha clínica de <?= e_name($p['name']) ?>
    </h2>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 14px;">
        <div style="background: var(--paper); border: 1px solid var(--line); border-radius: 12px; padding: 12px 16px;">
            <span class="muted" style="font-size: 12px; text-transform: uppercase; font-weight: 700;">Especie</span>
            <div style="font-size: 15px; font-weight: 700; color: var(--ink); margin-top: 4px;"><?= e_name($p['animal_type']) ?></div>
        </div>

        <div style="background: var(--paper); border: 1px solid var(--line); border-radius: 12px; padding: 12px 16px;">
            <span class="muted" style="font-size: 12px; text-transform: uppercase; font-weight: 700;">Sexo</span>
            <div style="font-size: 15px; font-weight: 700; color: var(--ink); margin-top: 4px;">
                <i class="fa-solid <?= ($p['sexo'] ?? '') === 'Hembra' ? 'fa-venus' : 'fa-mars' ?>" style="color: var(--green); margin-right: 4px;"></i>
                <?= e($p['sexo'] ?: 'No registrado') ?>
            </div>
        </div>

        <div style="background: var(--paper); border: 1px solid var(--line); border-radius: 12px; padding: 12px 16px;">
            <span class="muted" style="font-size: 12px; text-transform: uppercase; font-weight: 700;">Raza</span>
            <div style="font-size: 15px; font-weight: 700; color: var(--ink); margin-top: 4px;"><?= e_name($p['breed'] ?: 'Mestizo') ?></div>
        </div>

        <div style="background: var(--paper); border: 1px solid var(--line); border-radius: 12px; padding: 12px 16px;">
            <span class="muted" style="font-size: 12px; text-transform: uppercase; font-weight: 700;">Peso actual</span>
            <div style="font-size: 15px; font-weight: 700; color: var(--ink); margin-top: 4px;">
                <?= $p['weight_kg'] ? e((string)$p['weight_kg']) . ' kg' : 'No registrado' ?>
            </div>
        </div>

        <div style="background: var(--paper); border: 1px solid var(--line); border-radius: 12px; padding: 12px 16px;">
            <span class="muted" style="font-size: 12px; text-transform: uppercase; font-weight: 700;">Altura</span>
            <div style="font-size: 15px; font-weight: 700; color: var(--ink); margin-top: 4px;">
                <?= $p['height_cm'] ? e((string)$p['height_cm']) . ' cm' : 'No registrada' ?>
            </div>
        </div>

        <div style="background: var(--paper); border: 1px solid var(--line); border-radius: 12px; padding: 12px 16px;">
            <span class="muted" style="font-size: 12px; text-transform: uppercase; font-weight: 700;">Nacimiento</span>
            <div style="font-size: 15px; font-weight: 700; color: var(--ink); margin-top: 4px;">
                <?= $p['birth_date'] ? date('d/m/Y', strtotime($p['birth_date'])) : 'No especificada' ?>
            </div>
        </div>
    </div>

    <!-- Tutores Responsables de la Mascota -->
    <div style="margin-top: 16px; padding-top: 14px; border-top: 1px solid var(--line);">
        <span class="muted" style="font-size: 12px; text-transform: uppercase; font-weight: 700; display: block; margin-bottom: 8px;">
            <i class="fa-solid fa-users" style="color: var(--green); margin-right: 4px;"></i> Tutores responsables:
        </span>
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <?php foreach ($owners as $ow): ?>
                <span class="pill" style="font-size: 13px; padding: 6px 12px; background: var(--paper); border: 1px solid var(--line);">
                    <i class="fa-solid fa-user" style="color: var(--green); margin-right: 4px;"></i>
                    <?= e_name($ow['full_name']) ?> (DNI: <?= e($ow['dni']) ?>)
                    <?php if ((int)$ow['is_primary'] === 1): ?>
                        <strong style="color: var(--green); font-size: 11px; margin-left: 4px;">(Principal)</strong>
                    <?php else: ?>
                        <span class="muted" style="font-size: 11px; margin-left: 4px;">(Co-tutor)</span>
                    <?php endif; ?>
                </span>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Historial de Consultas Médicas -->
<div style="margin-top: 24px;">
    <h2 style="font-size: 22px; color: var(--ink); margin-bottom: 16px;">
        <i class="fa-solid fa-book-medical" style="color: var(--green); margin-right: 8px;"></i>Historial médico y atenciones (<?= count($rows) ?>)
    </h2>

    <?php if (!$rows): ?>
        <div class="card" style="text-align: center; padding: 48px 20px; background: #ffffff; border-radius: 18px; border: 1px solid var(--line);">
            <span style="font-size: 40px; color: var(--green); display: block; margin-bottom: 12px;"><i class="fa-solid fa-clipboard-list"></i></span>
            <h3>Esta mascota todavía no tiene consultas médicas registradas</h3>
            <p class="muted">Cuando asistas a una veterinaria de la red, los profesionales cargarán aquí su ficha técnica, diagnósticos y vacunas.</p>
        </div>
    <?php else: ?>
        <?php foreach ($rows as $r): ?>
            <?php $statusClass = $r['status'] === 'Dado de Alta' ? 'badge-success' : 'badge-warning'; ?>
            <div class="card" style="margin-bottom: 20px; background: #ffffff; border-radius: 18px; border: 1px solid var(--line); padding: 24px; box-shadow: 0 4px 18px rgba(24,59,53,0.03);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; flex-wrap: wrap; gap: 8px;">
                    <span class="badge <?= $statusClass ?>"><?= e($r['status']) ?></span>
                    <span class="muted" style="font-size: 13px;">
                        <i class="fa-solid fa-calendar-day" style="margin-right: 4px;"></i> <?= date('d/m/Y H:i', strtotime($r['created_at'])) ?> hs
                    </span>
                </div>
                
                <h3 style="margin: 4px 0 8px; font-size: 20px; color: var(--ink);"><?= e($r['reason']) ?></h3>
                <p class="muted name-titlecase" style="margin-bottom: 16px; font-size: 14px;">
                    <i class="fa-solid fa-hospital" style="color: var(--green); margin-right: 4px;"></i> Atendido en: <strong><?= e_name($r['clinic_name']) ?></strong>
                </p>
                
                <div style="background: #fcfdfc; border: 1px solid var(--line); border-radius: 12px; padding: 18px; margin-bottom: 18px;">
                    <p style="margin: 0 0 12px;">
                        <strong style="color: var(--ink); font-size: 13px;"><i class="fa-solid fa-file-medical" style="color: var(--green); margin-right: 6px;"></i>Evolución y notas:</strong><br>
                        <span style="color: #374151; font-size: 14px; line-height: 1.6; display: block; margin-top: 4px;"><?= nl2br(e($r['notes'])) ?></span>
                    </p>
                    
                    <?php if ($r['diagnosis']): ?>
                        <p style="margin: 0 0 12px;">
                            <strong style="color: var(--ink); font-size: 13px;"><i class="fa-solid fa-stethoscope" style="color: var(--green); margin-right: 6px;"></i>Diagnóstico:</strong><br>
                            <span style="color: #374151; font-size: 14px; line-height: 1.6; display: block; margin-top: 4px;"><?= nl2br(e($r['diagnosis'])) ?></span>
                        </p>
                    <?php endif; ?>
                    
                    <?php if ($r['treatment']): ?>
                        <p style="margin: 0;">
                            <strong style="color: var(--ink); font-size: 13px;"><i class="fa-solid fa-pills" style="color: var(--green); margin-right: 6px;"></i>Indicaciones y tratamiento:</strong><br>
                            <span style="color: #374151; font-size: 14px; line-height: 1.6; display: block; margin-top: 4px;"><?= nl2br(e($r['treatment'])) ?></span>
                        </p>
                    <?php endif; ?>
                </div>
                
                <?php if ($r['vaccine_expiry']): ?>
                    <div class="notice" style="margin-bottom: 18px; background: #eaf7ee; border: 1px solid #cce8d5; color: #1e6348; border-radius: 12px; padding: 12px 16px;">
                        <i class="fa-solid fa-syringe" style="margin-right: 6px;"></i> <strong>Vacuna aplicada</strong> · Vencimiento: <?= date('d/m/Y', strtotime($r['vaccine_expiry'])) ?>
                        <?= $r['next_dose'] ? '· Próximo refuerzo sugerido: ' . date('d/m/Y', strtotime($r['next_dose'])) : '' ?>
                    </div>
                <?php endif; ?>
                
                <div style="border-top: 1px solid var(--line); padding-top: 18px; margin-top: 16px;">
                    <h4 style="margin: 0 0 12px; font-size: 14px; font-weight: 700; color: var(--ink);">
                        <i class="fa-solid fa-paperclip" style="color: var(--green); margin-right: 6px;"></i>Documentos y estudios adjuntos
                    </h4>
                    
                    <?php 
                    $qf = db()->prepare("
                        SELECT id, original_name, created_at 
                        FROM consultation_files 
                        WHERE consultation_id = ? 
                        ORDER BY created_at DESC
                    ");
                    $qf->execute([$r['id']]);
                    $archivos = $qf->fetchAll();
                    ?>
                    
                    <?php if ($archivos): ?>
                        <div style="display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 16px;">
                            <?php foreach ($archivos as $f): ?>
                                <a class="btn secondary btn-sm" href="<?= url('archivo_consulta.php?id=' . (int)$f['id']) ?>" target="_blank">
                                    <i class="fa-solid fa-file-pdf" style="color: #dc2626; margin-right: 4px;"></i> <?= e($f['original_name']) ?> 
                                    <span class="muted" style="font-size: 11px;">(<?= date('d/m/y', strtotime($f['created_at'])) ?>)</span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <form method="post" enctype="multipart/form-data" action="<?= url('cliente/subir_archivo.php') ?>" style="margin-top: 14px;">
                        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                        <input type="hidden" name="consultation_id" value="<?= (int)$r['id'] ?>">
                        <input type="hidden" name="pet_id" value="<?= (int)$pid ?>">
                        
                        <div class="attachment-uploader">
                            <div class="attachment-dropzone" style="padding: 16px;">
                                <div class="attachment-dropzone-icon" style="width: 36px; height: 36px; font-size: 16px; margin-bottom: 6px;">
                                    <i class="fa-solid fa-cloud-arrow-up"></i>
                                </div>
                                <div class="attachment-dropzone-title" style="font-size: 13.5px;">
                                    <span>Subir estudio complementario</span> (PDF, JPG, PNG hasta 5 MB)
                                </div>
                            </div>

                            <input type="file" name="attachment" class="attachment-input-hidden" accept=".pdf,.jpg,.jpeg,.png" required onchange="this.form.submit()">
                        </div>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- =========================================================================
     MODAL: CÓDIGO DE AUTORIZACIÓN PARA VETERINARIA
     ========================================================================= -->
<div id="modalAccessCode" class="modal-overlay" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 9999; place-items: center; padding: 20px;">
    <div class="modal-window" style="background: #ffffff; border-radius: 20px; max-width: 480px; width: 100%; padding: 30px; box-shadow: 0 10px 40px rgba(0,0,0,0.2); text-align: center;">
        <div style="width: 60px; height: 60px; background: rgba(35,119,101,0.1); color: var(--green); border-radius: 50%; display: grid; place-items: center; font-size: 26px; margin: 0 auto 16px;">
            <i class="fa-solid fa-key"></i>
        </div>

        <h3 style="margin: 0 0 8px; font-size: 22px; color: var(--ink);">Código de atención veterinaria</h3>
        <p class="muted" style="margin: 0 0 20px; font-size: 13.5px; line-height: 1.5;">
            Dictele este código al veterinario para autorizarlo a asentar o modificar el historial clínico de <strong><?= e_name($p['name']) ?></strong>.
        </p>

        <!-- Display del Código Grande -->
        <div style="background: #f0f7f4; border: 2px dashed var(--green); border-radius: 16px; padding: 20px; margin-bottom: 20px;">
            <div id="accessCodeDisplay" style="font-size: 38px; font-weight: 800; font-family: monospace; letter-spacing: 6px; color: var(--green);">
                ------
            </div>
            <div style="font-size: 12px; color: var(--muted); margin-top: 6px;">
                <i class="fa-solid fa-clock" style="margin-right: 4px;"></i> Válido por 30 minutos · De un solo uso
            </div>
        </div>

        <div style="display: flex; gap: 10px; justify-content: center; flex-wrap: wrap;">
            <button type="button" class="btn secondary" onclick="generateNewAccessCode()">
                <i class="fa-solid fa-arrows-rotate" style="margin-right: 6px;"></i> Generar otro código
            </button>
            <button type="button" class="btn primary" onclick="closeModal('modalAccessCode')">
                Listo
            </button>
        </div>
    </div>
</div>

<!-- =========================================================================
     MODAL: ASIGNAR OTROS DUEÑOS (CO-TUTORES) POR DNI
     ========================================================================= -->
<div id="modalCoOwners" class="modal-overlay" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 9999; place-items: center; padding: 20px;">
    <div class="modal-window" style="background: #ffffff; border-radius: 20px; max-width: 520px; width: 100%; padding: 28px; box-shadow: 0 10px 40px rgba(0,0,0,0.2); max-height: 90vh; overflow-y: auto;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
            <h3 style="margin: 0; font-size: 20px; color: var(--ink);">
                <i class="fa-solid fa-user-plus" style="color: var(--green); margin-right: 8px;"></i>Dueños y Co-tutores
            </h3>
            <button type="button" onclick="closeModal('modalCoOwners')" style="background: none; border: none; font-size: 22px; cursor: pointer; color: var(--muted);">&times;</button>
        </div>

        <p class="muted" style="margin: 0 0 18px; font-size: 13.5px; line-height: 1.5;">
            Ingresá el DNI de otra persona para que también figure como dueño/a de <strong><?= e_name($p['name']) ?></strong> y pueda acceder a su libreta sanitaria digital.
        </p>

        <!-- Formulario para Agregar Nuevo Dueño -->
        <form id="formAddCoOwner" style="display: flex; gap: 8px; margin-bottom: 20px;">
            <input type="text" id="coOwnerDniInput" placeholder="Ingresá número de DNI..." required inputmode="numeric" style="margin: 0; flex: 1; font-size: 14px;">
            <button type="submit" class="btn primary" id="btnAddCoOwner" style="flex-shrink: 0;">
                <i class="fa-solid fa-user-plus" style="margin-right: 4px;"></i> Asignar
            </button>
        </form>

        <div id="coOwnerSuccessMsg" style="display: none; padding: 10px 14px; background: #eaf7ee; color: #1e6348; border-radius: 10px; font-size: 13px; margin-bottom: 16px;"></div>
        <div id="coOwnerErrorMsg" style="display: none; padding: 10px 14px; background: #fef2f2; color: #991b1b; border-radius: 10px; font-size: 13px; margin-bottom: 16px;"></div>

        <!-- Lista de Dueños Actuales -->
        <h4 style="font-size: 14px; margin: 0 0 10px; color: var(--ink);">Personas con acceso de dueño:</h4>
        <div id="coOwnersList" style="display: flex; flex-direction: column; gap: 8px;">
            <?php foreach ($owners as $ow): ?>
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px 14px; background: var(--paper); border: 1px solid var(--line); border-radius: 10px;">
                    <div>
                        <strong style="font-size: 14px; color: var(--ink);"><?= e_name($ow['full_name']) ?></strong>
                        <div class="muted" style="font-size: 12px;">DNI: <?= e($ow['dni']) ?> <?= (int)$ow['is_primary'] === 1 ? '· <em>Tutor Principal</em>' : '· <em>Co-tutor</em>' ?></div>
                    </div>
                    <?php if ((int)$ow['is_primary'] !== 1 && (int)$ow['id'] !== $clientId): ?>
                        <button type="button" class="btn secondary btn-sm" style="font-size: 11px; padding: 4px 8px;" onclick="removeCoOwner(<?= (int)$ow['id'] ?>)">
                            <i class="fa-solid fa-user-minus"></i> Quitar
                        </button>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script>
function openModal(id) {
    const el = document.getElementById(id);
    if (el) el.style.display = 'grid';
}

function closeModal(id) {
    const el = document.getElementById(id);
    if (el) el.style.display = 'none';
}

window.addEventListener('click', function(e) {
    const m1 = document.getElementById('modalAccessCode');
    if (e.target === m1) closeModal('modalAccessCode');
    const m2 = document.getElementById('modalCoOwners');
    if (e.target === m2) closeModal('modalCoOwners');
});

// =========================================================================
// Código de Acceso Veterinario
// =========================================================================
async function openAccessCodeModal() {
    openModal('modalAccessCode');
    await generateNewAccessCode();
}

async function generateNewAccessCode() {
    const display = document.getElementById('accessCodeDisplay');
    display.textContent = '...';
    try {
        const resp = await fetch('<?= url("cliente/api_codigo_acceso.php") ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ pet_id: <?= (int)$pid ?>, action: 'generate' })
        });
        const json = await resp.json();
        if (json.ok) {
            display.textContent = json.formatted || json.code;
        } else {
            display.textContent = 'ERROR';
        }
    } catch (e) {
        display.textContent = 'ERROR';
    }
}

// =========================================================================
// Asignar Co-dueño por DNI
// =========================================================================
document.getElementById('formAddCoOwner').addEventListener('submit', async function(e) {
    e.preventDefault();
    const dniInput = document.getElementById('coOwnerDniInput');
    const dni = dniInput.value.trim();
    const btn = document.getElementById('btnAddCoOwner');
    const successMsg = document.getElementById('coOwnerSuccessMsg');
    const errorMsg = document.getElementById('coOwnerErrorMsg');

    successMsg.style.display = 'none';
    errorMsg.style.display = 'none';
    btn.disabled = true;

    try {
        const resp = await fetch('<?= url("cliente/api_co_owners.php") ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ pet_id: <?= (int)$pid ?>, action: 'add', dni: dni })
        });
        const json = await resp.json();
        if (json.ok) {
            successMsg.textContent = json.message;
            successMsg.style.display = 'block';
            dniInput.value = '';
            setTimeout(() => location.reload(), 1200);
        } else {
            errorMsg.textContent = json.error || 'Error al asignar el dueño.';
            errorMsg.style.display = 'block';
        }
    } catch (e) {
        errorMsg.textContent = 'Error de conexión con el servidor.';
        errorMsg.style.display = 'block';
    } finally {
        btn.disabled = false;
    }
});

async function removeCoOwner(targetClientId) {
    if (!confirm('¿Deseás remover a este usuario como co-dueño de la mascota?')) return;
    try {
        const resp = await fetch('<?= url("cliente/api_co_owners.php") ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ pet_id: <?= (int)$pid ?>, action: 'remove', client_id: targetClientId })
        });
        const json = await resp.json();
        if (json.ok) {
            location.reload();
        } else {
            alert(json.error || 'No se pudo quitar el dueño.');
        }
    } catch (e) {
        alert('Error de conexión.');
    }
}
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
