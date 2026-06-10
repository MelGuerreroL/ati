<?php
session_start();
require_once 'con_db.php';
require_once __DIR__ . '/bitacora_helpers.php';

// Registrar el cierre de sesión en la bitácora ANTES de destruir la sesión
if (isset($_SESSION['usuario_id'])) {
    $user_id = $_SESSION['usuario_id'];
    registrar_bitacora($conexion, $user_id, 2, 'LOGOUT', "El usuario ID: {$user_id} cerró sesión.");
}

// destruir sesión por completo
$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time()-42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
}
session_destroy();
header('Location: ../../public/index.php');
exit;
?>
