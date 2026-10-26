<?php
    require_once __DIR__ . '/config/config.php';
    $pageTitle = 'Planes de Servicio y Precios';
    $pageDescription = 'Conocé el Plan Clínicas Pro para veterinarias: fichas clínicas ilimitadas, gestión de stock, recordatorios y migración asistida.';
    $isLanding = true;
    require __DIR__ . '/includes/header.php';
?>

<div class="bg-paper section-padding-sm">
    <div class="public-container">
        <!-- Encabezado de Página -->
        <div class="page-intro-header">
            <span class="section-eyebrow">Plan y Tarifa Transparente</span>
            <h1>Inversión previsible, sin contratos forzados ni sorpresas</h1>
            <p>
                El Plan Clínicas Pro incluye <strong>migración asistida de pacientes sin costo</strong>, actualizaciones automáticas en la nube y respaldos diarios de seguridad.
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

        <!-- Plan disponible -->
        <div class="pricing-cards-grid single-plan-grid">
            <div class="pricing-card featured">
                <h3 class="pricing-tier-name">Plan Clínicas Pro</h3>
                <p class="pricing-tier-desc">La solución completa para veterinarias con equipo médico, historias clínicas, stock y farmacia.</p>
                
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
