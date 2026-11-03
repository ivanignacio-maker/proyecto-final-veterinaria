<?php
/**
 * includes/roles.php
 *
 * Roles dentro de una veterinaria:
 *   - jefe          → acceso a todo (historiales, turnos, stock).
 *   - doctor        → historiales médicos y turnos.
 *   - recepcionista → solo turnos (reservar / confirmar / cancelar).
 *
 * Este archivo lo carga config/config.php, así que está disponible en todas las páginas.
 * Además crea solo, la primera vez, las columnas nuevas que necesita la base de datos.
 */

const VET_ROLES = [
    'jefe'          => 'Jefe/a de la clínica',
    'doctor'        => 'Doctor/a',
    'recepcionista' => 'Recepcionista',
];

/**
 * Agrega a la base las columnas nuevas (si todavía no existen):
 *   vet_users.role            → rol de cada usuario (los existentes quedan como "jefe")
 *   appointments.vet_user_id  → profesional elegido para el turno (puede quedar vacío)
 */
function ensure_vet_roles_schema(): void {
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        $pdo = db();
        if (!$pdo->query("SHOW COLUMNS FROM vet_users LIKE 'role'")->fetch()) {
            $pdo->exec("ALTER TABLE vet_users ADD COLUMN role ENUM('jefe','doctor','recepcionista') NOT NULL DEFAULT 'jefe' AFTER email");
        }
        if (!$pdo->query("SHOW COLUMNS FROM appointments LIKE 'vet_user_id'")->fetch()) {
            $pdo->exec("ALTER TABLE appointments ADD COLUMN vet_user_id INT NULL DEFAULT NULL AFTER pet_id");
        }
    } catch (Throwable $e) {
        error_log('Schema ensure roles: ' . $e->getMessage());
    }
}
ensure_vet_roles_schema();

/** Rol del usuario veterinario logueado, leído de la base en cada pedido (así un cambio del admin rige al instante). */
function vet_current_role(): string {
    static $cache = [];
    $uid = (int)($_SESSION['vet_user_id'] ?? 0);
    if ($uid <= 0) return '';
    if (!isset($cache[$uid])) {
        try {
            $q = db()->prepare('SELECT role FROM vet_users WHERE id = ?');
            $q->execute([$uid]);
            $role = $q->fetchColumn();
            $cache[$uid] = is_string($role) && isset(VET_ROLES[$role]) ? $role : '';
        } catch (Throwable $e) {
            $cache[$uid] = '';
        }
    }
    return $cache[$uid];
}

function vet_role_label(?string $role): string {
    return VET_ROLES[$role ?? ''] ?? 'Sin rol';
}

/** Pantalla de inicio de cada rol (la recepcionista no tiene panel: va directo a los turnos). */
function vet_home_url(?string $role = null): string {
    $role = $role ?? vet_current_role();
    return $role === 'recepcionista' ? url('turnos/index.php') : url('dashboard.php');
}

/**
 * Qué roles pueden abrir cada página. Está todo en este único lugar.
 * Toda página que no figure acá queda para jefe y doctor (la opción más segura).
 */
function vet_allowed_roles_for_script(string $scriptName): array {
    $path = str_replace('\\', '/', $scriptName);
    $base = rtrim((string)BASE_URL, '/');
    $rel  = ($base !== '' && strpos($path, $base . '/') === 0) ? substr($path, strlen($base) + 1) : ltrim($path, '/');

    if ($rel === 'turnos/index.php' || $rel === 'turnos/api_mascotas_cliente.php' || $rel === 'ayuda.php') {
        return ['jefe', 'doctor', 'recepcionista'];
    }
    if (strpos($rel, 'stock/') === 0) {
        return ['jefe'];
    }
    return ['jefe', 'doctor'];
}

/** Lo llama require_vet(): si el rol no alcanza, redirige a su pantalla de inicio. */
function vet_enforce_role(): void {
    $role = vet_current_role();
    if ($role === '') {
        header('Location: ' . url('login.php?tipo=veterinaria'));
        exit;
    }
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    if (in_array($role, vet_allowed_roles_for_script($script), true)) {
        return;
    }
    // Sin mensaje cuando solo está pasando por el panel de inicio (ej. la recepcionista al ingresar).
    if (basename($script) !== 'dashboard.php') {
        flash('Tu rol (' . vet_role_label($role) . ') no tiene acceso a esa sección.');
    }
    header('Location: ' . vet_home_url($role));
    exit;
}

/** Profesionales de una clínica que pueden atender (jefe y doctores activos). */
function vet_professionals(int $clinicId): array {
    try {
        $q = db()->prepare("
            SELECT id, username, role
            FROM vet_users
            WHERE clinic_id = ? AND active = 1 AND role IN ('jefe', 'doctor')
            ORDER BY username
        ");
        $q->execute([$clinicId]);
        return $q->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}
