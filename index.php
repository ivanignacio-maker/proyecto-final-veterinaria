<?php
    require_once __DIR__ . '/config/config.php';
    $pageTitle = 'Plataforma de Gestión Veterinaria y Libreta Digital';
    $pageDescription = 'Optimizá la atención de tu clínica veterinaria. Historias clínicas compartidas, control de stock inteligente, libreta digital para tutores y migración de datos 100% asistida y gratuita.';
    $isLanding = true;
    require __DIR__ . '/includes/header.php';
?>

<!-- =========================================================================
     1. HERO SECTION (SAMI.VET INSPIRADO + CRO)
     ========================================================================= -->
<section class="hero-section">
    <div class="public-container">
        <div class="hero-grid">
            <!-- Columna Izquierda: Copywriting y CTAs -->
            <div class="hero-content">
                <div class="badge-pulse-wrapper">
                    <span class="badge-pulse-dot"></span>
                    <span>La evolución de la gestión clínica veterinaria</span>
                </div>

                <h1 class="hero-headline">
                    La salud de cada mascota, gestionada con <span class="text-highlight">precisión médica</span> y calidez.
                </h1>

                <p class="hero-subtitle">
                    Centralizá historias clínicas completas, automatizá el control de medicamentos e inventario, y conectá a los tutores de mascotas con su libreta sanitaria digital. Todo en una plataforma ágil, en la nube y pensada para veterinarios reales.
                </p>

                <div class="hero-cta-wrapper">
                    <a href="planes.php" class="btn-hero-primary">
                        <span>Empezar prueba de 14 días</span>
                        <i class="fa-solid fa-arrow-right"></i>
                    </a>
                    <a href="login.php" class="btn-hero-secondary">
                        <span>Ingresar a la plataforma</span>
                        <i class="fa-solid fa-arrow-up-right-from-square"></i>
                    </a>
                </div>

                <div class="hero-trust-list">
                    <div class="hero-trust-item">
                        <span class="check-green"><i class="fa-solid fa-check"></i></span>
                        <span>Sin tarjeta de crédito</span>
                    </div>
                    <div class="hero-trust-item">
                        <span class="check-green"><i class="fa-solid fa-check"></i></span>
                        <span>Migración asistida 100% gratis</span>
                    </div>
                    <div class="hero-trust-item">
                        <span class="check-green"><i class="fa-solid fa-check"></i></span>
                        <span>Acceso 24/7 seguro en la nube</span>
                    </div>
                </div>
            </div>

            <!-- Columna Derecha: Mockup UI Interactivo de la Plataforma -->
            <div class="hero-mockup-wrapper">
                <div class="mockup-card-main">
                    <div class="mockup-header-bar">
                        <div class="mockup-dots">
                            <span class="dot-red"></span>
                            <span class="dot-yellow"></span>
                            <span class="dot-green"></span>
                        </div>
                        <div class="mockup-search-pill">
                            <i class="fa-solid fa-lock"></i> app.vetcompartido.com/historia/milo-302
                        </div>
                        <div class="mockup-status-online">
                            <span class="badge-pulse-dot"></span> En línea
                        </div>
                    </div>

                    <!-- Snapshot de Paciente y Ficha Clínica -->
                    <div class="mockup-pet-snapshot">
                        <div class="pet-avatar-badge"><i class="fa-solid fa-dog"></i></div>
                        <div>
                            <h3 class="pet-details-title">Milo · Golden Retriever</h3>
                            <p class="pet-meta-text">Tutor: Martín Gómez · DNI: 35.849.201 · 3 años</p>
                        </div>
                        <span class="status-tag-green"><i class="fa-solid fa-circle-check" style="margin-right: 4px;"></i>Vacunas al día</span>
                    </div>

                    <!-- Métricas de Consulta Rápida -->
                    <div class="mockup-metrics-grid">
                        <div class="mockup-metric-box">
                            <span class="mockup-metric-num">31.4 kg</span>
                            <span class="mockup-metric-lbl">Peso actual</span>
                        </div>
                        <div class="mockup-metric-box">
                            <span class="mockup-metric-num">Séxtuple</span>
                            <span class="mockup-metric-lbl">Última dosis</span>
                        </div>
                        <div class="mockup-metric-box">
                            <span class="mockup-metric-num">12 Nov</span>
                            <span class="mockup-metric-lbl">Próx. control</span>
                        </div>
                    </div>

                    <!-- Últimos Registros Médicos Compartidos -->
                    <div class="mockup-timeline-item">
                        <div>
                            <span class="timeline-clinic"><i class="fa-solid fa-hospital" style="color: var(--green); margin-right: 4px;"></i>Veterinaria San Roque</span>
                            <div style="font-size: 11px; color: var(--muted);">Aplicación Rabia + Chequeo dental general</div>
                        </div>
                        <span class="timeline-badge">Ficha firmada</span>
                    </div>

                    <div class="mockup-timeline-item">
                        <div>
                            <span class="timeline-clinic"><i class="fa-solid fa-hospital" style="color: var(--green); margin-right: 4px;"></i>Clínica Belgrano (Guardia)</span>
                            <div style="font-size: 11px; color: var(--muted);">Control ecográfico preventivo · Sin hallazgos</div>
                        </div>
                        <span class="timeline-badge">Interconsulta</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- =========================================================================
     2. QUÉ HACEMOS / QUÉ OFRECEMOS (DUAL TARGET: CLÍNICAS + TUTORES)
     ========================================================================= -->
