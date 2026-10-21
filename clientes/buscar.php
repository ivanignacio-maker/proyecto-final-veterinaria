<?php
require_once __DIR__ . '/../config/config.php';
require_vet();

$results = [];
$dni = trim($_GET['dni'] ?? '');

// Si se ingresó un DNI, buscar el cliente en la base de datos
if ($dni !== '') {
    $q = db()->prepare("
        SELECT c.*, cc.last_seen_at 
        FROM clients c 
        LEFT JOIN clinic_clients cc ON cc.client_id = c.id AND cc.clinic_id = ? 
        WHERE c.dni = ?
    ");
    $q->execute([$_SESSION['clinic_id'], $dni]);
    $results = $q->fetchAll();
}

$pageTitle = 'Buscar Cliente | VetCompartido';
require __DIR__ . '/../includes/header.php';
?>

<div class="panel">
    <h1>Buscar cliente</h1>
    <p class="muted">Ingresá el número de DNI para localizar al tutor y el historial médico unificado de sus mascotas.</p>
    
    <form method="get" style="margin-top: 18px;">
        <label for="dni_input">DNI del tutor / responsable</label>
        <div style="display: flex; gap: 12px; max-width: 520px; align-items: center;">
            <input id="dni_input" name="dni" value="<?= e($dni) ?>" required inputmode="numeric" placeholder="Ej. 44649955" style="margin-top: 0; font-size: 15px;">
            <button type="submit" style="flex-shrink: 0;">Buscar</button>
        </div>
        
        <div class="actions" style="margin-top: 20px;">
            <a class="btn secondary" href="<?= url('clientes/nuevo.php') ?>">
                <span>+ Registrar nuevo cliente</span>
            </a>
            <a class="btn secondary" href="<?= url('dashboard.php') ?>">
                <span>← Volver al panel</span>
            </a>
        </div>
    </form>
</div>

<?php if ($dni !== ''): ?>
    <div class="card">
        <h2>Resultados de búsqueda</h2>
        
        <?php if (!$results): ?>
            <div style="padding: 16px; background: #fffbf0; border: 1px solid #f6e0b5; border-radius: 12px; color: #8f6208;">
                <p style="margin: 0 0 10px;">⚠️ No se encontró ningún cliente registrado con el DNI <strong><?= e($dni) ?></strong>.</p>
                <a class="btn primary" href="<?= url('clientes/nuevo.php') ?>">Dar de alta este cliente</a>
            </div>
        <?php else: ?>
            <?php foreach ($results as $c): ?>
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 18px 20px; background: var(--paper); border: 1px solid var(--line); border-radius: 14px; margin-bottom: 12px; flex-wrap: wrap; gap: 14px;">
                    <div>
                        <div style="font-size: 17px; font-weight: 800; font-family: 'Manrope', sans-serif; color: var(--ink);">
                            👤 <?= e_name($c['full_name']) ?>
                        </div>
                        <div class="muted" style="font-size: 13.5px; margin-top: 4px;">
                            DNI: <strong><?= e($c['dni']) ?></strong> · Correo: <?= e($c['email']) ?> · Teléfono: <?= e($c['phone']) ?>
                        </div>
                    </div>
                    <a class="btn" href="<?= url('clientes/ver.php?id=' . (int)$c['id']) ?>">
                        <span>Abrir ficha y mascotas</span>
                        <span>→</span>
                    </a>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>