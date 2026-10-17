<?php

require_once __DIR__ . '/config/config.php';

// 1. Manejo de la cookie "Recuérdame" y eliminación del token en la base de datos
if (!empty($_COOKIE['vet_remember'])) {
    $parts = explode(':', (string)$_COOKIE['vet_remember'], 2);

    // Si la cookie tiene el formato correcto (selector:validador)
    if (count($parts) === 2) {
        try {
            $selector = $parts[0];
            $stmt = db()->prepare("DELETE FROM remember_tokens WHERE selector = ?");
            $stmt->execute([$selector]);
        } catch (Throwable $e) {
            // Silenciado intencionalmente (puedes agregar error_log($e->getMessage()) aquí si lo necesitas)
        }
    }

    // Destruir la cookie en el navegador
    setcookie('vet_remember', '', [
        'expires'  => time() - 3600,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}

// 2. Limpiar las variables de sesión específicas del usuario
unset(
    $_SESSION['vet_user_id'],
    $_SESSION['clinic_id'],
    $_SESSION['vet_username'],
    $_SESSION['clinic_name']
);

// 3. Regenerar el ID de la sesión para prevenir ataques de fijación de sesión
session_regenerate_id(true);

// 4. Redirigir al usuario a la página de login
header('Location: ' . url('login.php'));
exit;