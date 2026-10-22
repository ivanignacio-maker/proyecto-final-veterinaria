<?php
declare(strict_types=1);

const GEOCODER_USER_AGENT = 'VetCompartido/1.0 (proyecto universitario)';

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