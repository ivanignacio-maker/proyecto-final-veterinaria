<?php
    require_once __DIR__ . '/config/config.php';
    $pageTitle = 'Migración Asistida de Datos | Sin Costo';
    $pageDescription = 'Te ayudamos a migrar todos los datos de tu veterinaria sin costo. Desde planillas de Excel, software anterior o fichas en papel, los estructuramos e importamos a tu nueva cuenta.';
    $isLanding = true;
    require __DIR__ . '/includes/header.php';
?>

<div class="bg-paper section-padding-sm migration-page-background">
    <div class="public-container">
        <!-- Encabezado de Página -->
        <div class="page-intro-header">
            <span class="section-eyebrow"><i class="fa-solid fa-truck-ramp-box" style="margin-right: 6px;"></i>Migración Asistida Sin Costo</span>
            <h1>Pasá todos tus datos a <?= APP_NAME ?> sin esfuerzo</h1>
            <p>
                Sabemos que el mayor obstáculo para modernizar tu veterinaria es la complejidad de migrar la base de datos existente. 
                <strong>Nuestro equipo técnico se encarga de todo:</strong> toma tus planillas, exportaciones o fichas y las deja listas en tu cuenta sin interrumpir la atención de tus pacientes.
            </p>
        </div>

        <!-- Proceso Paso a Paso -->
        <div style="max-width: 860px; margin: 0 auto 50px;">
            <div class="section-header-center" style="margin-bottom: 35px;">
                <h2 class="section-title"><i class="fa-solid fa-bolt" style="color: var(--green); margin-right: 8px;"></i>¿Cómo funciona el proceso?</h2>
                <p class="section-desc">Un método transparente y sin complicaciones en 3 simples pasos</p>
            </div>

            <div style="display: flex; flex-direction: column; gap: 24px;">
                <div class="migration-card migration-process-card" style="background: var(--white); border: 1px solid var(--line); border-radius: 18px; padding: 28px 30px; display: flex; gap: 20px; align-items: flex-start;">
                    <div style="width: 48px; height: 48px; border-radius: 14px; background: var(--mint); color: var(--green); display: grid; place-items: center; font-size: 20px; font-weight: 800; flex-shrink: 0; font-family: 'Manrope', sans-serif;">1</div>
                    <div>
                        <h3 style="font: 700 18px 'Manrope', sans-serif; color: var(--ink); margin: 0 0 6px;">
                            <i class="fa-solid fa-cloud-arrow-up" style="color: var(--green); margin-right: 6px;"></i>Nos compartís tus archivos
                        </h3>
                        <p style="font-size: 14px; line-height: 1.7; color: var(--muted); margin: 0;">
                            Planillas de Excel, archivos CSV, copias de seguridad de tu software anterior, listados de pacientes o incluso fotografías de fichas de papel. Aceptamos cualquier formato disponible en tu centro veterinario.
                        </p>
                    </div>
                </div>

                <div class="migration-card migration-process-card" style="background: var(--white); border: 1px solid var(--line); border-radius: 18px; padding: 28px 30px; display: flex; gap: 20px; align-items: flex-start;">
                    <div style="width: 48px; height: 48px; border-radius: 14px; background: var(--mint); color: var(--green); display: grid; place-items: center; font-size: 20px; font-weight: 800; flex-shrink: 0; font-family: 'Manrope', sans-serif;">2</div>
                    <div>
                        <h3 style="font: 700 18px 'Manrope', sans-serif; color: var(--ink); margin: 0 0 6px;">
                            <i class="fa-solid fa-wand-magic-sparkles" style="color: var(--green); margin-right: 6px;"></i>Limpieza y estandarización técnica
                        </h3>
                        <p style="font-size: 14px; line-height: 1.7; color: var(--muted); margin: 0;">
                            Nuestro equipo de ingenieros normaliza y estandariza razas, tutores, historiales de vacunas, pesos, teléfonos y direcciones bajo estrictos protocolos de confidencialidad y buenas prácticas médicas.
                        </p>
                    </div>
                </div>

                <div class="migration-card migration-process-card" style="background: var(--white); border: 1px solid var(--line); border-radius: 18px; padding: 28px 30px; display: flex; gap: 20px; align-items: flex-start;">
                    <div style="width: 48px; height: 48px; border-radius: 14px; background: var(--mint); color: var(--green); display: grid; place-items: center; font-size: 20px; font-weight: 800; flex-shrink: 0; font-family: 'Manrope', sans-serif;">3</div>
                    <div>
                        <h3 style="font: 700 18px 'Manrope', sans-serif; color: var(--ink); margin: 0 0 6px;">
                            <i class="fa-solid fa-circle-check" style="color: var(--green); margin-right: 6px;"></i>Validación y puesta en marcha inmediata
                        </h3>
                        <p style="font-size: 14px; line-height: 1.7; color: var(--muted); margin: 0;">
                            Revisamos juntos los datos importados en una videollamada personalizada, resolvemos cualquier inquietud y empezás a atender sin demoras ni estrés de carga manual.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Formatos Aceptados -->
        <div style="max-width: 860px; margin: 0 auto 50px;">
            <div class="section-header-center" style="margin-bottom: 30px;">
                <h2 class="section-title"><i class="fa-solid fa-file-circle-check" style="color: var(--green); margin-right: 8px;"></i>Formatos soportados</h2>
                <p class="section-desc">Cualquiera sea tu soporte actual, podemos digitalizarlo e integrarlo</p>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 16px;">
                <div class="migration-card migration-format-card" style="background: var(--white); border: 1px solid var(--line); border-radius: 14px; padding: 20px; text-align: center;">
                    <div style="font-size: 28px; color: #217346; margin-bottom: 8px;"><i class="fa-solid fa-file-excel"></i></div>
                    <strong style="display: block; font-size: 14px; color: var(--ink); margin-bottom: 4px;">Excel / Planillas</strong>
                    <span style="font-size: 12px; color: var(--muted);">.xlsx, .xls, .ods</span>
                </div>
                <div class="migration-card migration-format-card" style="background: var(--white); border: 1px solid var(--line); border-radius: 14px; padding: 20px; text-align: center;">
                    <div style="font-size: 28px; color: #0284c7; margin-bottom: 8px;"><i class="fa-solid fa-file-csv"></i></div>
                    <strong style="display: block; font-size: 14px; color: var(--ink); margin-bottom: 4px;">Archivos CSV</strong>
                    <span style="font-size: 12px; color: var(--muted);">Exportaciones de software</span>
                </div>
                <div class="migration-card migration-format-card" style="background: var(--white); border: 1px solid var(--line); border-radius: 14px; padding: 20px; text-align: center;">
                    <div style="font-size: 28px; color: #6366f1; margin-bottom: 8px;"><i class="fa-solid fa-database"></i></div>
                    <strong style="display: block; font-size: 14px; color: var(--ink); margin-bottom: 4px;">Bases de datos</strong>
                    <span style="font-size: 12px; color: var(--muted);">Backups .sql, Access, etc.</span>
                </div>
                <div class="migration-card migration-format-card" style="background: var(--white); border: 1px solid var(--line); border-radius: 14px; padding: 20px; text-align: center;">
                    <div style="font-size: 28px; color: #d97706; margin-bottom: 8px;"><i class="fa-solid fa-book-medical"></i></div>
                    <strong style="display: block; font-size: 14px; color: var(--ink); margin-bottom: 4px;">Fichas en papel</strong>
                    <span style="font-size: 12px; color: var(--muted);">Fotos y escaneos legibles</span>
                </div>
            </div>
        </div>

        <!-- Garantías de Seguridad -->
        <div style="max-width: 860px; margin: 0 auto 50px;">
            <div class="section-header-center" style="margin-bottom: 30px;">
                <h2 class="section-title"><i class="fa-solid fa-shield-halved" style="color: var(--green); margin-right: 8px;"></i>Garantías de seguridad y privacidad</h2>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 20px;">
                <div class="migration-card migration-security-card" style="background: var(--white); border: 1px solid var(--line); border-radius: 16px; padding: 24px;">
                    <div style="width: 46px; height: 46px; border-radius: 12px; background: var(--mint); color: var(--green); display: grid; place-items: center; font-size: 20px; margin-bottom: 14px;">
                        <i class="fa-solid fa-lock"></i>
                    </div>
                    <h4 style="font: 700 16px 'Manrope', sans-serif; color: var(--ink); margin: 0 0 8px;">Confidencialidad absoluta</h4>
                    <p style="font-size: 13px; color: var(--muted); line-height: 1.6; margin: 0;">
                        Los archivos temporales se alojan en entornos cifrados y son eliminados de forma segura e irreversible tras la confirmación de la importación.
                    </p>
                </div>

                <div class="migration-card migration-security-card" style="background: var(--white); border: 1px solid var(--line); border-radius: 16px; padding: 24px;">
                    <div style="width: 46px; height: 46px; border-radius: 12px; background: #fdf5e2; color: #9c6c10; display: grid; place-items: center; font-size: 20px; margin-bottom: 14px;">
                        <i class="fa-solid fa-shield-cat"></i>
                    </div>
                    <h4 style="font: 700 16px 'Manrope', sans-serif; color: var(--ink); margin: 0 0 8px;">Cero pérdida de historiales</h4>
                    <p style="font-size: 13px; color: var(--muted); line-height: 1.6; margin: 0;">
                        Corremos rutinas de validación cruzada para evitar registros duplicados, preservar fechas de vacunas y asegurar la coherencia clínica.
                    </p>
                </div>

                <div class="migration-card migration-security-card" style="background: var(--white); border: 1px solid var(--line); border-radius: 16px; padding: 24px;">
                    <div style="width: 46px; height: 46px; border-radius: 12px; background: #eef0fb; color: #4f46e5; display: grid; place-items: center; font-size: 20px; margin-bottom: 14px;">
                        <i class="fa-solid fa-user-doctor"></i>
                    </div>
                    <h4 style="font: 700 16px 'Manrope', sans-serif; color: var(--ink); margin: 0 0 8px;">Tus datos son siempre tuyos</h4>
                    <p style="font-size: 13px; color: var(--muted); line-height: 1.6; margin: 0;">
                        Tu centro veterinario es el único dueño de la base de datos. Podés exportar la información completa en cualquier momento sin restricciones.
                    </p>
                </div>
            </div>
        </div>

        <!-- Preguntas Específicas de Migración -->
        <div style="max-width: 860px; margin: 0 auto 50px;">
            <div class="section-header-center" style="margin-bottom: 30px;">
                <h2 class="section-title"><i class="fa-solid fa-circle-question" style="color: var(--green); margin-right: 8px;"></i>Preguntas frecuentes sobre la migración</h2>
            </div>

            <div class="faq-accordion-wrapper">
                <div class="faq-item">
                    <button type="button" class="faq-question-btn">
                        <span>¿Cuánto tiempo suele demorar la migración completa?</span>
                        <span class="faq-toggle-icon">+</span>
                    </button>
                    <div class="faq-answer-content">
                        <p>
                            Para clínicas con hasta 2.500 pacientes el proceso habitual se completa en menos de 48 horas hábiles. Durante todo el proceso tu veterinaria puede seguir trabajando con normalidad.
                        </p>
                    </div>
                </div>

                <div class="faq-item">
                    <button type="button" class="faq-question-btn">
                        <span>¿Tiene algún costo adicional el servicio de migración?</span>
                        <span class="faq-toggle-icon">+</span>
                    </button>
                    <div class="faq-answer-content">
                        <p>
                            No. La migración asistida está 100% incluida sin costo en todos nuestros planes. Creemos que cambiar a una mejor tecnología no debe implicar barreras de entrada ni sobrecostos.
                        </p>
                    </div>
                </div>

                <div class="faq-item">
                    <button type="button" class="faq-question-btn">
                        <span>¿Qué sucede si mis archivos están desordenados o incompletos?</span>
                        <span class="faq-toggle-icon">+</span>
                    </button>
                    <div class="faq-answer-content">
                        <p>
                            Es una situación muy frecuente. Nuestros técnicos limpian campos repetidos, estandarizan formatos telefónicos y organizan las fichas para que tu base quede más prolija que antes.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Banner de Conversión Directa -->
        <div class="final-cta-box" style="max-width: 860px; margin: 0 auto;">
            <span class="section-eyebrow">Comenzar ahora</span>
            <h3>¿Querés que evaluemos tus archivos sin compromiso?</h3>
            <p>
                Escribinos hoy mismo indicándonos qué software o formato utilizás actualmente. Te respondemos en menos de 2 horas con los pasos exactos.
            </p>
            <div class="final-cta-buttons">
                <a href="<?= url('contacto.php?motivo=migracion') ?>" class="btn-hero-primary">
                    <i class="fa-solid fa-paper-plane" style="margin-right: 6px;"></i> Solicitar migración gratuita
                </a>
                <a href="<?= url('planes.php') ?>" class="btn-hero-secondary">
                    Ver planes y precios
                </a>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
