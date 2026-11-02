<?php
/**
 * ayuda.php — Centro de ayuda y soporte.
 *
 * Una sola página para tutores y veterinarias: muestra las preguntas frecuentes que
 * corresponden a quien está logueado (y, en veterinarias, a su rol) y los datos de contacto.
 */
require_once __DIR__ . '/config/config.php';

$isClient = !empty($_SESSION['client_id']);
$isVet    = !empty($_SESSION['vet_user_id']) && !empty($_SESSION['clinic_id']);

// Sin sesión: se lo manda al contacto público.
if (!$isClient && !$isVet) {
    header('Location: ' . url('contacto.php'));
    exit;
}

$role = $isVet ? vet_current_role() : '';

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
        ['¿Qué puedo hacer según mi rol?', 'Jefe/a: todo (historiales, turnos y stock). Doctor/a: historiales médicos y turnos. Recepcionista: solo turnos. Tu rol actual es: ' . vet_role_label($role) . '.'],
        ['¿Cómo cambio mi contraseña o mis datos?', 'Desde el panel de inicio, con el botón de perfil. Si tu rol es recepcionista y no ves el panel, pedile al jefe/a o al administrador que lo actualice.'],
        ['¿Cómo agrego usuarios a la clínica?', 'Los usuarios y sus roles los gestiona el administrador de VetCare. Escribinos desde la sección de contacto de abajo y lo resolvemos.'],
    ]];
    $sections[] = ['Turnos', 'fa-calendar-check', [
        ['¿Cómo reservo un turno?', 'En "Turnos", buscá al cliente por DNI, elegí su mascota, el profesional (opcional), la fecha y el motivo. El turno queda confirmado.'],
        ['¿Cómo confirmo o cancelo una solicitud de un tutor?', 'En la agenda de "Turnos", cambiá el estado con el selector de la derecha y tocá OK.'],
        ['¿Cómo veo solo los turnos que eligieron atenderse conmigo?', 'Arriba de la agenda tocá "Solo los míos" (disponible para jefe/a y doctores).'],
    ]];
    if (in_array($role, ['jefe', 'doctor'], true)) {
        $sections[] = ['Historiales médicos', 'fa-book-medical', [
            ['¿Cómo busco a un cliente o a una mascota?', 'Desde "Buscar cliente" (por DNI del tutor) o "Buscar mascota" (por nombre o ID) en el menú.'],
            ['¿Cómo asiento una consulta?', 'Abrí la ficha de la mascota y tocá "Agregar consulta". Si el tutor te dio un código de acceso, ingresalo cuando se te pida.'],
            ['¿Cómo cargo una vacuna con recordatorio?', 'En la consulta indicá la fecha de vencimiento y la próxima dosis; el sistema le avisa al tutor por correo antes de la fecha.'],
        ]];
    }
    if ($role === 'jefe') {
        $sections[] = ['Stock', 'fa-boxes-stacked', [
            ['¿Cómo administro el stock de medicamentos?', 'Desde "Stock" podés cargar insumos y actualizar cantidades. Solo el jefe/a de la clínica accede a esta sección.'],
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
    .help-contact > div { background: var(--paper); border: 1px solid var(--line); border-radius: 12px; padding: 14px 16px; }
</style>

<div class="panel">
    <h1><i class="fa-solid fa-life-ring" style="color: var(--green); margin-right: 8px;"></i>Ayuda y soporte</h1>
    <p class="muted">
        Respuestas rápidas para <?= $isClient ? 'tutores' : 'el equipo de la veterinaria (' . e(vet_role_label($role)) . ')' ?>.
        Si no encontrás lo que buscás, escribinos desde "Contacto" al final de la página.
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

<div class="card" style="margin-top: 22px;">
    <h2 style="margin-top: 0; font-size: 19px;"><i class="fa-solid fa-headset" style="color: var(--green); margin-right: 8px;"></i>Contacto</h2>
    <p class="muted" style="margin-bottom: 0;">¿Necesitás ayuda de una persona? Estos son nuestros canales de soporte.</p>
    <div class="help-contact">
        <div>
            <strong><i class="fa-solid fa-envelope" style="color: var(--green); margin-right: 6px;"></i>Correo</strong><br>
            <a href="mailto:soporte@vetcompartido.com?subject=<?= rawurlencode('Soporte ' . APP_NAME) ?>" style="color: var(--green); font-weight: 700;">soporte@vetcompartido.com</a>
        </div>
        <div>
            <strong><i class="fa-solid fa-phone" style="color: var(--green); margin-right: 6px;"></i>Teléfono</strong><br>
            +54 (11) 5820-4100
        </div>
        <div>
            <strong><i class="fa-solid fa-comments" style="color: var(--green); margin-right: 6px;"></i>Formulario</strong><br>
            <a href="<?= url('contacto.php') ?>" style="color: var(--green); font-weight: 700;">Ir al formulario de contacto</a>
        </div>
    </div>
</div>

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
