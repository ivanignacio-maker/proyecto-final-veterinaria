<?php
require_once __DIR__ . '/../config/config.php';
require_vet();

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

// Procesar actualización de datos del cliente/tutor si se envió el formulario
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_client') {
    csrf_check();
    $name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    if ($name === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Completá todos los campos obligatorios con un correo válido.';
    } else {
        $q = db()->prepare("UPDATE clients SET full_name = ?, email = ?, phone = ? WHERE id = ?");
        $q->execute([format_name($name), strtolower($email), $phone, $id]);
        flash('Datos del tutor actualizados exitosamente en la base de datos.');
        header('Location: ' . url('clientes/ver.php?id=' . $id));
        exit;
    }
}

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
        <div style="width: 72px; height: 72px; border-radius: 50%; background: #eaf5ef; border: 2.5px solid var(--green); display: grid; place-items: center; color: var(--green); box-shadow: 0 4px 14px rgba(35, 119, 101, 0.15); flex-shrink: 0; font-size: 30px; overflow: hidden;">
            <img src="<?= !empty($c['avatar_path']) ? url('archivo_avatar.php?id=' . (int)$c['id']) : url('assets/persona_generica.png') ?>" alt="Foto de perfil de <?= e_name($c['full_name']) ?>" style="width: 100%; height: 100%; object-fit: cover;">
        </div>
        
        <div style="flex: 1; min-width: 240px;">
            <div style="display: flex; gap: 10px; align-items: center; margin-bottom: 4px;">
                <span class="pill"><i class="fa-solid fa-id-badge" style="margin-right: 4px;"></i> Tutor Registrado</span>
                <span class="muted" style="font-size: 13px;">ID #<?= (int)$c['id'] ?></span>
            </div>
            <h1 style="margin: 0 0 6px; font-size: 28px;"><?= e_name($c['full_name']) ?></h1>
            <div style="display: flex; flex-direction: column; gap: 4px; font-size: 13.5px; color: var(--muted); margin-top: 6px;">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <i class="fa-solid fa-id-card" style="width: 16px; color: var(--green); text-align: center;"></i>
                    <span>DNI: <strong style="color: var(--ink);"><?= e($c['dni']) ?></strong></span>
                </div>
                <?php if (!empty($c['email'])): ?>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <i class="fa-solid fa-envelope" style="width: 16px; color: var(--green); text-align: center;"></i>
                        <span><?= e($c['email']) ?></span>
                    </div>
                <?php endif; ?>
                <?php if (!empty($c['phone'])): ?>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <i class="fa-solid fa-phone" style="width: 16px; color: var(--green); text-align: center;"></i>
                        <span><?= e($c['phone']) ?></span>
                    </div>
                <?php endif; ?>
            </div>




        </div>
    </div>
    
    <div class="actions" style="margin-top: 24px; padding-top: 18px; border-top: 1px solid var(--line);">
        <button type="button" class="btn secondary" onclick="openModal('modalEditClient')">
            <i class="fa-solid fa-user-pen" style="margin-right: 6px;"></i> Editar tutor
        </button>
        <a class="btn secondary" href="<?= url('clientes/buscar.php') ?>">
            <i class="fa-solid fa-magnifying-glass" style="margin-right: 6px;"></i> Buscar cliente
        </a>
        <a class="btn secondary" href="<?= url('dashboard.php') ?>">
            <i class="fa-solid fa-arrow-left" style="margin-right: 6px;"></i> Panel principal
        </a>
        <a class="btn primary" href="<?= url('mascotas/nueva.php?client_id=' . $id) ?>">
            <i class="fa-solid fa-plus" style="margin-right: 6px;"></i> Registrar mascota
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
                    <th>Sexo</th>
                    <th>Raza</th>
                    <th>Peso / Altura</th>
                    <th>Acción</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pets as $p): ?>
                    <tr>
                        <td style="width: 60px;">
                            <img src="<?= pet_photo_url($p) ?>" alt="Foto de <?= e_name($p['name']) ?>" style="width: 44px; height: 44px; border-radius: 10px; object-fit: cover; display: block;">
                        </td>
                        <td>
                            <strong style="font-size: 15px; color: var(--ink);"><?= e_name($p['name']) ?></strong>
                            <div class="muted" style="font-size: 12px;">ID #<?= (int)$p['id'] ?></div>
                        </td>
                        <td><span class="pill"><?= e_name($p['animal_type']) ?></span></td>
                        <td>
                            <?php if (!empty($p['sexo'])): ?>
                                <span class="badge" style="background: var(--paper); border: 1px solid var(--line); color: var(--ink);">
                                    <i class="fa-solid <?= $p['sexo'] === 'Hembra' ? 'fa-venus' : 'fa-mars' ?>" style="color: var(--green); margin-right: 4px;"></i>
                                    <?= e($p['sexo']) ?>
                                </span>
                            <?php else: ?>
                                <span class="muted">-</span>
                            <?php endif; ?>
                        </td>
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

