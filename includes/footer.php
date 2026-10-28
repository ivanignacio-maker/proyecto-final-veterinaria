    </main>

<?php if (!empty($isLanding) || (empty($_SESSION['clinic_id']) && empty($_SESSION['client_id']) && empty($_SESSION['admin_id']))): ?>
    <footer class="public-footer">
        <div class="footer-top">
            <div class="footer-grid">
                <!-- Columna 1: Marca y Propuesta -->
                <div class="footer-col footer-col-brand">
                    <a class="footer-brand" href="<?= url('index.php') ?>">
                        <img src="<?= url('assets/logo.webp') ?>?v=4" alt="<?= APP_NAME ?>" class="footer-brand-icon-img">
                        <span class="footer-brand-name"><?= APP_NAME ?></span>
                    </a>
                    <p class="footer-description">
                        Plataforma SaaS de gestión clínica y colaboración médica veterinaria. Unificamos historias clínicas, control de stock y comunicación con tutores de mascotas en un entorno seguro y ágil.
                    </p>
                    <div class="footer-trust-tags">
                        <span class="trust-tag"><i class="fa-solid fa-shield-halved"></i> Cifrado médico 256-bit</span>
                        <span class="trust-tag"><i class="fa-solid fa-cloud"></i> Respaldos diarios automáticos</span>
                        <span class="trust-tag"><i class="fa-solid fa-bolt"></i> 99.9% Uptime garantizado</span>
                    </div>
                </div>

                <!-- Redes sociales visibles en la versión mobile -->
                <div class="footer-social-mobile">
                    <h3 class="footer-heading">Redes</h3>
                    <div class="footer-social-links">
                        <a class="contact-social-link" href="https://instagram.com" target="_blank" rel="noopener noreferrer">
                            <i class="fa-brands fa-instagram" aria-hidden="true"></i> Instagram
                        </a>
                        <a class="contact-social-link" href="https://facebook.com" target="_blank" rel="noopener noreferrer">
                            <i class="fa-brands fa-facebook" aria-hidden="true"></i> Facebook
                        </a>
                        <a class="contact-social-link" href="https://linkedin.com" target="_blank" rel="noopener noreferrer">
                            <i class="fa-brands fa-linkedin" aria-hidden="true"></i> LinkedIn
                        </a>
                    </div>
                </div>

                <!-- Columna 2: Soluciones Clínicas -->
                <div class="footer-col footer-col-solutions">
                    <h3 class="footer-heading">Soluciones</h3>
                    <ul class="footer-links">
                        <li><a href="<?= url('index.php#soluciones') ?>">Historia Clínica Unificada</a></li>
                        <li><a href="<?= url('index.php#soluciones') ?>">Control de Stock y Medicamentos</a></li>
                        <li><a href="<?= url('index.php#soluciones') ?>">Libreta Sanitaria Digital (Tutores)</a></li>
                        <li><a href="<?= url('migracion.php') ?>" class="highlight-link">Migración Asistida</a></li>
                        <li><a href="<?= url('planes.php') ?>">Planes para Clínicas y Consultorios</a></li>
                    </ul>
                </div>

                <!-- Columna 3: Empresa y Legal -->
                <div class="footer-col footer-col-institutional">
                    <h3 class="footer-heading">Institucional</h3>
                    <ul class="footer-links">
                        <li><a href="<?= url('nosotros.php') ?>">Quiénes somos</a></li>
                        <li><a href="<?= url('terminos.php') ?>">Términos para veterinarias</a></li>
                        <li><a href="<?= url('contacto.php') ?>">Contacto comercial</a></li>
                        <li><a href="<?= url('index.php#faq') ?>">Preguntas frecuentes</a></li>

                    </ul>
                </div>

                <!-- Columna 4: Accesos Rápidos y Ayuda -->
                <div class="footer-col footer-col-portals">
                    <h3 class="footer-heading">Portales y Ayuda</h3>
                    <div class="footer-access-cards">
                        <a href="<?= url('login.php?tipo=veterinaria') ?>" class="footer-access-item">
                            <span class="access-icon"><i class="fa-solid fa-hospital"></i></span>
                            <div>
                                <strong>Acceso Veterinarias</strong>
                                <span>Ingreso al panel clínico</span>
                            </div>
                        </a>
                        <a href="<?= url('login.php?tipo=cliente') ?>" class="footer-access-item">
                            <span class="access-icon"><i class="fa-solid fa-paw"></i></span>
                            <div>
                                <strong>Portal de Clientes</strong>
                                <span>Ver libreta de tu mascota</span>
                            </div>
                        </a>
                    </div>
                    <div class="footer-contact-direct">
                        <span>¿Dudas para migrar tu sistema?</span>
                        <a href="<?= url('contacto.php?motivo=migracion') ?>" class="footer-btn-help">Hablar con un asesor técnico</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Barra Inferior -->
        <div class="footer-bottom">
            <div class="footer-bottom-inner">
                <p class="copyright-text">
                    &copy; <?= date('Y') ?> <?= APP_NAME ?>. Todos los derechos reservados. Desarrollado para clínicas veterinarias y tutores responsables.
                </p>
                <div class="footer-legal-links">
                    <a href="<?= url('terminos.php') ?>">Términos de servicio</a>
                    <span class="legal-dot">·</span>
                    <a href="<?= url('terminos.php#privacidad') ?>">Privacidad y Protección de Datos</a>
                    <span class="legal-dot">·</span>
                    <a href="<?= url('contacto.php') ?>">Soporte 24/7</a>
                </div>
            </div>
        </div>
    </footer>
<?php else: ?>
    <!-- Footer Limpio para Paneles y Vistas Internas -->
    <footer style="text-align: center; padding: 26px 15px; color: #667085; font-size: 0.88rem; border-top: 1px solid #e1e7ef; margin-top: 40px;">
        <p style="margin: 0;">
            <i class="fa-solid fa-paw" style="color: var(--green); margin-right: 4px;"></i> <strong><?= APP_NAME ?></strong> · Sistema de gestión clínica y turnos veterinarios.
        </p>
    </footer>
<?php endif; ?>

    <script src="<?= url('assets/app.js') ?>?v=8"></script>
</body>
</html>
<?php
// Recordatorios de vacunas: una vez por día, después de entregar la página.
require_once __DIR__ . '/recordatorios_vacunas.php';
maybe_run_daily_vaccine_reminders();
