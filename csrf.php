<?php
// Generación y validación de tokens CSRF (requiere sesión ya iniciada)

function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

function csrf_verify($token) {
    return isset($_SESSION['csrf_token']) && is_string($token) && $token !== '' && hash_equals($_SESSION['csrf_token'], $token);
}
