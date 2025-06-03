<?php
// Es importante que config.php (que inicia la sesión) se incluya primero si no está ya iniciado.
// Sin embargo, como session_start() ya está en config.php, solo necesitamos requerirlo.
require_once 'config.php';
require_once 'includes/functions.php'; // Para logSystemEvent

// Antes de destruir la sesión, registramos el evento de logout
if (isset($_SESSION['user_id'])) {
    logSystemEvent($pdo, 'INFO', 'LOGOUT_SUCCESS', "Usuario '{$_SESSION['username']}' cerró sesión exitosamente.", 'Auth', null, $_SESSION['user_id']);
}

// Destruir todas las variables de sesión.
$_SESSION = array();

// Si se desea destruir la sesión completamente, borre también la cookie de sesión.
// Nota: ¡Esto destruirá la sesión, y no solo los datos de la sesión!
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Finalmente, destruir la sesión.
session_destroy();

// Redirigir a la página de login (index.php en la nueva estructura)
header("Location: " . APP_URL . "/index.php?status=logged_out");
exit();
?>