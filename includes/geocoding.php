<?php
declare(strict_types=1);

const GEOCODER_USER_AGENT = 'VetCare/1.0 (proyecto universitario)';

/** Busca una dirección real en OpenStreetMap/Nominatim. */
function geocode_address(string $address): ?array {
    static $lastRequestAt = 0.0;
    $address = trim($address);
    if ($address === '') return null;

    // Reutilizar resultados para no consultar repetidamente el servicio público.
    $cacheDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'vetcare-geocoding';
    if (!is_dir($cacheDir)) @mkdir($cacheDir, 0700, true);
    $cacheFile = $cacheDir . DIRECTORY_SEPARATOR . hash('sha256', mb_strtolower($address)) . '.json';
    if (is_file($cacheFile) && filemtime($cacheFile) > time() - 30 * 86400) {
        $cached = json_decode((string)file_get_contents($cacheFile), true);
        if (is_array($cached) && isset($cached['lat'], $cached['lng'])) return $cached;
        if ($cached === ['not_found' => true]) return null;
    }

    // Nominatim solicita limitar las consultas a una por segundo.
    $wait = 1.0 - (microtime(true) - $lastRequestAt);
    if ($lastRequestAt > 0 && $wait > 0) usleep((int)($wait * 1000000));
    $lastRequestAt = microtime(true);

    $url = 'https://nominatim.openstreetmap.org/search?' . http_build_query([
        'format' => 'jsonv2', 'limit' => 1, 'countrycodes' => 'ar',
        'addressdetails' => 1, 'q' => $address . ', Argentina',
    ]);
    $context = stream_context_create(['http' => [
        'method' => 'GET', 'timeout' => 6,
        'header' => "User-Agent: " . GEOCODER_USER_AGENT . "\r\nAccept: application/json\r\n",
    ]]);
    $response = @file_get_contents($url, false, $context);
    if ($response === false) return null;
    $results = json_decode($response, true);
    if (!is_array($results) || empty($results[0]['lat']) || empty($results[0]['lon'])) {
        @file_put_contents($cacheFile, json_encode(['not_found' => true]));
        return null;
    }
    $point = ['lat' => (float)$results[0]['lat'], 'lng' => (float)$results[0]['lon']];
    @file_put_contents($cacheFile, json_encode($point));
    return $point;
}

/**
 * Mapeo completo de Provincias argentinas y sus principales localidades/ciudades.
 */
