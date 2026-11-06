<?php
/**
 * admin/convertir_imagenes.php
 *
 * Herramienta de una sola vez: convierte a WebP las fotos de perfil (tutores y mascotas)
 * que se subieron ANTES de que el sistema convirtiera automáticamente (.jpg / .png).
 * Las fotos nuevas ya se guardan en WebP solas.
 *
 * Entrá desde el navegador como administrador. Primero muestra cuántas hay; recién al
 * confirmar convierte, actualiza la base y borra el archivo viejo.
 */
require_once __DIR__ . '/../config/config.php';
require_admin();
csrf_check();

$targets = [
    ['clients', 'avatar_path'],
    ['pets',    'photo_path'],
];

// Fotos que todavía no son .webp
$pending = [];
foreach ($targets as [$table, $col]) {
    $rows = db()->query("SELECT id, `$col` AS path FROM `$table` WHERE `$col` IS NOT NULL AND `$col` <> '' AND `$col` NOT LIKE '%.webp'")->fetchAll();
    foreach ($rows as $r) {
        $pending[] = ['table' => $table, 'col' => $col, 'id' => (int)$r['id'], 'path' => (string)$r['path']];
    }
}

$report = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $root = realpath(ensure_upload_dir());
    foreach ($pending as $item) {
        $source = $root ? realpath($root . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $item['path'])) : false;
        // Solo se tocan archivos que estén dentro de la carpeta de uploads
        if ($source === false || !is_file($source) || strpos($source, $root . DIRECTORY_SEPARATOR) !== 0) {
            $report[] = "#{$item['id']} ({$item['table']}): archivo no encontrado, se omitió.";
            continue;
        }
        $newRel = preg_replace('/\.[^.\/\\\\]+$/', '', $item['path']) . '.webp';
        $dest = $root . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $newRel);
        if (!convert_image_to_webp($source, $dest, 85)) {
            $report[] = "#{$item['id']} ({$item['table']}): no se pudo convertir {$item['path']}.";
            continue;
        }
        $up = db()->prepare("UPDATE `{$item['table']}` SET `{$item['col']}` = ? WHERE id = ?");
        $up->execute([$newRel, $item['id']]);
        if ($dest !== $source) { @unlink($source); }
        $report[] = "#{$item['id']} ({$item['table']}): {$item['path']} → $newRel";
    }
    $pending = [];
}

$pageTitle = 'Convertir fotos a WebP';
require __DIR__ . '/../includes/header.php';
?>
<div class="panel">
    <h1><i class="fa-solid fa-image" style="color: var(--green); margin-right: 8px;"></i>Convertir fotos antiguas a WebP</h1>
    <p class="muted">Las fotos nuevas ya se convierten solas. Esta herramienta pasa a WebP las que se subieron antes.</p>

    <?php if ($report): ?>
        <div class="notice"><strong>Listo.</strong> Resultado:</div>
        <ul>
            <?php foreach ($report as $line): ?><li><?= e($line) ?></li><?php endforeach; ?>
        </ul>
    <?php elseif (!$pending): ?>
        <div class="notice">No hay fotos pendientes: todas están en WebP.</div>
    <?php else: ?>
        <p>Hay <strong><?= count($pending) ?></strong> foto<?= count($pending) === 1 ? '' : 's' ?> para convertir.</p>
        <form method="post" onsubmit="return confirm('Se convertirán las fotos y se borrarán los archivos originales. ¿Continuar?');">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
            <button class="btn primary" type="submit">Convertir ahora</button>
        </form>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
