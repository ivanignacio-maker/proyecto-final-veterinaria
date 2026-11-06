<?php
    require_once __DIR__ . '/config/config.php';
    $pageTitle = 'Quiénes somos | El origen de ' . APP_NAME;
    $pageDescription = 'Conocé cómo nació ' . APP_NAME . ': un servicio de gestión veterinaria creado por cuatro estudiantes de la UTN Facultad Regional Haedo para facilitar el trabajo de las clínicas y la comunicación con los tutores.';
    $isLanding = true;
    $bodyClass = 'page-bg-follow page-bg-about';
    require __DIR__ . '/includes/header.php';
?>

<div class="bg-paper section-padding-sm about-page-background">
    <div class="public-container">
        <!-- Encabezado de Página -->
        <div class="page-intro-header">
            <span class="section-eyebrow">Sobre <?= APP_NAME ?></span>
            <h1>Una idea académica para mejorar la gestión veterinaria</h1>
            <p>
                Somos cuatro estudiantes de la UTN Facultad Regional Haedo. Creamos <?= APP_NAME ?> para hacer más simple el trabajo de las veterinarias y acercar a quienes cuidan la salud de cada mascota.
            </p>
        </div>

        <!-- Misión e Historia (Grid de 2 Columnas) -->
        <div class="about-mission-grid">
            <div class="about-story-card">
                <span class="eyebrow">EL ORIGEN DEL PROYECTO</span>
                <h2 style="font: 800 28px 'Manrope', sans-serif; color: var(--ink); margin: 12px 0 18px;">
                    ¿Cómo nació <?= APP_NAME ?>?
                </h2>
                <p style="font-size: 15px; line-height: 1.75; color: var(--muted); margin-bottom: 16px;">
                    <?= APP_NAME ?> nació como un proyecto académico de cuatro estudiantes de la Facultad Regional Haedo de la Universidad Tecnológica Nacional. La idea surgió de una pregunta: ¿cómo podríamos facilitar la gestión de las veterinarias y mejorar la comunicación con los tutores?
                </p>
                <p style="font-size: 15px; line-height: 1.75; color: var(--muted); margin-bottom: 16px;">
                    Pensamos la plataforma para que los equipos veterinarios puedan organizar la información clínica y las tareas cotidianas, mientras los tutores cuentan con un espacio para acompañar el cuidado de sus mascotas.
                </p>
                <p style="font-size: 15px; line-height: 1.75; color: var(--muted); margin: 0;">
                    <strong>Ese proyecto hoy toma forma como un servicio de gestión clínica veterinaria:</strong> una propuesta para reunir historias clínicas, turnos y herramientas de seguimiento, y hacer más fluida la interacción entre cada clínica y quienes cuidan la salud de sus animales.
                </p>
            </div>

            <div>
                <div class="about-side-card" style="background: var(--white); border: 1px solid var(--line); border-radius: 22px; padding: 34px; margin-bottom: 22px;">
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
                        Facilitar la gestión diaria de las veterinarias y mejorar la comunicación con los tutores, para que la información del cuidado esté mejor organizada y acompañe la salud de cada mascota.
                    </p>
                </div>

                <div class="about-side-card" style="background: var(--white); border: 1px solid var(--line); border-radius: 22px; padding: 34px;">
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
                        Ser una herramienta de referencia que conecte a las clínicas veterinarias con los tutores y ayude a que la información sanitaria de cada mascota sea más clara, organizada y fácil de consultar.
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
                    <p>Organizamos la información clínica para acompañar el trabajo veterinario y ayudar a mantener los datos importantes de cada paciente a mano.</p>
                </div>

                <div class="value-card">
                    <div class="value-icon-box"><i class="fa-solid fa-bolt"></i></div>
                    <h4>Cero Fricción</h4>
                    <p>Buscamos que las tareas cotidianas sean simples, con una interfaz clara y accesos directos a la información que se necesita.</p>
                </div>

                <div class="value-card">
                    <div class="value-icon-box"><i class="fa-solid fa-lock"></i></div>
                    <h4>Custodia Ética</h4>
                    <p>Tratamos la información de clínicas, tutores y pacientes con responsabilidad y respeto por la privacidad.</p>
                </div>

                <div class="value-card">
                    <div class="value-icon-box"><i class="fa-solid fa-handshake"></i></div>
                    <h4>Acompañamiento Humano</h4>
                    <p>La migración asistida busca facilitar el paso de la información existente y acompañar la adopción de la plataforma.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
