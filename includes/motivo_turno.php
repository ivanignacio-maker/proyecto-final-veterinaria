<?php
// Selector de motivo de turno: motivos habituales + "Otro" con texto libre.
// Antes de incluirlo, la página define $motivoSel (opción elegida) y $motivoOtro (texto de "Otro").
$motivoSel  = $motivoSel  ?? '';
$motivoOtro = $motivoOtro ?? '';
?>
<label for="turnoMotivo">Motivo de la consulta</label>
<select id="turnoMotivo" name="reason_option" required>
    <option value="">Seleccioná un motivo</option>
    <?php foreach (APPOINTMENT_REASONS as $m): ?>
        <option value="<?= e($m) ?>" <?= $motivoSel === $m ? 'selected' : '' ?>><?= e($m) ?></option>
    <?php endforeach; ?>
    <option value="<?= e(APPOINTMENT_REASON_OTHER) ?>" <?= $motivoSel === APPOINTMENT_REASON_OTHER ? 'selected' : '' ?>>Otro (especificar)</option>
</select>
<input id="turnoMotivoOtro" name="reason_other" value="<?= e($motivoOtro) ?>" maxlength="<?= APPOINTMENT_REASON_MAX ?>"
       placeholder="Escribí el motivo de la consulta" aria-label="Otro motivo" style="margin-top: 8px;"
       <?= $motivoSel === APPOINTMENT_REASON_OTHER ? 'required' : 'hidden' ?>>
<script>
(function () {
    // "Otro" muestra el campo de texto y lo vuelve obligatorio.
    const sel  = document.getElementById('turnoMotivo');
    const otro = document.getElementById('turnoMotivoOtro');
    sel.addEventListener('change', function () {
        const isOther = sel.value === <?= json_encode(APPOINTMENT_REASON_OTHER) ?>;
        otro.hidden = !isOther;
        otro.required = isOther;
        if (isOther) { otro.focus(); }
    });
})();
</script>
