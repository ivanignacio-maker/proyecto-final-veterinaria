<?php
// "Recientes" ahora es parte del historial clínico (historial/index.php).
require_once __DIR__ . '/../config/config.php';
header('Location: ' . url('historial/index.php'));
exit;
