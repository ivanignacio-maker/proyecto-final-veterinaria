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

                <!-- Columna 4: Redes Sociales -->
                <div class="footer-col footer-col-social">
                    <h3 class="footer-heading">Redes sociales</h3>
                    <div class="footer-social-icons">
                        <a class="footer-social-icon" href="https://instagram.com/vetcare.ok" target="_blank" rel="noopener noreferrer" aria-label="Instagram" title="Instagram">
                            <i class="fa-brands fa-instagram" aria-hidden="true"></i>
                        </a>
                        <a class="footer-social-icon" href="https://www.facebook.com/profile.php?id=61596432201348" target="_blank" rel="noopener noreferrer" aria-label="Facebook" title="Facebook">
                            <i class="fa-brands fa-facebook-f" aria-hidden="true"></i>
                        </a>
                        <a class="footer-social-icon" href="https://x.com/vetcare_ok" target="_blank" rel="noopener noreferrer" aria-label="X" title="X">
                            <i class="fa-brands fa-x-twitter" aria-hidden="true"></i>
                        </a>
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

    <script src="<?= url('assets/app.js') ?>?v=<?= filemtime(__DIR__ . '/../assets/app.js') ?>"></script>
    <script src="<?= url('assets/date-selectors.js') ?>?v=<?= filemtime(__DIR__ . '/../assets/date-selectors.js') ?>"></script>
</body>
</html>
<?php
// Recordatorios de vacunas: una vez por día, después de entregar la página.
require_once __DIR__ . '/recordatorios_vacunas.php';
maybe_run_daily_vaccine_reminders();
