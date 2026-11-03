<?php
require_once __DIR__ . '/config/config.php';

http_response_code(404);
$pageTitle = 'Página no encontrada';
$pageDescription = 'No encontramos esta página. Volvé al inicio de ' . APP_NAME . ' o contactanos si necesitás ayuda.';
$isLanding = true;
$bodyClass = 'page-bg-follow page-bg-error';

require __DIR__ . '/includes/header.php';
?>

<main class="main-landing">
    <section class="error-page-section">
        <div class="public-container">
            <div class="error-page-card">
                <div class="error-page-content">
                    <span class="error-page-icon" aria-hidden="true">
                        <i class="fa-solid fa-paw"></i>
                    </span>
                    <span class="section-eyebrow">Error 404</span>
                    <h1 class="error-page-title">No encontramos esta página</h1>
                    <p class="error-page-copy">
                        Parece que el enlace cambió o que la dirección no existe. Podés volver al inicio y seguir recorriendo VetCare.
                    </p>
                    <div class="error-page-actions">
                        <a href="<?= url('index.php') ?>" class="btn-hero-primary">
                            <i class="fa-solid fa-house" aria-hidden="true"></i>
                            <span>Volver al inicio</span>
                        </a>
                        <a href="<?= url('contacto.php') ?>" class="btn-hero-secondary">
                            <i class="fa-regular fa-envelope" aria-hidden="true"></i>
                            <span>Contactar soporte</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>
