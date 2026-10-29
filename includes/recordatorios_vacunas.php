<?php
/**
 * Recordatorios de vacunas por correo.
 *
 * Regla:
 *  - Cada consulta con motivo "Vacuna" tiene fecha de vencimiento y/o próxima dosis.
 *    Se toma la más cercana que todavía no pasó (fecha de renovación).
 *  - 1er aviso: cuando faltan 14 días o menos para esa fecha.
 *  - 2do aviso: 7 días después del primero, solo si el tutor no sacó turno.
 *  - No se avisa si la mascota ya tiene un turno pendiente, o si después se le
 *    registró otra vacuna (se considera renovada).
 *
 * Se ejecuta una vez por día: sola al visitar el sitio (ver includes/footer.php)
 * o a mano / con el Programador de tareas: php scripts/recordatorios_vacunas.php
 */

const VACCINE_REMINDER_DAYS_BEFORE = 14;   // primer aviso: días antes de la renovación
const VACCINE_REMINDER_RESEND_DAYS = 7;    // reenvío: días después del aviso anterior
const VACCINE_REMINDER_MAX = 2;            // cantidad máxima de avisos por vacuna

function reminders_storage_dir(): string {
    $dir = dirname(__DIR__) . '/storage';
    if (!is_dir($dir . '/correos')) {
        mkdir($dir . '/correos', 0775, true);
    }
    // Nadie debe poder abrir estos archivos desde el navegador.
    if (!is_file($dir . '/.htaccess')) {
        file_put_contents($dir . '/.htaccess', "Require all denied\n");
    }
    return $dir;
}

