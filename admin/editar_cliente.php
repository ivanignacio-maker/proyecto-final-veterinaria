<?php
require_once __DIR__ . '/../config/config.php';
require_admin();

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

$q = db()->prepare("SELECT * FROM clients WHERE id = ?");
$q->execute([$id]);
$client = $q->fetch();

if (!$client) {
    flash('El tutor o dueño no existe.');
    header('Location: ' . url('admin/clientes.php'));
    exit;
}

$error = '';

// Procesamiento de formularios POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sent = $_POST['csrf'] ?? '';
    if (!is_string($sent) || !hash_equals($_SESSION['csrf'] ?? '', $sent)) {
        $error = 'La sesión expiró o el token de seguridad es inválido. Recargá la página.';
    } else {
        // 1. Crear nueva mascota para este tutor desde el modal
        if (isset($_POST['action']) && $_POST['action'] === 'crear_mascota') {
            $petName   = format_name(trim($_POST['new_pet_name'] ?? ''));
            $petType   = format_name(trim($_POST['new_pet_type'] ?? ''));
            $petSexo   = trim($_POST['new_pet_sexo'] ?? '');
            $petBreed  = trim($_POST['new_pet_breed'] ?? '');
            $birthDate = trim($_POST['new_birth_date'] ?? '');

            if (!in_array($petSexo, ['Macho', 'Hembra'], true)) {
                $petSexo = null;
            }

            if ($petName === '' || $petType === '' || !$petSexo) {
                $error = 'Completá al menos el nombre, la especie y el sexo (Macho o Hembra).';
            } else {
                $photoPath = null;
                if (!empty($_FILES['new_pet_photo']['name'])) {
                    $f = $_FILES['new_pet_photo'];
                    $mime = $f['error'] === UPLOAD_ERR_OK ? (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']) : '';
                    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

                    if ($f['error'] !== UPLOAD_ERR_OK || $f['size'] > 3 * 1024 * 1024 || !isset($allowed[$mime])) {
                        $error = 'La foto de la mascota debe ser JPG, PNG o WEBP y pesar hasta 3 MB.';
                    } else {
                        $dir = ensure_upload_dir() . DIRECTORY_SEPARATOR . 'pets';
                        if (!is_dir($dir)) mkdir($dir, 0750, true);
                        $photoRel = 'pets/' . bin2hex(random_bytes(20)) . '.' . $allowed[$mime];
                        if (move_uploaded_file($f['tmp_name'], ensure_upload_dir() . DIRECTORY_SEPARATOR . $photoRel)) {
                            $photoPath = $photoRel;
                        } else {
                            $error = 'No se pudo guardar la foto de la mascota. Volvé a intentarlo.';
                        }
                    }
                }

                if ($error === '') {
                    try {
                        $stmtPet = db()->prepare("
                            INSERT INTO pets (client_id, name, animal_type, sexo, breed, birth_date, photo_path) 
                            VALUES (?, ?, ?, ?, ?, ?, ?)
                        ");
                        $stmtPet->execute([
                            $id, 
                            $petName, 
                            $petType, 
                            $petSexo,
                            $petBreed !== '' ? $petBreed : null, 
                            $birthDate !== '' ? $birthDate : null,
                            $photoPath
                        ]);
                        $newPetId = (int)db()->lastInsertId();

                        audit_log('pet', $newPetId, 'admin_created', ['client_id' => $id, 'name' => $petName, 'sexo' => $petSexo]);

                        flash('Mascota agregada correctamente al tutor.');
                        header('Location: ' . url('admin/editar_cliente.php?id=' . $id));
                        exit;
                    } catch (Throwable $ex) {
                        error_log('Error creando mascota desde admin: ' . $ex->getMessage());
                        $error = 'No se pudo agregar la mascota. Revisá los datos ingresados.';
                    }
                }
            }
        }
        // 2. Guardar cambios generales del cliente y sus mascotas
        else {
            $form = [
                'full_name' => format_name(trim($_POST['full_name'] ?? '')),
                'dni'       => trim($_POST['dni'] ?? ''),
                'email'     => strtolower(trim($_POST['email'] ?? '')),
                'phone'     => trim($_POST['phone'] ?? ''),
                'password'  => (string)($_POST['password'] ?? ''),
            ];

            // Validar unicidad del DNI para otros tutores
            $qChkDni = db()->prepare("SELECT id FROM clients WHERE dni = ? AND id != ?");
            $qChkDni->execute([$form['dni'], $id]);
            $dniOccupied = $qChkDni->fetch();

            if ($form['full_name'] === '' || $form['dni'] === '' || !filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
                $error = 'Completá el nombre completo, el DNI y un correo electrónico válido.';
            } elseif ($dniOccupied) {
                $error = 'Ese número de DNI ya está asignado a otro tutor en el sistema.';
            } elseif ($form['password'] !== '' && strlen($form['password']) < 6) {
                $error = 'La nueva contraseña debe contener al menos 6 caracteres.';
            } else {
                // Obtener mascotas actuales para actualizar cambios inline
                $qPets = db()->prepare("SELECT id, name, animal_type, sexo, breed, birth_date, photo_path FROM pets WHERE client_id = ? ORDER BY id");
                $qPets->execute([$id]);
                $currentPets = $qPets->fetchAll();

                $postedPets = is_array($_POST['pets'] ?? null) ? $_POST['pets'] : [];

                try {
                    $pdo = db();
                    $pdo->beginTransaction();

                    // Actualizar datos del cliente
                    if ($form['password'] !== '') {
                        $qUp = $pdo->prepare("UPDATE clients SET full_name = ?, dni = ?, email = ?, phone = ?, password_hash = ? WHERE id = ?");
                        $qUp->execute([
                            $form['full_name'], 
                            $form['dni'], 
                            $form['email'], 
                            $form['phone'], 
                            password_hash($form['password'], PASSWORD_DEFAULT), 
                            $id
                        ]);
                    } else {
                        $qUp = $pdo->prepare("UPDATE clients SET full_name = ?, dni = ?, email = ?, phone = ? WHERE id = ?");
                        $qUp->execute([
                            $form['full_name'], 
                            $form['dni'], 
                            $form['email'], 
                            $form['phone'], 
                            $id
                        ]);
                    }

                    // Actualizar datos de cada mascota (incluyendo sexo y foto)
                    foreach ($currentPets as $p) {
                        $pid = (int)$p['id'];
                        if (isset($postedPets[$pid])) {
                            $inPet = $postedPets[$pid];
                            $pName  = format_name(trim((string)($inPet['name'] ?? '')));
                            $pType  = format_name(trim((string)($inPet['animal_type'] ?? '')));
                            $pSexo  = trim((string)($inPet['sexo'] ?? ''));
                            $pBreed = trim((string)($inPet['breed'] ?? ''));
                            $pBirth = trim((string)($inPet['birth_date'] ?? ''));

                            if (!in_array($pSexo, ['Macho', 'Hembra'], true)) {
                                $pSexo = null;
                            }

                            // Subida de nueva foto para esta mascota si se envió
                            $petPhotoRel = null;
                            if (!empty($_FILES['pet_photo_' . $pid]['name'])) {
                                $pf = $_FILES['pet_photo_' . $pid];
                                $pmime = $pf['error'] === UPLOAD_ERR_OK ? (new finfo(FILEINFO_MIME_TYPE))->file($pf['tmp_name']) : '';
                                $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
                                if ($pf['error'] === UPLOAD_ERR_OK && $pf['size'] <= 3 * 1024 * 1024 && isset($allowed[$pmime])) {
                                    $dir = ensure_upload_dir() . DIRECTORY_SEPARATOR . 'pets';
                                    if (!is_dir($dir)) mkdir($dir, 0750, true);
                                    $photoRel = 'pets/' . bin2hex(random_bytes(20)) . '.' . $allowed[$pmime];
                                    if (move_uploaded_file($pf['tmp_name'], ensure_upload_dir() . DIRECTORY_SEPARATOR . $photoRel)) {
                                        $petPhotoRel = $photoRel;
                                    } else {
                                        $error = 'No se pudo guardar la foto de una mascota. Volvé a intentarlo.';
                                    }
                                } else {
                                    $error = 'Cada foto debe ser JPG, PNG o WEBP y pesar hasta 3 MB.';
                                }
                            }

                            if ($pName !== '' && $pType !== '') {
                                if ($petPhotoRel !== null) {
                                    $stmtPetUp = $pdo->prepare("UPDATE pets SET name = ?, animal_type = ?, sexo = ?, breed = ?, birth_date = ?, photo_path = ? WHERE id = ? AND client_id = ?");
                                    $stmtPetUp->execute([
                                        $pName,
                                        $pType,
                                        $pSexo,
                                        $pBreed !== '' ? $pBreed : null,
                                        $pBirth !== '' ? $pBirth : null,
                                        $petPhotoRel,
                                        $pid,
                                        $id
                                    ]);
                                } else {
                                    $stmtPetUp = $pdo->prepare("UPDATE pets SET name = ?, animal_type = ?, sexo = ?, breed = ?, birth_date = ? WHERE id = ? AND client_id = ?");
                                    $stmtPetUp->execute([
                                        $pName,
                                        $pType,
                                        $pSexo,
                                        $pBreed !== '' ? $pBreed : null,
                                        $pBirth !== '' ? $pBirth : null,
                                        $pid,
                                        $id
                                    ]);
                                }
                            }
                        }
                    }

                    if ($error !== '') {
                        $pdo->rollBack();
                    } else {
                        $pdo->commit();

                        audit_log('client', $id, 'admin_update', [
                            'full_name' => $form['full_name'],
                            'dni'       => $form['dni'],
                            'email'     => $form['email'],
                        ]);

                        flash('Los datos del tutor y sus mascotas se guardaron correctamente.');
                        header('Location: ' . url('admin/clientes.php'));
                        exit;
                    }

                } catch (Throwable $ex) {
                    if (isset($pdo) && $pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    error_log('Error actualizando cliente desde admin: ' . $ex->getMessage());
                    $error = 'No se pudieron guardar los cambios. Revisá que el correo o DNI no estén en conflicto.';
                }
            }
        }
    }
}

// Cargar datos actuales actualizados
$q = db()->prepare("SELECT * FROM clients WHERE id = ?");
$q->execute([$id]);
$client = $q->fetch();

$form = [
    'full_name' => (string)$client['full_name'],
    'dni'       => (string)$client['dni'],
    'email'     => (string)$client['email'],
    'phone'     => (string)($client['phone'] ?? ''),
];

// Cargar mascotas del cliente
$qPets = db()->prepare("SELECT * FROM pets WHERE client_id = ? ORDER BY id");
$qPets->execute([$id]);
$pets = $qPets->fetchAll();

$pageTitle = 'Editar tutor | ' . e_name($client['full_name']);
require __DIR__ . '/../includes/header.php';
?>

<div class="panel">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px; margin-bottom: 8px;">
        <div style="display: flex; align-items: center; gap: 14px;">
            <div style="width: 54px; height: 54px; border-radius: 50%; background: #eaf5ef; border: 2px solid var(--green); display: grid; place-items: center; color: var(--green); flex-shrink: 0; font-size: 24px; overflow: hidden;">
                <img src="<?= !empty($client['avatar_path']) ? url('archivo_avatar.php?id=' . (int)$client['id']) : url('assets/persona_generica.png') ?>" alt="" style="width: 100%; height: 100%; object-fit: cover;">
            </div>
            <div>
                <h1 style="margin: 0; font-size: 26px;">
                    <i class="fa-solid fa-pen-to-square" style="color: var(--green); margin-right: 6px;"></i>Editar tutor / dueño
                </h1>
                <p class="muted" style="margin: 4px 0 0;">ID #<?= (int)$id ?> · Modificá los datos del responsable, sus credenciales y sus mascotas asociadas.</p>
            </div>
        </div>
        <div style="display: flex; gap: 8px;">
            <a href="<?= url('clientes/ver.php?id=' . (int)$id) ?>" class="btn secondary btn-sm" target="_blank" title="Ver ficha pública del tutor">
                <i class="fa-solid fa-arrow-up-right-from-square" style="margin-right: 4px;"></i> Ver ficha
            </a>
            <a href="<?= url('admin/clientes.php') ?>" class="btn secondary btn-sm">
                <i class="fa-solid fa-arrow-left" style="margin-right: 4px;"></i> Volver
            </a>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="notice error">
            <span class="notice-icon"><i class="fa-solid fa-triangle-exclamation"></i></span>
            <span><?= e($error) ?></span>
        </div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
        <input type="hidden" name="id" value="<?= (int)$id ?>">

        <h2 style="font-size: 20px; margin: 24px 0 16px;">Datos del tutor</h2>
        <div class="form-grid">
            <div>
                <label>Nombre y apellido completo *</label>
                <input type="text" name="full_name" maxlength="150" value="<?= e($form['full_name']) ?>" required>
            </div>

            <div>
                <label>DNI del tutor *</label>
                <input type="text" name="dni" maxlength="20" value="<?= e($form['dni']) ?>" required inputmode="numeric">
            </div>

            <div>
                <label>Correo electrónico (login) *</label>
                <input type="email" name="email" maxlength="190" value="<?= e($form['email']) ?>" required>
            </div>

            <div>
                <label>Teléfono o celular</label>
                <input type="text" name="phone" maxlength="50" value="<?= e($form['phone']) ?>">
            </div>

            <div>
                <label>Nueva contraseña (opcional)</label>
                <input type="password" name="password" minlength="6" placeholder="Dejala vacía para no modificarla" autocomplete="new-password">
            </div>
        </div>

        <!-- Sección de Mascotas del Tutor -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin: 32px 0 16px; flex-wrap: wrap; gap: 10px;">
            <div>
                <h2 style="font-size: 20px; margin: 0;">Mascotas a cargo (<?= count($pets) ?>)</h2>
                <p class="muted" style="margin: 4px 0 0; font-size: 13.5px;">Podés editar los datos y fotos de las mascotas directamente o registrar una nueva.</p>
            </div>
            <button type="button" class="btn secondary btn-sm" onclick="openModal('modalNuevaMascota')">
                <i class="fa-solid fa-plus" style="margin-right: 6px;"></i> Registrar nueva mascota
            </button>
        </div>

        <?php if (empty($pets)): ?>
            <div style="text-align: center; padding: 24px; background: var(--paper); border-radius: 12px; border: 1.5px dashed var(--line); margin-bottom: 20px;">
                <p class="muted" style="margin: 0;">Este tutor aún no tiene mascotas cargadas en la red.</p>
            </div>
        <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: 16px; margin-bottom: 24px;">
                <?php foreach ($pets as $p): ?>
                    <?php $pid = (int)$p['id']; ?>
                    <div style="border: 1px solid var(--line); border-radius: 14px; padding: 18px; background: var(--white);">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; flex-wrap: wrap; gap: 10px;">
                            <div style="display: flex; align-items: center; gap: 14px;">
                                <img src="<?= pet_photo_url($p) ?>" alt="Foto de <?= e_name($p['name']) ?>" style="width: 52px; height: 52px; border-radius: 14px; object-fit: cover; border: 1.5px solid var(--line);">
                                <div>
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <span class="pill" style="font-family: monospace; font-weight: 700;">#<?= $pid ?></span>
                                        <strong style="font-size: 17px; color: var(--ink);">
                                            <?= e_name($p['name']) ?>
                                        </strong>
                                        <span class="badge" style="background: rgba(35, 119, 101, 0.08); color: var(--green);">
                                            <?= e_name($p['animal_type'] ?: 'Mascota') ?>
                                        </span>
                                        <?php if (!empty($p['sexo'])): ?>
                                            <span class="badge" style="background: var(--paper); border: 1px solid var(--line); color: var(--ink);">
                                                <i class="fa-solid <?= $p['sexo'] === 'Hembra' ? 'fa-venus' : 'fa-mars' ?>" style="color: var(--green); margin-right: 4px;"></i>
                                                <?= e($p['sexo']) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <a href="<?= url('mascotas/ver.php?id=' . $pid) ?>" class="btn secondary btn-sm" target="_blank" style="font-size: 12px; padding: 5px 10px;">
                                <i class="fa-solid fa-book-medical" style="margin-right: 4px;"></i> Ver historia médica
                            </a>
                        </div>

                        <div class="form-grid">
                            <div>
                                <label>Nombre de la mascota *</label>
                                <input type="text" name="pets[<?= $pid ?>][name]" value="<?= e($p['name']) ?>" required>
                            </div>

                            <div>
                                <label>Especie / Tipo *</label>
                                <input type="text" name="pets[<?= $pid ?>][animal_type]" value="<?= e($p['animal_type']) ?>" placeholder="Ej. Perro, Gato" required>
                            </div>

                            <div>
                                <label>Sexo *</label>
                                <select name="pets[<?= $pid ?>][sexo]" required style="width: 100%;">
                                    <option value="">Seleccionar sexo</option>
                                    <option value="Macho" <?= ($p['sexo'] ?? '') === 'Macho' ? 'selected' : '' ?>>Macho</option>
                                    <option value="Hembra" <?= ($p['sexo'] ?? '') === 'Hembra' ? 'selected' : '' ?>>Hembra</option>
                                </select>
                            </div>

                            <div>
                                <label>Raza</label>
                                <input type="text" name="pets[<?= $pid ?>][breed]" value="<?= e($p['breed'] ?? '') ?>" placeholder="Ej. Caniche, Mestizo">
                            </div>

                            <div>
                                <label>Fecha de nacimiento</label>
                                <input type="date" name="pets[<?= $pid ?>][birth_date]" value="<?= e($p['birth_date'] ?? '') ?>">
                            </div>

                            <div>
                                <label>Actualizar foto de perfil (opcional)</label>
                                <input type="file" name="pet_photo_<?= $pid ?>" accept="image/jpeg,image/png,image/webp" style="font-size: 12px;">
                                <small class="muted" style="display: block; margin-top: 4px;">JPG, PNG o WEBP. Máximo 3 MB.</small>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="actions" style="margin-top: 24px; padding-top: 18px; border-top: 1px solid var(--line);">
            <button class="btn primary" type="submit">
                <i class="fa-solid fa-floppy-disk" style="margin-right: 6px;"></i> Guardar cambios
            </button>
            <a class="btn secondary" href="<?= url('admin/clientes.php') ?>">Cancelar</a>
        </div>
    </form>
</div>

<!-- Modal para agregar una nueva mascota -->
<div id="modalNuevaMascota" class="modal-overlay" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 9999; place-items: center; padding: 20px;">
    <div class="modal-window" style="background: #ffffff; border-radius: 20px; max-width: 480px; width: 100%; padding: 28px; box-shadow: 0 10px 40px rgba(0,0,0,0.2); max-height: 90vh; overflow-y: auto;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3 style="margin: 0; font-size: 18px; color: var(--ink);">
                <i class="fa-solid fa-paw" style="color: var(--green); margin-right: 6px;"></i>Nueva mascota para <?= e_name($client['full_name']) ?>
            </h3>
            <button type="button" onclick="closeModal('modalNuevaMascota')" style="background: none; border: none; font-size: 22px; cursor: pointer; color: var(--muted);">&times;</button>
        </div>

        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="crear_mascota">
            <input type="hidden" name="id" value="<?= (int)$id ?>">

            <!-- Vista previa dinámica según tipo de animal -->
            <div style="display: flex; align-items: center; gap: 14px; padding: 12px 14px; background: var(--paper); border: 1px solid var(--line); border-radius: 14px; margin-bottom: 16px;">
                <img id="adminNewPetPreview" src="<?= url('assets/otro_generico.png') ?>" alt="Avatar" style="width: 56px; height: 56px; border-radius: 14px; object-fit: cover; border: 2px solid var(--green); flex-shrink: 0; transition: transform 0.15s ease;">
                <div style="font-size: 13px;">
                    <strong id="adminNewPetTitle">Avatar ilustrado: Otro</strong>
                    <div class="muted" style="font-size: 11px;" id="adminNewPetSub">Se asigna según el tipo de animal indicado o foto.</div>
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 14px;">
                <label style="display: block; font-size: 13px; font-weight: 700; margin-bottom: 6px;">Nombre de la mascota *</label>
                <input type="text" name="new_pet_name" class="form-control" required style="width: 100%; padding: 10px; border: 1px solid var(--line); border-radius: 8px;" placeholder="Ej. Toby">
            </div>

            <div class="form-group" style="margin-bottom: 14px;">
                <label style="display: block; font-size: 13px; font-weight: 700; margin-bottom: 6px;">Especie / Tipo de animal *</label>
                <input type="text" name="new_pet_type" class="form-control" required style="width: 100%; padding: 10px; border: 1px solid var(--line); border-radius: 8px;" placeholder="Ej. Perro, Gato, Ave">
            </div>

            <div class="form-group" style="margin-bottom: 14px;">
                <label style="display: block; font-size: 13px; font-weight: 700; margin-bottom: 6px;">Sexo *</label>
                <select name="new_pet_sexo" class="form-control" required style="width: 100%; padding: 10px; border: 1px solid var(--line); border-radius: 8px;">
                    <option value="">Seleccionar sexo</option>
                    <option value="Macho">Macho</option>
                    <option value="Hembra">Hembra</option>
                </select>
            </div>

            <div class="form-group" style="margin-bottom: 14px;">
                <label style="display: block; font-size: 13px; font-weight: 700; margin-bottom: 6px;">Raza (opcional)</label>
                <input type="text" name="new_pet_breed" class="form-control" style="width: 100%; padding: 10px; border: 1px solid var(--line); border-radius: 8px;" placeholder="Ej. Mestizo, Golden">
            </div>

            <div class="form-group" style="margin-bottom: 14px;">
                <label style="display: block; font-size: 13px; font-weight: 700; margin-bottom: 6px;">Fecha de nacimiento (opcional)</label>
                <input type="date" name="new_birth_date" class="form-control" style="width: 100%; padding: 10px; border: 1px solid var(--line); border-radius: 8px;">
            </div>

            <div class="form-group" style="margin-bottom: 20px;">
                <label style="display: block; font-size: 13px; font-weight: 700; margin-bottom: 6px;">Foto de perfil (opcional)</label>
                <input type="file" name="new_pet_photo" accept="image/jpeg,image/png,image/webp" style="font-size: 13px;">
                <div style="font-size: 11px; color: var(--muted); margin-top: 4px;">JPG, PNG o WEBP. Máximo 3 MB.</div>
            </div>

            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" class="btn secondary" onclick="closeModal('modalNuevaMascota')">Cancelar</button>
                <button type="submit" class="btn primary">Agregar mascota</button>
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
    const m1 = document.getElementById('modalNuevaMascota');
    if (e.target === m1) closeModal('modalNuevaMascota');
});

document.addEventListener('DOMContentLoaded', function() {
    setupPetAvatarLivePreview({
        typeInput: document.querySelector('input[name="new_pet_type"]'),
        photoInput: document.querySelector('input[name="new_pet_photo"]'),
        previewImg: document.getElementById('adminNewPetPreview'),
        titleEl: document.getElementById('adminNewPetTitle'),
        subEl: document.getElementById('adminNewPetSub')
    });
});
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
