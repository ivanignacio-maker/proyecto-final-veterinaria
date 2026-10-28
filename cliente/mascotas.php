<?php
require_once __DIR__ . '/../config/config.php';
require_client();

$id = (int)$_SESSION['client_id'];

// 1. Obtener la información del cliente
$q = db()->prepare("SELECT * FROM clients WHERE id = ?");
$q->execute([$id]);
$c = $q->fetch();

// 2. Obtener el listado de mascotas del cliente (como dueño principal o co-dueño)
$q = db()->prepare("
    SELECT p.*, (CASE WHEN p.client_id = ? THEN 1 ELSE 0 END) AS is_primary_owner 
    FROM pets p 
    WHERE p.client_id = ? 
       OR p.id IN (SELECT pet_id FROM pet_co_owners WHERE client_id = ?)
    ORDER BY p.name
");
$q->execute([$id, $id, $id]);
$pets = $q->fetchAll();

$pageTitle = 'Mis mascotas | Portal Tutores';
require __DIR__ . '/../includes/header.php';
?>

<!-- Panel del Tutor / Perfil Personal -->
<div class="panel" style="background: #ffffff; border: 1px solid var(--line); border-radius: 20px; padding: 28px; box-shadow: 0 4px 20px rgba(24,59,53,0.04);">
    <div style="display: flex; justify-content: space-between; align-items: center; gap: 24px; flex-wrap: wrap;">
        <!-- Avatar y Datos del Tutor -->
        <div style="display: flex; align-items: center; gap: 20px; flex-wrap: wrap;">
            <div style="position: relative; flex-shrink: 0;">
                <div id="tutorAvatarContainer" style="width: 76px; height: 76px; border-radius: 50%; background: var(--mint); color: var(--green); display: grid; place-items: center; font-size: 32px; border: 2.5px solid var(--green); overflow: hidden; box-shadow: 0 4px 14px rgba(35,119,101,0.15);">
                    <img id="tutorAvatarImg" src="<?= !empty($c['avatar_path']) ? url('archivo_avatar.php?id=' . (int)$c['id']) : url('assets/persona_generica.webp') ?>" alt="Foto de perfil de <?= e_name($c['full_name']) ?>" style="width: 100%; height: 100%; object-fit: cover;">
                </div>
                <!-- Botón sutil para subir/cambiar foto de perfil del tutor -->
                <button type="button" class="tutor-avatar-camera-btn" onclick="document.getElementById('tutorPhotoInput').click()" title="Cargar imagen de perfil" aria-label="Cargar foto de perfil">
                    <i class="fa-solid fa-camera"></i>
                </button>
                <input type="file" id="tutorPhotoInput" accept="image/jpeg,image/png,image/webp" style="display: none;" onchange="previewTutorAvatar(this)">
            </div>

            <div>
                <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-bottom: 8px;">
                    <h1 style="margin: 0; font-size: 24px;" class="name-titlecase"><?= e_name($c['full_name']) ?></h1>
                    <span class="badge" style="background: var(--mint); color: var(--green); font-weight: 700; font-size: 12px; padding: 4px 10px; border-radius: 20px;">
                        <i class="fa-solid fa-id-badge" style="margin-right: 4px;"></i> Tutor Responsable
                    </span>
                </div>
                <!-- Datos del perfil en vertical (uno debajo del otro) -->
                <div style="display: flex; flex-direction: column; gap: 4px; font-size: 13.5px; color: var(--muted);">
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
                            <img class="pet-photo" src="<?= pet_photo_url($p) ?>" alt="Foto de <?= e_name($p['name']) ?>" style="width: 64px; height: 64px; border-radius: 16px; object-fit: cover; border: 1.5px solid var(--line);">

                            <div>
                                <h3 class="name-titlecase" style="margin: 0 0 4px; font-size: 19px; color: var(--ink);"><?= e_name($p['name']) ?></h3>
                                <span style="display: inline-block; font-size: 12px; font-weight: 700; color: var(--green); background: var(--mint); padding: 2px 10px; border-radius: 12px;">
                                    <?= e_name($p['animal_type']) ?> <?= $p['breed'] ? '· ' . e_name($p['breed']) : '' ?>
                                </span>
                            </div>
                        </div>

                        <div style="display: flex; align-items: center; gap: 4px;">
                            <?php if ((int)($p['is_primary_owner'] ?? 1) === 0): ?>
                                <span class="badge" style="background: rgba(35,119,101,0.1); color: var(--green); font-size: 11px;">Co-tutor</span>
                            <?php endif; ?>
                            <span class="pill" style="font-size: 11px; padding: 3px 8px;">#<?= (int)$p['id'] ?></span>
                        </div>
                    </div>

                    <!-- Ficha Rápida de Métricas -->
                    <div style="background: #f8faf8; border: 1px solid var(--line); border-radius: 12px; padding: 12px 16px; margin-bottom: 18px; font-size: 13px; line-height: 1.7; color: var(--ink);">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <i class="fa-solid <?= ($p['sexo'] ?? '') === 'Hembra' ? 'fa-venus' : 'fa-mars' ?>" style="width: 16px; color: var(--green);"></i>
                            <span>Sexo: <strong><?= e($p['sexo'] ?: 'No registrado') ?></strong></span>
                        </div>
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

                <!-- Botones de Acción: Ver libreta, Código Vet, Co-tutores y Editar datos -->
                <div style="display: flex; gap: 8px; flex-direction: column;">
                    <a class="btn primary" style="width: 100%; justify-content: center; text-align: center;" href="<?= url('cliente/historial.php?pet_id=' . (int)$p['id']) ?>">
                        <i class="fa-solid fa-notes-medical" style="margin-right: 6px;"></i> Ver libreta sanitaria
                    </a>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                        <button type="button" class="btn secondary btn-sm" style="justify-content: center; font-size: 12px; padding: 7px 6px;" onclick="openAccessCodeModalFor(<?= (int)$p['id'] ?>, '<?= e(addslashes($p['name'])) ?>')">
                            <i class="fa-solid fa-key" style="margin-right: 4px;"></i> Código vet
                        </button>
                        <button type="button" class="btn secondary btn-sm" style="justify-content: center; font-size: 12px; padding: 7px 6px;" onclick="openCoOwnersModalFor(<?= (int)$p['id'] ?>, '<?= e(addslashes($p['name'])) ?>')">
                            <i class="fa-solid fa-user-plus" style="margin-right: 4px;"></i> Co-tutores
                        </button>
                    </div>
                    <button type="button" class="btn secondary" style="width: 100%; justify-content: center;" onclick="openEditPetModal(<?= htmlspecialchars(json_encode([
                        'id' => (int)$p['id'],
                        'name' => $p['name'],
                        'animal_type' => $p['animal_type'],
                        'sexo' => $p['sexo'] ?? '',
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

            <div class="password-change-section">
                <div class="password-change-heading">Cambiar contraseña <span>(opcional)</span></div>
                <div class="form-group" style="margin-bottom: 12px;">
                    <label for="tutorCurrentPassword">Contraseña actual</label>
                    <input type="password" id="tutorCurrentPassword" autocomplete="current-password" placeholder="Ingresá tu contraseña actual">
                </div>
                <div class="form-group" style="margin-bottom: 24px;">
                    <label for="tutorNewPassword">Nueva contraseña</label>
                    <div data-password-strength class="password-strength-widget">
                        <input type="password" id="tutorNewPassword" data-strength-input autocomplete="new-password" placeholder="Creá una contraseña segura">
                        <div class="password-strength-meter" aria-hidden="true">
                            <span class="password-strength-segment"></span><span class="password-strength-segment"></span>
                            <span class="password-strength-segment"></span><span class="password-strength-segment"></span>
                        </div>
                        <span class="password-strength-label" data-strength-label>La seguridad aparecerá mientras escribís</span>
                        <ul class="password-strength-requirements">
                            <li data-password-rule="length"><span class="password-rule-mark">○</span> 8 caracteres</li>
                            <li data-password-rule="case"><span class="password-rule-mark">○</span> Mayúscula y minúscula</li>
                            <li data-password-rule="number"><span class="password-rule-mark">○</span> Un número</li>
                            <li data-password-rule="symbol"><span class="password-rule-mark">○</span> Un símbolo</li>
                            <li data-password-rule="uncommon"><span class="password-rule-mark">○</span> Sin patrones comunes</li>
                        </ul>
                    </div>
                </div>
                <div class="form-group" style="margin-bottom: 20px;">
                    <label for="tutorConfirmPassword">Confirmar nueva contraseña</label>
                    <input type="password" id="tutorConfirmPassword" autocomplete="new-password" placeholder="Repetí la nueva contraseña">
                </div>
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

            <!-- Vista previa dinámica en modal de edición -->
            <div style="display: flex; align-items: center; gap: 14px; padding: 12px 14px; background: var(--paper); border: 1px solid var(--line); border-radius: 14px; margin-bottom: 16px;">
                <img id="editPetAvatarPreview" src="<?= url('assets/otro_generico.webp') ?>" alt="Avatar" style="width: 56px; height: 56px; border-radius: 14px; object-fit: cover; border: 2px solid var(--green); flex-shrink: 0; transition: transform 0.15s ease;">
                <div style="font-size: 13px;">
                    <strong id="editPetAvatarTitle">Avatar de la mascota</strong>
                    <div class="muted" style="font-size: 11px;" id="editPetAvatarSub">Ilustración según tipo de animal o foto personalizada.</div>
                </div>
            </div>

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
                <label style="display: block; font-size: 13px; font-weight: 700; margin-bottom: 6px; color: var(--ink);">Sexo *</label>
                <select id="editPetSexo" style="width: 100%; border: 1px solid var(--line); border-radius: 10px; padding: 10px 14px; font-size: 14px;" required>
                    <option value="">Seleccionar sexo</option>
                    <option value="Macho">Macho</option>
                    <option value="Hembra">Hembra</option>
                </select>
            </div>

            <div class="form-group" style="margin-bottom: 14px;">
                <label style="display: block; font-size: 13px; font-weight: 700; margin-bottom: 6px; color: var(--ink);">Foto de perfil (opcional)</label>
                <input type="file" id="editPetPhoto" accept="image/jpeg,image/png,image/webp" style="font-size: 13px;">
                <div style="font-size: 11px; color: var(--muted); margin-top: 4px;">JPG, PNG o WEBP. Máximo 3 MB.</div>
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
    if (id === 'modalTutorProfile') {
        ['tutorCurrentPassword', 'tutorNewPassword', 'tutorConfirmPassword'].forEach(function(fieldId) {
            const field = document.getElementById(fieldId);
            if (field) field.value = '';
        });
        document.getElementById('tutorNewPassword').dispatchEvent(new Event('input', { bubbles: true }));
    }
    if (el) el.style.display = 'grid';
}

