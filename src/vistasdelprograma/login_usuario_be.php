<?php
session_start();
require 'con_db.php';
require_once __DIR__ . '/bitacora_helpers.php'; // Incluir el helper de la bitácora
/**
 * Asegura que exista el parámetro MAX_LOGIN_ATTEMPTS en la tabla tbl_ms_parametros.
 * Si no existe, lo inserta con el valor por defecto indicado.
 * Devuelve el valor entero del parámetro o el valor por defecto si ocurre algún fallo.
 */
function ensure_max_login_attempts_param($conexion, $default = 3) {
    $param = 'MAX_LOGIN_ATTEMPTS';

    // Intentar leer el parámetro primero
    $stmt = $conexion->prepare("SELECT valor FROM tbl_ms_parametros WHERE parametro = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('s', $param);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $row = $res->fetch_assoc()) {
            $stmt->close();
            return intval($row['valor']);
        }
        $stmt->close();
    }

    // No existe: intentar insertar con un owner razonable (usuario en sesión o primer admin)
    $owner_id = null;
    if (isset($_SESSION['usuario_id'])) $owner_id = intval($_SESSION['usuario_id']);
    if (!$owner_id) {
        $r = $conexion->query("SELECT id_usuario FROM tbl_ms_usuario WHERE tbl_ms_roles_id_rol = 2 LIMIT 1");
        if ($r && $row2 = $r->fetch_assoc()) $owner_id = intval($row2['id_usuario']);
    }
    if (!$owner_id) $owner_id = 1; // fallback

    $val = strval($default);

    // Intentar insertar sin id_parametro (si la tabla tiene AUTO_INCREMENT funcionará)
    $ins = $conexion->prepare("INSERT INTO tbl_ms_parametros (parametro, valor, fecha_creacion, fecha_modificacion, tbl_ms_usuario_id_usuario) VALUES (?, ?, CURDATE(), CURDATE(), ?)");
    if ($ins) {
        $ins->bind_param('ssi', $param, $val, $owner_id);
        $ins->execute();
        $ins->close();
        return $default;
    }

    // Si la inserción falló, devolver el valor por defecto
    return $default;
}

// Solo permitir método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../../public/index.php?error=Acceso no autorizado');
    exit;
}

// Obtener (y si hace falta crear) el parámetro de intentos máximos de la base de datos
$max_intentos = ensure_max_login_attempts_param($conexion, 3);


$correo = strtolower(trim($_POST['correo'] ?? ''));

// Expresión regular para validar correos con solo minúsculas y símbolos permitidos
$regex = "/^[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$/";

if (!preg_match($regex, $correo)) {
    // Redirigir con error si el correo no es válido
    header('Location: ../../public/index.php?error=Formato de correo o contraseña incorrecto.');
    exit;
}

$clave_input = $_POST['contraseña'] ?? '';

// Validación: no debe contener espacios
if (preg_match('/\s/', $clave_input)) {
    // Redirigir con mensaje de error
    header('Location: ../../public/index.php?error=Ingreso incorrecto de contraseña');
    exit();
}

if ($correo === '' || $clave_input === '') {
    header('Location: ../../public/index.php?error=Todos los campos son obligatorios');
    exit;
}

if ($correo === '' || $clave_input === '') {
    header('Location: ../../public/index.php?error=Todos los campos son obligatorios');
    exit;
}

// Buscar usuario por correo
$sql = "SELECT id_usuario, usuario, nombre_usuario, contraseña, correo_electronico, is_2fa_enabled, tbl_ms_roles_id_rol, intentos_fallidos, estado_bloqueo 
    FROM tbl_ms_usuario 
    WHERE correo_electronico = ? 
    LIMIT 1";
$stmt = $conexion->prepare($sql);
$stmt->bind_param('s', $correo);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    header('Location: ../../public/index.php?error=Correo electrónico incorrecto');
    exit;
}

$user = $res->fetch_assoc();
$stmt->close();

