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

$pageTitle = 'Mis mascotas | Portal Tutores';
require __DIR__ . '/../includes/header.php';
?>

<!-- Panel del Tutor / Perfil Personal -->
<div class="panel" style="background: #ffffff; border: 1px solid var(--line); border-radius: 20px; padding: 28px; box-shadow: 0 4px 20px rgba(24,59,53,0.04);">
    <div style="display: flex; justify-content: space-between; align-items: center; gap: 24px; flex-wrap: wrap;">
        <!-- Avatar y Datos del Tutor -->
        <div style="display: flex; align-items: center; gap: 20px; flex-wrap: wrap;">
            <div style="position: relative;">
                <div id="tutorAvatarContainer" style="width: 72px; height: 72px; border-radius: 50%; background: var(--mint); color: var(--green); display: grid; place-items: center; font-size: 30px; border: 2.5px solid var(--green); overflow: hidden; box-shadow: 0 4px 14px rgba(35,119,101,0.15);">
                    <?php if (!empty($c['avatar_path'])): ?>
                        <i class="fa-solid fa-user" id="tutorDefaultIcon" style="display: none;"></i>
                        <img id="tutorAvatarImg" src="<?= url('archivo_avatar.php?id=' . (int)$c['id']) ?>" alt="Avatar del tutor" style="width: 100%; height: 100%; object-fit: cover;">
                    <?php else: ?>
                        <i class="fa-solid fa-user" id="tutorDefaultIcon"></i>
                        <img id="tutorAvatarImg" src="" alt="Avatar del tutor" style="display: none; width: 100%; height: 100%; object-fit: cover;">
                    <?php endif; ?>
                </div>
                <!-- Botón sutil para subir/cambiar foto de perfil del tutor -->
                <button type="button" onclick="document.getElementById('tutorPhotoInput').click()" title="Cargar imagen de perfil" style="position: absolute; bottom: -2px; right: -2px; width: 28px; height: 28px; border-radius: 50%; background: var(--green); color: #fff; border: 2px solid #fff; display: grid; place-items: center; font-size: 11px; cursor: pointer; box-shadow: 0 2px 6px rgba(0,0,0,0.2);">
                    <i class="fa-solid fa-camera"></i>
                </button>
                <input type="file" id="tutorPhotoInput" accept="image/*" style="display: none;" onchange="previewTutorAvatar(this)">
            </div>

            <div>
                <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                    <h1 style="margin: 0; font-size: 24px;" class="name-titlecase"><?= e_name($c['full_name']) ?></h1>
                    <span class="badge" style="background: var(--mint); color: var(--green); font-weight: 700; font-size: 12px; padding: 4px 10px; border-radius: 20px;">
                        <i class="fa-solid fa-id-badge" style="margin-right: 4px;"></i> Tutor Responsable
                    </span>
                </div>
                <p class="muted" style="margin: 6px 0 0; font-size: 14px;">
                    <i class="fa-solid fa-id-card" style="margin-right: 4px;"></i> DNI: <strong><?= e($c['dni']) ?></strong> 
                    <?php if (!empty($c['email'])): ?>
                        · <i class="fa-solid fa-envelope" style="margin-right: 4px;"></i> <?= e($c['email']) ?>
                    <?php endif; ?>
                    <?php if (!empty($c['phone'])): ?>
                        · <i class="fa-solid fa-phone" style="margin-right: 4px;"></i> <?= e($c['phone']) ?>
                    <?php endif; ?>
                </p>
            </div>
        </div>

        <!-- Acciones: Registrar mascota y Editar perfil (Se quitó "Solicitar turno" porque ya está en el header) -->
        <div class="actions" style="margin: 0; display: flex; gap: 10px; flex-wrap: wrap;">
            <button type="button" class="btn secondary" onclick="openModal('modalTutorProfile')">
                <i class="fa-solid fa-user-pen"></i> Editar mis datos
            </button>
            <a class="btn primary" href="<?= url('cliente/agregar_mascota.php') ?>">
                <i class="fa-solid fa-plus"></i> Registrar mascota
            </a>
        </div>
    </div>
