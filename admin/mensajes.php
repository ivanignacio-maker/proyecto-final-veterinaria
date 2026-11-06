<?php
/**
 * admin/mensajes.php — Mensajes enviados desde Ayuda y desde el contacto público.
 * El administrador los lee, responde por correo (botón "Responder") y los marca como resueltos.
 */
require_once __DIR__ . '/../config/config.php';
require_admin();

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int)($_POST['id'] ?? 0);
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'resolver') {
        $pdo->prepare("UPDATE support_messages SET status = 'resuelto' WHERE id = ?")->execute([$id]);
        flash('Mensaje #' . $id . ' marcado como resuelto.');
    } elseif ($action === 'reabrir') {
        $pdo->prepare("UPDATE support_messages SET status = 'nuevo' WHERE id = ?")->execute([$id]);
        flash('Mensaje #' . $id . ' reabierto.');
    }
    header('Location: ' . url('admin/mensajes.php?' . http_build_query(array_filter(['estado' => $_GET['estado'] ?? '']))));
    exit;
}

$filter = (string)($_GET['estado'] ?? 'nuevo');
if (!in_array($filter, ['nuevo', 'resuelto', 'todos'], true)) {
    $filter = 'nuevo';
}

$counts = ['nuevo' => 0, 'resuelto' => 0];
foreach ($pdo->query('SELECT status, COUNT(*) n FROM support_messages GROUP BY status')->fetchAll() as $c) {
    $counts[$c['status']] = (int)$c['n'];
}

$sql = 'SELECT m.*, c.name AS clinic_name FROM support_messages m LEFT JOIN clinics c ON c.id = m.clinic_id';
if ($filter === 'todos') {
    $rows = $pdo->query($sql . ' ORDER BY m.id DESC LIMIT 200')->fetchAll();
} else {
    $q = $pdo->prepare($sql . ' WHERE m.status = ? ORDER BY m.id DESC LIMIT 200');
    $q->execute([$filter]);
    $rows = $q->fetchAll();
}

$pageTitle = 'Mensajes de soporte';
require __DIR__ . '/../includes/header.php';
?>

<div class="panel">
    <h1><i class="fa-solid fa-headset" style="color: var(--green); margin-right: 8px;"></i>Mensajes de soporte</h1>
    <p class="muted">Consultas enviadas desde la sección de ayuda y el formulario público de contacto.</p>
    <div style="display: flex; gap: 8px; flex-wrap: wrap; margin-top: 12px;">
        <a class="btn <?= $filter === 'nuevo' ? 'primary' : 'secondary' ?> btn-sm" href="<?= url('admin/mensajes.php?estado=nuevo') ?>">Nuevos (<?= $counts['nuevo'] ?>)</a>
        <a class="btn <?= $filter === 'resuelto' ? 'primary' : 'secondary' ?> btn-sm" href="<?= url('admin/mensajes.php?estado=resuelto') ?>">Resueltos (<?= $counts['resuelto'] ?>)</a>
        <a class="btn <?= $filter === 'todos' ? 'primary' : 'secondary' ?> btn-sm" href="<?= url('admin/mensajes.php?estado=todos') ?>">Todos</a>
    </div>
</div>

<?php if (!$rows): ?>
    <div class="card" style="margin-top: 22px; text-align: center; padding: 40px 20px;">
        <span style="font-size: 40px; color: var(--green); display: block; margin-bottom: 12px;"><i class="fa-solid fa-inbox"></i></span>
        <h3>No hay mensajes en esta vista</h3>
    </div>
<?php endif; ?>

<?php foreach ($rows as $m): ?>
    <div class="card" style="margin-top: 18px; padding: 22px;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; flex-wrap: wrap;">
            <div>
                <h2 style="margin: 0 0 4px; font-size: 18px;">#<?= (int)$m['id'] ?> · <?= e($m['subject']) ?></h2>
                <span class="muted" style="font-size: 13px;">
                    <?= date('d/m/Y H:i', strtotime($m['created_at'])) ?> hs ·
                    <strong><?= e($m['name']) ?></strong>
                    (<?= $m['sender_type'] === 'publico' ? 'contacto público' : ($m['sender_type'] === 'tutor' ? 'tutor' : 'clínica' . ($m['clinic_name'] ? ': ' . e($m['clinic_name']) : '')) ?>)
                </span>
            </div>
            <span class="badge <?= $m['status'] === 'nuevo' ? 'badge-warning' : 'badge-success' ?>"><?= $m['status'] === 'nuevo' ? 'Nuevo' : 'Resuelto' ?></span>
        </div>

        <p style="margin: 14px 0; font-size: 14px; line-height: 1.6; background: var(--paper); border: 1px solid var(--line); border-radius: 12px; padding: 14px;"><?= nl2br(e($m['message'])) ?></p>

        <div style="display: flex; gap: 14px; flex-wrap: wrap; margin: 0 0 14px; font-size: 13px;">
            <span><strong>Correo:</strong> <?= e($m['email']) ?></span>
            <?php if ($m['phone']): ?><span><strong>Teléfono:</strong> <?= e($m['phone']) ?></span><?php endif; ?>
        </div>

        <div style="display: flex; gap: 8px; flex-wrap: wrap; align-items: center; font-size: 13px;">
            <form method="post" style="margin: 0 0 0 auto;">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
                <input type="hidden" name="action" value="<?= $m['status'] === 'nuevo' ? 'resolver' : 'reabrir' ?>">
                <button type="submit" class="btn secondary btn-sm"><?= $m['status'] === 'nuevo' ? 'Marcar como resuelto' : 'Reabrir' ?></button>
            </form>
        </div>
    </div>
<?php endforeach; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
