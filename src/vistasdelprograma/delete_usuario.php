<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/bitacora_helpers.php';

// Conexión a la base de datos (igual que get_db_connection)
function get_db_connection() {
    $host = "localhost";
    $user = "root";
    $pass = "";
    $db   = "gestion_suscripciones";
    $conexion = mysqli_connect($host, $user, $pass, $db);
    if ($conexion) mysqli_set_charset($conexion, "utf8");
    return $conexion;
}

// Verificar sesión y permisos
if (empty($_SESSION['usuario']) || empty($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'error' => 'No ha iniciado sesión']);
    exit;
}
$rol_id = $_SESSION['rol_id'] ?? 0;
if ($rol_id != 2) {
    echo json_encode(['success' => false, 'error' => 'No tiene permisos para eliminar usuarios']);
    exit;
}
$admin_id = intval($_SESSION['usuario_id']);

// Esperar POST con id
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}
$id = intval($_POST['id'] ?? 0);
if ($id <= 0) {
    echo json_encode(['success' => false, 'error' => 'ID inválido']);
    exit;
}

$conexion = get_db_connection();
if (!$conexion) {
    echo json_encode(['success' => false, 'error' => 'Error de conexión: ' . mysqli_connect_error()]);
    exit;
}

mysqli_begin_transaction($conexion);
try {
    // Helper: comprobar existencia de tabla antes de ejecutar DELETE
    function tableExists($conn, $table) {
        $table = mysqli_real_escape_string($conn, $table);
        $res = mysqli_query($conn, "SHOW TABLES LIKE '{$table}'");
        if ($res === false) return false;
        $exists = mysqli_num_rows($res) > 0;
        mysqli_free_result($res);
        return $exists;
    }

    // Eliminar dependencias (si existen) para evitar fallos por FK
    $queries = [
        ["tbl_ms_seguridad_login", "DELETE FROM tbl_ms_seguridad_login WHERE tbl_ms_usuario_id_usuario = ?", 'i'],
        ["tbl_ms_hist_contraseña", "DELETE FROM tbl_ms_hist_contraseña WHERE tbl_ms_usuario_id_usuario = ?", 'i'],
        ["tbl_ms_bitacora", "DELETE FROM tbl_ms_bitacora WHERE id_usuario = ?", 'i'],
        ["tbl_ms_parametros", "DELETE FROM tbl_ms_parametros WHERE tbl_ms_usuario_id_usuario = ?", 'i']
    ];
    foreach ($queries as $q) {
        $tblName = $q[0];
        $sql = $q[1];
        $type = $q[2];
        if (!tableExists($conexion, $tblName)) {
            // Omitir tablas que no existan en esta instalación
            error_log("delete_usuario.php: tabla no encontrada, omitiendo -> {$tblName}");
            continue;
        }
        $stmt = mysqli_prepare($conexion, $sql);
        if (!$stmt) {
            throw new Exception('Error preparando eliminación en ' . $tblName . ': ' . mysqli_error($conexion));
        }
        mysqli_stmt_bind_param($stmt, $type, $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }

    // Eliminar usuario
    $stmt = mysqli_prepare($conexion, "DELETE FROM tbl_ms_usuario WHERE id_usuario = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    if (!mysqli_stmt_execute($stmt)) {
        $err = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        throw new Exception('No se pudo eliminar el usuario: ' . $err);
    }
    mysqli_stmt_close($stmt);

    // Registrar en bitácora
    registrar_bitacora($conexion, $admin_id, 1, 'DELETE', "El admin ID: {$admin_id} eliminó al usuario ID: {$id}.");

    mysqli_commit($conexion);
    echo json_encode(['success' => true]);
    exit;
} catch (Exception $e) {
    mysqli_rollback($conexion);
    error_log('Error delete_usuario.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
} finally {
    if (isset($conexion)) mysqli_close($conexion);
}
?>