<?php
require_once __DIR__ . '/../config/config.php';
require_client();

$pageTitle = 'Veterinarias cercanas | Portal Tutores';
require __DIR__ . '/../includes/header.php';
?>

<!-- Leaflet: librería gratuita de mapas. Los mapas los dibuja OpenStreetMap.
     Los atributos integrity verifican que el archivo del CDN no fue alterado. -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">

<style>
    .vc-controls { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; margin-top: 18px; }
    .vc-search { display: flex; gap: 8px; flex: 1; min-width: 260px; }
    .vc-search input { flex: 1; border: 1px solid var(--line); border-radius: 10px; padding: 10px 14px; font-size: 14px; }
    .vc-layout { display: grid; grid-template-columns: 1fr 380px; gap: 20px; margin-top: 20px; }
    #vcMap { height: 520px; border-radius: 16px; border: 1px solid var(--line); z-index: 0; }
    .vc-list { display: flex; flex-direction: column; gap: 12px; max-height: 520px; overflow-y: auto; }
    .vc-card { background: #fff; border: 1px solid var(--line); border-radius: 14px; padding: 14px 16px; cursor: pointer; transition: box-shadow .15s, border-color .15s; }
    .vc-card:hover { border-color: var(--green); box-shadow: 0 4px 14px rgba(24, 59, 53, 0.08); }
    .vc-card h3 { margin: 0 0 4px; font-size: 16px; color: var(--ink); }
    .vc-card .vc-dist { font-size: 12px; font-weight: 700; color: var(--green); background: var(--mint); padding: 2px 10px; border-radius: 12px; white-space: nowrap; }
    .vc-card .vc-line { font-size: 13px; color: var(--muted); margin-top: 6px; }
    .vc-card a { font-size: 13px; font-weight: 700; color: var(--green); }
    .vc-status { margin-top: 16px; font-size: 14px; }
    .vc-status.error { color: #991b1b; }
    .vc-pin { color: var(--green); font-size: 32px; text-shadow: 0 2px 4px rgba(0, 0, 0, 0.35); line-height: 1; }
    @media (max-width: 900px) {
        .vc-layout { grid-template-columns: 1fr; }
        #vcMap { height: 380px; }
        .vc-list { max-height: none; }
    }
</style>

<div class="panel">
    <h1><i class="fa-solid fa-location-dot" style="color: var(--green); margin-right: 8px;"></i>Veterinarias cercanas</h1>
    <p class="muted">Encontrá las veterinarias registradas en <?= APP_NAME ?> más cerca tuyo. Tu ubicación se usa solo para esta búsqueda y no se guarda.</p>

    <div class="vc-controls">
        <button type="button" class="btn primary" id="vcLocate">
            <i class="fa-solid fa-crosshairs" style="margin-right: 6px;"></i> Usar mi ubicación
        </button>
        <span class="muted" style="font-size: 13px;">o</span>
        <form class="vc-search" id="vcSearchForm">
            <input type="text" id="vcQuery" maxlength="200" placeholder="Escribí tu dirección o localidad (Ej. Ramos Mejía, Buenos Aires)">
            <button type="submit" class="btn secondary">Buscar</button>
        </form>
    </div>

    <!-- aria-live: los lectores de pantalla anuncian los mensajes de estado -->
    <div class="vc-status muted" id="vcStatus" aria-live="polite">Compartí tu ubicación o escribí una dirección para ver las veterinarias más cercanas.</div>
</div>

<div class="vc-layout">
    <div id="vcMap"></div>
    <div class="vc-list" id="vcList"></div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script>
(function () {
    // Datos que le pasa PHP al JavaScript (json_encode los escapa de forma segura).
    const API_NEAR = <?= json_encode(url('cliente/api_veterinarias_cercanas.php')) ?>;
    const API_GEO  = <?= json_encode(url('cliente/api_geocodificar.php')) ?>;
    const CSRF     = <?= json_encode(csrf_token()) ?>;

    const statusEl = document.getElementById('vcStatus');
    const listEl   = document.getElementById('vcList');

    // Mapa inicial centrado en Buenos Aires; se reubica cuando hay resultados.
    const map = L.map('vcMap').setView([-34.6037, -58.3816], 11);
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a>'
    }).addTo(map);

    // Pin propio con el ícono de Font Awesome (ya cargado en el sitio), así no
    // dependemos de las imágenes por defecto de Leaflet.
    const pinIcon = L.divIcon({
        className: '',
        html: '<div class="vc-pin"><i class="fa-solid fa-location-dot"></i></div>',
        iconSize: [26, 32], iconAnchor: [13, 32], popupAnchor: [0, -30]
    });

    const clinicLayer = L.layerGroup().addTo(map);
    let userMarker = null;
    let markers = [];

    // Evita que un nombre con < o " rompa el HTML (nunca insertar texto de la base sin escapar).
    function esc(s) {
        return String(s).replace(/[&<>"']/g, c => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]));
    }

    function setStatus(msg, isError) {
        statusEl.textContent = msg;
        statusEl.className = 'vc-status ' + (isError ? 'error' : 'muted');
    }

    // Envía datos por POST con el token CSRF y devuelve el JSON, o lanza un Error con el mensaje.
    async function post(url, fields) {
        const fd = new FormData();
        fd.append('csrf', CSRF);
        Object.keys(fields).forEach(k => fd.append(k, fields[k]));
        const resp = await fetch(url, { method: 'POST', body: fd, credentials: 'same-origin' });
        let json = null;
        try { json = await resp.json(); } catch (e) { /* respuesta no JSON */ }
        if (!json) throw new Error('Respuesta inesperada del servidor.');
        if (!json.ok) throw new Error(json.error || 'Error desconocido.');
        return json;
    }

    async function searchNear(lat, lng, label) {
        setStatus('Buscando veterinarias cercanas…', false);
        try {
            const data = await post(API_NEAR, { lat: lat, lng: lng });
            showResults(lat, lng, data.clinics, label);
        } catch (err) {
            setStatus(err.message, true);
        }
    }

    function showResults(lat, lng, clinics, label) {
        clinicLayer.clearLayers();
        markers = [];
        if (userMarker) { map.removeLayer(userMarker); }

        userMarker = L.circleMarker([lat, lng], {
            radius: 9, color: '#fff', weight: 3, fillColor: '#2b7bc4', fillOpacity: 1
        }).addTo(map).bindTooltip(label || 'Tu ubicación');

        const points = [[lat, lng]];
        listEl.innerHTML = '';

        if (!clinics.length) {
            setStatus('Todavía no hay veterinarias con ubicación cargada en el sistema.', true);
            map.setView([lat, lng], 13);
            return;
        }

        clinics.forEach(function (c, i) {
            const dir = 'https://www.google.com/maps/dir/?api=1&destination=' + c.lat + ',' + c.lng;
            const marker = L.marker([c.lat, c.lng], { icon: pinIcon }).addTo(clinicLayer);
            marker.bindPopup(
                '<strong>' + esc(c.name) + '</strong><br>' + esc(c.address) +
                '<br><em>a ' + c.distance_km.toLocaleString('es-AR') + ' km</em>'
            );
            markers.push(marker);
            points.push([c.lat, c.lng]);

            const card = document.createElement('div');
            card.className = 'vc-card';
            card.dataset.index = i;
            card.innerHTML =
                '<div style="display:flex; justify-content:space-between; gap:10px; align-items:flex-start;">' +
                    '<h3>' + esc(c.name) + '</h3>' +
                    '<span class="vc-dist">' + c.distance_km.toLocaleString('es-AR') + ' km</span>' +
                '</div>' +
                '<div class="vc-line"><i class="fa-solid fa-location-dot"></i> ' + esc(c.address) + '</div>' +
                '<div class="vc-line"><i class="fa-solid fa-envelope"></i> ' + esc(c.email) + '</div>' +
                '<div class="vc-line"><a href="' + dir + '" target="_blank" rel="noopener"><i class="fa-solid fa-diamond-turn-right"></i> Cómo llegar</a></div>';
            listEl.appendChild(card);
        });

        // Encuadrar el mapa para que se vean el tutor y todas las veterinarias.
        map.fitBounds(points, { padding: [40, 40], maxZoom: 15 });
        setStatus('Mostrando las ' + clinics.length + ' veterinaria' + (clinics.length === 1 ? '' : 's') + ' más cercana' + (clinics.length === 1 ? '' : 's') + (label ? ' a: ' + label : '') + '.', false);
    }

    // Clic en una tarjeta => el mapa vuela hasta esa veterinaria y abre su globo.
    // (Un solo listener en el contenedor sirve para todas las tarjetas.)
    listEl.addEventListener('click', function (ev) {
        if (ev.target.closest('a')) { return; }   // el link "Cómo llegar" funciona normal
        const card = ev.target.closest('.vc-card');
        if (!card) { return; }
        const m = markers[Number(card.dataset.index)];
        if (m) {
            map.flyTo(m.getLatLng(), 16);
            m.openPopup();
            document.getElementById('vcMap').scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    });

    // ---- Opción 1: ubicación del navegador ----
    function useMyLocation() {
        // La ubicación del navegador solo funciona en HTTPS o en localhost.
        if (!window.isSecureContext) {
            setStatus('Tu navegador bloquea la ubicación en este tipo de conexión. Escribí tu dirección o localidad.', true);
            return;
        }
        if (!('geolocation' in navigator)) {
            setStatus('Tu navegador no permite obtener la ubicación. Escribí tu dirección o localidad.', true);
            return;
        }
        setStatus('Pidiendo permiso para usar tu ubicación…', false);
        navigator.geolocation.getCurrentPosition(
            function (pos) { searchNear(pos.coords.latitude, pos.coords.longitude, 'tu ubicación'); },
            function (err) {
                const msgs = {
                    1: 'No diste permiso para usar tu ubicación. Podés escribir tu dirección o localidad.',
                    2: 'No pudimos detectar tu ubicación. Escribí tu dirección o localidad.',
                    3: 'Tardó demasiado en detectar tu ubicación. Probá de nuevo o escribí tu dirección.'
                };
                setStatus(msgs[err.code] || msgs[2], true);
            },
            { enableHighAccuracy: false, timeout: 10000, maximumAge: 60000 }
        );
    }
    document.getElementById('vcLocate').addEventListener('click', useMyLocation);

    // ---- Opción 2: escribir una dirección ----
    document.getElementById('vcSearchForm').addEventListener('submit', async function (ev) {
        ev.preventDefault();
        const text = document.getElementById('vcQuery').value.trim();
        if (text.length < 3) {
            setStatus('Escribí al menos 3 letras de tu dirección o localidad.', true);
            return;
        }
        setStatus('Buscando ese lugar…', false);
        try {
            const geo = await post(API_GEO, { q: text });
            await searchNear(geo.lat, geo.lng, text);
        } catch (err) {
            setStatus(err.message, true);
        }
    });

    // Si el tutor ya había dado permiso antes, buscamos solos al entrar.
    // (Si no, esperamos al botón: pedir permiso apenas carga la página molesta.)
    if (navigator.permissions && navigator.permissions.query) {
        navigator.permissions.query({ name: 'geolocation' }).then(function (p) {
            if (p.state === 'granted') { useMyLocation(); }
        }).catch(function () { /* navegador sin soporte: no pasa nada */ });
    }
})();
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