function ar_provinces_cities(): array {
    return [
        'Ciudad Autónoma de Buenos Aires' => [
            'Agronomía', 'Almagro', 'Balvanera', 'Barracas', 'Belgrano', 'Boedo', 'Caballito', 'Chacarita', 'Coghlan', 'Colegiales', 'Constitución', 'Flores', 'Floresta', 'La Boca', 'La Paternal', 'Liniers', 'Mataderos', 'Monte Castro', 'Montserrat', 'Nueva Pompeya', 'Núñez', 'Palermo', 'Parque Avellaneda', 'Parque Chacabuco', 'Parque Chas', 'Parque Patricios', 'Puerto Madero', 'Recoleta', 'Retiro', 'Saavedra', 'San Cristóbal', 'San Nicolás', 'San Telmo', 'Vélez Sársfield', 'Versalles', 'Villa Crespo', 'Villa del Parque', 'Villa Devoto', 'Villa General Mitre', 'Villa Lugano', 'Villa Luro', 'Villa Ortúzar', 'Villa Pueyrredón', 'Villa Real', 'Villa Riachuelo', 'Villa Santa Rita', 'Villa Soldati', 'Villa Urquiza'
        ],
        'Buenos Aires' => [
            'La Plata', 'Mar del Plata', 'Bahía Blanca', 'San Justo', 'Avellaneda', 'Lanús', 'Quilmes', 'Lomas de Zamora', 'Morón', 'San Isidro', 'Vicente López', 'Tigre', 'Pilar', 'San Martín', 'Tres de Febrero', 'Merlo', 'Moreno', 'Florencio Varela', 'Berazategui', 'Esteban Echeverría', 'Ezeiza', 'San Vicente', 'Cañuelas', 'Zárate', 'Campana', 'San Nicolás', 'Pergamino', 'Junín', 'Chivilcoy', 'Trenque Lauquen', 'Necochea', 'Tandil', 'Olavarría', 'Azul', '9 de Julio'
        ],
        'Córdoba' => [
            'Córdoba', 'Villa María', 'Río Cuarto', 'San Francisco', 'Villa Carlos Paz', 'Alta Gracia', 'Jesús María', 'Bell Ville', 'Laboulaye', 'Cruz del Eje', 'Villa Dolores'
        ],
        'Santa Fe' => [
            'Rosario', 'Santa Fe', 'Rafaela', 'Venado Tuerto', 'Reconquista', 'Santo Tomé', 'Casilda', 'San Lorenzo', 'Villa Constitución', 'Granadero Baigorria'
        ],
        'Mendoza' => [
            'Mendoza', 'Godoy Cruz', 'Guaymallén', 'Las Heras', 'Maipú', 'San Martín', 'San Rafael', 'Luján de Cuyo', 'Tunuyán', 'General Alvear'
        ],
        'Tucumán' => [
            'San Miguel de Tucumán', 'Yerba Buena', 'Tafí Viejo', 'Concepción', 'Banda del Río Salí', 'Monteros', 'Aguilares'
        ],
        'Entre Ríos' => [
            'Paraná', 'Concordia', 'Gualeguaychú', 'Concepción del Uruguay', 'Villaguay', 'Gualeguay', 'Chajarí', 'Victoria', 'Colón'
        ],
        'Salta' => [
            'Salta', 'San Ramón de la Nueva Orán', 'Tartagal', 'General Güemes', 'Metán', 'Rosario de la Frontera', 'Cafayate'
        ],
        'Misiones' => [
            'Posadas', 'Oberá', 'Eldorado', 'Puerto Iguazú', 'Apóstoles', 'Leandro N. Alem', 'San Vicente'
        ],
        'Corrientes' => [
            'Corrientes', 'Goya', 'Paso de los Libres', 'Curuzú Cuatiá', 'Mercedes', 'Bella Vista', 'Santo Tomé'
        ],
        'Chaco' => [
            'Resistencia', 'Barranqueras', 'Fontana', 'Presidencia Roque Sáenz Peña', 'Villa Ángela', 'General San Martín', 'Las Breñas'
        ],
        'Santiago del Estero' => [
            'Santiago del Estero', 'La Banda', 'Termas de Río Hondo', 'Frías', 'Añatuya', 'Loreto'
        ],
        'San Juan' => [
            'San Juan', 'Rawson', 'Rivadavia', 'Chimbas', 'Santa Lucía', 'Pocito', 'Caucete'
        ],
        'Jujuy' => [
            'San Salvador de Jujuy', 'Palpalá', 'San Pedro de Jujuy', 'Libertador General San Martín', 'La Quiaca', 'Perico'
        ],
        'Río Negro' => [
            'Viedma', 'San Carlos de Bariloche', 'General Roca', 'Cipolletti', 'Villa Regina', 'Cinco Saltos', 'El Bolsón'
        ],
        'Neuquén' => [
            'Neuquén', 'Cutral Có', 'Plaza Huincul', 'Zapala', 'San Martín de los Andes', 'Villa La Angostura', 'Centenario'
        ],
        'Chubut' => [
            'Rawson', 'Comodoro Rivadavia', 'Trelew', 'Puerto Madryn', 'Esquel', 'Sarmiento'
        ],
        'Formosa' => [
            'Formosa', 'Clorinda', 'Pirané', 'El Colorado', 'Las Lomitas'
        ],
        'San Luis' => [
            'San Luis', 'Villa Mercedes', 'Merlo', 'Justo Daract', 'La Toma'
        ],
        'Catamarca' => [
            'San Fernando del Valle de Catamarca', 'Valle Viejo', 'Fray Mamerto Esquiú', 'Andalgalá', 'Tinogasta', 'Belén'
        ],
        'La Pampa' => [
            'Santa Rosa', 'General Pico', 'Toay', '25 de Mayo', 'General Acha', 'Eduardo Castex'
        ],
        'La Rioja' => [
            'La Rioja', 'Chilecito', 'Aimogasta', 'Chamical', 'Nonogasta'
        ],
        'Santa Cruz' => [
            'Río Gallegos', 'Caleta Olivia', 'Pico Truncado', 'El Calafate', 'Las Heras', 'Puerto Deseado'
        ],
        'Tierra del Fuego' => [
            'Ushuaia', 'Río Grande', 'Tolhuin'
        ],
    ];
}

function ar_provinces(): array {
    return array_keys(ar_provinces_cities());
}

function clinic_full_address(array $clinic): string {
    $parts = array_filter([
        trim((string)($clinic['street'] ?? '')),
        trim((string)($clinic['city'] ?? '')),
        trim((string)($clinic['province'] ?? '')),
    ], fn($p) => $p !== '');
    return implode(', ', $parts);
}