<section class="section-padding bg-paper" id="soluciones">
    <div class="public-container">
        <div class="section-header-center">
            <span class="section-eyebrow">Soluciones Integrales</span>
            <h2 class="section-title">Diseñado para la eficiencia clínica y el bienestar de los pacientes</h2>
            <p class="section-desc">
                Resolvemos las dos caras del cuidado veterinario: le damos a tu equipo herramientas profesionales de gestión y a las familias de las mascotas un acceso claro y transparente a su salud.
            </p>
        </div>

        <div class="solutions-dual-wrapper">
            <!-- Bloque 1: Para la Veterinaria y su Equipo Médico -->
            <div class="audience-block">
                <div class="audience-block-header">
                    <div class="audience-badge-icon"><i class="fa-solid fa-hospital"></i></div>
                    <div class="audience-title-text">
                        <h3>Para Clínicas y Médicos Veterinarios</h3>
                        <p>Optimización operativa, precisión diagnóstica y fidelización de pacientes.</p>
                    </div>
                </div>

                <div class="features-grid-3">
                    <article class="feature-card-clean">
                        <div class="feature-icon-bubble"><i class="fa-solid fa-clipboard-list"></i></div>
                        <h4>Historia Clínica Unificada</h4>
                        <p>
                            Fichas médicas ágiles con antecedentes, cirugías, diagnósticos presuntivos y definitivos, planes terapéuticos y adjuntos de laboratorio o diagnóstico por imágenes.
                        </p>
                        <span class="feature-perk-tag"><i class="fa-solid fa-check"></i> Búsqueda ultrarrápida por DNI o chip</span>
                    </article>

                    <article class="feature-card-clean">
                        <div class="feature-icon-bubble"><i class="fa-solid fa-boxes-stacked"></i></div>
                        <h4>Control de Stock y Farmacia</h4>
                        <p>
                            Monitoreo de existencias en tiempo real, alertas preventivas de medicamentos próximos a vencer y trazabilidad exacta de insumos utilizados en cada consulta.
                        </p>
                        <span class="feature-perk-tag"><i class="fa-solid fa-check"></i> Evitá pérdidas por vencimientos</span>
                    </article>

                    <article class="feature-card-clean">
                        <div class="feature-icon-bubble"><i class="fa-solid fa-users-gear"></i></div>
                        <h4>Multiusuario y Colaboración</h4>
                        <p>
                            Permití que médicos veterinarios, especialistas interconsultores y personal de recepción accedan simultáneamente con registros de auditoría y máxima seguridad.
                        </p>
                        <span class="feature-perk-tag"><i class="fa-solid fa-check"></i> Registro inalterable de actividad</span>
                    </article>
                </div>
            </div>

            <!-- Bloque 2: Para los Tutores y Familias (Inspiración VeteApp) -->
            <div class="audience-block">
                <div class="audience-block-header">
                    <div class="audience-badge-icon"><i class="fa-solid fa-paw"></i></div>
                    <div class="audience-title-text">
                        <h3>Para Tutores y Familias de Mascotas</h3>
                        <p>Tranquilidad y seguimiento responsable de cada etapa de su vida.</p>
                    </div>
                </div>

                <div class="features-grid-3">
                    <article class="feature-card-clean">
                        <div class="feature-icon-bubble"><i class="fa-solid fa-mobile-screen-button"></i></div>
                        <h4>Libreta Sanitaria Digital</h4>
                        <p>
                            Toda la información médica en el bolsillo: vacunas certificadas, antiparasitarios internos y externos, pesos históricos y recomendaciones personalizadas.
                        </p>
                        <span class="feature-perk-tag"><i class="fa-solid fa-check"></i> Sin libretas de papel perdidas</span>
                    </article>

                    <article class="feature-card-clean">
                        <div class="feature-icon-bubble"><i class="fa-solid fa-bell"></i></div>
                        <h4>Recordatorios Preventivos</h4>
                        <p>
                            Avisos oportunos para que ninguna dosis quede sin aplicar. Mayor adherencia a los planes sanitarios y visitas preventivas planificadas.
                        </p>
                        <span class="feature-perk-tag"><i class="fa-solid fa-check"></i> Tutores siempre informados</span>
                    </article>

                    <article class="feature-card-clean">
                        <div class="feature-icon-bubble"><i class="fa-solid fa-shield-heart"></i></div>
                        <h4>Atención en Urgencias</h4>
                        <p>
                            Si viajás o tu mascota necesita atención de guardia, cualquier veterinaria adherida puede consultar de inmediato sus alergias, patologías crónicas y medicación actual.
                        </p>
                        <span class="feature-perk-tag"><i class="fa-solid fa-check"></i> Información vital en segundos</span>
                    </article>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- =========================================================================
     4. BANNER DESTACADO DE MIGRACIÓN ASISTIDA (CRÍTICO PARA CONVERSIÓN)
     ========================================================================= -->