</div>

<!-- Listado de Mascotas -->
<div style="margin-top: 32px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 12px;">
        <div>
            <h2 style="margin: 0; font-size: 20px; font-weight: 800; color: var(--ink);">
                <i class="fa-solid fa-paw" style="color: var(--green); margin-right: 8px;"></i>Mascotas registradas (<?= count($pets) ?>)
            </h2>
            <p class="muted" style="margin: 4px 0 0; font-size: 13px;">Accedé al historial médico, vacunas y fichas de tus animales a cargo.</p>
        </div>
    </div>

    <div class="grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 24px;">
        <?php foreach ($pets as $p): ?>
            <div class="card" style="display: flex; flex-direction: column; justify-content: space-between; background: #ffffff; border: 1px solid var(--line); border-radius: 18px; padding: 24px; box-shadow: 0 4px 18px rgba(24,59,53,0.04); transition: transform 0.2s, box-shadow 0.2s;">
                <div>
                    <!-- Encabezado de la Tarjeta de Mascota -->
                    <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 14px; margin-bottom: 16px;">
                        <div style="display: flex; align-items: center; gap: 14px;">
                            <?php if ($p['photo_path']): ?>
                                <img class="pet-photo" src="<?= url('archivo_mascota.php?id=' . (int)$p['id']) ?>" alt="Foto de <?= e_name($p['name']) ?>" style="width: 64px; height: 64px; border-radius: 16px; object-fit: cover; border: 1.5px solid var(--line);">
                            <?php else: ?>
                                <div style="width: 64px; height: 64px; border-radius: 16px; background: var(--mint); color: var(--green); display: grid; place-items: center; font-size: 26px; border: 1.5px solid rgba(35, 119, 101, 0.2);">
                                    <i class="fa-solid fa-paw"></i>
                                </div>
                            <?php endif; ?>

                            <div>
                                <h3 class="name-titlecase" style="margin: 0 0 4px; font-size: 19px; color: var(--ink);"><?= e_name($p['name']) ?></h3>
                                <span style="display: inline-block; font-size: 12px; font-weight: 700; color: var(--green); background: var(--mint); padding: 2px 10px; border-radius: 12px;">
                                    <?= e_name($p['animal_type']) ?> <?= $p['breed'] ? '· ' . e_name($p['breed']) : '' ?>
                                </span>
                            </div>
                        </div>

                        <span class="pill" style="font-size: 11px; padding: 3px 8px;">#<?= (int)$p['id'] ?></span>
                    </div>

                    <!-- Ficha Rápida de Métricas -->
                    <div style="background: #f8faf8; border: 1px solid var(--line); border-radius: 12px; padding: 12px 16px; margin-bottom: 18px; font-size: 13px; line-height: 1.7; color: var(--ink);">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <i class="fa-solid fa-weight-scale" style="width: 16px; color: var(--green);"></i>
                            <span>Peso registrado: <strong><?= e((string)($p['weight_kg'] ?: 'No registrado')) ?></strong> <?= $p['weight_kg'] ? 'kg' : '' ?></span>
                        </div>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <i class="fa-solid fa-ruler-vertical" style="width: 16px; color: var(--green);"></i>
                            <span>Altura: <strong><?= e((string)($p['height_cm'] ?: 'No registrada')) ?></strong> <?= $p['height_cm'] ? 'cm' : '' ?></span>
                        </div>
                        <?php if (!empty($p['birth_date'])): ?>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <i class="fa-solid fa-cake-candles" style="width: 16px; color: var(--green);"></i>
                                <span>Nacimiento: <strong><?= date('d/m/Y', strtotime($p['birth_date'])) ?></strong></span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Botones de Acción: Ver libreta y Editar datos -->
                <div style="display: flex; gap: 8px; flex-direction: column;">
                    <a class="btn primary" style="width: 100%; justify-content: center; text-align: center;" href="<?= url('cliente/historial.php?pet_id=' . (int)$p['id']) ?>">
                        <i class="fa-solid fa-notes-medical" style="margin-right: 6px;"></i> Ver libreta sanitaria
                    </a>
                    <button type="button" class="btn secondary" style="width: 100%; justify-content: center;" onclick="openEditPetModal(<?= htmlspecialchars(json_encode([
                        'id' => (int)$p['id'],
                        'name' => $p['name'],
                        'animal_type' => $p['animal_type'],
                        'breed' => $p['breed'],
                        'weight_kg' => $p['weight_kg'],
                        'height_cm' => $p['height_cm'],
                        'birth_date' => $p['birth_date']
                    ]), ENT_QUOTES, 'UTF-8') ?>)">
                        <i class="fa-solid fa-pen-to-square" style="margin-right: 6px;"></i> Editar datos
                    </button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php if (!$pets): ?>
    <div class="card" style="text-align: center; padding: 48px 24px; margin-top: 24px; background: #ffffff; border: 1px solid var(--line); border-radius: 20px;">
        <span style="font-size: 44px; color: var(--green); display: block; margin-bottom: 14px;"><i class="fa-solid fa-paw"></i></span>
        <h3 style="font-size: 20px; font-weight: 800; color: var(--ink); margin: 0 0 6px;">Todavía no registraste ninguna mascota</h3>
        <p class="muted" style="max-width: 480px; margin: 0 auto 20px; font-size: 14px;">
            Hacé clic en el botón a continuación para dar de alta a tu primer animal de compañía y acceder a su libreta médica digital.
        </p>
        <div>
            <a class="btn primary" href="<?= url('cliente/agregar_mascota.php') ?>">
                <i class="fa-solid fa-plus" style="margin-right: 6px;"></i> Registrar mi primera mascota
            </a>
        </div>
    </div>
