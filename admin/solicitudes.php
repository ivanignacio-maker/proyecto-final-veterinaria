<?php
/**
 * admin/solicitudes.php — Solicitudes de alta que llegan desde contratar.php.
 *
 * Cada fila es una veterinaria que completó el formulario de contratación (y, si Mercado Pago
 * está configurado, intentó pagar). El administrador ve el estado del pago y, cuando está
 * todo en orden, toca "Registrar veterinaria": se abre admin/veterinarias.php con el formulario
 * de alta ya completo con los datos de la solicitud.
 */
require_once __DIR__ . '/../config/config.php';
require_admin();

$pdo = db();

// ---- Acciones (POST con CSRF) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int)($_POST['id'] ?? 0);
    $action = (string)($_POST['action'] ?? '');

    $q = $pdo->prepare('SELECT id, status, payment_status FROM plan_requests WHERE id = ?');
    $q->execute([$id]);
    $row = $q->fetch();

    if (!$row) {
        flash('La solicitud no existe.');
    } elseif ($action === 'descartar' && $row['status'] === 'nueva') {
        $pdo->prepare("UPDATE plan_requests SET status = 'descartada' WHERE id = ?")->execute([$id]);
        flash('Solicitud #' . $id . ' descartada.');
    } elseif ($action === 'reabrir' && $row['status'] === 'descartada') {
        $pdo->prepare("UPDATE plan_requests SET status = 'nueva' WHERE id = ?")->execute([$id]);
        flash('Solicitud #' . $id . ' reabierta.');
    } elseif ($action === 'marcar_pagada' && $row['payment_status'] !== 'aprobado') {
        // Para pagos que se cobran por fuera de Mercado Pago (transferencia, efectivo...).
        $note = mb_substr(trim((string)($_POST['note'] ?? '')), 0, 255);
        $pdo->prepare("UPDATE plan_requests SET payment_status = 'aprobado', paid_at = NOW(), admin_note = ? WHERE id = ?")
            ->execute([$note !== '' ? $note : 'Pago confirmado manualmente por el administrador', $id]);
        audit_log('plan_request', $id, 'payment_marked_manually', ['note' => $note]);
        flash('Solicitud #' . $id . ' marcada como pagada.');
    }
    header('Location: ' . url('admin/solicitudes.php?' . http_build_query(array_filter(['estado' => $_GET['estado'] ?? '']))));
    exit;
}

$filter = (string)($_GET['estado'] ?? 'nueva');
if (!in_array($filter, ['nueva', 'registrada', 'descartada', 'todas'], true)) {
    $filter = 'nueva';
}

$counts = ['nueva' => 0, 'registrada' => 0, 'descartada' => 0];
foreach ($pdo->query('SELECT status, COUNT(*) n FROM plan_requests GROUP BY status')->fetchAll() as $c) {
    $counts[$c['status']] = (int)$c['n'];
}

if ($filter === 'todas') {
    $rows = $pdo->query('SELECT pr.*, c.name AS registered_clinic FROM plan_requests pr LEFT JOIN clinics c ON c.id = pr.clinic_id ORDER BY pr.id DESC LIMIT 200')->fetchAll();
} else {
    $q = $pdo->prepare('SELECT pr.*, c.name AS registered_clinic FROM plan_requests pr LEFT JOIN clinics c ON c.id = pr.clinic_id WHERE pr.status = ? ORDER BY pr.id DESC LIMIT 200');
    $q->execute([$filter]);
    $rows = $q->fetchAll();
}

$payBadge = [
    'aprobado' => 'badge-success', 'en_proceso' => 'badge-warning', 'pendiente' => 'badge-warning',
    'rechazado' => 'badge-danger', 'cancelado' => 'badge-danger',
];

$pageTitle = 'Solicitudes de alta';
require __DIR__ . '/../includes/header.php';
?>

<div class="panel">
    <h1><i class="fa-solid fa-file-signature" style="color: var(--green); margin-right: 8px;"></i>Solicitudes de alta</h1>
    <p class="muted">Veterinarias que completaron el formulario de contratación. Registralas cuando el pago esté aprobado.</p>

    <div style="display: flex; gap: 8px; flex-wrap: wrap; margin-top: 12px;">
        <?php foreach (['nueva' => 'Nuevas', 'registrada' => 'Registradas', 'descartada' => 'Descartadas'] as $k => $label): ?>
            <a class="btn <?= $filter === $k ? 'primary' : 'secondary' ?> btn-sm" href="<?= url('admin/solicitudes.php?estado=' . $k) ?>"><?= e($label) ?> (<?= $counts[$k] ?>)</a>
        <?php endforeach; ?>
        <a class="btn <?= $filter === 'todas' ? 'primary' : 'secondary' ?> btn-sm" href="<?= url('admin/solicitudes.php?estado=todas') ?>">Todas</a>
    </div>
</div>

<?php if (!$rows): ?>
    <div class="card" style="margin-top: 22px; text-align: center; padding: 40px 20px;">
        <span style="font-size: 40px; color: var(--green); display: block; margin-bottom: 12px;"><i class="fa-solid fa-inbox"></i></span>
        <h3>No hay solicitudes en esta vista</h3>
    </div>
<?php endif; ?>