<section class="migration-banner-section" id="migracion">
    <div class="public-container">
        <div class="migration-banner-container">
            <div class="migration-content-grid">
                <!-- Columna Izquierda: Mensaje y Eliminación de Barreras -->
                <div>
                    <div class="migration-badge-strip">
                        <span><i class="fa-solid fa-shield-halved" style="margin-right: 6px;"></i> Sin Fricción · Soporte Dedicado 1 a 1</span>
                    </div>

                    <h2 class="migration-headline">
                        ¿Usás otro software, Excel o fichas en papel? <br>
                        <span>Te ayudamos a migrar tus pacientes actuales sin costo.</span>
                    </h2>

                    <p class="migration-description">
                        Sabemos que el mayor obstáculo para modernizar tu veterinaria es la complejidad de pasar la base de datos vieja. 
                        <strong>No tenés que cargar nada a mano:</strong> nuestro equipo técnico toma tus planillas, exportaciones o fichas, las estructura y las deja listas en tu cuenta sin interrumpir la atención de tu clínica.
                    </p>

                    <div class="migration-cta-group">
                        <a href="migracion.php" class="btn-migration-action">
                            <span>Ver cómo migramos tus datos</span>
                            <i class="fa-solid fa-arrow-right"></i>
                        </a>
                        <span class="migration-direct-chat">
                            <i class="fa-solid fa-comments" style="margin-right: 6px;"></i> <span>Evaluación previa de tus datos en 15 minutos</span>
                        </span>
                    </div>
                </div>

                <!-- Columna Derecha: Proceso Paso a Paso Transparente -->
                <div class="migration-steps-box">
                    <div class="steps-box-header">
                        <span><i class="fa-solid fa-bolt" style="color: var(--green); margin-right: 6px;"></i> ¿Cómo funciona la migración asistida?</span>
                    </div>

                    <div class="migration-step-row">
                        <div class="step-num-bubble">1</div>
                        <div class="step-text">
                            <h5>Nos compartís tus archivos</h5>
                            <p>Planillas de Excel, archivos CSV, copias de seguridad de tu software anterior o listados de pacientes.</p>
                        </div>
                    </div>

                    <div class="migration-step-row">
                        <div class="step-num-bubble">2</div>
                        <div class="step-text">
                            <h5>Limpieza y estandarización</h5>
                            <p>Estandarizamos razas, clientes, historiales de vacunas y teléfonos bajo estrictos protocolos de confidencialidad médica.</p>
                        </div>
                    </div>

                    <div class="migration-step-row">
                        <div class="step-num-bubble">3</div>
                        <div class="step-text">
                            <h5>Validación y puesta en marcha</h5>
                            <p>Revisamos juntos los datos migrados, capacitamos a tu equipo en 30 minutos y empezás a trabajar sin estrés.</p>
                        </div>
                    </div>

                    <div class="migration-guarantee-pill">
                        <span><i class="fa-solid fa-lock" style="margin-right: 6px;"></i> Compromiso de confidencialidad y Cero Pérdida de Historiales</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- =========================================================================
     6. PREGUNTAS FRECUENTES (FAQ ACORDEÓN)
     ========================================================================= -->