<?php endif; ?>

<!-- =========================================================================
     MODAL: EDITAR PERFIL DEL TUTOR (Interacción Front End)
     ========================================================================= -->
<div id="modalTutorProfile" class="modal-overlay" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 9999; place-items: center; padding: 20px;">
    <div class="modal-window" style="background: #ffffff; border-radius: 20px; max-width: 500px; width: 100%; padding: 28px; box-shadow: 0 10px 40px rgba(0,0,0,0.2); max-height: 90vh; overflow-y: auto;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3 style="margin: 0; font-size: 20px; color: var(--ink);">
                <i class="fa-solid fa-user-pen" style="color: var(--green); margin-right: 8px;"></i>Editar datos personales
            </h3>
            <button type="button" onclick="closeModal('modalTutorProfile')" style="background: none; border: none; font-size: 22px; cursor: pointer; color: var(--muted);">&times;</button>
        </div>

        <div style="padding: 12px 16px; background: #f0f7f4; border-radius: 12px; margin-bottom: 20px; font-size: 13px; color: var(--ink); line-height: 1.5; border: 1px solid #d2e7dd;">
            <i class="fa-solid fa-circle-info" style="color: var(--green); margin-right: 4px;"></i>
            Los datos son verificados por la clínica para garantizar la custodia médica oficial de tus mascotas.
        </div>

        <form id="formTutorProfile" enctype="multipart/form-data">
            <div class="form-group" style="margin-bottom: 14px;">
                <label style="display: block; font-size: 13px; font-weight: 700; margin-bottom: 6px; color: var(--ink);">Nombre y Apellido</label>
                <input type="text" id="tutorEditName" name="full_name" value="<?= e($c['full_name']) ?>" style="width: 100%; border: 1px solid var(--line); border-radius: 10px; padding: 10px 14px; font-size: 14px;" required>
            </div>

            <div class="form-group" style="margin-bottom: 14px;">
                <label style="display: block; font-size: 13px; font-weight: 700; margin-bottom: 6px; color: var(--ink);">DNI (Documento de Identidad)</label>
                <input type="text" value="<?= e($c['dni']) ?>" style="width: 100%; border: 1px solid var(--line); border-radius: 10px; padding: 10px 14px; font-size: 14px; background: #f9f9f9;" disabled title="El DNI es el identificador oficial de tu libreta médica">
            </div>

            <div class="form-group" style="margin-bottom: 14px;">
                <label style="display: block; font-size: 13px; font-weight: 700; margin-bottom: 6px; color: var(--ink);">Correo Electrónico</label>
                <input type="email" id="tutorEditEmail" name="email" value="<?= e($c['email']) ?>" style="width: 100%; border: 1px solid var(--line); border-radius: 10px; padding: 10px 14px; font-size: 14px;" required>
            </div>

            <div class="form-group" style="margin-bottom: 14px;">
                <label style="display: block; font-size: 13px; font-weight: 700; margin-bottom: 6px; color: var(--ink);">Teléfono / WhatsApp</label>
                <input type="tel" id="tutorEditPhone" name="phone" value="<?= e($c['phone'] ?? '') ?>" placeholder="+54 9 11..." style="width: 100%; border: 1px solid var(--line); border-radius: 10px; padding: 10px 14px; font-size: 14px;">
            </div>

            <div class="form-group" style="margin-bottom: 20px;">
                <label style="display: block; font-size: 13px; font-weight: 700; margin-bottom: 6px; color: var(--ink);">Foto de perfil</label>
                <input type="file" id="tutorModalPhoto" name="avatar" accept="image/jpeg,image/png,image/webp" style="font-size: 13px;">
                <div style="font-size: 11px; color: var(--muted); margin-top: 4px;">JPG, PNG o WEBP. Máximo 3 MB.</div>
            </div>

            <div id="tutorSuccessMsg" style="display: none; padding: 10px 14px; background: #eaf7ee; color: #1e6348; border-radius: 10px; font-size: 13px; margin-bottom: 16px;">
                <i class="fa-solid fa-circle-check"></i> Datos guardados correctamente.
            </div>
            <div id="tutorErrorMsg" style="display: none; padding: 10px 14px; background: #fef2f2; color: #991b1b; border-radius: 10px; font-size: 13px; margin-bottom: 16px;">
                <i class="fa-solid fa-circle-exclamation"></i> <span id="tutorErrorText"></span>
            </div>

            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" class="btn secondary" onclick="closeModal('modalTutorProfile')">Cerrar</button>
                <button type="submit" class="btn primary" id="btnSaveTutor">Guardar cambios</button>
            </div>
        </form>
    </div>
