<?php
/**
 * Correo saliente de VetCare (no se sube al repositorio: ver .gitignore).
 *
 * Por ahora sale desde una cuenta personal (sincehaller@gmail.com).
 * Para pasar a vetcare987@gmail.com:
 *  1. En esa cuenta, activá la verificación en 2 pasos.
 *  2. Creá una "contraseña de aplicación" en https://myaccount.google.com/apppasswords
 *  3. Cambiá MAIL_USER y MAIL_PASS acá abajo (16 letras; los espacios no importan).
 * Si MAIL_PASS queda vacío, los correos se guardan en storage/correos/ (modo prueba).
 */
define('MAIL_HOST', 'smtp.gmail.com');
define('MAIL_PORT', 587);
define('MAIL_USER', 'sincehaller@gmail.com');
define('MAIL_PASS', 'pjhq gmju suoj mwxx');
define('MAIL_FROM_NAME', 'VetCare');
