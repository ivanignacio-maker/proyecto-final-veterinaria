(function () {
    function inspectPassword(value) {
        var normalized = value.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
        var common = ['password', 'contrasena', 'qwerty', 'admin', 'welcome', 'letmein', 'abc123', '123456', '654321', 'abcdef'];
        var hasCommon = common.some(function (part) { return normalized.indexOf(part) !== -1; }) || /^(.)\1{5,}$/.test(value);
        return {
            length: value.length >= 8,
            case: /[a-z]/.test(value) && /[A-Z]/.test(value),
            number: /[0-9]/.test(value),
            symbol: /[^a-zA-Z0-9]/.test(value),
            uncommon: value.length > 0 && !hasCommon
        };
    }

    function initialize() {
        document.querySelectorAll('[data-password-strength]').forEach(function (widget) {
            var input = widget.querySelector('[data-strength-input]');
            var segments = widget.querySelectorAll('.password-strength-segment');
            var label = widget.querySelector('[data-strength-label]');
            if (!input || !label) return;

            function update() {
                var value = input.value;
                var checks = inspectPassword(value);
                var passed = Object.keys(checks).filter(function (key) { return checks[key]; }).length;
                var strength = !value ? 0 : passed <= 2 ? 1 : passed <= 3 ? 2 : passed <= 4 ? 3 : 4;
                var names = ['', 'Débil', 'Regular', 'Buena', 'Fuerte'];
                label.textContent = value ? names[strength] : 'La seguridad aparecerá mientras escribís';
                widget.setAttribute('data-strength', String(strength));
                segments.forEach(function (segment, index) {
                    segment.classList.toggle('filled', index < strength);
                });
                Object.keys(checks).forEach(function (key) {
                    var item = widget.querySelector('[data-password-rule="' + key + '"]');
                    if (!item) return;
                    item.classList.toggle('met', checks[key]);
                    var mark = item.querySelector('.password-rule-mark');
                    if (mark) mark.textContent = checks[key] ? '✓' : '○';
                });
            }

            input.addEventListener('input', update);
            update();
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialize);
    } else {
        initialize();
    }
})();