</div>

<!-- =========================================================================
     MODAL: EDITAR DATOS DE LA MASCOTA (Interacción Front End)
     ========================================================================= -->
<div id="modalEditPet" class="modal-overlay" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 9999; place-items: center; padding: 20px;">
    <div class="modal-window" style="background: #ffffff; border-radius: 20px; max-width: 500px; width: 100%; padding: 28px; box-shadow: 0 10px 40px rgba(0,0,0,0.2); max-height: 90vh; overflow-y: auto;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3 style="margin: 0; font-size: 20px; color: var(--ink);">
                <i class="fa-solid fa-paw" style="color: var(--green); margin-right: 8px;"></i>Editar datos de la mascota
            </h3>
            <button type="button" onclick="closeModal('modalEditPet')" style="background: none; border: none; font-size: 22px; cursor: pointer; color: var(--muted);">&times;</button>
        </div>

        <div style="padding: 12px 16px; background: #f0f7f4; border-radius: 12px; margin-bottom: 20px; font-size: 13px; color: var(--ink); line-height: 1.5; border: 1px solid #d2e7dd;">
            <i class="fa-solid fa-circle-info" style="color: var(--green); margin-right: 4px;"></i>
            Modificá los datos generales de tu mascota. Las fichas médicas y vacunas oficiales permanecen protegidas por tu veterinaria.
        </div>

        <form id="formEditPet">
            <input type="hidden" id="editPetId">

            <div class="form-group" style="margin-bottom: 14px;">
                <label style="display: block; font-size: 13px; font-weight: 700; margin-bottom: 6px; color: var(--ink);">Nombre</label>
                <input type="text" id="editPetName" style="width: 100%; border: 1px solid var(--line); border-radius: 10px; padding: 10px 14px; font-size: 14px;" required>
            </div>

            <div class="form-group" style="margin-bottom: 14px;">
                <label style="display: block; font-size: 13px; font-weight: 700; margin-bottom: 6px; color: var(--ink);">Especie / Tipo</label>
                <select id="editPetType" style="width: 100%; border: 1px solid var(--line); border-radius: 10px; padding: 10px 14px; font-size: 14px;" required>
                    <option value="Perro">Perro</option>
                    <option value="Gato">Gato</option>
                    <option value="Ave">Ave</option>
                    <option value="Roedor">Roedor</option>
                    <option value="Reptil">Reptil</option>
                    <option value="Otro">Otro</option>
                </select>
            </div>

            <div class="form-group" style="margin-bottom: 14px;">
                <label style="display: block; font-size: 13px; font-weight: 700; margin-bottom: 6px; color: var(--ink);">Raza</label>
                <input type="text" id="editPetBreed" style="width: 100%; border: 1px solid var(--line); border-radius: 10px; padding: 10px 14px; font-size: 14px;">
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 14px;">
                <div>
                    <label style="display: block; font-size: 13px; font-weight: 700; margin-bottom: 6px; color: var(--ink);">Peso (kg)</label>
                    <input type="number" step="0.01" id="editPetWeight" style="width: 100%; border: 1px solid var(--line); border-radius: 10px; padding: 10px 14px; font-size: 14px;">
                </div>
                <div>
                    <label style="display: block; font-size: 13px; font-weight: 700; margin-bottom: 6px; color: var(--ink);">Altura (cm)</label>
                    <input type="number" step="0.1" id="editPetHeight" style="width: 100%; border: 1px solid var(--line); border-radius: 10px; padding: 10px 14px; font-size: 14px;">
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 20px;">
                <label style="display: block; font-size: 13px; font-weight: 700; margin-bottom: 6px; color: var(--ink);">Fecha de nacimiento</label>
                <input type="date" id="editPetBirth" style="width: 100%; border: 1px solid var(--line); border-radius: 10px; padding: 10px 14px; font-size: 14px;">
            </div>

            <div id="petSuccessMsg" style="display: none; padding: 10px 14px; background: #eaf7ee; color: #1e6348; border-radius: 10px; font-size: 13px; margin-bottom: 16px;">
                <i class="fa-solid fa-circle-check"></i> Datos de la mascota guardados correctamente.
            </div>
            <div id="petErrorMsg" style="display: none; padding: 10px 14px; background: #fef2f2; color: #991b1b; border-radius: 10px; font-size: 13px; margin-bottom: 16px;">
                <i class="fa-solid fa-circle-exclamation"></i> <span id="petErrorText"></span>
            </div>

            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" class="btn secondary" onclick="closeModal('modalEditPet')">Cerrar</button>
                <button type="submit" class="btn primary" id="btnSavePet">Guardar cambios</button>
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

