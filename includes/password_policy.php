<?php
declare(strict_types=1);

/**
 * includes/password_policy.php
 *
 * Política de contraseñas ÚNICA de VetCare. Se usa al crear usuarios (tutores, veterinarias,
 * administrador) y al cambiar o restablecer una contraseña.
 *
 * Reglas: mínimo 8 caracteres, al menos 1 mayúscula, 1 minúscula y 1 número.
 */

/** Texto corto para mostrar debajo de los campos de contraseña. */
function password_policy_hint(): string {
    return 'Mínimo 8 caracteres, con al menos una mayúscula, una minúscula y un número.';
}

/** Devuelve el mensaje de error si la contraseña no cumple la política, o null si es válida. */
function password_policy_error(string $password): ?string {
    if (strlen($password) < 8) {
        return 'La contraseña debe tener al menos 8 caracteres.';
    }
    if (!preg_match('/[a-z]/', $password)) {
        return 'La contraseña debe incluir al menos una letra minúscula.';
    }
    if (!preg_match('/[A-Z]/', $password)) {
        return 'La contraseña debe incluir al menos una letra mayúscula.';
    }
    if (!preg_match('/[0-9]/', $password)) {
        return 'La contraseña debe incluir al menos un número.';
    }

    // Contraseñas demasiado obvias, aunque cumplan las reglas de arriba (ej. "Password1").
    $normalized = strtolower(strtr($password, ['ñ' => 'n', 'Ñ' => 'N', 'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u']));
    foreach (['password', 'contrasena', 'qwerty', 'admin', 'welcome', 'letmein', 'abc123', '123456', '654321', 'abcdef'] as $pattern) {
        if (strpos($normalized, $pattern) !== false) {
            return 'Evitá palabras comunes y secuencias fáciles de adivinar.';
        }
    }
    if (preg_match('/^(.)\1{5,}$/s', $password)) {
        return 'Evitá repetir el mismo carácter muchas veces.';
    }

    return null;
}

/**
 * Genera una contraseña aleatoria que cumple la política (para cuando el administrador
 * da de alta a alguien y no escribe una). 12 caracteres, sin letras ambiguas (0/O, 1/l/I).
 */
function generate_strong_password(int $length = 12): string {
    $upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    $lower = 'abcdefghijkmnpqrstuvwxyz';
    $digit = '23456789';
    $all   = $upper . $lower . $digit;

    do {
        // Uno de cada tipo asegurado + el resto al azar, y se mezclan.
        $chars = [
            $upper[random_int(0, strlen($upper) - 1)],
            $lower[random_int(0, strlen($lower) - 1)],
            $digit[random_int(0, strlen($digit) - 1)],
        ];
        while (count($chars) < $length) {
            $chars[] = $all[random_int(0, strlen($all) - 1)];
        }
        for ($i = count($chars) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$chars[$i], $chars[$j]] = [$chars[$j], $chars[$i]];
        }
        $password = implode('', $chars);
    } while (password_policy_error($password) !== null);   // por si justo formó una palabra prohibida

    return $password;
}
