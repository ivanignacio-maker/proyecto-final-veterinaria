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
    initPortalInteractiveGridBackground();
    initAttachmentUploaders();
    initLandingCardReveal();
    initClientTopographyBackground();
});

function initPortalInteractiveGridBackground() {
    const svg = document.getElementById('portalInteractiveGridBackground');
    if (!svg) return;

    const cellSize = 40;
    const namespace = 'http://www.w3.org/2000/svg';
    let columns = 0;
    let rows = 0;
    let cells = [];
    let activeCell = null;

    function createGrid() {
        const width = window.innerWidth;
        const height = window.innerHeight;
        columns = Math.ceil(width / cellSize);
        rows = Math.ceil(height / cellSize);
        cells = new Array(columns * rows);
        activeCell = null;

        const fragment = document.createDocumentFragment();
        for (let row = 0; row < rows; row++) {
            for (let column = 0; column < columns; column++) {
                const cell = document.createElementNS(namespace, 'rect');
                cell.setAttribute('x', String(column * cellSize));
                cell.setAttribute('y', String(row * cellSize));
                cell.setAttribute('width', String(cellSize));
                cell.setAttribute('height', String(cellSize));
                cell.setAttribute('fill', 'currentColor');
                cell.setAttribute('stroke', 'currentColor');
                cell.setAttribute('stroke-opacity', '.11');
                cell.setAttribute('fill-opacity', '0');
                cell.style.transition = 'fill-opacity 1000ms ease-out';
                const index = row * columns + column;
                cells[index] = cell;
                fragment.appendChild(cell);
            }
        }
        svg.replaceChildren(fragment);
    }

    function activateCell(index) {
        const nextCell = index >= 0 && index < cells.length ? cells[index] : null;
        if (nextCell === activeCell) return;

        if (activeCell) {
            activeCell.style.transition = 'fill-opacity 1000ms ease-out';
            activeCell.setAttribute('fill-opacity', '0');
        }

        activeCell = nextCell;
        if (activeCell) {
            activeCell.style.transition = 'fill-opacity 0ms';
            activeCell.setAttribute('fill-opacity', '.16');
        }
    }

    function handlePointerMove(event) {
        const bounds = svg.getBoundingClientRect();
        const column = Math.floor((event.clientX - bounds.left) / cellSize);
        const row = Math.floor((event.clientY - bounds.top) / cellSize);
        if (column < 0 || row < 0 || column >= columns || row >= rows) {
            activateCell(-1);
            return;
        }
        activateCell(row * columns + column);
    }

    function handlePointerLeave() {
        activateCell(-1);
    }

    function cleanup() {
        window.removeEventListener('resize', createGrid);
        window.removeEventListener('pointermove', handlePointerMove);
        window.removeEventListener('pointerleave', handlePointerLeave);
    }

    createGrid();
    window.addEventListener('resize', createGrid, { passive: true });
    window.addEventListener('pointermove', handlePointerMove, { passive: true });
    window.addEventListener('pointerleave', handlePointerLeave, { passive: true });
    window.addEventListener('pagehide', cleanup, { once: true });
}

