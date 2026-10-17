<?php
require_once __DIR__ . '/../config/config.php';

// 1. Limpiar la variable de sesión específica del cliente
unset($_SESSION['client_id']);

// 2. Regenerar el ID de la sesión por seguridad (previene fijación de sesión)
session_regenerate_id(true);

// 3. Redirigir al portal de inicio de sesión del cliente
header('Location: ' . url('cliente/login.php'));
exit;