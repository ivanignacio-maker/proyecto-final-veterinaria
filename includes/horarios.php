<?php
declare(strict_types=1);

/**
 * includes/horarios.php
 *
 * Días y horarios de atención de cada veterinaria (columnas available_days, opening_time y
 * closing_time de `clinics`), para que los turnos solo se den cuando la veterinaria atiende.
 *
 * Los turnos van cada 15 minutos: el primero a partir de la apertura y el último antes del cierre.
 * Si la veterinaria cierra "antes" de abrir (ej. 20:00 a 02:00) se entiende que cruza la medianoche.
 */

const SCHEDULE_SLOT_MINUTES = 15;

/** Nombres de los días (en plural) según su número ISO (1 = lunes ... 7 = domingo). */
function schedule_day_names(): array {
    return [1 => 'lunes', 2 => 'martes', 3 => 'miércoles', 4 => 'jueves', 5 => 'viernes', 6 => 'sábados', 7 => 'domingos'];
}

/**
 * Interpreta el texto de "Días de atención" y devuelve los días ISO (1-7) en que atiende,
 * o null si no hay días cargados o no se entienden (en ese caso no se restringe por día).
 * Acepta las opciones del formulario ("Lunes a Viernes", "Fines de semana (Sábados y Domingos)",
 * "Guardia 24 hs"...) y textos libres como "lun mar", "Lunes, Miércoles y Viernes" o "mar a sab".
 */
