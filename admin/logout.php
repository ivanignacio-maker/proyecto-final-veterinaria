<?php
require_once __DIR__ . '/../config/config.php';

// 1. Limpiar la variable de sesión específica del administrador
unset($_SESSION['admin_id']);

// 2. Limpiar cookie de recordarme
clear_remember_cookie();

// 3. Regenerar el ID de la sesión por seguridad
session_regenerate_id(true);

// 4. Redirigir al inicio de sesión unificado
header('Location: ' . url('login.php'));
exit;