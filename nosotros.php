<?php
    require_once __DIR__ . '/config/config.php';
    $pageTitle = 'Quiénes Somos | Nuestra Misión en Salud Veterinaria';
    $pageDescription = 'Conocé la historia y el equipo detrás de ' . APP_NAME . '. Nacimos para eliminar el papel, unificar las historias clínicas y darle a las veterinarias la tecnología moderna que merecen.';
    $isLanding = true;
    require __DIR__ . '/includes/header.php';
?>

<div class="bg-paper section-padding-sm about-page-background">
    <div class="public-container">
        <!-- Encabezado de Página -->
        <div class="page-intro-header">
            <span class="section-eyebrow">Sobre <?= APP_NAME ?></span>
            <h1>Construyendo el futuro de la gestión veterinaria colaborativa</h1>
            <p>
                Unimos a profesionales de la salud animal y tutores de mascotas a través de tecnología ágil, segura y profundamente humana.
            </p>
        </div>

        <!-- Misión e Historia (Grid de 2 Columnas) -->
        <div class="about-mission-grid">
            <div class="about-story-card">
                <span class="eyebrow">EL ORIGEN DEL PROYECTO</span>
                <h2 style="font: 800 28px 'Manrope', sans-serif; color: var(--ink); margin: 12px 0 18px;">
                    ¿Por qué nació <?= APP_NAME ?>?
                </h2>
                <p style="font-size: 15px; line-height: 1.75; color: var(--muted); margin-bottom: 16px;">
                    Durante décadas, la gestión veterinaria estuvo encasillada en dos extremos: o sistemas antiguos de escritorio instalados en una sola computadora propensa a fallas, o carpetas llenas de fichas en papel y libretas de vacunación que los tutores extravían con frecuencia.
                </p>
                <p style="font-size: 15px; line-height: 1.75; color: var(--muted); margin-bottom: 16px;">
                    Cuando un animal requería atención en una guardia de emergencia o una interconsulta con un especialista, el médico tratante se encontraba sin antecedentes fidedignos ni registros de tratamientos previos.
                </p>
                <p style="font-size: 15px; line-height: 1.75; color: var(--muted); margin: 0;">
                    <strong><?= APP_NAME ?> nació para transformar esa realidad:</strong> creamos un ecosistema en la nube donde la historia clínica acompaña verdaderamente al paciente y la veterinaria opera con la velocidad, el control de stock y la profesionalidad de un centro médico moderno.
                </p>
            </div>

            <div>
                <div style="background: #ffffff; border: 1px solid var(--line); border-radius: 22px; padding: 34px; box-shadow: 0 8px 30px rgba(24,59,53,0.04); margin-bottom: 22px;">
                    <div style="display: flex; gap: 16px; align-items: center; margin-bottom: 14px;">
                        <span style="width: 44px; height: 44px; border-radius: 12px; background: var(--mint); color: var(--green); display: grid; place-items: center; font-size: 20px;">
                            <i class="fa-solid fa-bullseye"></i>
                        </span>
                        <div>
                            <h4 style="font: 800 18px 'Manrope', sans-serif; margin: 0; color: var(--ink);">Nuestra Misión</h4>
                            <span style="font-size: 12px; color: var(--muted);">El propósito que nos guía cada día</span>
                        </div>
                    </div>
                    <p style="font-size: 14px; line-height: 1.7; color: var(--muted); margin: 0;">
                        Empoderar a los equipos veterinarios simplificando su carga administrativa, brindándoles herramientas precisas de stock e historias clínicas, para que puedan dedicar su tiempo a lo que de verdad importa: la medicina preventiva y el cuidado de los animales.
                    </p>
                </div>

                <div style="background: #ffffff; border: 1px solid var(--line); border-radius: 22px; padding: 34px; box-shadow: 0 8px 30px rgba(24,59,53,0.04);">
                    <div style="display: flex; gap: 16px; align-items: center; margin-bottom: 14px;">
                        <span style="width: 44px; height: 44px; border-radius: 12px; background: #fdf5e2; color: #9c6c10; display: grid; place-items: center; font-size: 20px;">
                            <i class="fa-solid fa-binoculars"></i>
                        </span>
                        <div>
                            <h4 style="font: 800 18px 'Manrope', sans-serif; margin: 0; color: var(--ink);">Nuestra Visión</h4>
                            <span style="font-size: 12px; color: var(--muted);">Hacia dónde vamos</span>
                        </div>
                    </div>
                    <p style="font-size: 14px; line-height: 1.7; color: var(--muted); margin: 0;">
                        Convertirnos en la red colaborativa de salud animal de referencia, erradicando por completo el papel y garantizando que cada mascota cuente con su libreta sanitaria digital segura, accesible e inviolable.
                    </p>
                </div>
            </div>
        </div>

        <!-- Pilares y Valores de la Plataforma -->
        <div style="margin-bottom: 40px;">
            <div class="section-header-center" style="margin-bottom: 40px;">
                <span class="section-eyebrow">Nuestros Principios</span>
                <h2 class="section-title">Los valores que sostienen nuestra plataforma</h2>
            </div>

            <div class="values-grid-4">
                <div class="value-card">
                    <div class="value-icon-box"><i class="fa-solid fa-stethoscope"></i></div>
                    <h4>Rigor Médico Primero</h4>
                    <p>Diseñamos cada campo y flujo de trabajo consultando a veterinarios clínicos, especialistas y cirujanos en ejercicio.</p>
                </div>

                <div class="value-card">
                    <div class="value-icon-box"><i class="fa-solid fa-bolt"></i></div>
                    <h4>Cero Fricción</h4>
                    <p>La tecnología debe sumar segundos, no restarlos. Interfaces limpias, búsquedas instantáneas y cero pantallas engorrosas.</p>
                </div>

                <div class="value-card">
                    <div class="value-icon-box"><i class="fa-solid fa-lock"></i></div>
                    <h4>Custodia Ética</h4>
                    <p>Respeto inquebrantable por el secreto profesional y la privacidad. Tus pacientes y tus datos te pertenecen únicamente a vos.</p>
                </div>

                <div class="value-card">
                    <div class="value-icon-box"><i class="fa-solid fa-handshake"></i></div>
                    <h4>Acompañamiento Humano</h4>
                    <p>Migramos tus datos sin costo y te brindamos soporte personalizado que comprende las necesidades reales de una clínica.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