function schedule_parse_days(?string $text): ?array {
    $t = mb_strtolower(trim((string)$text), 'UTF-8');
    if ($t === '') {
        return null;
    }
    $t = strtr($t, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u']);

    if (preg_match('/todos|24\s*h|guardia/', $t)) {
        return [1, 2, 3, 4, 5, 6, 7];
    }
    if (strpos($t, 'fin') !== false && strpos($t, 'semana') !== false) {
        return [6, 7];
    }

    $map = ['lun' => 1, 'mar' => 2, 'mie' => 3, 'jue' => 4, 'vie' => 5, 'sab' => 6, 'dom' => 7];
    // Cada palabra que empiece como un día ("lunes", "lun", "sabados"...) o el conector "a" (rango)
    preg_match_all('/\b(lun|mar|mie|jue|vie|sab|dom)[a-z]*|\ba\b/', $t, $m);
    $days = [];
    $pendingRange = false;
    $last = null;
    foreach ($m[0] as $i => $word) {
        if ($word === 'a') {
            $pendingRange = $last !== null;
            continue;
        }
        $day = $map[$m[1][$i]];
        if ($pendingRange) {
            // "lunes a viernes": completa del día anterior al actual (también "viernes a lunes")
            for ($d = $last; $d !== $day; $d = $d % 7 + 1) {
                $days[$d] = true;
            }
            $pendingRange = false;
        }
        $days[$day] = true;
        $last = $day;
    }
    if (!$days) {
        return null;
    }
    $days = array_keys($days);
    sort($days);
    return $days;
}

/** "HH:MM" o "HH:MM:SS" a minutos desde la medianoche; null si está vacío o no es válido. */
function schedule_minutes(?string $time): ?int {
    if (!preg_match('/^(\d{1,2}):(\d{2})/', (string)$time, $m) || (int)$m[1] > 23 || (int)$m[2] > 59) {
        return null;
    }
    return (int)$m[1] * 60 + (int)$m[2];
}

function schedule_hhmm(int $minutes): string {
    $minutes = (($minutes % 1440) + 1440) % 1440;
    return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
}

/**
 * Horario de turnos de una veterinaria a partir de su fila de `clinics`.
 * Devuelve:
 *   days       => días ISO permitidos, o null si no hay restricción de días
 *   first_slot => primer turno "HH:MM" (o null si no hay horario cargado)
 *   last_slot  => último turno "HH:MM" (si es menor que first_slot, el horario cruza la medianoche)
 *   label      => texto para mostrar, ej. "Lunes a Viernes, de 08:00 a 22:00"
 */
function clinic_schedule(array $clinic): array {
    $daysText = trim((string)($clinic['available_days'] ?? ''));
    $days = schedule_parse_days($daysText);
    $open = schedule_minutes($clinic['opening_time'] ?? null);
    $close = schedule_minutes($clinic['closing_time'] ?? null);
    $allDay = (bool)preg_match('/24\s*h/i', $daysText);

    $first = $last = null;
    $hoursLabel = '';
    if ($allDay || ($open !== null && $close !== null && $open === $close)) {
        $first = 0;
        $last = 1440 - SCHEDULE_SLOT_MINUTES;
        $hoursLabel = $allDay ? '' : 'las 24 hs';   // "Guardia 24 hs" ya lo dice
    } elseif ($open !== null && $close !== null) {
        $step = SCHEDULE_SLOT_MINUTES;
        $first = (int)ceil($open / $step) * $step;                        // primer turno desde la apertura
        $last = (int)(ceil($close / $step) * $step) - $step;              // último turno antes del cierre
        $hoursLabel = 'de ' . schedule_hhmm($open) . ' a ' . schedule_hhmm($close);
    }

    $label = trim(($daysText !== '' && $days !== null ? $daysText : '') . ($hoursLabel !== '' ? ', ' . $hoursLabel : ''), ', ');

    return [
        'days'       => $days,
        'first_slot' => $first !== null ? schedule_hhmm($first) : null,
        'last_slot'  => $last !== null ? schedule_hhmm($last) : null,
        'label'      => $label,
    ];
}

/** ¿El horario "HH:MM" cae entre el primer y el último turno (contemplando la medianoche)? */
function schedule_time_allowed(array $schedule, string $hhmm): bool {
    if ($schedule['first_slot'] === null) {
        return true;
    }
    $t = schedule_minutes($hhmm);
    $first = schedule_minutes($schedule['first_slot']);
    $last = schedule_minutes($schedule['last_slot']);
    return $first <= $last ? ($t >= $first && $t <= $last) : ($t >= $first || $t <= $last);
}

/** Mensaje de error si el turno cae fuera de los días u horarios de la veterinaria; null si está bien. */
function clinic_schedule_error(array $schedule, DateTimeInterface $when): ?string {
    $suffix = $schedule['label'] !== '' ? ' La veterinaria atiende ' . $schedule['label'] . '.' : '';
    if ($schedule['days'] !== null && !in_array((int)$when->format('N'), $schedule['days'], true)) {
        return 'La veterinaria no atiende los ' . schedule_day_names()[(int)$when->format('N')] . '.' . $suffix;
    }
    if (!schedule_time_allowed($schedule, $when->format('H:i'))) {
        return 'Ese horario está fuera del horario de atención.' . $suffix;
    }
    return null;
}

/** Atributos data-* para el selector de turno (assets/date-selectors.js). */
function schedule_data_attrs(array $schedule, string $fallbackStart = '', string $fallbackEnd = ''): string {
    return 'data-days="' . e(implode(',', $schedule['days'] ?? [])) . '"'
        . ' data-time-start="' . e($schedule['first_slot'] ?? $fallbackStart) . '"'
        . ' data-time-end="' . e($schedule['last_slot'] ?? $fallbackEnd) . '"';
}

// ============================================================================
// Turnos ocupados: un turno Confirmado bloquea ese horario en la veterinaria,
// así nadie más puede pedirlo, asignarlo ni confirmarlo.
// ============================================================================

/** Estados que ocupan el horario. */
const APPOINTMENT_BLOCKING_STATUSES = ['Confirmado', 'Atendido'];

/**
 * Bloquea la fila de la veterinaria hasta el final de la transacción, para que dos personas
 * no tomen el mismo horario al mismo tiempo. Llamar dentro de una transacción.
 */
function lock_clinic_for_booking(int $clinicId): void {
    $q = db()->prepare("SELECT id FROM clinics WHERE id = ? FOR UPDATE");
    $q->execute([$clinicId]);
}

/** ¿Ya hay un turno confirmado en esa veterinaria a esa fecha y hora ("Y-m-d H:i:s")? */
function appointment_slot_taken(int $clinicId, string $dateTime, int $excludeId = 0): bool {
    $in = implode(',', array_fill(0, count(APPOINTMENT_BLOCKING_STATUSES), '?'));
    $q = db()->prepare("
        SELECT 1 FROM appointments
        WHERE clinic_id = ? AND appointment_at = ? AND id <> ? AND status IN ($in)
        LIMIT 1
    ");
    $q->execute(array_merge([$clinicId, $dateTime, $excludeId], APPOINTMENT_BLOCKING_STATUSES));
    return (bool)$q->fetchColumn();
}

/** Horarios futuros ya confirmados de cada veterinaria: [clinic_id => ["2026-11-09T10:00", ...]]. */
function clinics_taken_slots(array $clinicIds): array {
    $clinicIds = array_values(array_unique(array_map('intval', $clinicIds)));
    if (!$clinicIds) {
        return [];
    }
    $inClinics = implode(',', array_fill(0, count($clinicIds), '?'));
    $inStatus = implode(',', array_fill(0, count(APPOINTMENT_BLOCKING_STATUSES), '?'));
    $q = db()->prepare("
        SELECT clinic_id, DATE_FORMAT(appointment_at, '%Y-%m-%dT%H:%i') AS slot
        FROM appointments
        WHERE clinic_id IN ($inClinics) AND status IN ($inStatus) AND appointment_at > NOW()
        ORDER BY appointment_at
    ");
    $q->execute(array_merge($clinicIds, APPOINTMENT_BLOCKING_STATUSES));
    $taken = [];
    foreach ($q->fetchAll() as $r) {
        $taken[(int)$r['clinic_id']][] = $r['slot'];
    }
    return $taken;
}

const APPOINTMENT_SLOT_TAKEN_MSG = 'Ese horario ya está reservado. Elegí otro.';