<section class="section-padding bg-paper" id="faq">
    <div class="public-container">
        <div class="section-header-center">
            <span class="section-eyebrow">Resolvemos tus dudas</span>
            <h2 class="section-title">Preguntas Frecuentes sobre la plataforma</h2>
            <p class="section-desc">
                Todo lo que necesitás saber sobre la seguridad de tus historiales clínicos, la gestión diaria y el acceso de tutores.
            </p>
        </div>

        <div class="faq-accordion-wrapper">
            <!-- FAQ 1: General (la explicación detallada de migración se trasladó a migracion.php) -->
            <div class="faq-item">
                <button type="button" class="faq-question-btn">
                    <span>¿Cómo inicio la prueba de 14 días y qué incluye?</span>
                    <span class="faq-toggle-icon">+</span>
                </button>
                <div class="faq-answer-content">
                    <p>
                        Podés activar tu cuenta inmediatamente seleccionando cualquiera de nuestros planes sin tarjeta de crédito. Durante los 14 días contás con acceso ilimitado a todas las herramientas: historias clínicas compartidas, control de stock y portal de tutores. Si ya contás con datos de pacientes anteriores, podés solicitar la <a href="migracion.php" style="color: var(--green); font-weight: 700; text-decoration: underline;">migración asistida sin costo</a> desde el primer día.
                    </p>
                </div>
            </div>

            <!-- FAQ 2 -->
            <div class="faq-item">
                <button type="button" class="faq-question-btn">
                    <span>¿Están seguros y protegidos los datos médicos de mis pacientes?</span>
                    <span class="faq-toggle-icon">+</span>
                </button>
                <div class="faq-answer-content">
                    <p>
                        Absolutamente. Toda la información viaja y se almacena con cifrado de nivel bancario de 256 bits en servidores de alta seguridad. Realizamos respaldos diarios automáticos y garantizamos un 99.9% de disponibilidad. Los datos clínicos siempre son propiedad exclusiva de tu veterinaria.
                    </p>
                </div>
            </div>

            <!-- FAQ 3 -->
            <div class="faq-item">
                <button type="button" class="faq-question-btn">
                    <span>¿Los tutores de mascotas pueden modificar las historias clínicas?</span>
                    <span class="faq-toggle-icon">+</span>
                </button>
                <div class="faq-answer-content">
                    <p>
                        No. Los tutores tienen un perfil exclusivamente de lectura e interacción amigable (su Libreta Sanitaria Digital). Únicamente los médicos veterinarios autorizados pueden emitir diagnósticos, registrar vacunas, recetar medicamentos o asentar notas clínicas.
                    </p>
                </div>
            </div>

            <!-- FAQ 4 -->
            <div class="faq-item">
                <button type="button" class="faq-question-btn">
                    <span>¿Necesito instalar algún programa en mi computadora?</span>
                    <span class="faq-toggle-icon">+</span>
                </button>
                <div class="faq-answer-content">
                    <p>
                        No requiere ninguna instalación compleja ni servidores locales. <?= APP_NAME ?> funciona 100% en la nube desde cualquier navegador web moderno (Chrome, Safari, Firefox, Edge) tanto en computadoras de escritorio como en notebooks, tablets o celulares.
                    </p>
                </div>
            </div>

            <!-- FAQ 5 -->
            <div class="faq-item">
                <button type="button" class="faq-question-btn">
                    <span>¿Qué sucede si algún día decido darme de baja del servicio?</span>
                    <span class="faq-toggle-icon">+</span>
                </button>
                <div class="faq-answer-content">
                    <p>
                        No tenemos contratos de permanencia ni cláusulas de retención forzada. Podés solicitar la exportación completa de tus bases de datos, historias clínicas y fichas de clientes en formatos estándar (Excel/CSV/PDF) en cualquier momento sin costo alguno.
                    </p>
                </div>
            </div>

            <!-- FAQ 6 -->
            <div class="faq-item">
                <button type="button" class="faq-question-btn">
                    <span>¿Cómo acceden los tutores a la libreta de su mascota?</span>
                    <span class="faq-toggle-icon">+</span>
                </button>
                <div class="faq-answer-content">
                    <p>
                        Desde la pestaña "Portal Tutores" de nuestra plataforma, el tutor ingresa con su DNI y correo electrónico registrado en la veterinaria. Allí podrá visualizar en tiempo real todas las mascotas a su cargo, vacunas vigentes y recordatorios preventivos.
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- =========================================================================
     7. BANNER FINAL DE CONVERSIÓN
     ========================================================================= -->
<section class="final-cta-section bg-white">
    <div class="public-container">
        <div class="final-cta-box">
            <span class="section-eyebrow">Empezá hoy</span>
            <h3>Unite a la red de veterinarias que ya transformaron su gestión</h3>
            <p>
                Brindá una experiencia médica superior a tus pacientes y simplificá el trabajo administrativo de tu equipo desde el primer día.
            </p>
            <div class="final-cta-buttons">
                <a href="planes.php" class="btn-hero-primary">Empezar prueba de 14 días <i class="fa-solid fa-arrow-right" style="margin-left: 6px;"></i></a>
                <a href="migracion.php" class="btn-hero-secondary"><i class="fa-solid fa-truck-ramp-box" style="margin-right: 6px;"></i> Conocer la migración asistida</a>
            </div>
        </div>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
