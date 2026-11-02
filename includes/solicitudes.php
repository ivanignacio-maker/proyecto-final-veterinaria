<?php
/**
 * includes/solicitudes.php
 *
 * Todo lo compartido por tres funciones nuevas de VetCare:
 *   1. Solicitudes de alta + pago del plan (contratar.php, Mercado Pago, admin/solicitudes.php)
 *   2. Mensajes de soporte enviados desde "Ayuda" (ayuda.php, admin/mensajes.php)
 *   3. Catálogo de planes y precios (el servidor es quien decide cuánto se cobra)
 *
 * Las tablas se crean solas la primera vez (igual que el resto de las ensure_*).
 */

// Casilla que recibe los avisos (solicitudes nuevas, pagos aprobados, mensajes de soporte).
if (!defined('SUPPORT_EMAIL')) define('SUPPORT_EMAIL', 'soporte@vetcompartido.com');

/* ------------------------------------------------------------------ */
/* 1. Catálogo de planes                                               */
/* ------------------------------------------------------------------ */

/**
 * Planes y precios en pesos argentinos. Es la ÚNICA fuente del importe que se cobra:
 * el navegador solo envía "mensual" o "anual", nunca el precio.
 * (Los mismos valores que muestra planes.php: $34.000/mes, o $27.200/mes con facturación anual.)
 */
function plan_catalog(): array {
    return [
        'clinicas' => [
            'name'    => 'Plan Clínicas Pro',
            'billing' => [
                'mensual' => ['label' => 'Mensual',                    'amount' => 34000,       'detail' => '$34.000 por mes'],
                'anual'   => ['label' => 'Anual (20% de descuento)',   'amount' => 27200 * 12,  'detail' => '$27.200 por mes, un solo pago de $326.400'],
            ],
        ],
    ];
}

function plan_money(float $amount): string {
    return '$' . number_format($amount, 0, ',', '.');
}

/* ------------------------------------------------------------------ */
/* 2. Tablas                                                           */
/* ------------------------------------------------------------------ */

function ensure_solicitudes_tables(): void {
    try {
        $pdo = db();

        // CREATE TABLE confirma cualquier transacción abierta, así que solo se ejecuta si la tabla falta.
        if (!$pdo->query("SHOW TABLES LIKE 'plan_requests'")->fetchColumn()) {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS plan_requests (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    token CHAR(32) NOT NULL,                       -- clave secreta para volver a ver el estado del pago
                    plan_code VARCHAR(30) NOT NULL,
                    billing ENUM('mensual','anual') NOT NULL,
                    amount DECIMAL(12,2) NOT NULL,
                    -- Datos de la clínica
                    clinic_name VARCHAR(150) NOT NULL,
                    cuit VARCHAR(20) NOT NULL,
                    clinic_email VARCHAR(190) NOT NULL,
                    phone VARCHAR(50) NULL,
                    available_days VARCHAR(100) NULL,
                    opening_time VARCHAR(5) NULL,
                    closing_time VARCHAR(5) NULL,
                    province VARCHAR(60) NOT NULL,
                    city VARCHAR(120) NOT NULL,
                    street VARCHAR(180) NOT NULL,
                    -- Datos del responsable (será el primer usuario 'jefe')
                    responsible_name VARCHAR(120) NOT NULL,
                    responsible_email VARCHAR(190) NOT NULL,
                    responsible_phone VARCHAR(50) NULL,
                    staff_count TINYINT UNSIGNED NULL,
                    notes TEXT NULL,
                    -- Pago
                    payment_status ENUM('pendiente','en_proceso','aprobado','rechazado','cancelado') NOT NULL DEFAULT 'pendiente',
                    mp_preference_id VARCHAR(80) NULL,
                    mp_payment_id VARCHAR(40) NULL,
                    mp_status_detail VARCHAR(80) NULL,
                    paid_at DATETIME NULL,
                    -- Gestión del administrador
                    status ENUM('nueva','registrada','descartada') NOT NULL DEFAULT 'nueva',
                    clinic_id INT NULL,
                    admin_note VARCHAR(255) NULL,
                    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_token (token),
                    KEY idx_payment (payment_status),
                    KEY idx_status (status)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }

        if (!$pdo->query("SHOW TABLES LIKE 'support_messages'")->fetchColumn()) {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS support_messages (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    sender_type ENUM('tutor','veterinaria') NOT NULL,
                    client_id INT NULL,
                    vet_user_id INT NULL,
                    clinic_id INT NULL,
                    name VARCHAR(120) NOT NULL,
                    email VARCHAR(190) NOT NULL,
                    phone VARCHAR(50) NULL,
                    subject VARCHAR(150) NOT NULL,
                    message TEXT NOT NULL,
                    status ENUM('nuevo','resuelto') NOT NULL DEFAULT 'nuevo',
                    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                    KEY idx_status (status),
                    KEY idx_sender (sender_type)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }
    } catch (Throwable $e) {
        error_log('Schema ensure solicitudes/soporte: ' . $e->getMessage());
    }
}
ensure_solicitudes_tables();

/* ------------------------------------------------------------------ */
/* 3. Etiquetas para mostrar                                           */
/* ------------------------------------------------------------------ */

function plan_payment_label(string $status): string {
    return [
        'pendiente'  => 'Pago pendiente',
        'en_proceso' => 'Pago en proceso',
        'aprobado'   => 'Pago aprobado',
        'rechazado'  => 'Pago rechazado',
        'cancelado'  => 'Pago cancelado',
    ][$status] ?? $status;
}

/** Aviso interno por correo. Nunca interrumpe la operación si el correo falla. */
function notify_support_team(string $subject, string $htmlBody, string $textBody): void {
    try {
        require_once __DIR__ . '/recordatorios_vacunas.php';
        send_app_mail(SUPPORT_EMAIL, 'Equipo ' . APP_NAME, $subject, $htmlBody, $textBody);
    } catch (Throwable $e) {
        error_log('notify_support_team: ' . $e->getMessage());
    }
}
