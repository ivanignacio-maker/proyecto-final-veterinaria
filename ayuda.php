<?php
/**
 * ayuda.php — Centro de ayuda y soporte.
 *
 * Una sola página para tutores y veterinarias: muestra las preguntas frecuentes que
 * corresponden a quien está logueado (y, en veterinarias, a su rol), un formulario de
 * soporte (se guarda en support_messages y avisa por correo) y los datos de contacto.
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/solicitudes.php';

$isClient = !empty($_SESSION['client_id']);
$isVet    = !empty($_SESSION['vet_user_id']) && !empty($_SESSION['clinic_id']);

// Sin sesión: se lo manda al contacto público.
if (!$isClient && !$isVet) {
    header('Location: ' . url('contacto.php'));
    exit;
}

$role = $isVet ? vet_current_role() : '';

// ---------------------------------------------------------------------------
// Formulario de soporte (similar al de contacto.php, pero ya sabemos quién escribe)
// ---------------------------------------------------------------------------
$senderType = $isClient ? 'tutor' : 'veterinaria';
$topics = $isClient
    ? ['Problema con mis mascotas o su historial', 'Turnos', 'Mi cuenta o contraseña', 'Veterinarias cercanas', 'Sugerencia', 'Otro']
    : ['Problema con historiales médicos', 'Turnos', 'Usuarios, roles o contraseñas', 'Stock', 'Facturación o plan', 'Sugerencia', 'Otro'];

// Datos de quien escribe (para precompletar el formulario y registrar el mensaje).
$sender = ['name' => '', 'email' => '', 'phone' => '', 'clinic' => ''];
if ($isClient) {
    $q = db()->prepare('SELECT full_name, email, phone FROM clients WHERE id = ?');
    $q->execute([(int)$_SESSION['client_id']]);
    if ($r = $q->fetch()) {
        $sender = ['name' => (string)$r['full_name'], 'email' => (string)$r['email'], 'phone' => (string)($r['phone'] ?? ''), 'clinic' => ''];
    }
} else {
    $q = db()->prepare('SELECT u.username, u.email, c.name AS clinic_name, c.phone AS clinic_phone FROM vet_users u JOIN clinics c ON c.id = u.clinic_id WHERE u.id = ?');
    $q->execute([(int)$_SESSION['vet_user_id']]);
    if ($r = $q->fetch()) {
        $sender = ['name' => (string)$r['username'], 'email' => (string)$r['email'], 'phone' => (string)($r['clinic_phone'] ?? ''), 'clinic' => (string)$r['clinic_name']];
    }
}

$msgSuccess = '';
$msgError   = '';
$form = ['nombre' => $sender['name'], 'email' => $sender['email'], 'telefono' => $sender['phone'], 'asunto' => $topics[0], 'mensaje' => ''];

if (!empty($_SESSION['help_sent'])) {          // viene de un envío exitoso (patrón Post → Redirect → Get)
    unset($_SESSION['help_sent']);
    $msgSuccess = '¡Gracias! Recibimos tu mensaje y te vamos a responder al correo indicado a la brevedad.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    foreach (['nombre', 'email', 'telefono', 'asunto', 'mensaje'] as $k) {
        $form[$k] = trim((string)($_POST[$k] ?? ''));
    }
    $form['email'] = strtolower($form['email']);

    if ($form['nombre'] === '' || $form['email'] === '' || $form['mensaje'] === '') {
        $msgError = 'Completá los campos obligatorios (nombre, correo y mensaje).';
    } elseif (!filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
        $msgError = 'Ingresá un correo electrónico válido.';
    } elseif (!in_array($form['asunto'], $topics, true)) {
        $msgError = 'Elegí un asunto de la lista.';
    } elseif (mb_strlen($form['mensaje']) < 10) {
        $msgError = 'Contanos un poco más: el mensaje es demasiado corto.';
    } elseif (mb_strlen($form['mensaje']) > 3000 || mb_strlen($form['nombre']) > 120) {
        $msgError = 'El mensaje (máx. 3000 caracteres) o el nombre son demasiado largos.';
    } else {
        try {
            $pdo = db();
            $ownerCol = $isClient ? 'client_id' : 'vet_user_id';
            $ownerId  = $isClient ? (int)$_SESSION['client_id'] : (int)$_SESSION['vet_user_id'];

            // Freno anti-spam: máximo 5 mensajes por hora por persona.
            $recent = $pdo->prepare("SELECT COUNT(*) FROM support_messages WHERE $ownerCol = ? AND created_at > (NOW() - INTERVAL 1 HOUR)");
            $recent->execute([$ownerId]);
            if ((int)$recent->fetchColumn() >= 5) {
                $msgError = 'Enviaste varios mensajes seguidos. Esperá un rato antes de escribir de nuevo.';
            } else {
                $pdo->prepare('INSERT INTO support_messages (sender_type, client_id, vet_user_id, clinic_id, name, email, phone, subject, message) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)')
                    ->execute([
                        $senderType,
                        $isClient ? $ownerId : null,
                        $isVet ? $ownerId : null,
                        $isVet ? (int)$_SESSION['clinic_id'] : null,
                        $form['nombre'], $form['email'], $form['telefono'] ?: null,
                        $form['asunto'], $form['mensaje'],
                    ]);

                $who = $isVet ? $form['nombre'] . ' (' . vet_role_label($role) . ', ' . $sender['clinic'] . ')' : $form['nombre'] . ' (tutor)';
                notify_support_team(
                    'Soporte [' . $form['asunto'] . '] de ' . $form['nombre'],
                    '<p><strong>' . e($who) . '</strong> · ' . e($form['email']) . ($form['telefono'] ? ' · ' . e($form['telefono']) : '') . '</p>'
                    . '<p><strong>Asunto:</strong> ' . e($form['asunto']) . '</p><p>' . nl2br(e($form['mensaje'])) . '</p>',
                    "$who - {$form['email']}
Asunto: {$form['asunto']}

{$form['mensaje']}"
                );

                $_SESSION['help_sent'] = 1;
                header('Location: ' . url('ayuda.php') . '#contacto');
                exit;
            }
        } catch (Throwable $ex) {
            error_log('ayuda.php (soporte): ' . $ex->getMessage());
            $msgError = 'No pudimos enviar tu mensaje. Probá de nuevo en unos minutos o escribinos por correo.';
        }
    }
}

// Cada sección: título, ícono y lista de [pregunta, respuesta].
$sections = [];

if ($isClient) {
    $sections[] = ['Mis mascotas', 'fa-paw', [
        ['¿Cómo registro una mascota?', 'Entrá a "Mis mascotas" y tocá "Registrar mascota". Completá nombre, especie y sexo; la raza, el peso y la foto son opcionales.'],
        ['¿Cómo cambio mis datos o mi foto de perfil?', 'En "Mis mascotas" tocá "Editar mis datos" o el ícono de la cámara sobre tu foto. Aceptamos fotos JPG, PNG o WEBP; se guardan automáticamente en formato WebP.'],
        ['¿Cómo edito los datos o la foto de mi mascota?', 'En la tarjeta de tu mascota tocá "Editar datos". Ahí también podés cambiar su foto.'],
        ['¿Cómo doy de baja a una mascota?', 'En su tarjeta tocá el ícono del tacho de basura y elegí el motivo. Si sos el tutor principal se borra la mascota con todo su historial; si sos co-tutor solo te desvinculás.'],
    ]];
    $sections[] = ['Turnos y veterinarias', 'fa-calendar-check', [
        ['¿Cómo pido un turno?', 'Entrá a "Turnos", elegí la veterinaria, tu mascota, el profesional que preferís (o "sin preferencia"), la fecha y el motivo. La veterinaria tiene que confirmar la solicitud.'],
        ['¿Por qué solo veo algunas veterinarias?', 'La lista muestra las que ya visitaste. Para pedir turno en otra, buscala por nombre o tocá "Ordenar por cercanía".'],
        ['¿Cómo encuentro veterinarias cerca mío?', 'En "Veterinarias cercanas" tocá "Usar mi ubicación" o escribí tu dirección. Desde ahí podés ir directo a pedir turno en la que elijas.'],
        ['¿Cómo sé si mi turno fue confirmado?', 'En "Turnos", abajo, vas a ver el estado de cada solicitud: Solicitado, Confirmado, Cancelado o Atendido.'],
    ]];
    $sections[] = ['Libreta sanitaria y accesos', 'fa-notes-medical', [
        ['¿Dónde veo el historial médico?', 'En "Mis mascotas" tocá "Ver libreta sanitaria" en la mascota. Ahí están las consultas, diagnósticos, vacunas y estudios adjuntos.'],
        ['¿Cómo adjunto un estudio?', 'En la libreta sanitaria, dentro de la consulta, subí un PDF, JPG o PNG de hasta 5 MB.'],
        ['¿Para qué sirve el código para la veterinaria?', 'Es un código de 6 dígitos, válido 30 minutos y de un solo uso, que le dictás al veterinario para autorizarlo a cargar o modificar el historial de tu mascota.'],
        ['¿Cómo comparto una mascota con otra persona?', 'Usá "Co-tutores" en la tarjeta de la mascota e ingresá el DNI de la otra persona (tiene que tener cuenta).'],
    ]];
}

if ($isVet) {
    $sections[] = ['Primeros pasos', 'fa-circle-play', [
        ['¿Qué puedo hacer según mi rol?', 'Dueño/a: todo (historiales, turnos, stock y gestión de usuarios). Doctor/a: historiales médicos y turnos. Recepcionista: solo turnos. Tu rol actual es: ' . vet_role_label($role) . '.'],
        ['¿Cómo cambio mi contraseña o mis datos?', 'Desde el panel de inicio, con el botón de perfil. Si tu rol es recepcionista y no ves el panel, pedile al dueño/a o al administrador que lo actualice.'],
        ['¿Cómo agrego usuarios a la clínica?', 'Si sos Dueño/a de la clínica, podés registrar nuevos integrantes, dar de baja y cambiar roles desde la sección "Usuarios" en la barra de navegación.'],
    ]];
    $sections[] = ['Turnos', 'fa-calendar-check', [
        ['¿Cómo reservo un turno?', 'En "Turnos", buscá al cliente por DNI, elegí su mascota, el profesional (opcional), la fecha y el motivo. El turno queda confirmado.'],
        ['¿Cómo confirmo o cancelo una solicitud de un tutor?', 'En la agenda de "Turnos", cambiá el estado con el selector de la derecha y tocá OK.'],
        ['¿Cómo veo solo los turnos que eligieron atenderse conmigo?', 'Arriba de la agenda tocá "Solo los míos" (disponible para dueño/a y doctores).'],
    ]];
    if (in_array($role, ['jefe', 'dueno', 'dueño', 'doctor'], true)) {
        $sections[] = ['Historiales médicos', 'fa-book-medical', [
            ['¿Cómo busco a un cliente o a una mascota?', 'Desde "Buscar cliente" (por DNI del tutor) o "Buscar mascota" (por nombre o ID) en el menú.'],
            ['¿Cómo asiento una consulta?', 'Abrí la ficha de la mascota y tocá "Agregar consulta". Si el tutor te dio un código de acceso, ingresalo cuando se te pida.'],
            ['¿Cómo cargo una vacuna con recordatorio?', 'En la consulta indicá la fecha de vencimiento y la próxima dosis; el sistema le avisa al tutor por correo antes de la fecha.'],
        ]];
    }
    if (vet_is_owner($role)) {
        $sections[] = ['Gestión de usuarios y stock', 'fa-users-gear', [
            ['¿Cómo administro los usuarios de la clínica?', 'Desde la sección "Usuarios" del menú podés dar de alta a doctores, recepcionistas u otros dueños, dar de baja cuentas y cambiar roles o contraseñas.'],
            ['¿Cómo administro el stock de medicamentos?', 'Desde "Stock" podés cargar insumos y actualizar cantidades. Solo el dueño/a de la clínica accede a esta sección.'],
        ]];
    }
}

$pageTitle = 'Ayuda y soporte | ' . APP_NAME;
require __DIR__ . '/includes/header.php';
?>

<style>
    .help-faq { border: 1px solid var(--line); border-radius: 12px; margin-bottom: 8px; background: var(--white); }
    .help-faq summary { cursor: pointer; padding: 13px 16px; font-weight: 700; color: var(--ink); list-style: none; display: flex; justify-content: space-between; gap: 12px; align-items: center; }
    .help-faq summary::-webkit-details-marker { display: none; }
    .help-faq summary::after { content: '+'; color: var(--green); font-size: 20px; line-height: 1; }
    .help-faq[open] summary::after { content: '–'; }
    .help-faq p { margin: 0; padding: 0 16px 14px; color: var(--muted); line-height: 1.6; font-size: 14px; }
    .help-contact { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 14px; margin-top: 14px; }
    .help-form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 16px; }
    #supportForm select { border-radius: 12px; padding: 12px 14px; border: 1px solid #dbe6de; background: #fbfdfb; font-family: inherit; font-size: 14px; color: var(--ink); }
    .help-contact > div { background: var(--paper); border: 1px solid var(--line); border-radius: 12px; padding: 14px 16px; }
</style>

<div class="panel">
    <h1><i class="fa-solid fa-life-ring" style="color: var(--green); margin-right: 8px;"></i>Ayuda y soporte</h1>
    <p class="muted">
        Respuestas rápidas para <?= $isClient ? 'tutores' : 'el equipo de la veterinaria (' . e(vet_role_label($role)) . ')' ?>.
        Si no encontrás lo que buscás, escribinos desde el <a href="#contacto" style="color: var(--green); font-weight: 700;">formulario de contacto</a> al final de la página.
    </p>
    <input type="search" id="helpSearch" placeholder="Buscar en la ayuda… (ej. turno, foto, código)" aria-label="Buscar en la ayuda" autocomplete="off" style="max-width: 460px;">
    <p class="muted" id="helpEmpty" hidden style="margin-top: 10px;">No encontramos preguntas con ese texto. Probá con otra palabra o contactanos abajo.</p>
</div>

<?php foreach ($sections as [$title, $icon, $faqs]): ?>
    <div class="card help-section" style="margin-top: 22px;">
        <h2 style="margin-top: 0; font-size: 19px;">
            <i class="fa-solid <?= e($icon) ?>" style="color: var(--green); margin-right: 8px;"></i><?= e($title) ?>
        </h2>
        <?php foreach ($faqs as [$question, $answer]): ?>
            <details class="help-faq">
                <summary><?= e($question) ?></summary>
                <p><?= e($answer) ?></p>
            </details>
        <?php endforeach; ?>
    </div>
<?php endforeach; ?>

<div class="card help-support-card" id="contacto" style="margin-top: 22px;">
    <h2 style="margin-top: 0; font-size: 19px;"><i class="fa-solid fa-headset" style="color: var(--green); margin-right: 8px;"></i>Contactar a soporte</h2>
    <p class="muted" style="margin-bottom: 0;">¿Necesitás ayuda de una persona? Escribinos y te respondemos por correo.</p>

    <?php if ($msgSuccess): ?>
        <div class="notice ok" style="margin-top: 16px; padding: 14px 18px; border-radius: 12px; display: flex; gap: 12px; align-items: center; background: #eaf7ee; color: #1e6348; border: 1px solid #cce8d5;">
            <i class="fa-solid fa-circle-check" style="font-size: 20px;"></i><span><?= e($msgSuccess) ?></span>
        </div>
    <?php endif; ?>
    <?php if ($msgError): ?>
        <div class="notice error" style="margin-top: 16px; padding: 14px 18px; border-radius: 12px; display: flex; gap: 12px; align-items: center; background: #fff0ee; color: #a63e39; border: 1px solid #fbdcd9;">
            <i class="fa-solid fa-triangle-exclamation" style="font-size: 20px;"></i><span><?= e($msgError) ?></span>
        </div>
    <?php endif; ?>

    <form method="post" action="<?= url('ayuda.php') ?>#contacto" class="auth-form" id="supportForm" style="margin-top: 16px;">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

        <div class="help-form-grid">
            <div class="form-group">
                <label for="nombre"><span>Nombre y apellido *</span></label>
                <div class="input-icon-wrapper">
                    <span class="field-icon"><i class="fa-solid fa-user"></i></span>
                    <input id="nombre" name="nombre" value="<?= e($form['nombre']) ?>" maxlength="120" required autocomplete="name">
                </div>
            </div>
            <div class="form-group">
                <label for="email"><span>Correo para responderte *</span></label>
                <div class="input-icon-wrapper">
                    <span class="field-icon"><i class="fa-solid fa-envelope"></i></span>
                    <input id="email" type="email" name="email" value="<?= e($form['email']) ?>" maxlength="190" required autocomplete="email">
                </div>
            </div>
            <div class="form-group">
                <label for="telefono"><span>Teléfono / WhatsApp</span><span class="label-hint">(Opcional)</span></label>
                <div class="input-icon-wrapper">
                    <span class="field-icon"><i class="fa-solid fa-phone"></i></span>
                    <input id="telefono" type="tel" name="telefono" value="<?= e($form['telefono']) ?>" maxlength="50" autocomplete="tel">
                </div>
            </div>
            <div class="form-group">
                <label for="asunto"><span>Asunto *</span></label>
                <select id="asunto" name="asunto" class="support-subject-select" required>
                    <?php foreach ($topics as $t): ?>
                        <option value="<?= e($t) ?>" <?= $form['asunto'] === $t ? 'selected' : '' ?>><?= e($t) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-group" style="margin-top: 14px;">
            <label for="mensaje"><span>Mensaje *</span><span class="label-hint">Contanos qué pasó y qué esperabas que pasara</span></label>
            <textarea id="mensaje" name="mensaje" rows="5" maxlength="3000" required minlength="10" placeholder="Escribí acá los detalles de tu consulta…" style="width: 100%; border-radius: 12px; padding: 12px 14px; border: 1px solid #dbe6de; background: #fbfdfb; font-family: inherit; font-size: 14px; color: var(--ink); resize: vertical;"><?= e($form['mensaje']) ?></textarea>
        </div>

        <div style="margin-top: 18px; display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
            <button type="submit" id="supportBtn" class="btn primary" style="padding: 12px 26px;">
                <i class="fa-solid fa-paper-plane" style="margin-right: 8px;"></i>Enviar mensaje
            </button>
            <span class="muted" style="font-size: 12px;"><i class="fa-solid fa-lock" style="margin-right: 4px;"></i>Solo lo ve el equipo de soporte de <?= e(APP_NAME) ?>.</span>
        </div>
    </form>

    <div class="help-contact">
        <div>
            <strong><i class="fa-solid fa-envelope" style="color: var(--green); margin-right: 6px;"></i>Correo</strong><br>
            <a href="mailto:<?= e(SUPPORT_EMAIL) ?>?subject=<?= rawurlencode('Soporte ' . APP_NAME) ?>" style="color: var(--green); font-weight: 700;"><?= e(SUPPORT_EMAIL) ?></a>
        </div>
        <div>
            <strong><i class="fa-solid fa-phone" style="color: var(--green); margin-right: 6px;"></i>Teléfono</strong><br>
            +54 (11) 5820-4100
        </div>
    </div>
</div>

<script>
// Evita enviar el mensaje dos veces.
document.getElementById('supportForm').addEventListener('submit', function () {
    const b = document.getElementById('supportBtn');
    setTimeout(function () { b.disabled = true; }, 0);
});
</script>

<script>
// Buscador: oculta las preguntas que no coinciden y las secciones que quedan vacías.
(function () {
    const input = document.getElementById('helpSearch');
    const empty = document.getElementById('helpEmpty');
    const norm = s => String(s).normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
    input.addEventListener('input', function () {
        const term = norm(input.value.trim());
        let total = 0;
        document.querySelectorAll('.help-section').forEach(function (section) {
            let shown = 0;
            section.querySelectorAll('.help-faq').forEach(function (faq) {
                const match = term === '' || norm(faq.textContent).includes(term);
                faq.hidden = !match;
                if (match) { shown++; faq.open = term !== ''; }
            });
            section.hidden = shown === 0;
            total += shown;
        });
        empty.hidden = total > 0;
    });
})();
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
