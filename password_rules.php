<?php
// Validación de política de contraseñas para el alta pública de usuarios

function validar_password($password) {
    $errores = [];

    if (strlen($password) < 8) {
        $errores[] = 'Debe tener al menos 8 caracteres.';
    }
    if (!preg_match('/[A-Z]/', $password)) {
        $errores[] = 'Debe tener al menos una letra mayúscula.';
    }
    if (!preg_match('/[0-9]/', $password)) {
        $errores[] = 'Debe tener al menos un número.';
    }
    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        $errores[] = 'Debe tener al menos un carácter especial.';
    }
    if (contiene_digitos_correlativos($password)) {
        $errores[] = 'Los números no pueden ser correlativos (ej. 123 o 321).';
    }

    return $errores;
}

function contiene_digitos_correlativos($password) {
    $largo = strlen($password);
    for ($i = 0; $i < $largo - 1; $i++) {
        $actual = $password[$i];
        $siguiente = $password[$i + 1];
        if (ctype_digit($actual) && ctype_digit($siguiente)) {
            $diferencia = (int)$siguiente - (int)$actual;
            if ($diferencia === 1 || $diferencia === -1) {
                return true;
            }
        }
    }
    return false;
}
