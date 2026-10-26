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
    const previewLink = e.target.closest('.js-attachment-preview');
    if (previewLink) {
        e.preventDefault();
        openAttachmentPreview(previewLink);
        return;
    }

    if (e.target.closest('[data-close-attachment-viewer]') || e.target.id === 'attachmentPreviewModal') {
        closeAttachmentPreview();
    }

    const el = e.target.closest('[data-confirm]');
    if (el) {
        if (!confirm(el.dataset.confirm || '¿Estás seguro de continuar?')) {
            e.preventDefault();
        }
    }
});

function openAttachmentPreview(link) {
    const modal = document.getElementById('attachmentPreviewModal');
    if (!modal) return;

    const image = modal.querySelector('.attachment-viewer-image');
    const pdf = modal.querySelector('.attachment-viewer-pdf');
    const unsupported = modal.querySelector('.attachment-viewer-unsupported');
    const download = modal.querySelector('.attachment-viewer-download');
    const title = modal.querySelector('#attachmentViewerTitle');
    const type = link.dataset.fileType || '';
    const previewUrl = link.dataset.previewUrl || link.href;

    image.hidden = true;
    image.removeAttribute('src');
    pdf.hidden = true;
    pdf.removeAttribute('src');
    unsupported.hidden = true;
    title.textContent = link.dataset.fileName || 'Vista previa del archivo';
    download.href = link.dataset.downloadUrl || '#';
    download.setAttribute('download', link.dataset.fileName || '');

    if (type === 'application/pdf') {
        pdf.src = previewUrl;
        pdf.hidden = false;
    } else if (['image/jpeg', 'image/png', 'image/webp'].includes(type)) {
        image.src = previewUrl;
        image.alt = link.dataset.fileName || 'Imagen adjunta';
        image.hidden = false;
    } else {
        unsupported.hidden = false;
    }

    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('attachment-viewer-open');
    modal.querySelector('.attachment-viewer-close').focus();
}

function closeAttachmentPreview() {
    const modal = document.getElementById('attachmentPreviewModal');
    if (!modal) return;
    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('attachment-viewer-open');
    modal.querySelector('.attachment-viewer-image').removeAttribute('src');
    modal.querySelector('.attachment-viewer-pdf').removeAttribute('src');
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeAttachmentPreview();
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
            btn.setAttribute('aria-expanded', item.classList.contains('active') ? 'true' : 'false');
            btn.addEventListener('click', () => {
                const isActive = item.classList.toggle('active');
                btn.setAttribute('aria-expanded', isActive ? 'true' : 'false');
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
        const errorAlert = uploader.querySelector('.attachment-upload-error');
        const removeBtn = uploader.querySelector('.attachment-remove-btn');
        const filenameEl = uploader.querySelector('.attachment-filename');
        const sizeEl = uploader.querySelector('.attachment-filesize');
        const thumbEl = uploader.querySelector('.attachment-thumb');
        const progressBar = uploader.querySelector('.attachment-progress-bar');
        const form = uploader.closest('form');
        const flowBtn = form ? form.querySelector('.btn-flow') : null;

        if (!input || !dropzone) return;
        if (flowBtn) flowBtn.disabled = true;

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
                const file = dt.files[0];
                if (!validateFile(file)) {
                    clearSelectedFile();
                    showFileError(file);
                    return;
                }
                clearFileError();
                input.files = dt.files;
                handleFileSelect(file);
                if (input.dataset.autoSubmit === 'true' && form) {
                    form.requestSubmit();
                }
            }
        });

        // Cambio manual en el input de archivo
        input.addEventListener('change', () => {
            if (input.files && input.files[0]) {
                const file = input.files[0];
                if (!validateFile(file)) {
                    clearSelectedFile();
                    showFileError(file);
                    return;
                }
                clearFileError();
                handleFileSelect(file);
                if (input.dataset.autoSubmit === 'true' && form) {
                    form.requestSubmit();
                }
            }
        });

        function validateFile(file) {
            const extension = file.name.split('.').pop().toLowerCase();
            const accept = (input.getAttribute('accept') || '').toLowerCase();
            const allowedExtensions = accept.split(',')
                .map(value => value.trim().replace(/^\./, ''))
                .filter(value => value && !value.includes('/'));
            const maxSize = Number(input.dataset.maxSizeBytes || 5 * 1024 * 1024);
            return allowedExtensions.includes(extension) && file.size <= maxSize;
        }

        function showFileError(file) {
            if (!errorAlert) return;
            const maxSize = Number(input.dataset.maxSizeBytes || 5 * 1024 * 1024);
            const message = file.size > maxSize
                ? `El archivo supera el límite de ${Math.round(maxSize / (1024 * 1024))} MB. No se adjuntó.`
                : 'Formato no permitido. Revisá los formatos aceptados. El archivo no se adjuntó.';
            errorAlert.innerHTML = '<i class="fa-solid fa-circle-exclamation notice-icon"></i><span></span>';
            errorAlert.querySelector('span').textContent = message;
            errorAlert.style.display = 'flex';
        }

        function clearFileError() {
            if (errorAlert) errorAlert.style.display = 'none';
        }

        function clearSelectedFile() {
            input.value = '';
            if (card) card.classList.remove('has-file');
            dropzone.style.display = 'block';
            if (thumbEl) thumbEl.innerHTML = '';
            if (flowBtn) flowBtn.disabled = true;
        }

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
                    img.onload = () => URL.revokeObjectURL(img.src);
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
            if (card) {
                dropzone.style.display = 'none';
                card.classList.add('has-file');
            }

            if (flowBtn) {
                flowBtn.removeAttribute('disabled');
            }
        }

        // Botón (×) para remover el archivo seleccionado
        if (removeBtn) {
            removeBtn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                clearSelectedFile();
                clearFileError();
            });
        }

        // Animación fluida al enviar el formulario (FlowButton + Progress Bar)
        if (form && flowBtn) {
            form.addEventListener('submit', (e) => {
                if (!input.files || input.files.length === 0) {
                    e.preventDefault();
                    if (errorAlert) {
                        errorAlert.innerHTML = '<i class="fa-solid fa-circle-exclamation notice-icon"></i><span></span>';
                        errorAlert.querySelector('span').textContent = 'Seleccioná un archivo antes de enviarlo.';
                        errorAlert.style.display = 'flex';
                    }
                    return;
                }

                uploader.classList.add('is-uploading');
                flowBtn.classList.add('is-flowing');
                flowBtn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin btn-flow-icon"></i> <span>Subiendo archivo...</span>';

                if (progressBar) {
                    progressBar.style.width = '25%';
                    setTimeout(() => progressBar.style.width = '65%', 350);
                    setTimeout(() => progressBar.style.width = '95%', 700);
                }
            });
        }
    });
}

