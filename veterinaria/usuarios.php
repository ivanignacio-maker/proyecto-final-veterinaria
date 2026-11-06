<?php
require_once __DIR__ . '/../config/config.php';
require_vet();
vet_enforce_role();

// Solo usuarios con rol de dueño pueden gestionar el equipo de la veterinaria
if (!vet_is_owner()) {
    flash('Acceso exclusivo para dueños de la clínica.');
    header('Location: ' . vet_home_url());
    exit;
}

$clinicId = (int)$_SESSION['clinic_id'];
$currentUserId = (int)$_SESSION['vet_user_id'];
$pdo = db();
$error = '';
$profileSchemaReady = true;

// Algunas instalaciones locales se crearon con el esquema inicial, anterior
// a los campos de nombre, apellido y avatar que usa esta pantalla. Sumarlos
// sin borrar ni recrear la tabla mantiene intactos usuarios y contraseñas.
try {
    $profileColumns = [
        'nombre' => "ALTER TABLE vet_users ADD COLUMN nombre VARCHAR(100) NOT NULL DEFAULT '' AFTER clinic_id",
        'apellido' => "ALTER TABLE vet_users ADD COLUMN apellido VARCHAR(100) NOT NULL DEFAULT '' AFTER nombre",
        'avatar_path' => "ALTER TABLE vet_users ADD COLUMN avatar_path VARCHAR(255) NULL AFTER role",
    ];
    foreach ($profileColumns as $column => $alterSql) {
        $columnCheck = $pdo->query("SHOW COLUMNS FROM vet_users LIKE " . $pdo->quote($column));
        if (!$columnCheck->fetch()) {
            $pdo->exec($alterSql);
        }
    }
    // Completa nombres vacíos de registros antiguos a partir del usuario
    // existente, para que el listado no quede sin identificar.
    $pdo->exec("UPDATE vet_users SET nombre = COALESCE(NULLIF(TRIM(nombre), ''), NULLIF(TRIM(username), ''), CONCAT('Usuario ', id)) WHERE nombre IS NULL OR TRIM(nombre) = ''");
} catch (Throwable $e) {
    error_log('Migración de perfil de usuarios veterinarios: ' . $e->getMessage());
    $profileSchemaReady = false;
    $error = 'No se pudo actualizar la estructura de usuarios de la veterinaria. Revisá que MySQL permita modificar la tabla vet_users.';
}

