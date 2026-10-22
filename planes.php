<?php
    require_once __DIR__ . '/config/config.php';
    $pageTitle = 'Planes de Servicio y Precios';
    $pageDescription = 'Elegí el plan perfecto para tu clínica o consultorio veterinario. Fichas clínicas ilimitadas, gestión de stock, recordatorios y migración de datos 100% asistida y gratuita.';
    $isLanding = true;
    require __DIR__ . '/includes/header.php';
?>

<div class="bg-paper section-padding-sm">
    <div class="public-container">
        <!-- Encabezado de Página -->
        <div class="page-intro-header">
            <span class="section-eyebrow">Planes y Tarifas Transparentes</span>
            <h1>Inversión previsible, sin contratos forzados ni sorpresas</h1>
            <p>
                Todos nuestros planes incluyen la <strong>migración asistida de pacientes sin costo</strong>, actualizaciones automáticas en la nube y respaldos diarios de seguridad.
            </p>
        </div>

        <!-- Selector Mensual / Anual -->
        <div class="pricing-toggle-wrap">
            <span class="toggle-label" onclick="document.getElementById('pricingSwitch').click()">Facturación Mensual</span>
            <div class="toggle-switch-ui active" id="pricingSwitch" title="Alternar facturación mensual/anual">
                <span class="toggle-knob"></span>
            </div>
            <span class="toggle-label" onclick="document.getElementById('pricingSwitch').click()">
                Facturación Anual <span class="badge-discount-save">Ahorrá 20%</span>
            </span>
        </div>

        <!-- Tarjetas de Precios Principales -->
        <div class="pricing-cards-grid">
            <!-- Plan 1: Consultorio / Básico -->
            <div class="pricing-card">
                <h3 class="pricing-tier-name">Plan Básico</h3>
                <p class="pricing-tier-desc">Para profesionales independientes, atención domiciliaria o consultorios individuales.</p>
                
                <div class="pricing-price-wrap">
                    <span class="price-currency">$</span>
                    <span class="price-amount" data-monthly="18.500" data-annual="14.800">14.800</span>
                    <span class="price-period">/mes (facturado anual)</span>
                </div>

                <ul class="pricing-features-list">
                    <li class="pricing-feature-item">
                        <span class="feature-check-icon"><i class="fa-solid fa-check"></i></span>
                        <span>Hasta 500 pacientes con ficha activa</span>
                    </li>
                    <li class="pricing-feature-item">
                        <span class="feature-check-icon"><i class="fa-solid fa-check"></i></span>
                        <span>Historias clínicas digitales completas</span>
                    </li>
                    <li class="pricing-feature-item">
                        <span class="feature-check-icon"><i class="fa-solid fa-check"></i></span>
                        <span>2 usuarios incluidos (Médico y Recepción)</span>
                    </li>
                    <li class="pricing-feature-item">
                        <span class="feature-check-icon"><i class="fa-solid fa-check"></i></span>
                        <span>Acceso web para tutores (Libreta Sanitaria)</span>
                    </li>
                    <li class="pricing-feature-item">
                        <span class="feature-check-icon"><i class="fa-solid fa-check"></i></span>
                        <span>Registro de vacunas y desparasitaciones</span>
                    </li>
                    <li class="pricing-feature-item highlight-feature">
                        <span class="feature-check-icon"><i class="fa-solid fa-check"></i></span>
                        <span><strong>Migración asistida de datos sin costo</strong></span>
                    </li>
                    <li class="pricing-feature-item">
                        <span class="feature-check-icon"><i class="fa-solid fa-check"></i></span>
                        <span>Soporte técnico por correo y guías de uso</span>
                    </li>
                </ul>

                <a href="contacto.php?plan=basico" class="btn-pricing-cta">Elegir Plan Básico</a>
            </div>

            <!-- Plan 2: Clínicas Pro (Destacado) -->
            <div class="pricing-card featured">
                <div class="featured-top-ribbon"><i class="fa-solid fa-star" style="margin-right: 4px;"></i> Opción Más Elegida</div>
                <h3 class="pricing-tier-name">Plan Clínicas Pro</h3>
                <p class="pricing-tier-desc">La solución completa para clínicas con equipo médico, quirófano y administración de farmacia.</p>
                
                <div class="pricing-price-wrap">
                    <span class="price-currency">$</span>
                    <span class="price-amount" data-monthly="34.000" data-annual="27.200">27.200</span>
                    <span class="price-period">/mes (facturado anual)</span>
                </div>

                <ul class="pricing-features-list">
                    <li class="pricing-feature-item">
                        <span class="feature-check-icon"><i class="fa-solid fa-check"></i></span>
                        <span><strong>Pacientes e historias clínicas ilimitadas</strong></span>
                    </li>
                    <li class="pricing-feature-item">
                        <span class="feature-check-icon"><i class="fa-solid fa-check"></i></span>
                        <span><strong>Control de stock y farmacia en tiempo real</strong></span>
                    </li>
                    <li class="pricing-feature-item">
                        <span class="feature-check-icon"><i class="fa-solid fa-check"></i></span>
                        <span>Alertas automáticas de fármacos por vencer</span>
                    </li>
                    <li class="pricing-feature-item">
                        <span class="feature-check-icon"><i class="fa-solid fa-check"></i></span>
                        <span>Hasta 8 usuarios concurrentes con permisos</span>
                    </li>
                    <li class="pricing-feature-item">
                        <span class="feature-check-icon"><i class="fa-solid fa-check"></i></span>
                        <span>Recordatorios preventivos para tutores</span>
                    </li>
                    <li class="pricing-feature-item highlight-feature">
                        <span class="feature-check-icon"><i class="fa-solid fa-check"></i></span>
                        <span><strong>Migración Asistida Express (&lt;24 hs)</strong></span>
                    </li>
                    <li class="pricing-feature-item">
                        <span class="feature-check-icon"><i class="fa-solid fa-check"></i></span>
                        <span>Soporte preferencial directo por WhatsApp</span>
                    </li>
                </ul>

                <a href="contacto.php?plan=clinicas" class="btn-pricing-cta primary-cta">Probar 14 días gratis</a>
            </div>

            <!-- Plan 3: Hospitales / Redes -->
            <div class="pricing-card">
                <h3 class="pricing-tier-name">Plan Hospitales</h3>
                <p class="pricing-tier-desc">Para centros de alta complejidad, guardias 24 hs o instituciones con múltiples sedes.</p>
                
                <div class="pricing-price-wrap">
                    <span class="price-currency">$</span>
                    <span class="price-amount" data-monthly="59.000" data-annual="47.200">47.200</span>
                    <span class="price-period">/mes (facturado anual)</span>
                </div>

                <ul class="pricing-features-list">
                    <li class="pricing-feature-item">
                        <span class="feature-check-icon"><i class="fa-solid fa-check"></i></span>
                        <span>Usuarios y sucursales sin límite</span>
                    </li>
                    <li class="pricing-feature-item">
                        <span class="feature-check-icon"><i class="fa-solid fa-check"></i></span>
                        <span>Gestión de múltiples depósitos de stock</span>
                    </li>
                    <li class="pricing-feature-item">
                        <span class="feature-check-icon"><i class="fa-solid fa-check"></i></span>
                        <span>Auditoría médica avanzada y registro de cambios</span>
                    </li>
                    <li class="pricing-feature-item">
                        <span class="feature-check-icon"><i class="fa-solid fa-check"></i></span>
                        <span>Reportes epidemiológicos y analítica de gestión</span>
                    </li>
                    <li class="pricing-feature-item highlight-feature">
                        <span class="feature-check-icon"><i class="fa-solid fa-check"></i></span>
                        <span>Migración técnica personalizada y capacitación</span>
                    </li>
                    <li class="pricing-feature-item">
                        <span class="feature-check-icon"><i class="fa-solid fa-check"></i></span>
                        <span>Asesor de cuenta dedicado y SLA 99.9%</span>
                    </li>
                </ul>

                <a href="contacto.php?plan=hospitales" class="btn-pricing-cta">Consultar por Plan Hospitales</a>
            </div>
        </div>

        <!-- Tabla Comparativa Detallada de Características -->
        <div style="margin-top: 70px;">
            <div class="section-header-center" style="margin-bottom: 35px;">
                <span class="section-eyebrow">Comparativa Completa</span>
                <h2 class="section-title">Detalle de funcionalidades por plan</h2>
            </div>

            <div style="background: #ffffff; border: 1px solid var(--line); border-radius: 20px; overflow: hidden; box-shadow: 0 4px 20px rgba(24,59,53,0.04);">
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 14px;">
                        <thead>
                            <tr style="background: #f7faf8; border-bottom: 1px solid var(--line);">
                                <th style="padding: 18px 24px; font-family: 'Manrope', sans-serif; font-size: 15px; color: var(--ink);">Características</th>
                                <th style="padding: 18px 20px; font-family: 'Manrope', sans-serif; font-size: 15px; color: var(--ink); text-align: center;">Básico</th>
                                <th style="padding: 18px 20px; font-family: 'Manrope', sans-serif; font-size: 15px; color: var(--green); text-align: center; background: #eef8f3;">Clínicas Pro</th>
                                <th style="padding: 18px 20px; font-family: 'Manrope', sans-serif; font-size: 15px; color: var(--ink); text-align: center;">Hospitales</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Bloque 1: Pacientes y Fichas -->
                            <tr style="border-bottom: 1px solid #edf4ef;">
                                <td style="padding: 16px 24px; font-weight: 700; color: var(--ink);">Límite de Pacientes</td>
                                <td style="padding: 16px 20px; text-align: center; color: var(--muted);">500 fichas</td>
                                <td style="padding: 16px 20px; text-align: center; font-weight: 700; color: var(--green); background: #f9fdfa;">Ilimitados</td>
                                <td style="padding: 16px 20px; text-align: center; font-weight: 700; color: var(--ink);">Ilimitados</td>
                            </tr>
                            <tr style="border-bottom: 1px solid #edf4ef;">
                                <td style="padding: 16px 24px; color: #435e54;">Historia clínica digital unificada</td>
                                <td style="padding: 16px 20px; text-align: center; color: var(--green);"><i class="fa-solid fa-check"></i></td>
                                <td style="padding: 16px 20px; text-align: center; color: var(--green); background: #f9fdfa;"><i class="fa-solid fa-check"></i></td>
                                <td style="padding: 16px 20px; text-align: center; color: var(--green);"><i class="fa-solid fa-check"></i></td>
                            </tr>
                            <tr style="border-bottom: 1px solid #edf4ef;">
                                <td style="padding: 16px 24px; color: #435e54;">Libreta digital para tutores (Web)</td>
                                <td style="padding: 16px 20px; text-align: center; color: var(--green);"><i class="fa-solid fa-check"></i></td>
                                <td style="padding: 16px 20px; text-align: center; color: var(--green); background: #f9fdfa;"><i class="fa-solid fa-check"></i></td>
                                <td style="padding: 16px 20px; text-align: center; color: var(--green);"><i class="fa-solid fa-check"></i></td>
                            </tr>

                            <!-- Bloque 2: Stock y Medicación -->
                            <tr style="border-bottom: 1px solid #edf4ef;">
                                <td style="padding: 16px 24px; font-weight: 700; color: var(--ink);">Módulo de Stock y Medicamentos</td>
                                <td style="padding: 16px 20px; text-align: center; color: var(--muted);"><span style="color: var(--muted); font-size: 16px;">—</span></td>
                                <td style="padding: 16px 20px; text-align: center; font-weight: 700; color: var(--green); background: #f9fdfa;">Avanzado</td>
                                <td style="padding: 16px 20px; text-align: center; font-weight: 700; color: var(--ink);">Multidepósito</td>
                            </tr>
                            <tr style="border-bottom: 1px solid #edf4ef;">
                                <td style="padding: 16px 24px; color: #435e54;">Alertas de vencimiento de fármacos</td>
                                <td style="padding: 16px 20px; text-align: center; color: var(--muted);"><span style="color: var(--muted); font-size: 16px;">—</span></td>
                                <td style="padding: 16px 20px; text-align: center; color: var(--green); background: #f9fdfa;"><i class="fa-solid fa-check"></i></td>
                                <td style="padding: 16px 20px; text-align: center; color: var(--green);"><i class="fa-solid fa-check"></i></td>
                            </tr>

                            <!-- Bloque 3: Operaciones y Usuarios -->
                            <tr style="border-bottom: 1px solid #edf4ef;">
                                <td style="padding: 16px 24px; font-weight: 700; color: var(--ink);">Usuarios incluidos</td>
                                <td style="padding: 16px 20px; text-align: center; color: var(--muted);">2 cuentas</td>
                                <td style="padding: 16px 20px; text-align: center; font-weight: 700; color: var(--green); background: #f9fdfa;">Hasta 8 cuentas</td>
                                <td style="padding: 16px 20px; text-align: center; font-weight: 700; color: var(--ink);">Ilimitados</td>
                            </tr>
                            <tr style="border-bottom: 1px solid #edf4ef;">
                                <td style="padding: 16px 24px; font-weight: 700; color: var(--green);">Migración Asistida de Pacientes</td>
                                <td style="padding: 16px 20px; text-align: center; font-weight: 700; color: var(--green);">Sin costo</td>
                                <td style="padding: 16px 20px; text-align: center; font-weight: 700; color: var(--green); background: #f9fdfa;">Prioritaria (&lt;24h)</td>
                                <td style="padding: 16px 20px; text-align: center; font-weight: 700; color: var(--green);">Personalizada VIP</td>
                            </tr>
                            <tr>
                                <td style="padding: 16px 24px; color: #435e54;">Canal de Soporte</td>
                                <td style="padding: 16px 20px; text-align: center; color: var(--muted);">Email y Centro de Ayuda</td>
                                <td style="padding: 16px 20px; text-align: center; font-weight: 700; color: var(--green); background: #f9fdfa;">WhatsApp Directo</td>
                                <td style="padding: 16px 20px; text-align: center; font-weight: 700; color: var(--ink);">Teléfono y Asesor Dedicado</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Banner de Garantía y Confianza -->
        <div style="margin-top: 50px; background: #ffffff; border: 1px solid var(--line); border-radius: 20px; padding: 30px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 20px;">
            <div style="display: flex; align-items: center; gap: 16px;">
                <span style="font-size: 36px; color: var(--green);"><i class="fa-solid fa-shield-halved"></i></span>
                <div>
                    <h4 style="font: 800 17px 'Manrope', sans-serif; color: var(--ink); margin: 0 0 3px;">
                        Garantía de Satisfacción de 14 Días
                    </h4>
                    <p style="font-size: 13px; color: var(--muted); margin: 0;">
                        Probá la plataforma en tu clínica sin costo ni compromiso. Si no cumple tus expectativas, podés exportar tus datos con un solo clic.
                    </p>
                </div>
            </div>
            <a href="contacto.php?motivo=demo" class="btn-hero-primary" style="padding: 12px 24px; font-size: 14px;">
                Solicitar Demostración Guiada
            </a>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
