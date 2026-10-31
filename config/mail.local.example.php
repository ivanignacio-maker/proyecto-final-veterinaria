<?php
/**
 * Datos del correo saliente. Copiá este archivo como config/mail.local.php
 * y completalo. mail.local.php no se sube al repositorio (tiene la contraseña).
 *
 * Con Gmail:
 *  1. Activá la verificación en 2 pasos en la cuenta.
 *  2. Creá una "contraseña de aplicación" en https://myaccount.google.com/apppasswords
 *  3. Pegala en MAIL_PASS (son 16 letras; los espacios no importan).
 */
define('MAIL_HOST', 'smtp.gmail.com');
define('MAIL_PORT', 587);
define('MAIL_USER', 'vetcare987@gmail.com');
define('MAIL_PASS', 'xxxx xxxx xxxx xxxx');
define('MAIL_FROM_NAME', 'VetCare');
