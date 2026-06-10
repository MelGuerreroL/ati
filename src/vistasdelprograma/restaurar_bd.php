<?php
require_once __DIR__ . '/../config/errorlogs.php';// Ajusta la ruta según tu estructura
require_once __DIR__ . '/../config/roles.php';

use App\config\errorlogs;
errorlogs::activa_error_logs();
// restaurar_bd.php
ob_start();

try {
    session_start();

    ini_set('max_execution_time', 300);
    ini_set('memory_limit', '512M');
    ini_set('display_errors', 0);
    error_reporting(E_ALL);

    header('Content-Type: application/json');

    require_once 'con_db.php'; // conexión a la BD
    require_once __DIR__ . '/bitacora_helpers.php'; // Para registro en bitácora

    if (!isset($conexion) || $conexion->connect_error) {
        throw new Exception('Error de conexión a la base de datos');
    }

    if (empty($_SESSION['usuario']) || empty($_SESSION['usuario_id'])) {
        throw new Exception('Debes iniciar sesión para realizar esta acción.');
    }

    if (!user_has_permission('config', PERM_UPDATE)) {
        throw new Exception('No tiene permisos para restaurar la base de datos.');
    }

    if (!isset($_FILES['sql_file'])) {
        throw new Exception('No se recibió ningún archivo.');
    }

    if ($_FILES['sql_file']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Error en la carga del archivo.');
    }

    $archivoTmp = $_FILES['sql_file']['tmp_name'];
    $nombreArchivo = $_FILES['sql_file']['name'];

    if (pathinfo($nombreArchivo, PATHINFO_EXTENSION) !== 'sql') {
        throw new Exception('El archivo debe tener extensión .sql');
    }

    $contenidoSQL = file_get_contents($archivoTmp);
    if (!$contenidoSQL || strlen($contenidoSQL) === 0) {
        throw new Exception('El archivo está vacío o no se pudo leer correctamente.');
    }

    // Registrar inicio de restauración en bitácora
    $id_usuario = $_SESSION['usuario_id'];
    $tamaño_archivo = filesize($archivoTmp);
    $tamaño_mb = round($tamaño_archivo / 1024 / 1024, 2);
    
    registrar_bitacora(
        $conexion, 
        $id_usuario, 
        12, // ID del objeto "Restaurar BD" (ajustar según tu tabla tbl_objetos)
        'RESTORE_START', 
        "Iniciando restauración de BD desde archivo: $nombreArchivo (Tamaño: {$tamaño_mb} MB)"
    );

    // Desactivar verificación de claves foráneas temporalmente
    $conexion->query("SET FOREIGN_KEY_CHECKS = 0");
    $conexion->query("SET sql_mode = ''");

    // Registrar inicio de operaciones críticas
    registrar_bitacora(
        $conexion, 
        $id_usuario, 
        12, 
        'RESTORE_DROP_TABLES', 
        "Iniciando eliminación de tablas existentes para restauración completa de BD"
    );

    // Eliminar todas las tablas existentes
    $tablesResult = @$conexion->query("SHOW TABLES");
    if ($tablesResult) {
        while ($row = $tablesResult->fetch_array()) {
            @$conexion->query("DROP TABLE IF EXISTS `$row[0]`");
        }
    }

    // Eliminar procedimientos y funciones existentes
    $routinesResult = @$conexion->query("SELECT routine_name, routine_type FROM information_schema.routines WHERE routine_schema = DATABASE()");
    if ($routinesResult) {
        while ($row = $routinesResult->fetch_assoc()) {
            @$conexion->query("DROP $row[routine_type] IF EXISTS `$row[routine_name]`");
        }
    }

    // Ejecutar el SQL del archivo línea por línea (sentencias separadas por ;)
    $sentencias = array_filter(array_map('trim', explode(";", $contenidoSQL)));
    $ejecutadas = 0;
    $errores = 0;

    foreach ($sentencias as $sentencia) {
        if (!empty($sentencia)) {
            if ($conexion->query($sentencia) === true) {
                $ejecutadas++;
            } else {
                $errores++;
            }
        }
    }

    // Reactivar verificación de claves foráneas
    $conexion->query("SET FOREIGN_KEY_CHECKS = 1");

    // Registrar éxito en bitácora
    registrar_bitacora(
        $conexion, 
        $id_usuario, 
        12, // ID del objeto "Restaurar BD"
        'RESTORE_SUCCESS', 
        "BD restaurada exitosamente. Archivo: $nombreArchivo. Sentencias ejecutadas: $ejecutadas. Errores: $errores. Tamaño: {$tamaño_mb} MB"
    );

    echo json_encode([
        'status' => 'success',
        'message' => "Base de datos restaurada completamente. Sentencias ejecutadas: $ejecutadas; errores: $errores."
    ]);

} catch (Exception $e) {
    if (isset($conexion)) {
        @$conexion->query("SET FOREIGN_KEY_CHECKS = 1");
    }
    
    // Registrar error en bitácora
    if (isset($conexion) && isset($_SESSION['usuario_id'])) {
        $id_usuario = $_SESSION['usuario_id'];
        $archivo_error = isset($nombreArchivo) ? $nombreArchivo : 'archivo_desconocido';
        registrar_bitacora(
            $conexion, 
            $id_usuario, 
            12, // ID del objeto "Restaurar BD"
            'RESTORE_ERROR', 
            "Error al restaurar BD desde archivo: $archivo_error. Error: " . $e->getMessage()
        );
    }
    
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>
