<?php
require_once __DIR__ . '/config/config.php';
require_vet();

$cid = (int)$_SESSION['clinic_id'];
$vetUserId = (int)$_SESSION['vet_user_id'];

// Obtener datos del usuario veterinario actual
$q = db()->prepare("SELECT id, username, email FROM vet_users WHERE id = ?");
$q->execute([$vetUserId]);
$vetUser = $q->fetch();

// Obtener cantidad de turnos próximos solicitados o confirmados
$q = db()->prepare("SELECT COUNT(*) FROM appointments WHERE clinic_id = ? AND status IN ('Solicitado', 'Confirmado') AND appointment_at >= NOW()");
$q->execute([$cid]);
$turnos = $q->fetchColumn();

// Obtener cantidad de consultas actualmente en proceso
$q = db()->prepare("SELECT COUNT(*) FROM consultations WHERE clinic_id = ? AND status = 'En proceso'");
$q->execute([$cid]);
$open = $q->fetchColumn();

$pageTitle = 'Panel de Gestión Médica';
require __DIR__ . '/includes/header.php';
?>

<section class="dashboard-hero">
    <div class="badge-pulse-wrapper" style="margin-bottom: 14px;">
        <span class="badge-pulse-dot"></span>
        <span>Clínica Activa · <?= e_name($_SESSION['clinic_name']) ?></span>
    </div>

    <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 16px;">
        <div>
            <h1>Hola, <?= e_name($_SESSION['vet_username']) ?></h1>
            <p>
                Bienvenido a tu panel de gestión clínica en <?= e_name($_SESSION['clinic_name']) ?>. Accedé al registro unificado de historias clínicas, turnos y control de medicamentos en stock.
            </p>
        </div>
        <button type="button" class="btn secondary" onclick="openVetProfileModal()" style="flex-shrink: 0;">
            <i class="fa-solid fa-user-pen" style="margin-right: 6px;"></i> Editar mi perfil
        </button>
    </div>
    
    <div class="actions">
        <a class="btn primary" href="<?= url('clientes/buscar.php') ?>">
            <i class="fa-solid fa-magnifying-glass" style="margin-right: 6px;"></i>
            <span>Buscar cliente por DNI</span>
            <span style="margin-left: 4px;">→</span>
        </a>
        <a class="btn secondary" href="<?= url('clientes/nuevo.php') ?>">
            <i class="fa-solid fa-user-plus" style="margin-right: 6px;"></i>
            <span>Nuevo cliente</span>
        </a>
        <a class="btn secondary" href="<?= url('turnos/index.php') ?>">
            <i class="fa-solid fa-calendar-check" style="margin-right: 6px;"></i>
            <span>Gestionar turnos</span>
            <span style="margin-left: 4px;">→</span>
        </a>
    </div>
</section>

<!-- Contadores: cada tarjeta lleva directo a la sección correspondiente -->
<div class="grid">
    <a class="card stat-card" href="<?= url('turnos/index.php') ?>" style="border-top-color: #2b7bc4;">
        <span class="stat-icon" style="color: #2b7bc4; background: rgba(43, 123, 196, 0.12);"><i class="fa-solid fa-calendar-days"></i></span>
        <div class="stat-body">
            <p class="muted">Turnos próximos</p>
            <h2><?= (int)$turnos ?></h2>
            <span class="stat-hint">Solicitados o confirmados desde hoy</span>
        </div>
        <span class="stat-link">Gestionar <span aria-hidden="true">→</span></span>
    </a>

    <a class="card stat-card" href="<?= url('historial/index.php?estado=En+proceso') ?>" style="border-top-color: var(--gold);">
        <span class="stat-icon" style="color: var(--gold); background: rgba(228, 185, 107, 0.18);"><i class="fa-solid fa-stethoscope"></i></span>
        <div class="stat-body">
            <p class="muted">Consultas en proceso</p>
            <h2><?= (int)$open ?></h2>
            <span class="stat-hint">Pacientes sin alta médica todavía</span>
        </div>
        <span class="stat-link">Ver consultas <span aria-hidden="true">→</span></span>
    </a>
</div>

<div class="card">
    <h2><i class="fa-solid fa-cubes-stacked" style="color: var(--green); margin-right: 8px;"></i>Módulos y Operaciones Clínicas</h2>
    <p class="muted" style="margin-bottom: 16px;">Accesos directos al historial general y farmacia de tu veterinaria.</p>
    <div class="actions">
        <a class="btn secondary" href="<?= url('historial/index.php') ?>">
            <i class="fa-solid fa-book-medical" style="margin-right: 6px;"></i>
            <span>Historial clínico</span>
            <span style="margin-left: 4px;">→</span>
        </a>
        <a class="btn secondary" href="<?= url('stock/index.php') ?>">
            <i class="fa-solid fa-boxes-stacked" style="margin-right: 6px;"></i>
            <span>Medicamentos en stock</span>
            <span style="margin-left: 4px;">→</span>
        </a>
    </div>
</div>

<!-- =========================================================================
     MODAL: EDITAR PERFIL DEL VETERINARIO
     ========================================================================= -->
