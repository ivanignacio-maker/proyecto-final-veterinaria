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

    // 6. Inicialización de componentes de Carga de Archivos (Attachments & FlowButton)
    initAttachmentUploaders();
});

// ============================================================================
// COMPONENTE ATTACHMENTS & FLOW BUTTON (Vanilla JS)
// Inspirado en @syeddhasnainn/attachments y @xubohuah/flow-button de 21st.dev
// ============================================================================
function initAttachmentUploaders() {
    document.querySelectorAll('.attachment-uploader').forEach(uploader => {
        if (uploader.dataset.initialized === 'true') return;
        uploader.dataset.initialized = 'true';

        const dropzone = uploader.querySelector('.attachment-dropzone');
        const input = uploader.querySelector('.attachment-input-hidden');
        const card = uploader.querySelector('.attachment-preview-card');
        const removeBtn = uploader.querySelector('.attachment-remove-btn');
        const filenameEl = uploader.querySelector('.attachment-filename');
        const sizeEl = uploader.querySelector('.attachment-filesize');
        const thumbEl = uploader.querySelector('.attachment-thumb');
        const progressBar = uploader.querySelector('.attachment-progress-bar');
        const form = uploader.closest('form');
        const flowBtn = form ? form.querySelector('.btn-flow') : null;

        if (!input || !dropzone || !card) return;

        // Abrir selector al hacer clic o presionar Enter en el dropzone
        dropzone.addEventListener('click', () => input.click());
        dropzone.setAttribute('tabindex', '0');
        dropzone.setAttribute('role', 'button');
        dropzone.setAttribute('aria-label', 'Seleccionar o arrastrar archivo');

        dropzone.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                input.click();
            }
        });

        // Eventos Drag & Drop
        ['dragenter', 'dragover'].forEach(eventName => {
            dropzone.addEventListener(eventName, (e) => {
                e.preventDefault();
                e.stopPropagation();
                dropzone.classList.add('is-dragover');
            });
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropzone.addEventListener(eventName, (e) => {
                e.preventDefault();
                e.stopPropagation();
                dropzone.classList.remove('is-dragover');
            });
        });

        dropzone.addEventListener('drop', (e) => {
            const dt = e.dataTransfer;
            if (dt && dt.files && dt.files.length) {
                input.files = dt.files;
                handleFileSelect(dt.files[0]);
            }
        });

        // Cambio manual en el input de archivo
        input.addEventListener('change', () => {
            if (input.files && input.files[0]) {
                handleFileSelect(input.files[0]);
            }
        });

        function formatBytes(bytes, decimals = 1) {
            if (!bytes || bytes === 0) return '0 Bytes';
            const k = 1024;
            const dm = decimals < 0 ? 0 : decimals;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
        }

        function handleFileSelect(file) {
            if (filenameEl) filenameEl.textContent = file.name;
            if (sizeEl) sizeEl.textContent = formatBytes(file.size);

            // Generar vista previa en miniatura o icono representativo
            if (thumbEl) {
                thumbEl.innerHTML = '';
                if (file.type.startsWith('image/')) {
                    const img = document.createElement('img');
                    img.src = URL.createObjectURL(file);
                    img.alt = file.name;
                    thumbEl.appendChild(img);
                } else if (file.type === 'application/pdf' || file.name.toLowerCase().endsWith('.pdf')) {
                    thumbEl.innerHTML = '<i class="fa-solid fa-file-pdf" style="color: #dc2626;"></i>';
                } else if (file.name.match(/\.(doc|docx)$/i)) {
                    thumbEl.innerHTML = '<i class="fa-solid fa-file-word" style="color: #2b579a;"></i>';
                } else if (file.name.match(/\.(xls|xlsx|csv)$/i)) {
                    thumbEl.innerHTML = '<i class="fa-solid fa-file-excel" style="color: #217346;"></i>';
                } else {
                    thumbEl.innerHTML = '<i class="fa-solid fa-file-lines" style="color: var(--green);"></i>';
                }
            }

            // Ocultar zona de soltar y mostrar tarjeta de adjunto con animación
            dropzone.style.display = 'none';
            card.classList.add('has-file');

            if (flowBtn) {
                flowBtn.removeAttribute('disabled');
            }
        }

        // Botón (×) para remover el archivo seleccionado
        if (removeBtn) {
            removeBtn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                input.value = '';
                card.classList.remove('has-file');
                dropzone.style.display = 'block';
                if (thumbEl) thumbEl.innerHTML = '';
            });
        }

        // Animación fluida al enviar el formulario (FlowButton + Progress Bar)
        if (form && flowBtn) {
            form.addEventListener('submit', (e) => {
                if (input.files && input.files.length > 0) {
                    uploader.classList.add('is-uploading');
                    flowBtn.classList.add('is-flowing');
                    flowBtn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin btn-flow-icon"></i> <span>Subiendo archivo...</span>';
                    
                    if (progressBar) {
                        progressBar.style.width = '25%';
                        setTimeout(() => progressBar.style.width = '65%', 350);
                        setTimeout(() => progressBar.style.width = '95%', 700);
                    }
                }
            });
        }
    });
}
