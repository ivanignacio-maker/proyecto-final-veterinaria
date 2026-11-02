<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/pets_delete.php';
require_admin();

/**
 * Revisa una imagen subida ($_FILES[...]) y devuelve el texto del problema, o null si está bien.
 * Se usa ANTES de abrir la transacción, así un archivo no permitido nunca llega a la base de datos.
 */
function admin_image_error(array $f): ?string {
    if ($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE || $f['size'] > 5 * 1024 * 1024) {
        return 'La imagen pesa demasiado. El máximo es 5 MB.';
    }
    if ($f['error'] !== UPLOAD_ERR_OK) {
        return 'No se pudo recibir la imagen. Volvé a intentarlo.';
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
        return 'No se permite ese tipo de imagen. Usá JPG, PNG o WEBP.';
    }
    return null;
}

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
        // 0. Eliminar una mascota de este tutor (solo el administrador llega hasta acá)
        if (isset($_POST['action']) && $_POST['action'] === 'eliminar_mascota') {
            $delPetId = (int)($_POST['pet_id'] ?? 0);

            // La mascota tiene que pertenecer al tutor que se está editando
            $qDel = db()->prepare("SELECT id, name FROM pets WHERE id = ? AND client_id = ?");
            $qDel->execute([$delPetId, $id]);
            $petToDelete = $qDel->fetch();

            if (!$petToDelete) {
                $error = 'La mascota no existe o no pertenece a este tutor.';
            } else {
                try {
                    $result = delete_pet_cascade(db(), $delPetId);   // borra todo en una transacción
                    delete_pet_files($result);                       // y después quita los archivos del disco
                    audit_log('pet', $delPetId, 'admin_deleted', ['client_id' => $id, 'name' => $petToDelete['name']]);

                    flash('La mascota ' . format_name($petToDelete['name']) . ' y todo su historial fueron eliminados.');
                    header('Location: ' . url('admin/editar_cliente.php?id=' . $id));
                    exit;
                } catch (Throwable $ex) {
                    error_log('Error eliminando mascota desde admin: ' . $ex->getMessage());
                    $step = preg_match('/Paso «([^»]+)»/u', $ex->getMessage(), $m) ? ' (falló en: ' . $m[1] . ')' : '';
                    $error = 'No se pudo eliminar la mascota' . $step . '. No se borró nada.';
                }
            }
        }
        // 1. Crear nueva mascota para este tutor desde el modal
        elseif (isset($_POST['action']) && $_POST['action'] === 'crear_mascota') {
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
                    $allowed = ['image/jpeg', 'image/png', 'image/webp'];

                    if ($f['error'] !== UPLOAD_ERR_OK || $f['size'] > 5 * 1024 * 1024 || !in_array($mime, $allowed, true)) {
                        $error = 'La foto de la mascota debe ser JPG, PNG o WEBP y pesar hasta 5 MB.';
                    } else {
                        $saved = save_uploaded_image_as_webp($f, 'pets', bin2hex(random_bytes(20)));
                        if ($saved !== null) {
                            $photoPath = $saved;
                        } else {
                            $error = 'No se pudo procesar la foto en formato WebP.';
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

            // Validar las imágenes subidas (foto del tutor y de sus mascotas) antes de tocar la base
            $uploadError = null;
            $avatarFile = (!empty($_FILES['client_avatar']['name'])) ? $_FILES['client_avatar'] : null;
            if ($avatarFile) {
                $uploadError = admin_image_error($avatarFile);
            }
            foreach ($_FILES as $fieldName => $fileInfo) {
                if ($uploadError === null && str_starts_with($fieldName, 'pet_photo_') && !empty($fileInfo['name'])) {
                    $uploadError = admin_image_error($fileInfo);
                }
            }

            // Validar unicidad del DNI para otros tutores
            $qChkDni = db()->prepare("SELECT id FROM clients WHERE dni = ? AND id != ?");
            $qChkDni->execute([$form['dni'], $id]);
            $dniOccupied = $qChkDni->fetch();

            if ($uploadError !== null) {
                $error = $uploadError;
            } elseif ($form['full_name'] === '' || $form['dni'] === '' || !filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
                $error = 'Completá el nombre completo, el DNI y un correo electrónico válido.';
            } elseif ($dniOccupied) {
                $error = 'Ese número de DNI ya está asignado a otro tutor en el sistema.';
            } elseif ($form['password'] !== '' && ($passError = password_policy_error($form['password'])) !== null) {
                $error = $passError;
            } else {
                // Obtener mascotas actuales para actualizar cambios inline
                $qPets = db()->prepare("SELECT id, name, animal_type, sexo, breed, birth_date, photo_path FROM pets WHERE client_id = ? ORDER BY id");
                $qPets->execute([$id]);
                $currentPets = $qPets->fetchAll();

                $postedPets = is_array($_POST['pets'] ?? null) ? $_POST['pets'] : [];

                // Foto de perfil del tutor: se convierte a WebP automáticamente antes de guardarla
                $newAvatarRel = null;
                if ($avatarFile) {
                    $newAvatarRel = save_uploaded_image_as_webp($avatarFile, 'avatars', 'client_' . $id . '_' . bin2hex(random_bytes(8)));
                    if ($newAvatarRel === null) {
                        $error = 'No se pudo convertir la imagen a formato WebP. Probá con otra foto.';
                    }
                }
                $removeAvatar = !empty($_POST['remove_avatar']) && $newAvatarRel === null;

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

                    // Foto de perfil del tutor (nueva, o quitar la actual)
                    if ($newAvatarRel !== null) {
                        $pdo->prepare("UPDATE clients SET avatar_path = ? WHERE id = ?")->execute([$newAvatarRel, $id]);
                    } elseif ($removeAvatar) {
                        $pdo->prepare("UPDATE clients SET avatar_path = NULL WHERE id = ?")->execute([$id]);
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
                                $allowed = ['image/jpeg', 'image/png', 'image/webp'];
                                if ($pf['error'] === UPLOAD_ERR_OK && $pf['size'] <= 5 * 1024 * 1024 && in_array($pmime, $allowed, true)) {
                                    $saved = save_uploaded_image_as_webp($pf, 'pets', bin2hex(random_bytes(20)));
                                    if ($saved !== null) {
                                        $petPhotoRel = $saved;
                                    } else {
                                        $error = 'No se pudo procesar la foto en formato WebP.';
                                    }
                                } else {
                                    $error = 'No se permite ese tipo de imagen. Usá JPG, PNG o WEBP.';
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
                        if ($newAvatarRel) { @unlink(ensure_upload_dir() . DIRECTORY_SEPARATOR . $newAvatarRel); }
                    } else {
                        $pdo->commit();

                        // Con la base ya confirmada, se borra del disco la foto anterior si fue reemplazada o quitada
                        if (($newAvatarRel !== null || $removeAvatar) && !empty($client['avatar_path'])) {
                            $oldFile = ensure_upload_dir() . DIRECTORY_SEPARATOR . $client['avatar_path'];
                            if (is_file($oldFile)) { @unlink($oldFile); }
                        }

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
                    if (!empty($newAvatarRel)) { @unlink(ensure_upload_dir() . DIRECTORY_SEPARATOR . $newAvatarRel); }
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
                <img src="<?= !empty($client['avatar_path']) ? url('archivo_avatar.php?id=' . (int)$client['id']) : url('assets/persona_generica.webp') ?>" alt="" style="width: 100%; height: 100%; object-fit: cover;">
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

        <!-- Foto de perfil del tutor: el admin puede cambiarla o quitarla -->
        <div style="display: flex; align-items: center; gap: 18px; flex-wrap: wrap; padding: 14px 16px; border: 1px solid var(--line); border-radius: 14px; margin-bottom: 18px;">
            <img id="clientAvatarPreview"
                 src="<?= !empty($client['avatar_path']) ? url('archivo_avatar.php?id=' . (int)$client['id']) : url('assets/persona_generica.webp') ?>"
                 alt="Foto de perfil actual" style="width: 72px; height: 72px; border-radius: 50%; object-fit: cover; border: 2px solid var(--green); flex-shrink: 0;">
            <div style="flex: 1; min-width: 240px;">
                <label for="clientAvatarInput" style="font-weight: 700;"><i class="fa-solid fa-camera" style="color: var(--green); margin-right: 6px;"></i>Cambiar foto de perfil del tutor</label>
                <input type="file" id="clientAvatarInput" name="client_avatar" accept="image/jpeg,image/png,image/webp" style="font-size: 13px;">
                <small class="muted" style="display: block; margin-top: 4px;">JPG, PNG o WEBP, hasta 5 MB. Se convierte a WebP automáticamente al guardar.</small>
                <div id="clientAvatarNotice" role="alert" style="display: none; margin-top: 8px; padding: 8px 12px; background: #fef2f2; color: #991b1b; border: 1px solid #fca5a5; border-radius: 10px; font-size: 13px;"></div>
                <?php if (!empty($client['avatar_path'])): ?>
                    <label style="display: flex; align-items: center; gap: 6px; margin-top: 8px; font-size: 13px;">
                        <input type="checkbox" name="remove_avatar" value="1" style="width: auto;"> Quitar la foto actual
                    </label>
                <?php endif; ?>
            </div>
        </div>

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
                <input type="password" name="password" minlength="8" placeholder="Dejala vacía para no modificarla" title="<?= e(password_policy_hint()) ?>" autocomplete="new-password">
                <small class="muted" style="display: block; margin-top: 4px; font-size: 12px;"><?= e(password_policy_hint()) ?></small>
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
                            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                                <a href="<?= url('admin/ver_mascota.php?id=' . $pid) ?>" class="btn secondary btn-sm" target="_blank" style="font-size: 12px; padding: 5px 10px;">
                                    <i class="fa-solid fa-book-medical" style="margin-right: 4px;"></i> Ver historia médica
                                </a>
                                <button type="button" class="btn secondary btn-sm" style="font-size: 12px; padding: 5px 10px; color: #dc2626; border-color: #fca5a5;"
                                        data-pet-id="<?= $pid ?>" data-pet-name="<?= e($p['name']) ?>" onclick="confirmDeletePet(this)">
                                    <i class="fa-solid fa-trash-can" style="margin-right: 4px;"></i> Eliminar mascota
                                </button>
                            </div>
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

<!-- Formulario oculto para eliminar una mascota (no puede ir dentro del formulario grande de arriba) -->
<form id="formDeletePet" method="post" style="display: none;">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <input type="hidden" name="action" value="eliminar_mascota">
    <input type="hidden" name="id" value="<?= (int)$id ?>">
    <input type="hidden" name="pet_id" id="deletePetIdField" value="">
</form>

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
                <img id="adminNewPetPreview" src="<?= url('assets/otro_generico.webp') ?>" alt="Avatar" style="width: 56px; height: 56px; border-radius: 14px; object-fit: cover; border: 2px solid var(--green); flex-shrink: 0; transition: transform 0.15s ease;">
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

function confirmDeletePet(btn) {
    const name = btn.dataset.petName;
    if (!confirm('¿Eliminar a ' + name + ' definitivamente?\n\nSe borrarán también su historial médico, turnos, códigos y archivos adjuntos. Esta acción no se puede deshacer.')) {
        return;
    }
    document.getElementById('deletePetIdField').value = btn.dataset.petId;
    document.getElementById('formDeletePet').submit();
}

function closeModal(id) {
    const el = document.getElementById(id);
    if (el) el.style.display = 'none';
}

window.addEventListener('click', function(e) {
    const m1 = document.getElementById('modalNuevaMascota');
    if (e.target === m1) closeModal('modalNuevaMascota');
});

// Foto del tutor: avisa en el acto si el tipo de imagen no está permitido y muestra la vista previa
(function () {
    const input = document.getElementById('clientAvatarInput');
    const notice = document.getElementById('clientAvatarNotice');
    const preview = document.getElementById('clientAvatarPreview');
    if (!input) return;
    input.addEventListener('change', function () {
        notice.style.display = 'none';
        const file = input.files && input.files[0];
        if (!file) return;
        let problem = null;
        if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
            problem = 'No se permite ese tipo de imagen. Usá JPG, PNG o WEBP.';
        } else if (file.size > 5 * 1024 * 1024) {
            problem = 'La imagen pesa demasiado. El máximo es 5 MB.';
        }
        if (problem) {
            input.value = '';
            notice.textContent = problem;
            notice.style.display = 'block';
            return;
        }
        preview.src = URL.createObjectURL(file);
    });
})();

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