function vaccine_reminders_ensure_table(): void {
    // CREATE TABLE confirma cualquier transacción abierta, así que solo se ejecuta si falta la tabla.
    if (db()->query("SHOW TABLES LIKE 'vaccine_reminders'")->fetchColumn()) {
        return;
    }
    db()->exec("
        CREATE TABLE IF NOT EXISTS vaccine_reminders (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            consultation_id BIGINT UNSIGNED NOT NULL,
            due_date DATE NOT NULL,
            reminder_number TINYINT UNSIGNED NOT NULL,
            client_id BIGINT UNSIGNED NOT NULL,
            email VARCHAR(190) NOT NULL,
            delivery ENUM('smtp','archivo') NOT NULL,
            sent_at DATETIME NOT NULL,
            UNIQUE KEY uq_reminder (consultation_id, due_date, reminder_number),
            KEY idx_consultation (consultation_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

/**
 * Envía un correo. Sin SMTP configurado (MAIL_ENABLED en false) lo guarda como .eml
 * en storage/correos/ (modo prueba). Devuelve 'smtp' o 'archivo'; lanza excepción si falla.
 */
function send_app_mail(string $to, string $toName, string $subject, string $html, string $text): string {
    require_once dirname(__DIR__) . '/lib/PHPMailer/Exception.php';
    require_once dirname(__DIR__) . '/lib/PHPMailer/PHPMailer.php';
    require_once dirname(__DIR__) . '/lib/PHPMailer/SMTP.php';

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    $mail->CharSet = 'UTF-8';
    $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
    $mail->addAddress($to, $toName);
    $mail->Subject = $subject;
    $mail->isHTML(true);
    $mail->Body = $html;
    $mail->AltBody = $text;

    if (!MAIL_ENABLED) {
        $mail->preSend();
        $file = reminders_storage_dir() . '/correos/' . date('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.eml';
        file_put_contents($file, $mail->getSentMIMEMessage());
        return 'archivo';
    }

    $mail->isSMTP();
    $mail->Host = MAIL_HOST;
    $mail->Port = (int)MAIL_PORT;
    $mail->SMTPAuth = MAIL_USER !== '';
    $mail->Username = MAIL_USER;
    $mail->Password = MAIL_PASS;
    $mail->SMTPSecure = (int)MAIL_PORT === 465
        ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
        : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Timeout = 15;
    $mail->send();
    return 'smtp';
}

// ¿La mascota ya tiene turno? (uno pendiente, o uno pedido después del primer aviso)
function pet_has_appointment(int $petId, ?string $since): bool {
    $q = db()->prepare("
        SELECT 1 FROM appointments
        WHERE pet_id = ? AND status <> 'Cancelado'
          AND (appointment_at >= CURDATE() OR (? IS NOT NULL AND created_at >= ?))
        LIMIT 1
    ");
    $q->execute([$petId, $since, $since]);
    return (bool)$q->fetchColumn();
}

function vaccine_reminder_message(array $r, string $due, int $number): array {
    $pet    = format_name($r['pet_name']);
    $tutor  = format_name($r['full_name']);
    $clinic = format_name($r['clinic_name']);
    $dueTxt = date('d/m/Y', strtotime($due));
    $days   = (int)(new DateTime('today'))->diff(new DateTime($due))->format('%r%a');
    $when   = $days > 1 ? "vence en {$days} días ({$dueTxt})"
            : ($days === 1 ? "vence mañana ({$dueTxt})"
            : ($days === 0 ? "vence hoy ({$dueTxt})" : "venció el {$dueTxt}"));
    $link   = APP_URL . '/cliente/turnos.php';

    $subject = ($number > 1 ? 'Segundo aviso: ' : 'Recordatorio: ') . "la vacuna de {$pet} {$when}";
    $intro   = $number > 1
        ? "Te escribimos de nuevo porque todavía no vemos un turno para <strong>" . e($pet) . "</strong>."
        : "Queremos recordarte que se acerca la renovación de la vacuna de <strong>" . e($pet) . "</strong>.";

    $html = '<div style="font-family:Arial,Helvetica,sans-serif;background:#f7faf7;padding:24px;color:#183b35">'
        . '<div style="max-width:520px;margin:0 auto;background:#ffffff;border:1px solid #e1eae4;border-radius:16px;overflow:hidden">'
        . '<div style="background:#237765;color:#ffffff;padding:18px 24px;font-size:18px;font-weight:bold">🐾 ' . e(APP_NAME) . '</div>'
        . '<div style="padding:24px">'
        . '<p style="margin:0 0 12px">Hola ' . e($tutor) . ',</p>'
        . '<p style="margin:0 0 16px">' . $intro . '</p>'
        . '<div style="background:#eaf5ef;border-radius:12px;padding:14px 16px;margin:0 0 20px">'
        . '<strong>💉 La vacuna ' . e($when) . '.</strong><br>'
        . '<span style="color:#6e817b;font-size:14px">Aplicada en ' . e($clinic) . '</span></div>'
        . '<p style="margin:0 0 20px">Sacá un turno para mantenerla protegida:</p>'
        . '<p style="margin:0 0 24px"><a href="' . e($link) . '" style="background:#237765;color:#ffffff;text-decoration:none;padding:12px 22px;border-radius:10px;font-weight:bold;display:inline-block">Solicitar turno</a></p>'
        . '<p style="margin:0;color:#6e817b;font-size:12px">Si ya sacaste turno o la vacuna ya fue aplicada, ignorá este mensaje.</p>'
        . '</div></div></div>';

    $text = "Hola {$tutor},\n\n"
        . ($number > 1 ? "Te escribimos de nuevo porque todavía no vemos un turno para {$pet}.\n"
                       : "Se acerca la renovación de la vacuna de {$pet}.\n")
        . "La vacuna {$when}. Aplicada en {$clinic}.\n\n"
        . "Solicitá un turno: {$link}\n\n"
        . "Si ya sacaste turno o la vacuna ya fue aplicada, ignorá este mensaje.\n" . APP_NAME;

    return [$subject, $html, $text];
}

/**
 * Revisa todas las vacunas y envía los avisos que correspondan.
 * Devuelve un resumen por línea (para la consola).
 */
function run_vaccine_reminders(): array {
    vaccine_reminders_ensure_table();
    $log = [];
    $today = date('Y-m-d');

    // Última vacuna registrada de cada mascota (si después hubo otra, se considera renovada)
    $rows = db()->query("
        SELECT co.id, co.pet_id, co.vaccine_expiry, co.next_dose,
               p.name pet_name, c.id client_id, c.full_name, c.email, cl.name clinic_name
        FROM consultations co
        JOIN pets p     ON p.id = co.pet_id
        JOIN clients c  ON c.id = p.client_id
        JOIN clinics cl ON cl.id = co.clinic_id
        WHERE co.reason = 'Vacuna'
          AND (co.vaccine_expiry IS NOT NULL OR co.next_dose IS NOT NULL)
          AND NOT EXISTS (
              SELECT 1 FROM consultations newer
              WHERE newer.pet_id = co.pet_id AND newer.reason = 'Vacuna'
                AND (newer.created_at > co.created_at OR (newer.created_at = co.created_at AND newer.id > co.id))
          )
    ")->fetchAll();

    foreach ($rows as $r) {
        // Fecha de renovación: la más cercana que todavía no pasó.
        // Si ya pasaron todas, se usa la última (solo para completar un reenvío ya empezado).
        $dates = array_filter([$r['vaccine_expiry'], $r['next_dose']]);
        $upcoming = array_filter($dates, fn($d) => $d >= $today);
        $due = $upcoming ? min($upcoming) : max($dates);
        $daysLeft = (int)(new DateTime($today))->diff(new DateTime($due))->format('%r%a');
        $label = "Consulta #{$r['id']} · " . format_name($r['pet_name']) . " · renovación {$due}";

        $q = db()->prepare("SELECT COUNT(*) n, MIN(sent_at) first_sent, MAX(sent_at) last_sent FROM vaccine_reminders WHERE consultation_id = ? AND due_date = ?");
        $q->execute([$r['id'], $due]);
        $sent = $q->fetch();
        $n = (int)$sent['n'];

        if ($n >= VACCINE_REMINDER_MAX) {
            continue;
        }
        if ($n === 0 && !$upcoming) {
            continue;   // vencida sin avisos previos: el primer aviso es siempre antes de la fecha
        }
        if ($n === 0 && $daysLeft > VACCINE_REMINDER_DAYS_BEFORE) {
            continue;   // todavía falta más de 2 semanas
        }
        if ($n > 0 && strtotime($sent['last_sent']) > strtotime('-' . VACCINE_REMINDER_RESEND_DAYS . ' days')) {
            continue;   // el aviso anterior es de hace menos de una semana
        }
        if (pet_has_appointment((int)$r['pet_id'], $sent['first_sent'])) {
            $log[] = "$label → tiene turno, no se avisa";
            continue;
        }
        if (!filter_var($r['email'], FILTER_VALIDATE_EMAIL)) {
            $log[] = "$label → el cliente no tiene un correo válido";
            continue;
        }

        $number = $n + 1;
        [$subject, $html, $text] = vaccine_reminder_message($r, $due, $number);
        try {
            $delivery = send_app_mail($r['email'], format_name($r['full_name']), $subject, $html, $text);
            $q = db()->prepare("INSERT INTO vaccine_reminders(consultation_id, due_date, reminder_number, client_id, email, delivery, sent_at) VALUES(?, ?, ?, ?, ?, ?, NOW())");
            $q->execute([$r['id'], $due, $number, $r['client_id'], $r['email'], $delivery]);
            $log[] = "$label → aviso {$number} " . ($delivery === 'smtp' ? 'enviado' : 'guardado en storage/correos (modo prueba)') . " a {$r['email']}";
        } catch (Throwable $ex) {
            // No se registra: se vuelve a intentar en la próxima ejecución.
            error_log('Recordatorio de vacuna: ' . $ex->getMessage());
            $log[] = "$label → ERROR al enviar a {$r['email']}: " . $ex->getMessage();
        }
    }
    return $log;
}

/**
 * Corre los recordatorios como máximo una vez por día. Se llama al final de cada
 * página: primero entrega la página al navegador y después hace el trabajo.
 */
function maybe_run_daily_vaccine_reminders(): void {
    try {
        $lockFile = reminders_storage_dir() . '/recordatorios_vacunas.lock';
        $fh = fopen($lockFile, 'c+');
        if (!$fh || !flock($fh, LOCK_EX | LOCK_NB)) {
            return;   // otra visita ya los está corriendo
        }
        if (trim((string)stream_get_contents($fh)) === date('Y-m-d')) {
            flock($fh, LOCK_UN);
            fclose($fh);
            return;   // ya corrieron hoy
        }
        ftruncate($fh, 0);
        rewind($fh);
        fwrite($fh, date('Y-m-d'));
        fflush($fh);

        // Liberar la sesión: si no, el siguiente clic del usuario esperaría a que terminen los envíos.
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        ignore_user_abort(true);
        set_time_limit(120);
        while (ob_get_level() > 0) {
            ob_end_flush();
        }
        flush();

        run_vaccine_reminders();
        flock($fh, LOCK_UN);
        fclose($fh);
    } catch (Throwable $ex) {
        error_log('Recordatorios de vacunas: ' . $ex->getMessage());
    }
}
