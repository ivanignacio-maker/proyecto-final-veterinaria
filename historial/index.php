<?php
require_once __DIR__ . '/../config/config.php';
require_vet();

const HISTORIAL_RECIENTES = 10;
const HISTORIAL_MAX_RESULTADOS = 100;

// Se busca de una sola forma a la vez: por DNI del tutor, por ID de mascota o por fecha
$modes = ['dni' => 'DNI del tutor', 'mascota' => 'ID de mascota', 'fecha' => 'Fecha'];
$por = isset($modes[$_GET['por'] ?? '']) ? $_GET['por'] : 'dni';
$f = [
    'dni'     => preg_replace('/\D+/', '', $_GET['dni'] ?? ''),
    'mascota' => trim($_GET['mascota'] ?? ''),
    'desde'   => trim($_GET['desde'] ?? ''),
    'hasta'   => trim($_GET['hasta'] ?? ''),
];
// Filtro por estado (lo usa el acceso "Consultas en proceso" del panel)
$estado = in_array($_GET['estado'] ?? '', ['En proceso', 'Dado de Alta'], true) ? $_GET['estado'] : '';

$searching = isset($_GET['por']) || $estado !== '';
$errors = [];
$searchLabel = $estado === 'En proceso' ? 'consultas en proceso' : ($estado !== '' ? 'consultas dadas de alta' : '');
$where = ['co.clinic_id = ?'];
$params = [$_SESSION['clinic_id']];

$parseDate = static function (string $value): ?DateTime {
    $d = DateTime::createFromFormat('!Y-m-d', $value);
    return $d && $d->format('Y-m-d') === $value ? $d : null;
};

if (isset($_GET['por'])) {
    if ($por === 'dni') {
        if ($f['dni'] === '') {
            $errors[] = 'Ingresá el DNI del tutor.';
        } else {
            // Titular o co-titular de la mascota; coincide desde el comienzo del DNI
            $where[] = "(c.dni LIKE ? OR EXISTS (
                SELECT 1 FROM pet_co_owners pco JOIN clients cc ON cc.id = pco.client_id
                WHERE pco.pet_id = p.id AND cc.dni LIKE ?))";
            $params[] = $f['dni'] . '%';
            $params[] = $f['dni'] . '%';
            $searchLabel = 'DNI ' . $f['dni'];
        }
    } elseif ($por === 'mascota') {
        if (!ctype_digit($f['mascota'])) {
            $errors[] = 'Ingresá el ID de la mascota (solo números).';
        } else {
            $where[] = 'p.id = ?';
            $params[] = (int)$f['mascota'];
            $searchLabel = 'mascota #' . (int)$f['mascota'];
        }
    } else {
        $desde = $parseDate($f['desde']);
        $hasta = $f['hasta'] !== '' ? $parseDate($f['hasta']) : $desde;
        if (!$desde) {
            $errors[] = $f['desde'] === '' ? 'Elegí una fecha.' : 'La fecha no es válida.';
        } elseif (!$hasta) {
            $errors[] = 'La fecha "hasta" no es válida.';
        } elseif ($desde > $hasta) {
            $errors[] = 'La fecha "hasta" no puede ser anterior a la primera fecha.';
        } else {
            $where[] = 'co.created_at >= ?';
            $params[] = $desde->format('Y-m-d 00:00:00');
            $where[] = 'co.created_at < ?';
            $params[] = (clone $hasta)->modify('+1 day')->format('Y-m-d 00:00:00');
            $searchLabel = $desde == $hasta
                ? $desde->format('d/m/Y')
                : 'del ' . $desde->format('d/m/Y') . ' al ' . $hasta->format('d/m/Y');
        }
    }
}
if ($estado !== '') {
    $where[] = 'co.status = ?';
    $params[] = $estado;
}

$baseSql = "
    FROM consultations co
    JOIN pets p ON p.id = co.pet_id
    JOIN clients c ON c.id = p.client_id
";
$selectSql = "
    SELECT co.id consultation_id, co.created_at, co.reason, co.status,
           p.id pet_id, p.name pet_name, c.full_name, c.dni,
           (SELECT GROUP_CONCAT(CONCAT(cc.full_name, '\\t', cc.dni) ORDER BY pco.id SEPARATOR '\\n')
            FROM pet_co_owners pco JOIN clients cc ON cc.id = pco.client_id
            WHERE pco.pet_id = p.id) co_owners
