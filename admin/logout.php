<?php
require_once __DIR__ . '/../config/config.php';

// 1. Limpiar la variable de sesión específica del administrador
unset($_SESSION['admin_id']);

// 2. Regenerar el ID de la sesión por seguridad (previene ataques de fijación de sesión)
session_regenerate_id(true);

// 3. Redirigir al portal de inicio de sesión de administración
header('Location: ' . url('admin/login.php'));
exit;