async function previewTutorAvatar(input) {
    if (input.files && input.files[0]) {
        const file = input.files[0];
        const reader = new FileReader();
        reader.onload = function(e) {
            const img = document.getElementById('tutorAvatarImg');
            const icon = document.getElementById('tutorDefaultIcon');
            img.src = e.target.result;
            img.style.display = 'block';
            if (icon) icon.style.display = 'none';
        };
        reader.readAsDataURL(file);

        // Guardar automáticamente la foto en el backend
        const formData = new FormData();
        formData.append('full_name', document.getElementById('tutorEditName').value);
        formData.append('email', document.getElementById('tutorEditEmail').value);
        formData.append('phone', document.getElementById('tutorEditPhone').value);
        formData.append('avatar', file);

        try {
            const resp = await fetch('<?= url("cliente/api_actualizar_perfil.php") ?>', {
                method: 'POST',
                body: formData
            });
            const json = await resp.json();
            if (json.ok) {
                setTimeout(() => location.reload(), 800);
            }
        } catch (err) {
            console.error('Error al subir avatar:', err);
        }
    }
}

// =========================================================================
// AJAX: Actualizar perfil del tutor
// =========================================================================
document.getElementById('formTutorProfile').addEventListener('submit', async function(e) {
    e.preventDefault();

    const btn = document.getElementById('btnSaveTutor');
    const successMsg = document.getElementById('tutorSuccessMsg');
    const errorMsg = document.getElementById('tutorErrorMsg');
    const errorText = document.getElementById('tutorErrorText');

    successMsg.style.display = 'none';
    errorMsg.style.display = 'none';
    btn.disabled = true;
    btn.textContent = 'Guardando...';

    const formData = new FormData();
    formData.append('full_name', document.getElementById('tutorEditName').value);
    formData.append('email', document.getElementById('tutorEditEmail').value);
    formData.append('phone', document.getElementById('tutorEditPhone').value);

    const photoInput = document.getElementById('tutorModalPhoto');
    const quickPhoto = document.getElementById('tutorPhotoInput');
    if (photoInput && photoInput.files.length > 0) {
        formData.append('avatar', photoInput.files[0]);
    } else if (quickPhoto && quickPhoto.files.length > 0) {
        formData.append('avatar', quickPhoto.files[0]);
    }

    try {
        const resp = await fetch('<?= url("cliente/api_actualizar_perfil.php") ?>', {
            method: 'POST',
            body: formData
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

// =========================================================================
// MODAL: Editar mascota – Poblar campos
// =========================================================================
function openEditPetModal(pet) {
    document.getElementById('editPetId').value = pet.id;
    document.getElementById('editPetName').value = pet.name || '';
    document.getElementById('editPetType').value = pet.animal_type || 'Perro';
    document.getElementById('editPetBreed').value = pet.breed || '';
    document.getElementById('editPetWeight').value = pet.weight_kg || '';
    document.getElementById('editPetHeight').value = pet.height_cm || '';
    document.getElementById('editPetBirth').value = pet.birth_date || '';

    document.getElementById('petSuccessMsg').style.display = 'none';
    document.getElementById('petErrorMsg').style.display = 'none';
    openModal('modalEditPet');
}

// =========================================================================
// AJAX: Actualizar datos de la mascota
// =========================================================================
document.getElementById('formEditPet').addEventListener('submit', async function(e) {
    e.preventDefault();

    const btn = document.getElementById('btnSavePet');
    const successMsg = document.getElementById('petSuccessMsg');
    const errorMsg = document.getElementById('petErrorMsg');
    const errorText = document.getElementById('petErrorText');

    successMsg.style.display = 'none';
    errorMsg.style.display = 'none';
    btn.disabled = true;
    btn.textContent = 'Guardando...';

    const payload = {
        id:          parseInt(document.getElementById('editPetId').value),
        name:        document.getElementById('editPetName').value,
        animal_type: document.getElementById('editPetType').value,
        breed:       document.getElementById('editPetBreed').value,
        weight_kg:   document.getElementById('editPetWeight').value || null,
        height_cm:   document.getElementById('editPetHeight').value || null,
        birth_date:  document.getElementById('editPetBirth').value || ''
    };

    try {
        const resp = await fetch('<?= url("cliente/api_actualizar_mascota.php") ?>', {
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

// Cerrar modales al hacer clic fuera del contenido
window.addEventListener('click', function(e) {
    const modal1 = document.getElementById('modalTutorProfile');
    const modal2 = document.getElementById('modalEditPet');
    if (e.target === modal1) closeModal('modalTutorProfile');
    if (e.target === modal2) closeModal('modalEditPet');
});
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