<!-- Modal Editar Datos del Tutor (Veterinaria) -->
<div id="modalEditClient" class="modal-overlay" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 9999; place-items: center; padding: 20px;">
    <div class="modal-window" style="background: #ffffff; border-radius: 20px; max-width: 500px; width: 100%; padding: 28px; box-shadow: 0 10px 40px rgba(0,0,0,0.2); max-height: 90vh; overflow-y: auto;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3 style="margin: 0; font-size: 20px; color: var(--ink);">
                <i class="fa-solid fa-user-pen" style="color: var(--green); margin-right: 8px;"></i>Editar datos del tutor
            </h3>
            <button type="button" onclick="closeModal('modalEditClient')" style="background: none; border: none; font-size: 22px; cursor: pointer; color: var(--muted);">&times;</button>
        </div>

        <div style="padding: 12px 16px; background: #f0f7f4; border-radius: 12px; margin-bottom: 20px; font-size: 13px; color: var(--ink); line-height: 1.5; border: 1px solid #d2e7dd;">
            <i class="fa-solid fa-circle-info" style="color: var(--green); margin-right: 4px;"></i>
            Modificá los datos de contacto del tutor responsable. Los cambios se guardarán directamente en la base de datos.
        </div>

        <form method="post" action="<?= url('clientes/ver.php?id=' . $id) ?>">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="update_client">
            <input type="hidden" name="id" value="<?= $id ?>">

            <div class="form-group" style="margin-bottom: 14px;">
                <label style="display: block; font-size: 13px; font-weight: 700; margin-bottom: 6px; color: var(--ink);">Nombre y Apellido</label>
                <input type="text" name="full_name" value="<?= e($c['full_name']) ?>" style="width: 100%; border: 1px solid var(--line); border-radius: 10px; padding: 10px 14px; font-size: 14px;" required>
            </div>

            <div class="form-group" style="margin-bottom: 14px;">
                <label style="display: block; font-size: 13px; font-weight: 700; margin-bottom: 6px; color: var(--ink);">DNI (Documento de Identidad)</label>
                <input type="text" value="<?= e($c['dni']) ?>" style="width: 100%; border: 1px solid var(--line); border-radius: 10px; padding: 10px 14px; font-size: 14px; background: #f9f9f9;" disabled title="El DNI es el identificador único del cliente en la red">
            </div>

            <div class="form-group" style="margin-bottom: 14px;">
                <label style="display: block; font-size: 13px; font-weight: 700; margin-bottom: 6px; color: var(--ink);">Correo Electrónico</label>
                <input type="email" name="email" value="<?= e($c['email']) ?>" style="width: 100%; border: 1px solid var(--line); border-radius: 10px; padding: 10px 14px; font-size: 14px;" required>
            </div>

            <div class="form-group" style="margin-bottom: 20px;">
                <label style="display: block; font-size: 13px; font-weight: 700; margin-bottom: 6px; color: var(--ink);">Teléfono / WhatsApp</label>
                <input type="tel" name="phone" value="<?= e($c['phone'] ?? '') ?>" placeholder="Ej: 11 4455-6677" style="width: 100%; border: 1px solid var(--line); border-radius: 10px; padding: 10px 14px; font-size: 14px;" required>
            </div>

            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" class="btn secondary" onclick="closeModal('modalEditClient')">Cancelar</button>
                <button type="submit" class="btn primary">Guardar cambios</button>
            </div>
        </form>
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
    const m = document.getElementById('modalEditClient');
    if (e.target === m) closeModal('modalEditClient');
});
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