// Si el usuario ya está bloqueado en tbl_ms_usuario, bloquear acceso
if (isset($user['estado_bloqueo']) && strtoupper($user['estado_bloqueo']) === 'BLOQUEADO') {
    registrar_bitacora($conexion, $user['id_usuario'], 2, 'LOGIN_BLOCKED', "Intento de login para usuario bloqueado: {$user['usuario']}");
    header('Location: ../../public/index.php?error=Usuario bloqueado. Contacte al administrador.');
    exit;
}

// Verificar la contraseña (usa SHA512)
$input_hash = hash('sha512', $clave_input);
$stored_hash = $user['contraseña'];

// Si la contraseña es correcta
if ($input_hash === $stored_hash) {
    // Restablecer los intentos fallidos
    $upd = $conexion->prepare("UPDATE tbl_ms_usuario 
                              SET intentos_fallidos = 0, 
                                  estado_bloqueo = 'ACTIVO', 
                                  fecha_bloqueo = NULL 
                              WHERE id_usuario = ?");
    $upd->bind_param('i', $user['id_usuario']);
    $upd->execute();
    $upd->close();

    // Registrar en bitácora el inicio de sesión exitoso
    registrar_bitacora($conexion, $user['id_usuario'], 2, 'LOGIN_SUCCESS', "Inicio de sesión exitoso para el usuario: {$user['usuario']}");

    // Verificar si la doble autenticación está activada
    if (isset($user['is_2fa_enabled']) && $user['is_2fa_enabled'] == 1) {
        // Si 2FA está activada, redirigir a la página de verificación
        $_SESSION['pending_2fa_user_id'] = $user['id_usuario'];
        header('Location: verificar_2fa.php');
        exit;
    } else {
        // Si no tiene 2FA, iniciar sesión directamente
        $_SESSION['usuario_id'] = $user['id_usuario'];
        $_SESSION['usuario'] = $user['usuario'];
        $_SESSION['usuario_nombre'] = $user['nombre_usuario'];
        $_SESSION['rol_id'] = intval($user['tbl_ms_roles_id_rol']);
        session_regenerate_id(true);
        header('Location: menuprincipal.php'); // Redirigir al menú principal
        exit;
    }
}

// Si la contraseña es incorrecta
// Usamos la columna intentos_fallidos en tbl_ms_usuario
$currentIntentos = intval($user['intentos_fallidos']);
$newIntentos = $currentIntentos + 1;

if ($newIntentos >= $max_intentos) {
    // Bloquear usuario
    $upd2 = $conexion->prepare("UPDATE tbl_ms_usuario 
                               SET intentos_fallidos = ?, 
                                   estado_bloqueo = 'BLOQUEADO', 
                                   fecha_bloqueo = NOW() 
                               WHERE id_usuario = ?");
    $upd2->bind_param('ii', $newIntentos, $user['id_usuario']);
    $upd2->execute();
    $upd2->close();

    registrar_bitacora($conexion, $user['id_usuario'], 2, 'USER_LOCKED', "Usuario {$user['usuario']} bloqueado por {$newIntentos} intentos fallidos.");
    header('Location: ../../public/index.php?error=Usuario bloqueado por múltiples intentos fallidos.');
    exit;
} else {
    // Actualizar intentos
    $upd3 = $conexion->prepare("UPDATE tbl_ms_usuario 
                               SET intentos_fallidos = ? 
                               WHERE id_usuario = ?");
    $upd3->bind_param('ii', $newIntentos, $user['id_usuario']);
    $upd3->execute();
    $upd3->close();

    registrar_bitacora($conexion, $user['id_usuario'], 2, 'LOGIN_FAIL', "Intento de login fallido para usuario: {$user['usuario']}. Intento {$newIntentos} de {$max_intentos}.");
    $restantes = $max_intentos - $newIntentos;
    header('Location: ../../public/index.php?error=Usuario o contraseña incorrectos. Te quedan ' . $restantes . ' intentos.');
    exit;
}
?>
