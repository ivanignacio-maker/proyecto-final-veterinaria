// ============================================================================
// Funcionalidades de Formularios Médicos y Sistema (Backend)
// ============================================================================
document.addEventListener('change', function(e) {
    // Alternar campos de vacunación en consultas
    if (e.target.matches('[name="motivo"]')) {
        const box = document.querySelector('#vacuna-fields');
        if (box) box.hidden = (e.target.value !== 'Vacuna');
    }
    // Alternar campo de 'otro medicamento' en consultas
    if (e.target.matches('[name="medicamento_id"]')) {
        const box = document.querySelector('#otro-medicamento');
        if (box) box.hidden = (e.target.value !== 'otro');
    }
});

// Confirmaciones automáticas para enlaces y botones con data-confirm
document.addEventListener('click', function(e) {
    const el = e.target.closest('[data-confirm]');
    if (el) {
        if (!confirm(el.dataset.confirm || '¿Estás seguro de continuar?')) {
            e.preventDefault();
        }
    }
});

// Estilo para inputs de tipo fecha
document.querySelectorAll('input[type="date"]').forEach(input => {
    input.addEventListener('change', () => input.classList.toggle('has-value', !!input.value));
});

// ============================================================================
// Interactividad para Páginas Públicas (Landing, Planes, FAQ, Contacto)
// ============================================================================
document.addEventListener('DOMContentLoaded', () => {
    // 1. Menú Móvil Desplegable
    const mobileBtn = document.getElementById('mobileMenuBtn');
    const mobileDrawer = document.getElementById('mobileNavDrawer');
    if (mobileBtn && mobileDrawer) {
        mobileBtn.addEventListener('click', () => {
            const isOpen = mobileDrawer.classList.toggle('open');
            mobileBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });
        
        mobileDrawer.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', () => {
                mobileDrawer.classList.remove('open');
            });
        });
    }

    // 2. Acordeón de Preguntas Frecuentes (FAQ)
    const faqItems = document.querySelectorAll('.faq-item');
    faqItems.forEach(item => {
        const btn = item.querySelector('.faq-question-btn');
        if (btn) {
            btn.addEventListener('click', () => {
                const isActive = item.classList.contains('active');
                faqItems.forEach(other => {
                    if (other !== item) other.classList.remove('active');
                });
                item.classList.toggle('active', !isActive);
            });
        }
    });

    // 3. Toggle de Precios (Mensual / Anual con 20% de descuento)
    const pricingSwitch = document.getElementById('pricingSwitch');
    if (pricingSwitch) {
        pricingSwitch.addEventListener('click', () => {
            const isAnnual = pricingSwitch.classList.toggle('active');
            const periodLabels = document.querySelectorAll('.price-period');
            const amounts = document.querySelectorAll('.price-amount');
            
            amounts.forEach(amt => {
                const monthlyPrice = amt.getAttribute('data-monthly');
                const annualPrice = amt.getAttribute('data-annual');
                if (monthlyPrice && annualPrice) {
                    amt.textContent = isAnnual ? annualPrice : monthlyPrice;
                }
            });

            periodLabels.forEach(lbl => {
                lbl.textContent = isAnnual ? '/mes (facturado anual)' : '/mes';
            });
        });
    }

    // 4. Selector de Tipo de Usuario en Formulario de Contacto
    const userTypeOptions = document.querySelectorAll('.user-type-option');
    const userTypeHidden = document.getElementById('user_type_hidden');
    if (userTypeOptions.length && userTypeHidden) {
        userTypeOptions.forEach(opt => {
            opt.addEventListener('click', () => {
                userTypeOptions.forEach(o => o.classList.remove('selected'));
                opt.classList.add('selected');
                const val = opt.getAttribute('data-value');
                userTypeHidden.value = val;
            });
        });
    }

    // 5. Scroll suave para enlaces ancla internos (#soluciones, #migracion, #faq)
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            const targetId = this.getAttribute('href').substring(1);
            if (!targetId) return;
            const targetElement = document.getElementById(targetId);
            if (targetElement) {
                e.preventDefault();
                targetElement.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
});
