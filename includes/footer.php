    </main>

<?php if (!empty($isLanding) || (empty($_SESSION['clinic_id']) && empty($_SESSION['client_id']) && empty($_SESSION['admin_id']))): ?>
    <footer class="public-footer">
        <div class="footer-top">
            <div class="footer-grid">
                <!-- Columna 1: Marca y Redes -->
                <div class="footer-col footer-col-brand">
                    <a class="footer-brand" href="<?= url('index.php') ?>">
                        <img src="<?= url('assets/logo.webp') ?>?v=4" alt="<?= APP_NAME ?>" class="footer-brand-icon-img">
                        <span class="footer-brand-name"><?= APP_NAME ?></span>
                    </a>
                    <p class="footer-description">
                        Gestión clínica veterinaria y libreta sanitaria digital.
                    </p>
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

                <!-- Columna 2: Navegación -->
                <div class="footer-col footer-col-nav">
                    <h3 class="footer-heading">Navegación</h3>
                    <ul class="footer-links">
                        <li><a href="<?= url('index.php') ?>">Inicio</a></li>
                        <li><a href="<?= url('planes.php') ?>">Planes y precios</a></li>
                        <li><a href="<?= url('migracion.php') ?>" class="highlight-link">Migración asistida <span class="badge-pill-micro">Gratis</span></a></li>
                        <li><a href="<?= url('nosotros.php') ?>">Quiénes somos</a></li>
                        <li><a href="<?= url('contacto.php') ?>">Contacto</a></li>
                    </ul>
                </div>

                <!-- Columna 3: Funcionalidades -->
                <div class="footer-col footer-col-solutions">
                    <h3 class="footer-heading">Funcionalidades</h3>
                    <ul class="footer-links">
                        <li><a href="<?= url('index.php#soluciones') ?>">Historia clínica digital</a></li>
                        <li><a href="<?= url('index.php#soluciones') ?>">Control de stock de fármacos</a></li>
                        <li><a href="<?= url('registro_cliente.php') ?>">Libreta digital para tutores</a></li>
                        <li><a href="<?= url('index.php#faq') ?>">Preguntas frecuentes</a></li>
                    </ul>
                </div>

                <!-- Columna 4: Accesos y Soporte -->
                <div class="footer-col footer-col-access">
                    <h3 class="footer-heading">Accesos</h3>
                    <ul class="footer-links">
                        <li><a href="<?= url('login.php') ?>"><i class="fa-solid fa-arrow-right-to-bracket" style="font-size: 11px;"></i> Iniciar sesión</a></li>
                        <li><a href="<?= url('contratar.php') ?>"><i class="fa-solid fa-hospital" style="font-size: 11px;"></i> Contratar para clínica</a></li>
                        <li><a href="<?= url('registro_cliente.php') ?>"><i class="fa-solid fa-paw" style="font-size: 11px;"></i> Registro para tutores</a></li>
                        <li><a href="<?= url('ayuda.php') ?>"><i class="fa-solid fa-circle-question" style="font-size: 11px;"></i> Centro de ayuda</a></li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Barra Inferior -->
        <div class="footer-bottom">
            <div class="footer-bottom-inner">
                <p class="copyright-text">
                    &copy; <?= date('Y') ?> <?= APP_NAME ?>. Todos los derechos reservados.
                </p>
                <div class="footer-legal-links">
                    <a href="<?= url('terminos.php') ?>">Términos del servicio</a>
                    <span class="legal-dot">·</span>
                    <a href="<?= url('terminos.php#privacidad') ?>">Privacidad</a>
                    <span class="legal-dot">·</span>
                    <a href="<?= url('contacto.php') ?>">Soporte</a>
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