// Procesamiento de acciones POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $error === '') {
    csrf_check();
    $action = trim((string)($_POST['action'] ?? ''));

    // 1. REGISTRAR NUEVO USUARIO
    if ($action === 'crear_usuario') {
        $nombre   = format_name(trim((string)($_POST['new_nombre'] ?? '')));
        $apellido = format_name(trim((string)($_POST['new_apellido'] ?? '')));
        $email    = trim((string)($_POST['new_email'] ?? ''));
        $role     = trim((string)($_POST['new_role'] ?? ''));
        $password = (string)($_POST['new_password'] ?? '');

        if (!isset(VET_ROLES[$role]) && !in_array($role, ['dueno', 'dueño', 'jefe'], true)) {
            $error = 'Elegí un rol válido para el nuevo usuario (Dueño/a, Doctor/a o Recepcionista).';
        } elseif ($nombre === '' || $apellido === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Completá el nombre, el apellido y un correo electrónico válido.';
        } elseif ($password === '') {
            $error = 'Ingresá una contraseña inicial para el usuario.';
        } else {
            $saveRole = in_array($role, ['dueno', 'dueño', 'jefe'], true) ? 'jefe' : $role;

            $checkEmail = $pdo->prepare('SELECT id FROM vet_users WHERE LOWER(email) = LOWER(?) LIMIT 1');
            $checkEmail->execute([$email]);
            if ($checkEmail->fetch()) {
                $error = 'El correo electrónico ya está registrado en el sistema. Utilizá otro correo.';
            } else {
                try {
                    $ins = $pdo->prepare('INSERT INTO vet_users (clinic_id, nombre, apellido, username, email, role, password_hash, active) VALUES (?, ?, ?, ?, ?, ?, ?, 1)');
                    $ins->execute([
                        $clinicId,
                        $nombre,
                        $apellido,
                        trim($nombre . ' ' . $apellido),
                        $email,
                        $saveRole,
                        password_hash($password, PASSWORD_DEFAULT),
                    ]);
                    flash('Usuario ' . $nombre . ' ' . $apellido . ' agregado correctamente al equipo.');
                    header('Location: ' . url('veterinaria/usuarios.php'));
                    exit;
                } catch (Throwable $e) {
                    error_log('Error creando usuario de clínica: ' . $e->getMessage());
                    $error = 'No se pudo crear el usuario. Por favor revisá los datos e intentalo nuevamente.';
                }
            }
        }
    }

    // 2. EDITAR USUARIO (DATOS, ROL, CONTRASEÑA, ESTADO Y FOTO DE PERFIL)
    elseif ($action === 'editar_usuario') {
        $userId   = (int)($_POST['edit_user_id'] ?? 0);
        $nombre   = format_name(trim((string)($_POST['edit_nombre'] ?? '')));
        $apellido = format_name(trim((string)($_POST['edit_apellido'] ?? '')));
        $email    = trim((string)($_POST['edit_email'] ?? ''));
        $role     = trim((string)($_POST['edit_role'] ?? ''));
        $active   = !empty($_POST['edit_active']) ? 1 : 0;
        $password = (string)($_POST['edit_password'] ?? '');

        $targetQ = $pdo->prepare('SELECT id, role, active, avatar_path FROM vet_users WHERE id = ? AND clinic_id = ?');
        $targetQ->execute([$userId, $clinicId]);
        $targetUser = $targetQ->fetch();

        if (!$targetUser) {
            $error = 'El usuario que intentás modificar no existe o no pertenece a tu clínica.';
        } elseif (!isset(VET_ROLES[$role]) && !in_array($role, ['dueno', 'dueño', 'jefe'], true)) {
            $error = 'Elegí un rol válido para el usuario.';
        } elseif ($nombre === '' || $apellido === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Completá el nombre, el apellido y un correo válido.';
        } else {
            $saveRole = in_array($role, ['dueno', 'dueño', 'jefe'], true) ? 'jefe' : $role;

            $checkEmail = $pdo->prepare('SELECT id FROM vet_users WHERE LOWER(email) = LOWER(?) AND id != ? LIMIT 1');
            $checkEmail->execute([$email, $userId]);
            if ($checkEmail->fetch()) {
                $error = 'Ese correo ya está en uso por otro usuario.';
            } else {
                $activeOwnersQ = $pdo->prepare("SELECT COUNT(*) FROM vet_users WHERE clinic_id = ? AND active = 1 AND role IN ('jefe', 'dueno', 'dueño') AND id != ?");
                $activeOwnersQ->execute([$clinicId, $userId]);
                $otherActiveOwners = (int)$activeOwnersQ->fetchColumn();

                $isTargetOwner = vet_is_owner($targetUser['role']);
                $willBeActiveOwner = (vet_is_owner($saveRole) && $active === 1);

                if ($isTargetOwner && !$willBeActiveOwner && $otherActiveOwners === 0) {
                    $error = 'No se puede modificar: la veterinaria debe tener al menos un usuario activo con rol de Dueño/a.';
                } else {
                    try {
                        $newAvatarPath = $targetUser['avatar_path'];

                        // Procesamiento seguro de imagen con respaldo ante limitaciones de GD/JPEG en PHP
                        if (!empty($_FILES['edit_avatar']['name']) && $_FILES['edit_avatar']['error'] === UPLOAD_ERR_OK) {
                            $avatarFile = $_FILES['edit_avatar'];
                            $ext = strtolower(pathinfo($avatarFile['name'], PATHINFO_EXTENSION));

                            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true) && $avatarFile['size'] <= 5 * 1024 * 1024) {
                                $uploadDir = ensure_upload_dir() . DIRECTORY_SEPARATOR . 'avatars';
                                if (!is_dir($uploadDir)) { mkdir($uploadDir, 0755, true); }

                                $converted = false;
                                // Intentar conversión automática a WebP si GD está disponible
                                if (function_exists('imagecreatefromstring') && function_exists('imagewebp')) {
                                    $imgData = @file_get_contents($avatarFile['tmp_name']);
                                    $img = @imagecreatefromstring($imgData);
                                    if ($img !== false) {
                                        $fileName = 'vet_' . $userId . '_' . bin2hex(random_bytes(6)) . '.webp';
                                        $dest = $uploadDir . DIRECTORY_SEPARATOR . $fileName;
                                        if (@imagewebp($img, $dest, 85)) {
                                            @imagedestroy($img);
                                            $newAvatarPath = 'avatars/' . $fileName;
                                            $converted = true;
                                        } else {
                                            @imagedestroy($img);
                                        }
                                    }
                                }

                                // Respaldo: si GD no puede convertir, mover el archivo original directamente
                                if (!$converted) {
                                    $fileName = 'vet_' . $userId . '_' . bin2hex(random_bytes(6)) . '.' . ($ext === 'jpeg' ? 'jpg' : $ext);
                                    $dest = $uploadDir . DIRECTORY_SEPARATOR . $fileName;
                                    if (move_uploaded_file($avatarFile['tmp_name'], $dest)) {
                                        if (!empty($targetUser['avatar_path'])) {
                                            $oldFile = ensure_upload_dir() . DIRECTORY_SEPARATOR . $targetUser['avatar_path'];
                                            if (is_file($oldFile)) { @unlink($oldFile); }
                                        }
                                        $newAvatarPath = 'avatars/' . $fileName;
                                    }
                                } else {
                                    if (!empty($targetUser['avatar_path'])) {
                                        $oldFile = ensure_upload_dir() . DIRECTORY_SEPARATOR . $targetUser['avatar_path'];
                                        if (is_file($oldFile)) { @unlink($oldFile); }
                                    }
                                }
                            }
                        }

                        // Actualizar base de datos
                        if ($password !== '') {
                            $upd = $pdo->prepare('UPDATE vet_users SET nombre = ?, apellido = ?, email = ?, role = ?, active = ?, password_hash = ?, avatar_path = ? WHERE id = ? AND clinic_id = ?');
                            $upd->execute([$nombre, $apellido, $email, $saveRole, $active, password_hash($password, PASSWORD_DEFAULT), $newAvatarPath, $userId, $clinicId]);
                        } else {
                            $upd = $pdo->prepare('UPDATE vet_users SET nombre = ?, apellido = ?, email = ?, role = ?, active = ?, avatar_path = ? WHERE id = ? AND clinic_id = ?');
                            $upd->execute([$nombre, $apellido, $email, $saveRole, $active, $newAvatarPath, $userId, $clinicId]);
                        }

                        if ($userId === $currentUserId) {
                            $_SESSION['vet_username'] = trim($nombre . ' ' . $apellido);
                            $_SESSION['vet_email'] = $email;
                            if ($newAvatarPath) {
                                $_SESSION['vet_avatar'] = $newAvatarPath;
                            }
                        }

                        flash('Usuario ' . $nombre . ' ' . $apellido . ' actualizado con éxito.');
                        header('Location: ' . url('veterinaria/usuarios.php'));
                        exit;
                    } catch (Throwable $e) {
                        error_log('Error editando usuario: ' . $e->getMessage());
                        $error = 'No se pudo guardar la información del usuario.';
                    }
                }
            }
        }
    }

    // 3. CAMBIAR ESTADO RÁPIDO (DAR DE BAJA O REACTIVAR)
    elseif ($action === 'cambiar_estado') {
        $userId    = (int)($_POST['user_id'] ?? 0);
        $newActive = ((int)($_POST['new_active'] ?? 0) === 1) ? 1 : 0;

        $targetQ = $pdo->prepare('SELECT id, nombre, apellido, role, active FROM vet_users WHERE id = ? AND clinic_id = ?');
        $targetQ->execute([$userId, $clinicId]);
        $targetUser = $targetQ->fetch();

        if (!$targetUser) {
            $error = 'El usuario no existe o no pertenece a tu clínica.';
        } elseif ($newActive === 0 && vet_is_owner($targetUser['role'])) {
            $activeOwnersQ = $pdo->prepare("SELECT COUNT(*) FROM vet_users WHERE clinic_id = ? AND active = 1 AND role IN ('jefe', 'dueno', 'dueño') AND id != ?");
            $activeOwnersQ->execute([$clinicId, $userId]);
            if ((int)$activeOwnersQ->fetchColumn() === 0) {
                $error = 'No podés dar de baja al único Dueño/a activo de la clínica.';
            }
        }

        if ($error === '') {
            try {
                $upd = $pdo->prepare('UPDATE vet_users SET active = ? WHERE id = ? AND clinic_id = ?');
                $upd->execute([$newActive, $userId, $clinicId]);
                $fullName = trim(($targetUser['nombre'] ?? '') . ' ' . ($targetUser['apellido'] ?? ''));
                $msg = $newActive === 1 ? 'Usuario ' . $fullName . ' reactivado correctamente.' : 'Usuario ' . $fullName . ' dado de baja del sistema.';
                flash($msg);
                header('Location: ' . url('veterinaria/usuarios.php'));
                exit;
            } catch (Throwable $e) {
                error_log('Error cambiando estado: ' . $e->getMessage());
                $error = 'No se pudo actualizar el estado del usuario.';
            }
        }
    }
}

