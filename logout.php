<?php
/**
 * ==============================================================================
 * Cierre de Sesión - Logout
 * Centro de Documentación e Información "Jesús Rosas Marcano" - IUTA
 * ==============================================================================
 */

session_start();

// Destruir todas las variables de sesión
$_SESSION = array();

// Si se desea destruir la cookie de sesión, también se elimina
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destruir la sesión por completo
session_destroy();

// Redirigir al formulario de inicio de sesión
header('Location: login.php');
exit;