function initClientTopographyBackground() {
    const canvas = document.getElementById('clientTopographyBackground');
    if (!canvas) return;

    const ctx = canvas.getContext('2d', { alpha: true });
    if (!ctx) return;

    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const pointer = { x: -1000, y: -1000, active: false };
    const ripples = [];
    const gridSpacing = 32;
    const contourStep = 0.105;
    const hillRadius = 185;
    let width = 0;
    let height = 0;
    let pixelRatio = 1;
    let columns = 0;
    let rows = 0;
    let grid = new Float32Array(0);
    let animationFrame = 0;
    let lastFrame = 0;

    function addCrossing(points, count, ax, ay, av, bx, by, bv, level) {
        if ((av < level && bv >= level) || (bv < level && av >= level)) {
            const fraction = (level - av) / (bv - av);
            points[count] = ax + (bx - ax) * fraction;
            points[count + 1] = ay + (by - ay) * fraction;
            return count + 2;
        }
        return count;
    }

    function sampleTerrain(x, y, time, animated) {
        const phase = animated ? time * .00012 : 0;
        let elevation = Math.sin(x * .006 + Math.sin(y * .0038 + phase) * .8) * .38
            + Math.cos(y * .007 - x * .0022 - phase * .7) * .31
            + Math.sin((x + y) * .0042 + phase * .45) * .2
            + Math.cos((x * .45 - y) * .009) * .11;

        if (animated && pointer.active) {
            const dx = x - pointer.x;
            const dy = (y - pointer.y) * 1.08;
            const distanceSquared = dx * dx + dy * dy;
            elevation += .82 * Math.exp(-distanceSquared / (2 * hillRadius * hillRadius));
        }

        if (animated) {
            for (let index = ripples.length - 1; index >= 0; index--) {
                const ripple = ripples[index];
                const age = (time - ripple.startedAt) / 1000;
                if (age >= 4.2) {
                    ripples.splice(index, 1);
                    continue;
                }

                const dx = x - ripple.x;
                const dy = y - ripple.y;
                const distance = Math.sqrt(dx * dx + dy * dy);
                const envelope = Math.exp(-distance / 310) * Math.exp(-age * .72);
                elevation += Math.sin(distance * .036 - age * 5.2) * .52 * envelope;
            }
        }

        return elevation;
    }

    function drawContours(time, animated) {
        const darkMode = document.documentElement.dataset.theme === 'dark';
        ctx.strokeStyle = darkMode ? 'rgba(111, 205, 168, .24)' : 'rgba(27, 112, 93, .21)';
        ctx.lineWidth = 1.15;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';

        for (let row = 0; row < rows; row++) {
            const y = row * gridSpacing;
            for (let column = 0; column < columns; column++) {
                const x = column * gridSpacing;
                grid[row * columns + column] = sampleTerrain(x, y, time, animated);
            }
        }

        const firstLevel = -16;
        const lastLevel = 16;
        const intersections = new Float32Array(8);
        for (let levelIndex = firstLevel; levelIndex <= lastLevel; levelIndex++) {
            const level = levelIndex * contourStep;
            ctx.beginPath();

            for (let row = 0; row < rows - 1; row++) {
                const y = row * gridSpacing;
                const rowOffset = row * columns;
                for (let column = 0; column < columns - 1; column++) {
                    const x = column * gridSpacing;
                    const v0 = grid[rowOffset + column];
                    const v1 = grid[rowOffset + column + 1];
                    const v2 = grid[rowOffset + columns + column + 1];
                    const v3 = grid[rowOffset + columns + column];
                    const min = Math.min(v0, v1, v2, v3);
                    const max = Math.max(v0, v1, v2, v3);
                    if (level < min || level > max) continue;

                    let count = 0;
                    count = addCrossing(intersections, count, x, y, v0, x + gridSpacing, y, v1, level);
                    count = addCrossing(intersections, count, x + gridSpacing, y, v1, x + gridSpacing, y + gridSpacing, v2, level);
                    count = addCrossing(intersections, count, x + gridSpacing, y + gridSpacing, v2, x, y + gridSpacing, v3, level);
                    count = addCrossing(intersections, count, x, y + gridSpacing, v3, x, y, v0, level);

                    if (count === 4) {
                        ctx.moveTo(intersections[0], intersections[1]);
                        ctx.lineTo(intersections[2], intersections[3]);
                    } else if (count === 8) {
                        const centerIsHigh = (v0 + v1 + v2 + v3) / 4 >= level;
                        if (centerIsHigh) {
                            ctx.moveTo(intersections[0], intersections[1]);
                            ctx.lineTo(intersections[2], intersections[3]);
                            ctx.moveTo(intersections[4], intersections[5]);
                            ctx.lineTo(intersections[6], intersections[7]);
                        } else {
                            ctx.moveTo(intersections[0], intersections[1]);
                            ctx.lineTo(intersections[6], intersections[7]);
                            ctx.moveTo(intersections[2], intersections[3]);
                            ctx.lineTo(intersections[4], intersections[5]);
                        }
                    }
                }
            }

            ctx.stroke();
        }
    }

    function drawScene(time, animated) {
        ctx.clearRect(0, 0, width, height);
        drawContours(time, animated);
    }

    function resizeCanvas() {
        pixelRatio = Math.min(window.devicePixelRatio || 1, 1.25);
        width = window.innerWidth;
        height = window.innerHeight;
        canvas.width = Math.round(width * pixelRatio);
        canvas.height = Math.round(height * pixelRatio);
        canvas.style.width = `${width}px`;
        canvas.style.height = `${height}px`;
        ctx.setTransform(pixelRatio, 0, 0, pixelRatio, 0, 0);
        columns = Math.ceil(width / gridSpacing) + 1;
        rows = Math.ceil(height / gridSpacing) + 1;
        grid = new Float32Array(columns * rows);
        drawScene(performance.now(), !reduceMotion);
    }

    function animate(time) {
        animationFrame = 0;
        if (document.hidden) return;
        if (time - lastFrame < 40) {
            animationFrame = window.requestAnimationFrame(animate);
            return;
        }
        lastFrame = time;
        drawScene(time, true);
        animationFrame = window.requestAnimationFrame(animate);
    }

    function handlePointerMove(event) {
        pointer.x = event.clientX;
        pointer.y = event.clientY;
        pointer.active = true;
    }

    function handlePointerLeave() {
        pointer.active = false;
    }

    function handlePointerDown(event) {
        pointer.x = event.clientX;
        pointer.y = event.clientY;
        pointer.active = true;
        ripples.push({ x: event.clientX, y: event.clientY, startedAt: performance.now() });
        if (ripples.length > 4) ripples.shift();
    }

    function handleVisibilityChange() {
        if (document.hidden) {
            if (animationFrame) window.cancelAnimationFrame(animationFrame);
            animationFrame = 0;
        } else if (!reduceMotion && !animationFrame) {
            lastFrame = 0;
            animationFrame = window.requestAnimationFrame(animate);
        }
    }

    function cleanup() {
        if (animationFrame) window.cancelAnimationFrame(animationFrame);
        window.removeEventListener('resize', resizeCanvas);
        window.removeEventListener('pointermove', handlePointerMove);
        window.removeEventListener('pointerdown', handlePointerDown);
        window.removeEventListener('pointerleave', handlePointerLeave);
        document.removeEventListener('visibilitychange', handleVisibilityChange);
        themeObserver.disconnect();
    }

    resizeCanvas();
    window.addEventListener('resize', resizeCanvas, { passive: true });
    if (!reduceMotion) {
        window.addEventListener('pointermove', handlePointerMove, { passive: true });
        window.addEventListener('pointerdown', handlePointerDown, { passive: true });
        window.addEventListener('pointerleave', handlePointerLeave, { passive: true });
        document.addEventListener('visibilitychange', handleVisibilityChange);
        animationFrame = window.requestAnimationFrame(animate);
    }
    const themeObserver = new MutationObserver(() => drawScene(performance.now(), !reduceMotion));
    themeObserver.observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });
    window.addEventListener('pagehide', cleanup, { once: true });
}