";

$results = [];
$total = 0;
if ($searching && !$errors) {
    $whereSql = ' WHERE ' . implode(' AND ', $where);

    $q = db()->prepare('SELECT COUNT(*) ' . $baseSql . $whereSql);
    $q->execute($params);
    $total = (int)$q->fetchColumn();

    $q = db()->prepare($selectSql . $baseSql . $whereSql . ' ORDER BY co.created_at DESC, co.id DESC LIMIT ' . HISTORIAL_MAX_RESULTADOS);
    $q->execute($params);
    $results = $q->fetchAll();
}

// Últimos registros de la veterinaria
$q = db()->prepare($selectSql . $baseSql . ' WHERE co.clinic_id = ? ORDER BY co.created_at DESC, co.id DESC LIMIT ' . HISTORIAL_RECIENTES);
$q->execute([$_SESSION['clinic_id']]);
$recent = $q->fetchAll();

function historial_table(array $rows): void
{
    ?>
    <div style="overflow-x: auto;">
        <table class="historial-table">
            <thead>
                <tr>
                    <th>Fecha de atención</th>
                    <th>Tutor / Cliente</th>
                    <th>Paciente (Mascota)</th>
                    <th>Motivo de consulta</th>
                    <th>Estado</th>
                    <th>Ficha médica</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $r): ?>
                    <?php $statusClass = $r['status'] === 'Dado de Alta' ? 'badge-success' : 'badge-warning'; ?>
                    <tr>
                        <td>
                            <strong><?= date('d/m/Y', strtotime($r['created_at'])) ?></strong><br>
                            <span class="muted"><?= date('H:i', strtotime($r['created_at'])) ?> hs · Consulta #<?= (int)$r['consultation_id'] ?></span>
                        </td>
                        <td>
                            <strong class="name-titlecase"><?= e_name($r['full_name']) ?></strong><br>
                            <span class="muted">DNI <?= e($r['dni']) ?></span>
                            <?php foreach ($r['co_owners'] ? explode("\n", $r['co_owners']) : [] as $coOwner): ?>
                                <?php [$coName, $coDni] = array_pad(explode("\t", $coOwner, 2), 2, ''); ?>
                                <div class="historial-coowner">
                                    <i class="fa-solid fa-user-group" aria-hidden="true"></i>
                                    Co-titular: <span class="name-titlecase"><?= e_name($coName) ?></span> · DNI <?= e($coDni) ?>
                                </div>
                            <?php endforeach; ?>
                        </td>
                        <td>
                            <span class="pill name-titlecase"><i class="fa-solid fa-paw" style="color: var(--green); margin-right: 4px;"></i> <?= e_name($r['pet_name']) ?></span>
                            <span class="muted" style="font-size: 11px; margin-left: 4px;">#<?= (int)$r['pet_id'] ?></span>
                        </td>
                        <td>
                            <strong><?= e($r['reason']) ?></strong>
                        </td>
                        <td>
                            <span class="badge <?= $statusClass ?>"><?= e($r['status']) ?></span>
                        </td>
                        <td>
                            <a href="<?= url('consultas/ver.php?id=' . (int)$r['consultation_id']) ?>" class="btn secondary btn-sm">
                                <span>Ver consulta</span>
                                <i class="fa-solid fa-arrow-right" style="margin-left: 4px;"></i>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}

$pageTitle = 'Historial clínico | ' . APP_NAME;
require __DIR__ . '/../includes/header.php';
?>

