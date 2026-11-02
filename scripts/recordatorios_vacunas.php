<?php
/**
 * Envía ahora los recordatorios de vacunas pendientes (ver includes/recordatorios_vacunas.php).
 *
 * Uso:  C:\xampp\php\php.exe scripts\recordatorios_vacunas.php
 * Se puede agendar en el Programador de tareas de Windows para que corra todos los días.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Solo se puede ejecutar desde la consola.');
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/recordatorios_vacunas.php';

echo 'Recordatorios de vacunas · ' . date('d/m/Y H:i') . ' · ' . (MAIL_ENABLED ? 'SMTP ' . MAIL_HOST . ' como ' . MAIL_USER : 'MODO PRUEBA (falta la contraseña SMTP en config/mail.local.php)') . PHP_EOL;

$log = run_vaccine_reminders();
echo $log ? implode(PHP_EOL, $log) . PHP_EOL : 'No hay avisos para enviar hoy.' . PHP_EOL;

// Marcar el día como procesado para que el sitio no lo vuelva a correr hoy.
file_put_contents(reminders_storage_dir() . '/recordatorios_vacunas.lock', date('Y-m-d'));
