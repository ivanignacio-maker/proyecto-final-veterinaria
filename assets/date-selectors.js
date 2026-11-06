(() => {
    const monthNames = [
        'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
        'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'
    ];
    const pad = (value) => String(value).padStart(2, '0');
    const localToday = () => {
        const now = new Date();
        return { year: now.getFullYear(), month: now.getMonth() + 1, day: now.getDate() };
    };

    function addOption(select, value, label, disabled = false) {
        const option = document.createElement('option');
        option.value = value;
        option.textContent = label;
        option.disabled = disabled;
        select.appendChild(option);
    }

    function makeSelect(className, label, id = '') {
        const select = document.createElement('select');
        select.className = className;
        select.setAttribute('aria-label', label);
        if (id) select.id = id;
        return select;
    }

    function splitDate(value) {
        const match = String(value || '').match(/^(\d{4})-(\d{2})-(\d{2})/);
        return match ? { year: match[1], month: String(Number(match[2])), day: String(Number(match[3])) } : null;
    }

    function enhanceDate(input) {
        if (input.dataset.datePickerEnhanced) return;
        const initialValue = input.value;
        const wasRequired = input.required;
        const oldId = input.id;
        const wrapper = document.createElement('div');
        wrapper.className = 'vc-date-picker';
        wrapper.setAttribute('role', 'group');
        wrapper.setAttribute('aria-label', 'Fecha');

        const day = makeSelect('vc-date-day', 'Día', oldId ? `${oldId}Day` : '');
        const month = makeSelect('vc-date-month', 'Mes');
        const year = makeSelect('vc-date-year', 'Año');
        [day, month, year].forEach((select) => {
            select.dataset.datePickerPart = 'true';
            select.dataset.datePickerRequired = String(wasRequired || input.hasAttribute('data-required'));
        });
        addOption(day, '', 'Día');
        addOption(month, '', 'Mes');
        addOption(year, '', 'Año');
        monthNames.forEach((name, index) => addOption(month, String(index + 1), name));

        const now = localToday();
        const min = splitDate(input.min);
        const max = splitDate(input.max);
        const fromYear = min ? Number(min.year) : now.year - 120;
        const toYear = max ? Number(max.year) : now.year + 10;
        for (let y = fromYear; y <= toYear; y += 1) addOption(year, String(y), String(y));

        [day, month, year].forEach((select) => {
            select.required = wasRequired;
            wrapper.appendChild(select);
        });
        input.insertAdjacentElement('afterend', wrapper);
        input.type = 'hidden';
        input.required = false;
        input.tabIndex = -1;
        input.setAttribute('aria-hidden', 'true');
        input.classList.add('vc-date-source');
        input.dataset.datePickerEnhanced = 'true';

        const refreshDays = () => {
            const previousDay = day.value;
            day.replaceChildren();
            addOption(day, '', 'Día');
            const monthNumber = Number(month.value);
            const yearNumber = Number(year.value);
            const dayCount = monthNumber && yearNumber ? new Date(yearNumber, monthNumber, 0).getDate() : 31;
            for (let d = 1; d <= dayCount; d += 1) addOption(day, String(d), String(d));
            if (previousDay && Number(previousDay) <= dayCount) day.value = previousDay;
        };

        const sync = () => {
            refreshDays();
            [day, month, year].forEach((select) => select.setCustomValidity(''));
            const values = [day.value, month.value, year.value];
            const selectedCount = values.filter(Boolean).length;
            if (selectedCount === 0) {
                input.value = '';
                if (wasRequired) day.setCustomValidity('Completá el día, el mes y el año.');
                return;
            }
            if (selectedCount !== 3) {
                input.value = '';
                day.setCustomValidity('Completá el día, el mes y el año.');
                return;
            }
            const iso = `${year.value}-${pad(month.value)}-${pad(day.value)}`;
            if (min && iso < `${min.year}-${pad(min.month)}-${pad(min.day)}`) {
                day.setCustomValidity('La fecha es anterior al mínimo permitido.');
            } else if (max && iso > `${max.year}-${pad(max.month)}-${pad(max.day)}`) {
                day.setCustomValidity('La fecha supera el máximo permitido.');
            }
            input.value = iso;
        };

        [day, month, year].forEach((select) => select.addEventListener('change', sync));
        const picker = { day, month, year, sync };
        input._vcDatePicker = picker;
        window.setDatePickerValue = window.setDatePickerValue || ((target, value) => {
            const field = typeof target === 'string' ? document.getElementById(target) : target;
            if (!field) return;
            if (!field._vcDatePicker) { field.value = value || ''; return; }
            const parts = splitDate(value);
            const current = field._vcDatePicker;
            current.year.value = parts?.year || '';
            current.month.value = parts?.month || '';
            current.sync();
            current.day.value = parts?.day || '';
            current.sync();
        });
        window.setDatePickerValue(input, initialValue);
    }

    function enhanceAppointment(input) {
        if (input.dataset.appointmentPickerEnhanced) return;
        const initialValue = input.value;
        const wasRequired = input.required;
        const oldId = input.id;
        const wrapper = document.createElement('div');
        wrapper.className = 'vc-appointment-picker';
        wrapper.setAttribute('role', 'group');
        wrapper.setAttribute('aria-label', 'Fecha y horario del turno');

        const dateParts = document.createElement('div');
        dateParts.className = 'vc-date-picker vc-appointment-date';
        const day = makeSelect('vc-date-day', 'Día', oldId ? `${oldId}Day` : 'appointmentDay');
        const month = makeSelect('vc-date-month', 'Mes');
        const year = makeSelect('vc-date-year', 'Año');
        const time = makeSelect('vc-appointment-time', 'Horario');
        [day, month, year, time].forEach((select) => {
            select.dataset.datePickerPart = 'true';
            select.dataset.datePickerRequired = String(wasRequired);
        });
        addOption(day, '', 'Día');
        addOption(month, '', 'Mes');
        addOption(time, '', 'Elegí un horario');
        monthNames.forEach((name, index) => addOption(month, String(index + 1), name));
        const now = localToday();
        for (let y = now.year; y <= now.year + 20; y += 1) addOption(year, String(y), String(y));
        year.value = String(now.year);
        month.value = String(now.month);
        // Horario de atención opcional por campo (lo arma includes/horarios.php):
        //   data-time-start / data-time-end: primer y último turno "HH:MM" (sin ellos, todo el día;
        //     si el último es menor que el primero, el horario cruza la medianoche).
        //   data-days: días ISO en que atiende, ej. "1,2,3,4,5" (vacío = todos).
        const toMinutes = (text, fallback) => {
            const m = /^(\d{2}):(\d{2})$/.exec(text || '');
            return m ? Number(m[1]) * 60 + Number(m[2]) : fallback;
        };
        const allowedDays = () => (input.dataset.days || '').split(',').map(Number).filter(Boolean);
        const buildTimes = () => {
            const previous = time.value;
            time.replaceChildren();
            addOption(time, '', 'Elegí un horario');
            const firstSlot = toMinutes(input.dataset.timeStart, 0);
            const lastSlot = toMinutes(input.dataset.timeEnd, 24 * 60 - 15);
            const end = lastSlot >= firstSlot ? lastSlot : lastSlot + 24 * 60;
            for (let minutes = firstSlot; minutes <= end; minutes += 15) {
                const m = minutes % (24 * 60);
                const value = `${pad(Math.floor(m / 60))}:${pad(m % 60)}`;
                addOption(time, value, value);
            }
            if (previous && Array.from(time.options).some((option) => option.value === previous)) {
                time.value = previous;
            }
        };
        time.replaceChildren();
        buildTimes();

        [day, month, year, time].forEach((select) => { select.required = wasRequired; });
        dateParts.append(day, month, year);
        wrapper.append(dateParts, time);
        input.insertAdjacentElement('afterend', wrapper);
        input.type = 'hidden';
        input.required = false;
        input.tabIndex = -1;
        input.setAttribute('aria-hidden', 'true');
        input.classList.add('vc-date-source');
        input.dataset.appointmentPickerEnhanced = 'true';

        const refreshMonths = () => {
            const previousMonth = month.value;
            const yearNumber = Number(year.value);
            const today = localToday();
            month.replaceChildren();
            addOption(month, '', 'Mes');
            monthNames.forEach((name, index) => {
                const monthNumber = index + 1;
                if (yearNumber === today.year && monthNumber < today.month) return;
                addOption(month, String(monthNumber), name);
            });
            if (previousMonth && Array.from(month.options).some((option) => option.value === previousMonth)) {
                month.value = previousMonth;
            }
        };

        const refreshDays = () => {
            const previousDay = day.value;
            day.replaceChildren();
            addOption(day, '', 'Día');
            const monthNumber = Number(month.value);
            const yearNumber = Number(year.value);
            if (!monthNumber || !yearNumber) return;

            const dayCount = new Date(yearNumber, monthNumber, 0).getDate();
            const today = localToday();
            const firstAvailableDay = yearNumber === today.year && monthNumber === today.month ? today.day : 1;
            const days = allowedDays();
            for (let d = firstAvailableDay; d <= dayCount; d += 1) {
                // Solo los días en que atiende la veterinaria (getDay: 0 = domingo -> ISO 7)
                const iso = new Date(yearNumber, monthNumber - 1, d).getDay() || 7;
                if (days.length && !days.includes(iso)) continue;
                addOption(day, String(d), String(d));
            }
            if (previousDay && Array.from(day.options).some((option) => option.value === previousDay)) {
                day.value = previousDay;
            }
        };

        const sync = () => {
            refreshMonths();
            refreshDays();
            [day, month, year, time].forEach((select) => select.setCustomValidity(''));
            const hasDate = day.value && month.value && year.value;
            const today = localToday();

            // Se deshabilitan los horarios ya pasados (si es hoy) y los ya reservados
            // (data-taken: "2026-11-09T10:00,..." = turnos confirmados de la veterinaria).
            const isToday = hasDate && Number(year.value) === today.year && Number(month.value) === today.month && Number(day.value) === today.day;
            const current = new Date();
            const currentMinutes = current.getHours() * 60 + current.getMinutes();
            const taken = new Set((input.dataset.taken || '').split(',').filter(Boolean));
            const datePrefix = hasDate ? `${year.value}-${pad(month.value)}-${pad(day.value)}T` : '';
            Array.from(time.options).forEach((option) => {
                if (!option.value) return;
                const [hours, mins] = option.value.split(':').map(Number);
                const isPast = isToday && hours * 60 + mins <= currentMinutes;
                const isTaken = hasDate && taken.has(datePrefix + option.value);
                option.disabled = isPast || isTaken;
                option.textContent = isTaken ? `${option.value} · reservado` : option.value;
            });
            if (time.selectedOptions[0]?.disabled) time.value = '';
            if (wasRequired && !time.value && hasDate) time.setCustomValidity('Elegí un horario futuro.');

            if (!hasDate || !time.value || day.validity.customError) {
                input.value = '';
                if (wasRequired && !hasDate) day.setCustomValidity('Completá el día, el mes y el año.');
                return;
            }
            input.value = `${year.value}-${pad(month.value)}-${pad(day.value)}T${time.value}`;
        };

        [day, month, time].forEach((select) => {
            select.addEventListener('change', sync);
            select.addEventListener('focus', sync);
        });
        year.addEventListener('change', () => {
            month.value = '';
            day.value = '';
            sync();
        });
        year.addEventListener('focus', sync);
        const form = input.form;
        if (form) form.addEventListener('submit', sync, true);
        const picker = { day, month, year, time, sync, buildTimes };
        input._vcAppointmentPicker = picker;
        const match = String(initialValue || '').match(/^(\d{4})-(\d{2})-(\d{2})T(\d{2}:\d{2})$/);
        if (match) {
            year.value = match[1];
            refreshMonths();
            month.value = String(Number(match[2]));
            refreshDays();
            day.value = String(Number(match[3]));
            time.value = match[4];
        }
        sync();
    }

    function initialize(root = document) {
        root.querySelectorAll('input[type="date"]').forEach(enhanceDate);
        root.querySelectorAll('input[type="datetime-local"]').forEach(enhanceAppointment);
    }

    window.syncAppointmentPicker = (target) => {
        const input = typeof target === 'string' ? document.getElementById(target) : target;
        input?._vcAppointmentPicker?.sync();
    };

    // Cambia los días y horarios permitidos de un selector de turno (ej. al elegir otra veterinaria).
    // schedule = { days: "1,2,3", start: "08:00", end: "21:45", taken: "2026-11-09T10:00,..." };
    // vacíos = sin restricción.
    window.setAppointmentSchedule = (target, schedule) => {
        const input = typeof target === 'string' ? document.getElementById(target) : target;
        if (!input) return;
        input.dataset.days = schedule?.days || '';
        input.dataset.taken = schedule?.taken || '';
        input.dataset.timeStart = schedule?.start || '';
        input.dataset.timeEnd = schedule?.end || '';
        const picker = input._vcAppointmentPicker;
        if (!picker) return;
        picker.buildTimes();
        picker.sync();
    };

    initialize();
    document.addEventListener('DOMContentLoaded', () => initialize());
})();