<div class="panel">
    <h1><i class="fa-solid fa-book-medical" style="color: var(--green); margin-right: 8px;"></i>Historial clínico</h1>
    <p class="muted">Elegí cómo querés buscar y completá un solo dato.</p>

    <form method="get" class="historial-search" id="historialSearch" role="search" aria-label="Buscar en el historial">
        <fieldset class="historial-modes">
            <legend>Buscar por</legend>
            <?php foreach (['dni' => 'fa-id-card', 'mascota' => 'fa-paw', 'fecha' => 'fa-calendar-days'] as $mode => $icon): ?>
                <label class="historial-mode">
                    <input type="radio" name="por" value="<?= $mode ?>" <?= $por === $mode ? 'checked' : '' ?>>
                    <span><i class="fa-solid <?= $icon ?>"></i> <?= e($modes[$mode]) ?></span>
                </label>
            <?php endforeach; ?>
        </fieldset>

        <div class="historial-search-row">
            <div class="historial-field" data-por="dni">
                <label>
                    DNI del tutor
                    <input name="dni" value="<?= e($f['dni']) ?>" inputmode="numeric" maxlength="12" placeholder="Ej. 44649955" data-required>
                </label>
                <p class="historial-hint">Podés poner el DNI completo o solo los primeros números.</p>
            </div>
            <div class="historial-field" data-por="mascota">
                <label>
                    ID de la mascota
                    <input name="mascota" value="<?= e($f['mascota']) ?>" inputmode="numeric" maxlength="12" placeholder="Ej. 2" data-required>
                </label>
                <p class="historial-hint">Es el número que aparece al lado del nombre de la mascota (por ejemplo, Apolo #2).</p>
            </div>
            <div class="historial-field" data-por="fecha">
                <div class="historial-dates">
                    <label>
                        Fecha
                        <input type="date" name="desde" value="<?= e($f['desde']) ?>" max="<?= date('Y-m-d') ?>" data-required>
                    </label>
                    <label>
                        Hasta <span class="historial-optional">(opcional)</span>
                        <input type="date" name="hasta" value="<?= e($f['hasta']) ?>" max="<?= date('Y-m-d') ?>">
                    </label>
                </div>
                <p class="historial-hint">Para buscar un solo día, dejá "Hasta" vacío.</p>
            </div>
            <div class="historial-filter-actions">
                <button type="submit"><i class="fa-solid fa-magnifying-glass" style="margin-right: 6px;"></i>Buscar</button>
                <?php if ($searching): ?>
                    <a class="btn secondary" href="<?= url('historial/index.php') ?>">Limpiar</a>
                <?php endif; ?>
            </div>
        </div>
    </form>
</div>

<script>
// Mostrar solo el campo de la forma de búsqueda elegida
(function () {
    var form = document.getElementById('historialSearch');
    function sync(focus) {
        var por = form.querySelector('input[name="por"]:checked').value;
        form.querySelectorAll('[data-por]').forEach(function (group) {
            var on = group.getAttribute('data-por') === por;
            group.hidden = !on;
            group.querySelectorAll('input').forEach(function (input) {
                input.disabled = !on;
                input.required = on && input.hasAttribute('data-required');
            });
        });
        if (focus) {
            var first = form.querySelector('[data-por="' + por + '"] input');
            if (first) first.focus();
        }
    }
    form.querySelectorAll('input[name="por"]').forEach(function (radio) {
        radio.addEventListener('change', function () { sync(true); });
    });
    sync(false);
})();
</script>

<?php if ($searching): ?>
    <div class="card" id="resultados">
        <h2><i class="fa-solid fa-list-check" style="color: var(--green); margin-right: 8px;"></i>Resultados<?= $searchLabel !== '' && !$errors ? ': ' . e($searchLabel) : '' ?></h2>
        <?php if ($errors): ?>
            <div class="notice error" role="alert">
                <span class="notice-icon"><i class="fa-solid fa-triangle-exclamation"></i></span>
                <span><?= implode('<br>', array_map('e', $errors)) ?></span>
            </div>
        <?php elseif (!$results): ?>
            <p style="padding: 12px 0; color: var(--muted);">No hay consultas que coincidan con la búsqueda.</p>
        <?php else: ?>
            <p class="muted">
                <?= $total === 1 ? '1 consulta encontrada.' : $total . ' consultas encontradas.' ?>
                <?php if ($total > HISTORIAL_MAX_RESULTADOS): ?>
                    Se muestran las <?= HISTORIAL_MAX_RESULTADOS ?> más recientes; afiná los filtros para ver el resto.
                <?php endif; ?>
            </p>
            <?php historial_table($results); ?>
        <?php endif; ?>
    </div>
<?php endif; ?>

<div class="card">
    <h2><i class="fa-solid fa-clock-rotate-left" style="color: var(--green); margin-right: 8px;"></i>Recientes</h2>
    <p class="muted">Últimas <?= HISTORIAL_RECIENTES ?> consultas registradas en tu veterinaria.</p>
    <?php if (!$recent): ?>
        <p style="padding: 12px 0; color: var(--muted);">Todavía no hay consultas registradas por esta veterinaria.</p>
    <?php else: ?>
        <?php historial_table($recent); ?>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