// Obtener todos los usuarios de la clínica
if ($profileSchemaReady) {
    $qUsers = $pdo->prepare('SELECT id, nombre, apellido, email, role, active, avatar_path, created_at FROM vet_users WHERE clinic_id = ? ORDER BY active DESC, role ASC, nombre ASC');
    $qUsers->execute([$clinicId]);
    $users = $qUsers->fetchAll();
} else {
    // Evita que una instalación sin permisos ALTER vuelva a terminar en un fatal
    // error al intentar leer columnas que todavía no existen.
    $users = [];
}

// Estadísticas de usuarios
$countTotal = count($users);
$countActive = 0;
$countInactive = 0;
$countOwners = 0;
$countDoctors = 0;
$countReception = 0;

foreach ($users as $u) {
    if ($u['active']) {
        $countActive++;
        if (vet_is_owner($u['role'])) $countOwners++;
        elseif ($u['role'] === 'doctor') $countDoctors++;
        elseif ($u['role'] === 'recepcionista') $countReception++;
    } else {
        $countInactive++;
    }
}

$pageTitle = 'Gestión de usuarios y equipo | ' . APP_NAME;
require_once __DIR__ . '/../includes/header.php';
?>

<div class="panel">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px; margin-bottom: 20px;">
        <div>
            <h1 style="margin: 0; font-size: 26px; display: flex; align-items: center; gap: 10px;">
                <i class="fa-solid fa-users-gear" style="color: var(--green);"></i>
                <span>Gestión de Usuarios y Equipo</span>
            </h1>
            <p class="muted" style="margin: 6px 0 0; font-size: 14px;">
                Administrá el equipo de <strong><?= e_name($_SESSION['clinic_name'] ?? 'tu veterinaria') ?></strong>: registrá nuevos usuarios, cambiá roles y controlá las bajas y altas de acceso.
            </p>
        </div>
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <button type="button" class="btn primary" onclick="openModal('modalNuevoUsuario')">
                <i class="fa-solid fa-user-plus" style="margin-right: 6px;"></i> Registrar nuevo usuario
            </button>
            <a href="<?= url('dashboard.php') ?>" class="btn secondary">
                <i class="fa-solid fa-arrow-left" style="margin-right: 6px;"></i> Volver al panel
            </a>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="notice error" style="margin-bottom: 20px;">
            <span class="notice-icon"><i class="fa-solid fa-triangle-exclamation"></i></span>
            <span><?= e($error) ?></span>
        </div>
    <?php endif; ?>

    <!-- Tarjetas de resumen rápido -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 14px; margin-bottom: 24px;">
        <div style="background: var(--white); border: 1px solid var(--line); border-radius: 14px; padding: 14px 18px; box-shadow: 0 2px 8px rgba(0,0,0,0.03);">
            <div style="font-size: 12px; color: var(--muted); font-weight: 700; text-transform: uppercase;">Total de usuarios</div>
            <div style="font-size: 26px; font-weight: 800; color: var(--ink); margin-top: 4px;"><?= $countTotal ?></div>
            <div style="font-size: 12px; color: var(--muted); margin-top: 2px;">En tu clínica</div>
        </div>
        <div style="background: var(--white); border: 1px solid var(--line); border-radius: 14px; padding: 14px 18px; box-shadow: 0 2px 8px rgba(0,0,0,0.03);">
            <div style="font-size: 12px; color: #166534; font-weight: 700; text-transform: uppercase;">Dueños activos</div>
            <div style="font-size: 26px; font-weight: 800; color: #166534; margin-top: 4px;"><?= $countOwners ?></div>
            <div style="font-size: 12px; color: var(--muted); margin-top: 2px;">Control total</div>
        </div>
        <div style="background: var(--white); border: 1px solid var(--line); border-radius: 14px; padding: 14px 18px; box-shadow: 0 2px 8px rgba(0,0,0,0.03);">
            <div style="font-size: 12px; color: #1e40af; font-weight: 700; text-transform: uppercase;">Doctores/as</div>
            <div style="font-size: 26px; font-weight: 800; color: #1e40af; margin-top: 4px;"><?= $countDoctors ?></div>
            <div style="font-size: 12px; color: var(--muted); margin-top: 2px;">Clínica y turnos</div>
        </div>
        <div style="background: var(--white); border: 1px solid var(--line); border-radius: 14px; padding: 14px 18px; box-shadow: 0 2px 8px rgba(0,0,0,0.03);">
            <div style="font-size: 12px; color: #6b21a8; font-weight: 700; text-transform: uppercase;">Recepcionistas</div>
            <div style="font-size: 26px; font-weight: 800; color: #6b21a8; margin-top: 4px;"><?= $countReception ?></div>
            <div style="font-size: 12px; color: var(--muted); margin-top: 2px;">Gestión de turnos</div>
        </div>
        <?php if ($countInactive > 0): ?>
        <div style="background: var(--white); border: 1px solid #fecaca; border-radius: 14px; padding: 14px 18px; box-shadow: 0 2px 8px rgba(0,0,0,0.03);">
            <div style="font-size: 12px; color: #991b1b; font-weight: 700; text-transform: uppercase;">Dados de baja</div>
            <div style="font-size: 26px; font-weight: 800; color: #991b1b; margin-top: 4px;"><?= $countInactive ?></div>
            <div style="font-size: 12px; color: var(--muted); margin-top: 2px;">Acceso deshabilitado</div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Listado de usuarios -->
    <div class="card" style="margin-top: 10px;">
        <h2 style="font-size: 19px; margin: 0 0 16px;">
            <i class="fa-solid fa-list-ul" style="color: var(--green); margin-right: 6px;"></i> Usuarios registrados
        </h2>

        <?php if (!$users): ?>
            <p class="muted">No se encontraron usuarios para esta veterinaria.</p>
        <?php else: ?>
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr>
                            <th style="text-align: left; padding: 12px 14px;">Usuario</th>
                            <th style="text-align: left; padding: 12px 14px;">Correo de acceso</th>
                            <th style="text-align: left; padding: 12px 14px;">Rol actual</th>
                            <th style="text-align: left; padding: 12px 14px;">Estado</th>
                            <th style="text-align: right; padding: 12px 14px;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                            <?php
                            $isMe = ((int)$u['id'] === $currentUserId);
                            $isOwner = vet_is_owner($u['role']);
                            $roleLabel = vet_role_label($u['role']);
                            $isActive = (bool)$u['active'];
                            $fullName = trim(($u['nombre'] ?? '') . ' ' . ($u['apellido'] ?? ''));
                            if ($fullName === '') $fullName = 'Usuario #' . $u['id'];

                            $userAvatarSrc = !empty($u['avatar_path'])
                                ? url('uploads/' . $u['avatar_path'])
                                : url('assets/veterinario_generico.webp');
                            ?>
                            <tr style="border-top: 1px solid var(--line); <?= !$isActive ? 'background: #fbfbfb; opacity: 0.8;' : '' ?>">
                                <td style="padding: 14px;">
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <div style="width:38px;height:38px;border-radius:50%;overflow:hidden;border:2px solid var(--line);flex-shrink:0;">
                                            <img src="<?= $userAvatarSrc ?>" alt="Foto de <?= e_name($fullName) ?>" style="width:100%;height:100%;object-fit:cover;">
                                        </div>
                                        <div>
                                            <strong style="color: var(--ink); font-size: 14.5px;"><?= e_name($fullName) ?></strong>
                                            <?php if ($isMe): ?>
                                                <span style="display: inline-block; background: #e0f2fe; color: #0369a1; font-size: 11px; font-weight: 700; padding: 2px 7px; border-radius: 6px; margin-left: 6px;">Tu usuario</span>
                                            <?php endif; ?>
                                            <div style="font-size: 11.5px; color: var(--muted); margin-top: 2px;">
                                                ID #<?= (int)$u['id'] ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td style="padding: 14px; font-size: 13.5px;">
                                    <a href="mailto:<?= e($u['email']) ?>" style="color: var(--ink); text-decoration: none;">
                                        <?= e($u['email']) ?>
                                    </a>
                                </td>
                                <td style="padding: 14px;">
                                    <?php if ($isOwner): ?>
                                        <span style="display: inline-flex; align-items: center; gap: 5px; background: #dcfce7; color: #15803d; padding: 4px 10px; border-radius: 999px; font-size: 12px; font-weight: 700; border: 1px solid #bbf7d0;">
                                            <i class="fa-solid fa-crown" style="font-size: 11px;"></i> <?= e($roleLabel) ?>
                                        </span>
                                    <?php elseif ($u['role'] === 'doctor'): ?>
                                        <span style="display: inline-flex; align-items: center; gap: 5px; background: #dbeafe; color: #1d4ed8; padding: 4px 10px; border-radius: 999px; font-size: 12px; font-weight: 700; border: 1px solid #bfdbfe;">
                                            <i class="fa-solid fa-user-doctor" style="font-size: 11px;"></i> <?= e($roleLabel) ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="display: inline-flex; align-items: center; gap: 5px; background: #f3e8ff; color: #7e22ce; padding: 4px 10px; border-radius: 999px; font-size: 12px; font-weight: 700; border: 1px solid #e9d5ff;">
                                            <i class="fa-solid fa-headset" style="font-size: 11px;"></i> <?= e($roleLabel) ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 14px;">
                                    <?php if ($isActive): ?>
                                        <span style="display: inline-flex; align-items: center; gap: 5px; color: #166534; font-size: 12.5px; font-weight: 700;">
                                            <i class="fa-solid fa-circle-check" style="color: #22c55e;"></i> Activo
                                        </span>
                                    <?php else: ?>
                                        <span style="display: inline-flex; align-items: center; gap: 5px; color: #991b1b; font-size: 12.5px; font-weight: 700;">
                                            <i class="fa-solid fa-circle-xmark" style="color: #ef4444;"></i> Dado de baja
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 14px; text-align: right; white-space: nowrap;">
                                    <div style="display: inline-flex; gap: 8px; align-items: center;">
                                        <button type="button" class="btn secondary btn-sm" onclick='openEditUserModal(<?= json_encode([
                                            'id'         => (int)$u['id'],
                                            'nombre'     => (string)($u['nombre'] ?? ''),
                                            'apellido'   => (string)($u['apellido'] ?? ''),
                                            'email'      => (string)$u['email'],
                                            'role'       => (string)$u['role'],
                                            'active'     => (int)$u['active'],
                                            'avatar_url' => !empty($u['avatar_path']) ? url('uploads/' . $u['avatar_path']) : url('assets/veterinario_generico.webp')
                                        ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' title="Modificar datos, rol, contraseña o foto">
                                            <i class="fa-solid fa-pen-to-square" style="margin-right: 4px;"></i> Editar
                                        </button>

                                        <?php if ($isActive): ?>
                                            <button type="button" class="btn btn-sm" style="background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; font-size: 12px;" onclick="confirmChangeStatus(<?= (int)$u['id'] ?>, '<?= e(addslashes($fullName)) ?>', 0, <?= ($isOwner && $countOwners <= 1) ? 'true' : 'false' ?>)" title="Dar de baja este usuario">
                                                <i class="fa-solid fa-user-xmark" style="margin-right: 4px;"></i> Dar de baja
                                            </button>
                                        <?php else: ?>
                                            <button type="button" class="btn btn-sm" style="background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; font-size: 12px;" onclick="confirmChangeStatus(<?= (int)$u['id'] ?>, '<?= e(addslashes($fullName)) ?>', 1, false)" title="Reactivar este usuario">
                                                <i class="fa-solid fa-user-check" style="margin-right: 4px;"></i> Reactivar
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- MODAL: REGISTRAR NUEVO USUARIO -->
<div id="modalNuevoUsuario" class="modal-overlay" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 9999; place-items: center; padding: 20px;">
    <div class="modal-window" style="background: #ffffff; border-radius: 20px; max-width: 500px; width: 100%; padding: 28px; box-shadow: 0 10px 40px rgba(0,0,0,0.2);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3 style="margin: 0; font-size: 20px; color: var(--ink);">
                <i class="fa-solid fa-user-plus" style="color: var(--green); margin-right: 8px;"></i>Nuevo integrante de la veterinaria
            </h3>
            <button type="button" onclick="closeModal('modalNuevoUsuario')" style="background: none; border: none; font-size: 22px; cursor: pointer; color: var(--muted);">&times;</button>
        </div>

        <form method="post">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="crear_usuario">

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 14px;">
                <div class="form-group">
                    <label style="display: block; font-size: 13px; font-weight: 700; margin-bottom: 6px;">Nombre *</label>
                    <input type="text" name="new_nombre" class="form-control" required style="width: 100%; padding: 10px 12px; border: 1px solid var(--line); border-radius: 10px; font-size: 14px;" placeholder="Ej. Santiago">
                </div>
                <div class="form-group">
                    <label style="display: block; font-size: 13px; font-weight: 700; margin-bottom: 6px;">Apellido *</label>
                    <input type="text" name="new_apellido" class="form-control" required style="width: 100%; padding: 10px 12px; border: 1px solid var(--line); border-radius: 10px; font-size: 14px;" placeholder="Ej. Romero">
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 14px;">
                <label style="display: block; font-size: 13px; font-weight: 700; margin-bottom: 6px;">Correo de acceso (login) *</label>
                <input type="email" name="new_email" class="form-control" required style="width: 100%; padding: 10px 12px; border: 1px solid var(--line); border-radius: 10px; font-size: 14px;" placeholder="santiago@veterinaria.com">
            </div>

            <div class="form-group" style="margin-bottom: 14px;">
                <label style="display: block; font-size: 13px; font-weight: 700; margin-bottom: 6px;">Rol en la clínica *</label>
                <select name="new_role" required style="width: 100%; padding: 10px 12px; border: 1px solid var(--line); border-radius: 10px; font-size: 14px;">
                    <option value="">Elegí un rol para este usuario</option>
                    <option value="jefe">Dueño/a de la clínica (acceso total a historiales, stock y usuarios)</option>
                    <option value="doctor">Doctor/a (atención médica, historiales clínicos y turnos)</option>
                    <option value="recepcionista">Recepcionista (gestión de agenda y turnos)</option>
                </select>
            </div>

            <div class="form-group" style="margin-bottom: 22px;">
                <label style="display: block; font-size: 13px; font-weight: 700; margin-bottom: 6px;">Contraseña inicial *</label>
                <input type="password" name="new_password" class="form-control" required style="width: 100%; padding: 10px 12px; border: 1px solid var(--line); border-radius: 10px; font-size: 14px;" placeholder="Contraseña de ingreso inicial" autocomplete="new-password">
            </div>

            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" class="btn secondary" onclick="closeModal('modalNuevoUsuario')">Cancelar</button>
                <button type="submit" class="btn primary">
                    <i class="fa-solid fa-check" style="margin-right: 6px;"></i> Registrar usuario
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: EDITAR USUARIO -->
<div id="modalEditarUsuario" class="modal-overlay" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 9999; place-items: center; padding: 20px;">
    <div class="modal-window" style="background: #ffffff; border-radius: 20px; max-width: 500px; width: 100%; padding: 28px; box-shadow: 0 10px 40px rgba(0,0,0,0.2); max-height: 90vh; overflow-y: auto;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3 style="margin: 0; font-size: 20px; color: var(--ink);">
                <i class="fa-solid fa-user-pen" style="color: var(--green); margin-right: 8px;"></i>Editar usuario del equipo
            </h3>
            <button type="button" onclick="closeModal('modalEditarUsuario')" style="background: none; border: none; font-size: 22px; cursor: pointer; color: var(--muted);">&times;</button>
        </div>

        <form method="post" id="formEditarUsuario" enctype="multipart/form-data">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="editar_usuario">
            <input type="hidden" name="edit_user_id" id="editUserId">

            <div style="display: flex; align-items: center; gap: 14px; padding: 12px 14px; background: #f8faf8; border: 1px solid var(--line); border-radius: 12px; margin-bottom: 16px;">
                <img id="editUserAvatarPreview" src="" alt="Avatar" style="width: 52px; height: 52px; border-radius: 50%; object-fit: cover; border: 2px solid var(--green); flex-shrink: 0;">
                <div style="flex: 1; min-width: 0;">
                    <label style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 4px; color: var(--ink);">Foto de perfil (opcional)</label>
                    <input type="file" name="edit_avatar" accept="image/jpeg,image/jpg,image/png,image/webp" style="font-size: 12px; width: 100%;">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 14px;">
                <div class="form-group">
                    <label style="display: block; font-size: 13px; font-weight: 700; margin-bottom: 6px;">Nombre *</label>
                    <input type="text" name="edit_nombre" id="editNombre" class="form-control" required style="width: 100%; padding: 10px 12px; border: 1px solid var(--line); border-radius: 10px; font-size: 14px;">
                </div>
                <div class="form-group">
                    <label style="display: block; font-size: 13px; font-weight: 700; margin-bottom: 6px;">Apellido *</label>
                    <input type="text" name="edit_apellido" id="editApellido" class="form-control" required style="width: 100%; padding: 10px 12px; border: 1px solid var(--line); border-radius: 10px; font-size: 14px;">
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 14px;">
                <label style="display: block; font-size: 13px; font-weight: 700; margin-bottom: 6px;">Correo de acceso (login) *</label>
                <input type="email" name="edit_email" id="editEmail" class="form-control" required style="width: 100%; padding: 10px 12px; border: 1px solid var(--line); border-radius: 10px; font-size: 14px;">
            </div>

            <div class="form-group" style="margin-bottom: 14px;">
                <label style="display: block; font-size: 13px; font-weight: 700; margin-bottom: 6px;">Rol en la clínica *</label>
                <select name="edit_role" id="editRole" required style="width: 100%; padding: 10px 12px; border: 1px solid var(--line); border-radius: 10px; font-size: 14px;">
                    <option value="jefe">Dueño/a de la clínica (todo: historiales, turnos, stock y usuarios)</option>
                    <option value="doctor">Doctor/a (atención médica, historiales y turnos)</option>
                    <option value="recepcionista">Recepcionista (solo turnos)</option>
                </select>
            </div>

            <div class="form-group" style="margin-bottom: 16px;">
                <label style="display: block; font-size: 13px; font-weight: 700; margin-bottom: 6px;">Nueva contraseña (opcional)</label>
                <input type="password" name="edit_password" class="form-control" style="width: 100%; padding: 10px 12px; border: 1px solid var(--line); border-radius: 10px; font-size: 14px;" placeholder="Dejala vacía si no querés modificarla" autocomplete="new-password">
            </div>

            <div class="form-group" style="margin-bottom: 22px; padding: 12px; background: #f9fafb; border-radius: 10px; border: 1px solid var(--line);">
                <label style="display: flex; align-items: center; justify-content: flex-start; gap: 10px; font-weight: 700; font-size: 13.5px; cursor: pointer; text-align: left;">
                    <input type="checkbox" name="edit_active" id="editActive" value="1" style="width: auto; margin: 0;">
                    <span>Usuario activo (habilitado para iniciar sesión)</span>
                </label>
            </div>

            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" class="btn secondary" onclick="closeModal('modalEditarUsuario')">Cancelar</button>
                <button type="submit" class="btn primary">
                    <i class="fa-solid fa-floppy-disk" style="margin-right: 6px;"></i> Guardar cambios
                </button>
            </div>
        </form>
    </div>
