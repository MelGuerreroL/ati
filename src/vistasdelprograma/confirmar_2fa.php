<?php
session_start();
require 'con_db.php';
require '../vistasdelprograma/totp.php';

$is_setup_flow = isset($_SESSION['pending_totp_secret']); // ¿Estamos en el flujo de activación?

// 1. Validar que exista un usuario pendiente de 2FA
if (!isset($_SESSION['pending_2fa_user_id']) && !$is_setup_flow) {
    header("Location: ../../public/index.php?error=Acceso no autorizado");
    exit;
}

// 2. Validar que se haya enviado un código
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['codigo'])) {
    header("Location: verificar_2fa.php?error=Debes ingresar un código.");
    exit;
}

$codigo_ingresado = trim($_POST['codigo']);

if ($is_setup_flow) {
    // Flujo de activación: el secreto está en la sesión, no en la BD
    $user_id = $_SESSION['usuario_id']; // El usuario ya está logueado
    $secret = $_SESSION['pending_totp_secret'];
} else {
    // Flujo de login normal: el secreto está en la BD
    $user_id = $_SESSION['pending_2fa_user_id'];
}

$stmt = $conexion->prepare("SELECT id_usuario, usuario, nombre_usuario, totp_secret, tbl_ms_roles_id_rol FROM tbl_ms_usuario WHERE id_usuario = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // Si por alguna razón el usuario ya no existe o no tiene 2FA, abortar.
    session_destroy();
    header("Location: ../../public/index.php?error=Error de configuración de 2FA.");
    exit;
}

$user = $result->fetch_assoc();
$stmt->close();

if (!$is_setup_flow) {
    $secret = $user['totp_secret']; // Usar el secreto de la BD para el login
}

// 4. Verificar el código TOTP
if (verify_totp($secret, $codigo_ingresado)) {
    // 5. Código correcto: Iniciar sesión final
    
    // Si es el flujo de activación, actualizamos la BD
    if ($is_setup_flow) {
        $update_stmt = $conexion->prepare("UPDATE tbl_ms_usuario SET is_2fa_enabled = 1, totp_secret = ? WHERE id_usuario = ?");
        $update_stmt->bind_param('si', $secret, $user_id);
        $update_stmt->execute();
        $update_stmt->close();
        unset($_SESSION['pending_totp_secret']);
    }

    // Limpiar la sesión pendiente
    if (isset($_SESSION['pending_2fa_user_id'])) {
        unset($_SESSION['pending_2fa_user_id']);
    }

    // Establecer la sesión final de usuario
    $_SESSION['usuario_id'] = $user['id_usuario'];
    $_SESSION['usuario'] = $user['usuario'];
    $_SESSION['usuario_nombre'] = $user['nombre_usuario'];
    $_SESSION['rol_id'] = intval($user['tbl_ms_roles_id_rol']);
    
    // Regenerar ID de sesión para seguridad
    session_regenerate_id(true);

    // Redirigir al menú principal
    header("Location: menuprincipal.php");
    exit;

} else {
    // 6. Código incorrecto: Redirigir de vuelta con un error
    if ($is_setup_flow) {
        header("Location: activar_2fa.php?error=Código incorrecto. Inténtalo de nuevo.");
    } else {
        header("Location: verificar_2fa.php?error=Código incorrecto o expirado. Inténtalo de nuevo.");
    }
    exit;
}
?>