function initLandingCardReveal() {
    const main = document.querySelector('main.main-landing');
    if (!main || !('IntersectionObserver' in window) || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    const selector = '.feature-card-clean, .pricing-card, .pricing-guarantee-card, .value-card, .about-side-card, .migration-card, .faq-item, .stat-item-box, .mockup-card-main, .migration-banner-container, .migration-steps-box, .about-story-card, .legal-article-card, .final-cta-box, .contact-card-form, .contact-sidebar-card, .contact-map-card, .card, .panel';
    const targets = Array.from(main.querySelectorAll(selector));
    if (!targets.length) return;

    const observer = new IntersectionObserver((entries, currentObserver) => {
        entries.forEach(entry => {
            if (!entry.isIntersecting) return;
            entry.target.classList.add('is-revealed');
            currentObserver.unobserve(entry.target);
        });
    }, { threshold: 0.06, rootMargin: '0px 0px -20px 0px' });

    targets.forEach((element, index) => {
        element.style.setProperty('--reveal-delay', `${(index % 4) * 75}ms`);
        element.classList.add('scroll-reveal');
        observer.observe(element);
    });
}

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
        dog: (window.BASE_URL || '') + '/assets/perro_generico.webp',
        cat: (window.BASE_URL || '') + '/assets/gato_generico.webp',
        bird: (window.BASE_URL || '') + '/assets/ave_generico.webp',
        rodent: (window.BASE_URL || '') + '/assets/roedor_generico.webp',
        reptile: (window.BASE_URL || '') + '/assets/reptil_generico.webp',
        other: (window.BASE_URL || '') + '/assets/otro_generico.webp'
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