</div>

<form id="formCambiarEstado" method="post" style="display: none;">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <input type="hidden" name="action" value="cambiar_estado">
    <input type="hidden" name="user_id" id="statusUserId">
    <input type="hidden" name="new_active" id="statusNewActive">
</form>

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
    const m1 = document.getElementById('modalNuevoUsuario');
    const m2 = document.getElementById('modalEditarUsuario');
    if (e.target === m1) closeModal('modalNuevoUsuario');
    if (e.target === m2) closeModal('modalEditarUsuario');
});

function openEditUserModal(user) {
    document.getElementById('editUserId').value = user.id;
    document.getElementById('editNombre').value = user.nombre;
    document.getElementById('editApellido').value = user.apellido;
    document.getElementById('editEmail').value = user.email;
    document.getElementById('editUserAvatarPreview').src = user.avatar_url;

    const roleVal = (user.role === 'dueno' || user.role === 'dueño') ? 'jefe' : user.role;
    document.getElementById('editRole').value = roleVal;
    document.getElementById('editActive').checked = (parseInt(user.active) === 1);

    openModal('modalEditarUsuario');
}

function confirmChangeStatus(userId, fullName, newActive, isOnlyOwner) {
    if (newActive === 0) {
        if (isOnlyOwner) {
            alert('No es posible dar de baja a este usuario porque es el único Dueño/a activo de la clínica.');
            return;
        }
        if (!confirm('¿Estás seguro de que querés dar de baja a "' + fullName + '"?\nEl usuario no podrá volver a iniciar sesión hasta que sea reactivado.')) {
            return;
        }
    } else {
        if (!confirm('¿Deseás reactivar el acceso para "' + fullName + '"?')) {
            return;
        }
    }

    document.getElementById('statusUserId').value = userId;
    document.getElementById('statusNewActive').value = newActive;
    document.getElementById('formCambiarEstado').submit();
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