<div id="modalVetProfile" class="modal-overlay" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 9999; place-items: center; padding: 20px;">
    <div class="modal-window" style="background: #ffffff; border-radius: 20px; max-width: 500px; width: 100%; padding: 28px; box-shadow: 0 10px 40px rgba(0,0,0,0.2); max-height: 90vh; overflow-y: auto;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3 style="margin: 0; font-size: 20px; color: var(--ink);">
                <i class="fa-solid fa-user-pen" style="color: var(--green); margin-right: 8px;"></i>Editar mi perfil
            </h3>
            <button type="button" onclick="closeVetProfileModal()" style="background: none; border: none; font-size: 22px; cursor: pointer; color: var(--muted);">&times;</button>
        </div>

        <div style="padding: 12px 16px; background: #f0f7f4; border-radius: 12px; margin-bottom: 20px; font-size: 13px; color: var(--ink); line-height: 1.5; border: 1px solid #d2e7dd;">
            <i class="fa-solid fa-circle-info" style="color: var(--green); margin-right: 4px;"></i>
            Modificá tu nombre de usuario, correo electrónico o contraseña. Los cambios se aplican de forma inmediata.
        </div>

        <form id="formVetProfile">
            <div class="form-group" style="margin-bottom: 14px;">
                <label style="display: block; font-size: 13px; font-weight: 700; margin-bottom: 6px; color: var(--ink);">Nombre de usuario</label>
                <input type="text" id="vetEditUsername" value="<?= e($vetUser['username'] ?? '') ?>" style="width: 100%; border: 1px solid var(--line); border-radius: 10px; padding: 10px 14px; font-size: 14px;" required>
            </div>

            <div class="form-group" style="margin-bottom: 14px;">
                <label style="display: block; font-size: 13px; font-weight: 700; margin-bottom: 6px; color: var(--ink);">Correo electrónico</label>
                <input type="email" id="vetEditEmail" value="<?= e($vetUser['email'] ?? '') ?>" style="width: 100%; border: 1px solid var(--line); border-radius: 10px; padding: 10px 14px; font-size: 14px;" required>
            </div>

            <div class="form-group" style="margin-bottom: 14px;">
                <label style="display: block; font-size: 13px; font-weight: 700; margin-bottom: 6px; color: var(--ink);">Clínica</label>
                <input type="text" value="<?= e_name($_SESSION['clinic_name'] ?? '') ?>" style="width: 100%; border: 1px solid var(--line); border-radius: 10px; padding: 10px 14px; font-size: 14px; background: #f9f9f9;" disabled title="La clínica no puede ser modificada desde aquí">
            </div>

            <div class="form-group" style="margin-bottom: 20px;">
                <label style="display: block; font-size: 13px; font-weight: 700; margin-bottom: 6px; color: var(--ink);">Nueva contraseña <span style="font-weight: 400; color: var(--muted);">(opcional)</span></label>
                <input type="password" id="vetEditPassword" placeholder="Dejar en blanco para no cambiar" style="width: 100%; border: 1px solid var(--line); border-radius: 10px; padding: 10px 14px; font-size: 14px;">
                <div style="font-size: 11px; color: var(--muted); margin-top: 4px;">Mínimo 6 caracteres. Solo se cambia si escribís una nueva.</div>
            </div>

            <div id="vetSuccessMsg" style="display: none; padding: 10px 14px; background: #eaf7ee; color: #1e6348; border-radius: 10px; font-size: 13px; margin-bottom: 16px;">
                <i class="fa-solid fa-circle-check"></i> Perfil actualizado correctamente.
            </div>
            <div id="vetErrorMsg" style="display: none; padding: 10px 14px; background: #fef2f2; color: #991b1b; border-radius: 10px; font-size: 13px; margin-bottom: 16px;">
                <i class="fa-solid fa-circle-exclamation"></i> <span id="vetErrorText"></span>
            </div>

            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" class="btn secondary" onclick="closeVetProfileModal()">Cerrar</button>
                <button type="submit" class="btn primary" id="btnSaveVet">Guardar cambios</button>
            </div>
        </form>
    </div>
</div>

<script>
function openVetProfileModal() {
    document.getElementById('vetSuccessMsg').style.display = 'none';
    document.getElementById('vetErrorMsg').style.display = 'none';
    document.getElementById('vetEditPassword').value = '';
    document.getElementById('modalVetProfile').style.display = 'grid';
}

function closeVetProfileModal() {
    document.getElementById('modalVetProfile').style.display = 'none';
}

document.getElementById('formVetProfile').addEventListener('submit', async function(e) {
    e.preventDefault();

    const btn = document.getElementById('btnSaveVet');
    const successMsg = document.getElementById('vetSuccessMsg');
    const errorMsg = document.getElementById('vetErrorMsg');
    const errorText = document.getElementById('vetErrorText');

    successMsg.style.display = 'none';
    errorMsg.style.display = 'none';
    btn.disabled = true;
    btn.textContent = 'Guardando...';

    const payload = {
        username:     document.getElementById('vetEditUsername').value,
        email:        document.getElementById('vetEditEmail').value,
        new_password: document.getElementById('vetEditPassword').value
    };

    try {
        const resp = await fetch('<?= url("api_actualizar_perfil_vet.php") ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });

        const json = await resp.json();

        if (json.ok) {
            successMsg.style.display = 'block';
            setTimeout(() => location.reload(), 1200);
        } else {
            errorText.textContent = json.error || 'Error desconocido.';
            errorMsg.style.display = 'block';
        }
    } catch (err) {
        errorText.textContent = 'No se pudo conectar con el servidor.';
        errorMsg.style.display = 'block';
    } finally {
        btn.disabled = false;
        btn.textContent = 'Guardar cambios';
    }
});

window.addEventListener('click', function(e) {
    if (e.target === document.getElementById('modalVetProfile')) closeVetProfileModal();
});
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