function closeModal(id) {
    const el = document.getElementById(id);
    if (el) el.style.display = 'none';
}

async function previewTutorAvatar(input) {
    if (input.files && input.files[0]) {
        const file = input.files[0];
        const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
        if (!allowedTypes.includes(file.type) || file.size > 3 * 1024 * 1024) {
            input.value = '';
            alert('La foto debe ser JPG, PNG o WEBP y pesar hasta 3 MB.');
            return;
        }
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
            } else {
                alert(json.error || 'No se pudo guardar la foto de perfil.');
            }
        } catch (err) {
            console.error('Error al subir avatar:', err);
            alert('No se pudo conectar con el servidor para guardar la foto.');
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
    formData.append('current_password', document.getElementById('tutorCurrentPassword').value);
    formData.append('new_password', document.getElementById('tutorNewPassword').value);
    formData.append('confirm_password', document.getElementById('tutorConfirmPassword').value);

    const photoInput = document.getElementById('tutorModalPhoto');
    const quickPhoto = document.getElementById('tutorPhotoInput');
    const avatarFile = photoInput && photoInput.files.length > 0
        ? photoInput.files[0]
        : (quickPhoto && quickPhoto.files.length > 0 ? quickPhoto.files[0] : null);
    if (avatarFile && (!['image/jpeg', 'image/png', 'image/webp'].includes(avatarFile.type) || avatarFile.size > 3 * 1024 * 1024)) {
        errorText.textContent = 'La foto debe ser JPG, PNG o WEBP y pesar hasta 3 MB.';
        errorMsg.style.display = 'block';
        btn.disabled = false;
        btn.textContent = 'Guardar cambios';
        return;
    }
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
    document.getElementById('editPetSexo').value = pet.sexo || '';
    document.getElementById('editPetBreed').value = pet.breed || '';
    document.getElementById('editPetWeight').value = pet.weight_kg || '';
    document.getElementById('editPetHeight').value = pet.height_cm || '';
    document.getElementById('editPetBirth').value = pet.birth_date || '';
    if (document.getElementById('editPetPhoto')) {
        document.getElementById('editPetPhoto').value = '';
    }

    const petImg = document.getElementById('editPetAvatarPreview');
    const petTitle = document.getElementById('editPetAvatarTitle');
    const petSub = document.getElementById('editPetAvatarSub');
    if (petImg) {
        if (pet.photo_path) {
            petImg.src = '<?= url("archivo_mascota.php?id=") ?>' + pet.id;
            if (petTitle) petTitle.textContent = 'Foto personalizada cargada';
            if (petSub) petSub.textContent = 'Podés seleccionar una nueva imagen para reemplazarla o dejarla así.';
        } else {
            petImg.src = detectPetAvatar(pet.animal_type);
            if (petTitle) petTitle.textContent = 'Avatar según tipo: ' + (pet.animal_type || 'Otro');
            if (petSub) petSub.textContent = 'Ilustración genérica asignada.';
        }
    }

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

    const formData = new FormData();
    formData.append('id', document.getElementById('editPetId').value);
    formData.append('name', document.getElementById('editPetName').value);
    formData.append('animal_type', document.getElementById('editPetType').value);
    formData.append('sexo', document.getElementById('editPetSexo').value);
    formData.append('breed', document.getElementById('editPetBreed').value);
    formData.append('weight_kg', document.getElementById('editPetWeight').value);
    formData.append('height_cm', document.getElementById('editPetHeight').value);
    formData.append('birth_date', document.getElementById('editPetBirth').value);

    const petPhotoFile = document.getElementById('editPetPhoto')?.files[0];
    if (petPhotoFile) {
        formData.append('photo', petPhotoFile);
    }

    try {
        const resp = await fetch('<?= url("cliente/api_actualizar_mascota.php") ?>', {
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
// MODAL: CÓDIGO DE ACCESO PARA VETERINARIA
// =========================================================================
let currentCodePetId = 0;
function openAccessCodeModalFor(petId, petName) {
    currentCodePetId = petId;
    document.getElementById('codePetNameSpan').textContent = petName;
    openModal('modalAccessCode');
    generateNewAccessCodeForPet();
}

async function generateNewAccessCodeForPet() {
    const display = document.getElementById('accessCodeDisplay');
    display.textContent = '...';
    try {
        const resp = await fetch('<?= url("cliente/api_codigo_acceso.php") ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ pet_id: currentCodePetId, action: 'generate' })
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
// MODAL: ASIGNAR OTROS DUEÑOS (CO-TUTORES) POR DNI
// =========================================================================
let currentCoOwnerPetId = 0;
async function openCoOwnersModalFor(petId, petName) {
    currentCoOwnerPetId = petId;
    document.getElementById('coOwnerPetNameSpan').textContent = petName;
    document.getElementById('coOwnerSuccessMsg').style.display = 'none';
    document.getElementById('coOwnerErrorMsg').style.display = 'none';
    document.getElementById('coOwnerDniInput').value = '';
    openModal('modalCoOwners');
    await loadCoOwnersList(petId);
}

async function loadCoOwnersList(petId) {
    const container = document.getElementById('coOwnersListContainer');
    container.innerHTML = '<p class="muted">Cargando...</p>';
    try {
        const resp = await fetch('<?= url("cliente/api_co_owners.php") ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ pet_id: petId, action: 'list' })
        });
        const json = await resp.json();
        if (json.ok && json.owners) {
            renderCoOwners(json.owners);
        } else {
            container.innerHTML = '<p class="muted">No se pudieron cargar los dueños.</p>';
        }
    } catch (e) {
        container.innerHTML = '<p class="muted">Error al cargar la lista.</p>';
    }
}

function renderCoOwners(owners) {
    const container = document.getElementById('coOwnersListContainer');
    const myId = <?= (int)$id ?>;
    container.innerHTML = owners.map(ow => `
        <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px 14px; background: var(--paper); border: 1px solid var(--line); border-radius: 10px;">
            <div>
                <strong style="font-size: 14px; color: var(--ink);">${ow.full_name}</strong>
                <div class="muted" style="font-size: 12px;">DNI: ${ow.dni} ${parseInt(ow.is_primary) === 1 ? '· <em>Tutor Principal</em>' : '· <em>Co-tutor</em>'}</div>
            </div>
            ${parseInt(ow.is_primary) !== 1 && parseInt(ow.id) !== myId ? `
                <button type="button" class="btn secondary btn-sm" style="font-size: 11px; padding: 4px 8px;" onclick="removeCoOwnerFor(${ow.id})">
                    <i class="fa-solid fa-user-minus"></i> Quitar
                </button>
            ` : ''}
        </div>
    `).join('');
}

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
            body: JSON.stringify({ pet_id: currentCoOwnerPetId, action: 'add', dni: dni })
        });
        const json = await resp.json();
        if (json.ok) {
            successMsg.textContent = json.message;
            successMsg.style.display = 'block';
            dniInput.value = '';
            renderCoOwners(json.owners);
            setTimeout(() => { successMsg.style.display = 'none'; }, 3000);
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

async function removeCoOwnerFor(targetClientId) {
    if (!confirm('¿Deseás remover a este usuario como co-dueño de la mascota?')) return;
    try {
        const resp = await fetch('<?= url("cliente/api_co_owners.php") ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ pet_id: currentCoOwnerPetId, action: 'remove', client_id: targetClientId })
        });
        const json = await resp.json();
        if (json.ok) {
            renderCoOwners(json.owners);
        } else {
            alert(json.error || 'No se pudo quitar el dueño.');
        }
    } catch (e) {
        alert('Error de conexión.');
    }
}

// Cerrar modales al hacer clic fuera del contenido
window.addEventListener('click', function(e) {
    const modal1 = document.getElementById('modalTutorProfile');
    const modal2 = document.getElementById('modalEditPet');
    const modal3 = document.getElementById('modalAccessCode');
    const modal4 = document.getElementById('modalCoOwners');
    if (e.target === modal1) closeModal('modalTutorProfile');
    if (e.target === modal2) closeModal('modalEditPet');
    if (e.target === modal3) closeModal('modalAccessCode');
    if (e.target === modal4) closeModal('modalCoOwners');
});

// Setup live preview para modal de edición
setupPetAvatarLivePreview({
    typeInput: document.getElementById('editPetType'),
    photoInput: document.getElementById('editPetPhoto'),
    previewImg: document.getElementById('editPetAvatarPreview'),
    titleEl: document.getElementById('editPetAvatarTitle'),
    subEl: document.getElementById('editPetAvatarSub')
});
</script>

<!-- MODAL CÓDIGO ACCESO -->
<div id="modalAccessCode" class="modal-overlay" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 9999; place-items: center; padding: 20px;">
    <div class="modal-window" style="background: #ffffff; border-radius: 20px; max-width: 480px; width: 100%; padding: 30px; box-shadow: 0 10px 40px rgba(0,0,0,0.2); text-align: center;">
        <div style="width: 60px; height: 60px; background: rgba(35,119,101,0.1); color: var(--green); border-radius: 50%; display: grid; place-items: center; font-size: 26px; margin: 0 auto 16px;">
            <i class="fa-solid fa-key"></i>
        </div>
        <h3 style="margin: 0 0 8px; font-size: 22px; color: var(--ink);">Código de atención veterinaria</h3>
        <p class="muted" style="margin: 0 0 20px; font-size: 13.5px; line-height: 1.5;">
            Dictele este código al veterinario para autorizarlo a asentar o modificar el historial clínico de <strong id="codePetNameSpan">tu mascota</strong>.
        </p>
        <div style="background: #f0f7f4; border: 2px dashed var(--green); border-radius: 16px; padding: 20px; margin-bottom: 20px;">
            <div id="accessCodeDisplay" style="font-size: 38px; font-weight: 800; font-family: monospace; letter-spacing: 6px; color: var(--green);">
                ------
            </div>
            <div style="font-size: 12px; color: var(--muted); margin-top: 6px;">
                <i class="fa-solid fa-clock" style="margin-right: 4px;"></i> Válido por 30 minutos · De un solo uso
            </div>
        </div>
        <div style="display: flex; gap: 10px; justify-content: center; flex-wrap: wrap;">
            <button type="button" class="btn secondary" onclick="generateNewAccessCodeForPet()">
                <i class="fa-solid fa-arrows-rotate" style="margin-right: 6px;"></i> Generar otro código
            </button>
            <button type="button" class="btn primary" onclick="closeModal('modalAccessCode')">
                Listo
            </button>
        </div>
    </div>
</div>

<!-- MODAL CO-DUEÑOS -->
<div id="modalCoOwners" class="modal-overlay" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 9999; place-items: center; padding: 20px;">
    <div class="modal-window" style="background: #ffffff; border-radius: 20px; max-width: 520px; width: 100%; padding: 28px; box-shadow: 0 10px 40px rgba(0,0,0,0.2); max-height: 90vh; overflow-y: auto;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
            <h3 style="margin: 0; font-size: 20px; color: var(--ink);">
                <i class="fa-solid fa-user-plus" style="color: var(--green); margin-right: 8px;"></i>Dueños y Co-tutores de <span id="coOwnerPetNameSpan">tu mascota</span>
            </h3>
            <button type="button" onclick="closeModal('modalCoOwners')" style="background: none; border: none; font-size: 22px; cursor: pointer; color: var(--muted);">&times;</button>
        </div>
        <p class="muted" style="margin: 0 0 18px; font-size: 13.5px; line-height: 1.5;">
            Ingresá el DNI de otra persona para que también figure como dueño/a y pueda acceder a su libreta sanitaria digital.
        </p>
        <form id="formAddCoOwner" style="display: flex; gap: 8px; margin-bottom: 20px;">
            <input type="text" id="coOwnerDniInput" placeholder="Ingresá número de DNI..." required inputmode="numeric" style="margin: 0; flex: 1; font-size: 14px;">
            <button type="submit" class="btn primary" id="btnAddCoOwner" style="flex-shrink: 0;">
                <i class="fa-solid fa-user-plus" style="margin-right: 4px;"></i> Asignar
            </button>
        </form>
        <div id="coOwnerSuccessMsg" style="display: none; padding: 10px 14px; background: #eaf7ee; color: #1e6348; border-radius: 10px; font-size: 13px; margin-bottom: 16px;"></div>
        <div id="coOwnerErrorMsg" style="display: none; padding: 10px 14px; background: #fef2f2; color: #991b1b; border-radius: 10px; font-size: 13px; margin-bottom: 16px;"></div>
        <h4 style="font-size: 14px; margin: 0 0 10px; color: var(--ink);">Personas con acceso de dueño:</h4>
        <div id="coOwnersListContainer" style="display: flex; flex-direction: column; gap: 8px;"></div>
    </div>
</div>

<script src="<?= url('assets/password-strength.js') ?>?v=1" defer></script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
