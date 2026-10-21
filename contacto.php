<?php
    require_once __DIR__ . '/config/config.php';

    $msgSuccess = '';
    $msgError = '';

    // Parámetros opcionales para prellenado inteligente (CRO)
    $presetMotivo = $_GET['motivo'] ?? '';
    $presetPlan = $_GET['plan'] ?? '';
    
    $defaultAsunto = 'Consulta general';
    $defaultTipoUsuario = 'veterinaria';

    if ($presetMotivo === 'migracion') {
        $defaultAsunto = 'Solicitud de Migración Asistida de Pacientes (Sin Costo)';
        $defaultTipoUsuario = 'veterinaria';
    } elseif ($presetPlan) {
        $defaultAsunto = 'Consulta sobre Plan ' . ucfirst(e($presetPlan));
        $defaultTipoUsuario = 'veterinaria';
    } elseif (($_GET['tipo'] ?? '') === 'particular') {
        $defaultTipoUsuario = 'particular';
    }

    $nombre = '';
    $email = '';
    $telefono = '';
    $asunto = $defaultAsunto;
    $tipoUsuario = $defaultTipoUsuario;
    $mensaje = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();

        $nombre = trim($_POST['nombre'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        $asunto = trim($_POST['asunto'] ?? '');
        $tipoUsuario = ($_POST['tipo_usuario'] ?? 'veterinaria') === 'particular' ? 'particular' : 'veterinaria';
        $mensaje = trim($_POST['mensaje'] ?? '');

        if ($nombre === '' || $email === '' || $mensaje === '') {
            $msgError = 'Por favor completá los campos obligatorios (Nombre, Correo electrónico y Mensaje).';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $msgError = 'Por favor ingresá un correo electrónico válido.';
        } else {
            // En un sistema en producción aquí se enviaría un correo o webhook hacia el CRM/Slack
            $msgSuccess = '¡Gracias por comunicarte con VetCompartido! Recibimos tu consulta con éxito. Un asesor de nuestro equipo se pondrá en contacto a la brevedad.';
            // Limpiar campos tras éxito
            $nombre = '';
            $email = '';
            $telefono = '';
            $mensaje = '';
        }
    }

    $pageTitle = 'Contacto y Soporte Especializado';
    $pageDescription = 'Contactate con el equipo de VetCompartido. Solicitá tu migración asistida sin costo, consultá por planes para veterinarias o recibí asistencia técnica personalizada.';
    $isLanding = true;
    require __DIR__ . '/includes/header.php';
?>

<div class="bg-paper section-padding-sm">
    <div class="public-container">
        <!-- Encabezado de Página -->
        <div class="page-intro-header">
            <span class="section-eyebrow">Atención Personalizada</span>
            <h1>Estamos para ayudarte a dar el siguiente paso</h1>
            <p>
                Ya sea para migrar tu base de datos de pacientes, resolver dudas técnicas o conocer en detalle nuestros planes, nuestro equipo responde en menos de 2 horas hábiles.
            </p>
        </div>

        <div class="contact-grid">
            <!-- Formulario Principal -->
            <div class="contact-card-form">
                <?php if ($msgSuccess): ?>
                    <div class="notice ok" style="padding: 16px 20px; border-radius: 14px; margin-bottom: 24px; display: flex; align-items: center; gap: 12px; background: #eaf7ee; color: #1e6348; border: 1px solid #cce8d5;">
                        <span style="font-size: 22px;">✅</span>
                        <div>
                            <strong style="display: block; font-size: 15px; margin-bottom: 2px;">¡Mensaje enviado con éxito!</strong>
                            <span style="font-size: 13px;"><?= e($msgSuccess) ?></span>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($msgError): ?>
                    <div class="notice error" style="padding: 16px 20px; border-radius: 14px; margin-bottom: 24px; display: flex; align-items: center; gap: 12px; background: #fff0ee; color: #a63e39; border: 1px solid #fbdcd9;">
                        <span style="font-size: 22px;">⚠️</span>
                        <div>
                            <strong style="display: block; font-size: 15px; margin-bottom: 2px;">Atención</strong>
                            <span style="font-size: 13px;"><?= e($msgError) ?></span>
                        </div>
                    </div>
                <?php endif; ?>

                <form method="post" action="contacto.php" class="auth-form" autocomplete="on">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="tipo_usuario" id="user_type_hidden" value="<?= e($tipoUsuario) ?>">

                    <!-- Selector de Tipo de Usuario (Veterinaria vs Particular) -->
                    <div class="form-group" style="margin-bottom: 20px;">
                        <label>
                            <span>Tipo de Usuario o Interesado</span>
                            <span class="label-hint">Seleccioná tu perfil</span>
                        </label>
                        <div class="user-type-selector">
                            <div class="user-type-option <?= $tipoUsuario === 'veterinaria' ? 'selected' : '' ?>" data-value="veterinaria">
                                <span>🏥</span>
                                <div>
                                    <div style="font-size: 13px; font-weight: 700;">Clínica o Veterinario</div>
                                    <div style="font-size: 11px; color: var(--muted); font-weight: 400;">Gestión médica y stock</div>
                                </div>
                            </div>
                            <div class="user-type-option <?= $tipoUsuario === 'particular' ? 'selected' : '' ?>" data-value="particular">
                                <span>🐾</span>
                                <div>
                                    <div style="font-size: 13px; font-weight: 700;">Tutor de Mascota</div>
                                    <div style="font-size: 11px; color: var(--muted); font-weight: 400;">Libreta sanitaria digital</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                        <div class="form-group">
                            <label for="nombre">
                                <span>Nombre y Apellido *</span>
                            </label>
                            <div class="input-icon-wrapper">
                                <span class="field-icon">👤</span>
                                <input id="nombre" name="nombre" value="<?= e($nombre) ?>" required placeholder="Ej. Dra. Valeria Rossi" autocomplete="name">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="email">
                                <span>Correo electrónico *</span>
                            </label>
                            <div class="input-icon-wrapper">
                                <span class="field-icon">✉️</span>
                                <input id="email" type="email" name="email" value="<?= e($email) ?>" required placeholder="contacto@veterinaria.com" autocomplete="email">
                            </div>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1.2fr; gap: 16px; margin-top: 14px;">
                        <div class="form-group">
                            <label for="telefono">
                                <span>Teléfono / WhatsApp</span>
                                <span class="label-hint">(Opcional)</span>
                            </label>
                            <div class="input-icon-wrapper">
                                <span class="field-icon">📱</span>
                                <input id="telefono" type="tel" name="telefono" value="<?= e($telefono) ?>" placeholder="+54 9 11 2345-6789" autocomplete="tel">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="asunto">
                                <span>Asunto o Motivo</span>
                            </label>
                            <div class="input-icon-wrapper">
                                <span class="field-icon">📌</span>
                                <input id="asunto" name="asunto" value="<?= e($asunto) ?>" required placeholder="Ej. Migración de pacientes o consulta de plan">
                            </div>
                        </div>
                    </div>

                    <div class="form-group" style="margin-top: 14px;">
                        <label for="mensaje">
                            <span>Mensaje o Consulta *</span>
                            <span class="label-hint">Contanos sobre tu clínica o necesidad</span>
                        </label>
                        <textarea id="mensaje" name="mensaje" rows="5" required placeholder="Escribí aquí los detalles de tu consulta (por ejemplo: cantidad aproximada de pacientes a migrar, software actual que utilizás o dudas técnicas)..." style="width: 100%; border-radius: 12px; padding: 12px 14px; border: 1px solid #dbe6de; background: #fbfdfb; font-family: inherit; font-size: 14px; color: var(--ink); resize: vertical;"><?= e($mensaje) ?></textarea>
                    </div>

                    <div style="margin-top: 24px;">
                        <button type="submit" class="btn-hero-primary" style="width: 100%; justify-content: center; border: none; cursor: pointer; padding: 16px;">
                            <span>Enviar mensaje a nuestro equipo</span>
                            <span>→</span>
                        </button>
                    </div>

                    <div style="text-align: center; margin-top: 14px; font-size: 11px; color: var(--muted);">
                        🔒 Tus datos están resguardados bajo estricta confidencialidad médica y no serán compartidos con terceros.
                    </div>
                </form>
            </div>

            <!-- Sidebar de Canales Directos y Confianza -->
            <aside class="contact-sidebar-card">
                <div>
                    <h3 style="font: 800 20px 'Manrope', sans-serif; color: var(--ink); margin: 0 0 8px;">
                        Canales Directos
                    </h3>
                    <p style="font-size: 13px; color: var(--muted); margin: 0;">
                        Respondemos con agilidad para que tu centro veterinario nunca se detenga.
                    </p>
                </div>

                <div class="contact-info-block">
                    <div class="contact-info-icon">💬</div>
                    <div class="contact-info-text">
                        <strong>WhatsApp de Soporte y Migración</strong>
                        <p>+54 (11) 5820-4100<br><span style="font-size: 11px; color: var(--green); font-weight: 700;">● Guardias de soporte activas</span></p>
                    </div>
                </div>

                <div class="contact-info-block">
                    <div class="contact-info-icon">✉️</div>
                    <div class="contact-info-text">
                        <strong>Correo Institucional</strong>
                        <p>soporte@vetcompartido.com<br>info@vetcompartido.com</p>
                    </div>
                </div>

                <div class="contact-info-block">
                    <div class="contact-info-icon">⏱️</div>
                    <div class="contact-info-text">
                        <strong>Horario de Atención Comercial</strong>
                        <p>Lunes a Viernes de 8:00 a 19:00 hs.<br>Sábados de 9:00 a 14:00 hs.</p>
                    </div>
                </div>

                <div class="contact-highlight-box">
                    <strong style="display: block; font-size: 13px; color: var(--green); margin-bottom: 4px;">🚀 Compromiso de Migración Gratuita</strong>
                    Si tenés dudas sobre cómo exportar los datos de tu software anterior, podemos coordinar una videollamada de 15 minutos para guiarte paso a paso.
                </div>
            </aside>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
