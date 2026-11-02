<?php
declare(strict_types=1);

/** Return a user-facing error when a new password does not meet the shared policy. */
function password_policy_error(string $password): ?string {
    if (strlen($password) < 8) {
        return 'La contraseña debe tener al menos 8 caracteres.';
    }
    if (!preg_match('/[a-z]/', $password) || !preg_match('/[A-Z]/', $password)) {
        return 'Incluí al menos una mayúscula y una minúscula.';
    }
    if (!preg_match('/[0-9]/', $password)) {
        return 'Incluí al menos un número.';
    }
    if (!preg_match('/[^a-zA-Z0-9]/', $password)) {
        return 'Incluí al menos un símbolo, por ejemplo !, @ o #.';
    }

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
