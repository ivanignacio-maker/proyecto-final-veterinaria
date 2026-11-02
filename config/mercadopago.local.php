<?php
/**
 * Datos de Mercado Pago. Copiá este archivo como config/mercadopago.local.php y completalo.
 * mercadopago.local.php NO se sube al repositorio (tiene el Access Token, que es como una contraseña).
 *
 * Dónde conseguirlos: https://www.mercadopago.com.ar/developers/panel  →  Tu aplicación
 *   - Credenciales de prueba  → Access Token que empieza con "TEST-"  (para desarrollar, no cobra de verdad)
 *   - Credenciales de producción → Access Token que empieza con "APP_USR-" (cobra de verdad)
 *
 * Para probar pagos de mentira hay que pagar con un "usuario de prueba" y tarjetas de prueba
 * (documentación: https://www.mercadopago.com.ar/developers/es/docs/checkout-pro/integration-test).
 */
define('MP_ACCESS_TOKEN', 'APP_USR-6540995913653711-110216-81caabe4d58e84791edb276b0b14b6ea-3812389882');

// Opcional: "Clave secreta" del webhook (Tu aplicación → Webhooks). Sirve para verificar que el aviso viene de Mercado Pago.
// define('MP_WEBHOOK_SECRET', '');

// Opcional pero necesaria para que Mercado Pago pueda AVISAR al sistema cuando estás en tu PC:
// una URL pública https que apunte a tu proyecto (por ejemplo con ngrok: ngrok http 80).
// define('MP_PUBLIC_BASE_URL', 'https://abc123.ngrok-free.app/proyectofinalv5');

// Opcional: si cURL da "SSL certificate problem" en XAMPP, descargá https://curl.se/ca/cacert.pem y poné la ruta.
// define('MP_CA_BUNDLE', 'C:/xampp/php/extras/ssl/cacert.pem');