<?php foreach ($rows as $r): ?>
    <?php $paid = $r['payment_status'] === 'aprobado'; ?>
    <div class="card" style="margin-top: 18px; padding: 22px;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; flex-wrap: wrap;">
            <div>
                <h2 style="margin: 0 0 4px; font-size: 20px;">#<?= (int)$r['id'] ?> · <?= e($r['clinic_name']) ?></h2>
                <span class="muted" style="font-size: 13px;"><?= date('d/m/Y H:i', strtotime($r['created_at'])) ?> hs · <?= $r['billing'] === 'anual' ? 'Plan anual' : 'Plan mensual' ?> · <strong><?= e(plan_money((float)$r['amount'])) ?></strong></span>
            </div>
            <div style="display: flex; gap: 6px; flex-wrap: wrap;">
                <span class="badge <?= $payBadge[$r['payment_status']] ?? 'badge-warning' ?>"><?= e(plan_payment_label($r['payment_status'])) ?></span>
                <?php if ($r['status'] === 'registrada'): ?><span class="badge badge-success">Registrada</span><?php endif; ?>
                <?php if ($r['status'] === 'descartada'): ?><span class="badge badge-danger">Descartada</span><?php endif; ?>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 14px; margin-top: 16px; font-size: 14px;">
            <div style="background: var(--paper); border: 1px solid var(--line); border-radius: 12px; padding: 14px;">
                <strong>Veterinaria</strong><br>
                CUIT: <?= e($r['cuit']) ?><br>
                Correo: <?= e($r['clinic_email']) ?><br>
                Tel.: <?= e($r['phone'] ?: '—') ?><br>
                Dirección: <?= e($r['street']) ?>, <?= e($r['city']) ?>, <?= e($r['province']) ?><br>
                Atención: <?= e($r['available_days'] ?: '—') ?><?= $r['opening_time'] ? ' · ' . e($r['opening_time']) . ' a ' . e($r['closing_time']) : '' ?><br>
                Usuarios previstos: <?= $r['staff_count'] ? (int)$r['staff_count'] : '—' ?>
            </div>
            <div style="background: var(--paper); border: 1px solid var(--line); border-radius: 12px; padding: 14px;">
                <strong>Responsable (futuro Jefe)</strong><br>
                <?= e($r['responsible_name']) ?><br>
                <?= e($r['responsible_email']) ?><br>
                Tel.: <?= e($r['responsible_phone'] ?: '—') ?>
                <?php if ($r['mp_payment_id']): ?><br><br><strong>Mercado Pago</strong><br>N.º de operación: <?= e($r['mp_payment_id']) ?><?= $r['paid_at'] ? '<br>Pagado: ' . date('d/m/Y H:i', strtotime($r['paid_at'])) : '' ?><?php endif; ?>
                <?php if ($r['admin_note']): ?><br><br><em><?= e($r['admin_note']) ?></em><?php endif; ?>
            </div>
        </div>
        <?php if ($r['notes']): ?>
            <p style="margin: 12px 0 0; font-size: 14px;"><strong>Comentarios:</strong> <?= nl2br(e($r['notes'])) ?></p>
        <?php endif; ?>

        <div style="display: flex; gap: 8px; flex-wrap: wrap; margin-top: 16px; align-items: center;">
            <?php if ($r['status'] === 'nueva'): ?>
                <?php if ($paid): ?>
                    <a class="btn primary btn-sm" href="<?= url('admin/veterinarias.php?solicitud=' . (int)$r['id']) ?>">
                        <i class="fa-solid fa-building-circle-check" style="margin-right: 6px;"></i>Registrar veterinaria
                    </a>
                <?php else: ?>
                    <a class="btn secondary btn-sm" href="<?= url('admin/veterinarias.php?solicitud=' . (int)$r['id']) ?>" data-ask="Esta solicitud todavía no tiene el pago aprobado. ¿Querés registrarla igual?">
                        Registrar igual (sin pago)
                    </a>
                    <form method="post" style="display: flex; gap: 6px; margin: 0;">
                        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                        <input type="hidden" name="action" value="marcar_pagada">
                        <input type="text" name="note" maxlength="255" placeholder="Ej. Transferencia 03/11" style="margin: 0; font-size: 13px; padding: 6px 10px; min-width: 190px;">
                        <button class="btn secondary btn-sm" type="submit" data-ask="¿Confirmás que recibiste el pago por fuera de Mercado Pago?">Marcar como pagada</button>
                    </form>
                <?php endif; ?>
                <form method="post" style="margin: 0;">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                    <input type="hidden" name="action" value="descartar">
                    <button class="btn secondary btn-sm" type="submit" data-ask="¿Descartar esta solicitud?">Descartar</button>
                </form>
            <?php elseif ($r['status'] === 'registrada' && $r['clinic_id']): ?>
                <a class="btn secondary btn-sm" href="<?= url('admin/editar_veterinaria.php?id=' . (int)$r['clinic_id']) ?>">
                    Ver veterinaria registrada<?= $r['registered_clinic'] ? ': ' . e($r['registered_clinic']) : '' ?>
                </a>
            <?php elseif ($r['status'] === 'descartada'): ?>
                <form method="post" style="margin: 0;">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                    <input type="hidden" name="action" value="reabrir">
                    <button class="btn secondary btn-sm" type="submit">Reabrir</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
<?php endforeach; ?>

<script>
// Pide confirmación antes de las acciones marcadas con data-ask (enlaces y botones de formulario).
document.addEventListener('click', function (ev) {
    const el = ev.target.closest('[data-ask]');
    if (el && !window.confirm(el.getAttribute('data-ask'))) {
        ev.preventDefault();
    }
});
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