// ============================================================================
// Detección y Vista Previa Dinámica de Avatar de Mascotas por Tipo de Animal
// ============================================================================
function detectPetAvatar(animalType) {
    const typeNorm = (animalType || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').trim();

    const avatars = window.PET_GENERIC_AVATARS || {
        dog: (window.BASE_URL || '') + '/assets/perro_generico.png',
        cat: (window.BASE_URL || '') + '/assets/gato_generico.png',
        bird: (window.BASE_URL || '') + '/assets/ave_generico.png',
        rodent: (window.BASE_URL || '') + '/assets/roedor_generico.png',
        reptile: (window.BASE_URL || '') + '/assets/reptil_generico.png',
        other: (window.BASE_URL || '') + '/assets/otro_generico.png'
    };

    switch (typeNorm) {
        case 'perro':
            return avatars.dog;
        case 'gato':
            return avatars.cat;
        case 'ave':
            return avatars.bird;
        case 'roedor':
            return avatars.rodent;
        case 'reptil':
            return avatars.reptile;
        case 'otro':
        default:
            return avatars.other;
    }
}
window.detectPetAvatar = detectPetAvatar;

function setupPetAvatarLivePreview(cfg) {
    const { typeInput, photoInput, previewImg, titleEl, subEl } = cfg;
    if (!previewImg) return;

    let hasCustomPhoto = false;

    function update() {
        if (hasCustomPhoto) return;
        const type = typeInput ? typeInput.value : '';
        const src = detectPetAvatar(type);
        if (previewImg.src !== src) {
            previewImg.style.transform = 'scale(0.88)';
            setTimeout(() => {
                previewImg.src = src;
                previewImg.style.transform = 'scale(1)';
            }, 120);
        }
        if (titleEl) {
            titleEl.textContent = type ? 'Avatar ilustrado: ' + type : 'Avatar ilustrado: Otro';
        }
        if (subEl) {
            subEl.textContent = type 
                ? 'Ilustración genérica asignada para ' + type + '. Podés adjuntar una foto propia si lo preferís.'
                : 'Seleccioná un tipo de animal para asignar su avatar o subí una foto propia.';
        }
    }

    if (typeInput) {
        typeInput.addEventListener('change', update);
        typeInput.addEventListener('input', update);
    }

    if (photoInput) {
        photoInput.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                const file = this.files[0];
                const reader = new FileReader();
                reader.onload = function(e) {
                    hasCustomPhoto = true;
                    previewImg.src = e.target.result;
                    if (titleEl) titleEl.textContent = 'Foto personalizada seleccionada';
                    if (subEl) subEl.textContent = file.name + ' (' + (file.size / 1024).toFixed(0) + ' KB)';
                };
                reader.readAsDataURL(file);
            } else {
                hasCustomPhoto = false;
                update();
            }
        });
    }

    update();
}
window.setupPetAvatarLivePreview = setupPetAvatarLivePreview;

