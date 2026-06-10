<?php
session_start();
require 'con_db.php';
require_once __DIR__ . '/bitacora_helpers.php'; // Incluir el helper de la bitácora
require_once __DIR__ . '/../config/errorlogs.php';// Ajusta la ruta según tu estructura
require_once __DIR__ . '/../config/roles.php';

use App\config\errorlogs;
errorlogs::activa_error_logs();
/**
 * Asegura que exista el parámetro MAX_LOGIN_ATTEMPTS en la tabla tbl_ms_parametros.
 * Si no existe, lo inserta con el valor por defecto indicado.
 * Devuelve el valor entero del parámetro o el valor por defecto si ocurre algún fallo.
 */
function ensure_max_login_attempts_param($conexion, $default = 3) {
    $param = 'MAX_LOGIN_ATTEMPTS';

    // Verificar conexión antes de proceder
    if (!$conexion) {
        return $default;
    }

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
        $adminRoleQuery = sprintf(
            "SELECT id_usuario FROM tbl_ms_usuario WHERE tbl_ms_roles_id_rol = %d LIMIT 1",
            ROLE_SUPER_ADMIN
        );
        $r = $conexion->query($adminRoleQuery);
        if ($r && $row2 = $r->fetch_assoc()) $owner_id = intval($row2['id_usuario']);
    }
    if (!$owner_id) $owner_id = 1; // fallback

    $val = strval($default);

    // Intentar insertar sin id_parametro (si la tabla tiene AUTO_INCREMENT funcionará)
    $ins = $conexion->prepare("INSERT INTO tbl_ms_parametros (parametro, valor, id_usuario) VALUES (?, ?, ?)");
    if ($ins) {
        $ins->bind_param('ssi', $param, $val, $owner_id);
        $ins->execute();
        $ins->close();
        return $default;
    }

    // Si la inserción falló, devolver el valor por defecto
    return $default;
}

// Asegura la tabla de flags por usuario para preferencias (como omitir el recordatorio de 2FA)
function ensure_user_flags_table($conexion) {
    $sql = "CREATE TABLE IF NOT EXISTS tbl_ms_usuario_flags (
                id_usuario INT PRIMARY KEY,
                skip_2fa_prompt TINYINT(1) NOT NULL DEFAULT 0,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                CONSTRAINT fk_flags_usuario FOREIGN KEY (id_usuario) REFERENCES tbl_ms_usuario(id_usuario) ON DELETE CASCADE
            ) ENGINE=InnoDB";
    @$conexion->query($sql); // Silencioso si no hay permisos; el código seguirá con fallback
}

// Solo permitir método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['login_error'] = 'Acceso no autorizado';
    header('Location: ../../public/index.php');
    exit;
}

// Obtener (y si hace falta crear) el parámetro de intentos máximos de la base de datos
$max_intentos = ensure_max_login_attempts_param($conexion, 3);

// Verificar primero si hay usuarios en el sistema
$count_stmt = $conexion->prepare("SELECT COUNT(*) as total FROM tbl_ms_usuario");
if ($count_stmt) {
    $count_stmt->execute();
    $count_res = $count_stmt->get_result();
    if ($count_res) {
        $count_row = $count_res->fetch_assoc();
        $count_stmt->close();
        
        if (intval($count_row['total']) === 0) {
            // No redireccionar con parámetros GET, usar variable de sesión
            $_SESSION['login_error'] = 'No hay usuarios registrados en el sistema. Debe crear el primer usuario antes de poder iniciar sesión.';
            header('Location: ../../public/index.php');
            exit;
        }
    } else {
        $count_stmt->close();
    }
}

$correo = strtolower(trim($_POST['correo'] ?? ''));

// Expresión regular para validar correos con solo minúsculas y símbolos permitidos
$regex = "/^[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$/";

if (!preg_match($regex, $correo)) {
    // Redirigir con error si el correo no es válido
    $_SESSION['login_error'] = 'Formato de correo o contraseña incorrecto.';
    header('Location: ../../public/index.php');
    exit;
}

$clave_input = $_POST['contraseña'] ?? '';

// Validación: no debe contener espacios
if (preg_match('/\s/', $clave_input)) {
    // Redirigir con mensaje de error
    $_SESSION['login_error'] = 'Ingreso incorrecto de contraseña';
    header('Location: ../../public/index.php');
    exit();
}

if ($correo === '' || $clave_input === '') {
    $_SESSION['login_error'] = 'Todos los campos son obligatorios';
    header('Location: ../../public/index.php');
    exit;
}

if ($correo === '' || $clave_input === '') {
    $_SESSION['login_error'] = 'Todos los campos son obligatorios';
    header('Location: ../../public/index.php');
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
    $_SESSION['login_error'] = 'Correo electrónico incorrecto';
    header('Location: ../../public/index.php');
    exit;
}

$user = $res->fetch_assoc();
$stmt->close();

// Si el usuario ya está bloqueado en tbl_ms_usuario, bloquear acceso
if (isset($user['estado_bloqueo']) && strtoupper($user['estado_bloqueo']) === 'BLOQUEADO') {
    registrar_bitacora($conexion, $user['id_usuario'], 2, 'LOGIN_BLOCKED', "Intento de login para usuario bloqueado: {$user['usuario']}");
    $_SESSION['login_error'] = 'Usuario bloqueado. Contacte al administrador.';
    header('Location: ../../public/index.php');
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
        // 2FA activada: requerir verificación TOTP
        $_SESSION['pending_2fa_user_id'] = $user['id_usuario'];
        header('Location: verificar_2fa.php');
        exit;
    } else {
        // 2FA desactivada: decidir si mostrar QR de activación o no según preferencia del usuario
        ensure_user_flags_table($conexion);
        $skip = 0;
        $q = $conexion->prepare("SELECT skip_2fa_prompt FROM tbl_ms_usuario_flags WHERE id_usuario = ? LIMIT 1");
        if ($q) {
            $q->bind_param('i', $user['id_usuario']);
            $q->execute();
            $r = $q->get_result();
            if ($r && ($row = $r->fetch_assoc())) {
                $skip = intval($row['skip_2fa_prompt']);
            }
            $q->close();
        }

        if ($skip === 0) {
            // Sugerir activar 2FA con QR (flujo de configuración)
            $_SESSION['usuario_id'] = $user['id_usuario'];
            $_SESSION['usuario'] = $user['usuario'];
            $_SESSION['usuario_nombre'] = $user['nombre_usuario'];
            $_SESSION['rol_id'] = intval($user['tbl_ms_roles_id_rol']);
            session_regenerate_id(true);
            header('Location: activar_2fa.php');
            exit;
        }

        // Si el usuario optó por no mostrar más el QR, iniciar sesión normalmente
        $_SESSION['usuario_id'] = $user['id_usuario'];
        $_SESSION['usuario'] = $user['usuario'];
        $_SESSION['usuario_nombre'] = $user['nombre_usuario'];
        $_SESSION['rol_id'] = intval($user['tbl_ms_roles_id_rol']);
        session_regenerate_id(true);
        header('Location: menuprincipal.php');
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
    $_SESSION['login_error'] = 'Usuario bloqueado por múltiples intentos fallidos.';
    header('Location: ../../public/index.php');
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
    $_SESSION['login_error'] = "Usuario o contraseña incorrectos. Te quedan {$restantes} intentos.";
    header('Location: ../../public/index.php');
    exit;
}
?>
