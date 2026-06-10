<?php
// Manejo global de errores para evitar 500
$logFile = __DIR__ . '/periodos_debug.log';

set_error_handler(function($severity, $message, $file, $line) use ($logFile) {
    $logEntry = date('Y-m-d H:i:s') . " - PHP Error: $message in $file:$line\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND);
    error_log("PHP Error in $file:$line - $message");
    
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => 'Error interno del servidor', 'debug' => "Error en línea $line"]);
    exit;
});

set_exception_handler(function($exception) use ($logFile) {
    $logEntry = date('Y-m-d H:i:s') . " - Exception: " . $exception->getMessage() . " in " . $exception->getFile() . ":" . $exception->getLine() . "\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND);
    error_log("Uncaught Exception: " . $exception->getMessage());
    
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => 'Error interno del servidor', 'debug' => $exception->getMessage()]);
    exit;
});

require_once __DIR__ . '/../config/errorlogs.php';
require_once __DIR__ . '/../config/roles.php';

\App\config\errorlogs::activa_error_logs();
session_start();

require 'con_db.php';

// Validación de sesión
if (empty($_SESSION['usuario']) || empty($_SESSION['usuario_id'])) {
    // Si es una petición AJAX, devolver JSON
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(401);
        echo json_encode([
            'success' => false, 
            'error' => 'Sesión expirada', 
            'redirect' => '../../public/index.php'
        ]);
        exit;
    } else {
        header('Location: ../../public/index.php?error=Debes iniciar sesión');
        exit;
    }
}

// Verificar y corregir rol_id si falta (sesión incompleta)
if (!isset($_SESSION['rol_id']) && !empty($_SESSION['usuario_id'])) {
    try {
        $userId = intval($_SESSION['usuario_id']);
        $roleStmt = $conexion->prepare("SELECT tbl_ms_roles_id_rol FROM tbl_usuarios WHERE id_usuario = ? LIMIT 1");
        if ($roleStmt) {
            $roleStmt->bind_param('i', $userId);
            $roleStmt->execute();
            $roleResult = $roleStmt->get_result();
            if ($roleResult && ($roleRow = $roleResult->fetch_assoc())) {
                $_SESSION['rol_id'] = intval($roleRow['tbl_ms_roles_id_rol']);
                $logEntry = date('Y-m-d H:i:s') . " - rol_id recuperado automáticamente: " . $_SESSION['rol_id'] . "\n";
                file_put_contents($logFile, $logEntry, FILE_APPEND);
            }
            $roleStmt->close();
        }
    } catch (Exception $e) {
        $logEntry = date('Y-m-d H:i:s') . " - Error recuperando rol_id: " . $e->getMessage() . "\n";
        file_put_contents($logFile, $logEntry, FILE_APPEND);
    }
}

// Validación final de rol_id
if (!isset($_SESSION['rol_id'])) {
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(401);
        echo json_encode([
            'success' => false, 
            'error' => 'Sesión incompleta, inicia sesión nuevamente', 
            'redirect' => '../../public/index.php'
        ]);
        exit;
    } else {
        header('Location: ../../public/index.php?error=Debes iniciar sesión');
        exit;
    }
}

// Establecer header de respuesta JSON temprano
header('Content-Type: application/json; charset=utf-8');

// Validación AJAX simplificada (solo para POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
        // Solo advertir, no bloquear
        error_log("Warning: POST request without AJAX headers detected");
    }
}

// Helper: obtener ID de objeto para bitácora
function _get_objeto_id($conexion, $nombre_objeto) {
    // Buscar el objeto en tbl_objetos
    $stmt = $conexion->prepare("SELECT id_objetos FROM tbl_objetos WHERE objeto = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('s', $nombre_objeto);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && ($row = $res->fetch_assoc())) {
            $id = intval($row['id_objetos']);
            $stmt->close();
            return $id;
        }
        $stmt->close();
    }

    // Si no existe, intentar crearlo con los campos mínimos
    $ins = $conexion->prepare("INSERT INTO tbl_objetos (objeto) VALUES (?)");
    if ($ins) {
        $ins->bind_param('s', $nombre_objeto);
        if ($ins->execute()) {
            $newId = intval($conexion->insert_id);
            $ins->close();
            return $newId;
        }
        $ins->close();
    }
    
    // Si no se pudo insertar, devolver null para omitir la bitácora sin romper
    return null;
}

// Helper: restablecer AUTO_INCREMENT para secuencia continua
function resetAutoIncrement($conexion) {
    try {
        // Obtener el máximo ID actual
        $result = $conexion->query("SELECT MAX(id_periodo) as max_id FROM tbl_periodo");
        if ($result && $row = $result->fetch_assoc()) {
            $nextId = intval($row['max_id']) + 1;
            $conexion->query("ALTER TABLE tbl_periodo AUTO_INCREMENT = $nextId");
            return $nextId;
        }
    } catch (Exception $e) {
        error_log("Error resetting AUTO_INCREMENT: " . $e->getMessage());
        return false;
    }
    return false;
}

// Helper: reorganizar IDs para hacerlos secuenciales (función administrativa)
function reorganizeSequentialIds($conexion) {
    try {
        $conexion->begin_transaction();
        
        // Obtener todos los períodos ordenados por ID actual
        $result = $conexion->query("SELECT id_periodo, nombre_periodo FROM tbl_periodo ORDER BY id_periodo");
        $periodos = [];
        while ($row = $result->fetch_assoc()) {
            $periodos[] = $row;
        }
        
        // Crear tabla temporal
        $conexion->query("CREATE TEMPORARY TABLE temp_periodo AS SELECT * FROM tbl_periodo WHERE 1=0");
        
        // Insertar con IDs secuenciales
        $newId = 1;
        foreach ($periodos as $periodo) {
            $stmt = $conexion->prepare("INSERT INTO temp_periodo SELECT ?, nombre_periodo, dias, descripcion, creado_en FROM tbl_periodo WHERE id_periodo = ?");
            $stmt->bind_param('ii', $newId, $periodo['id_periodo']);
            $stmt->execute();
            $newId++;
        }
        
        // Reemplazar tabla original
        $conexion->query("TRUNCATE TABLE tbl_periodo");
        $conexion->query("INSERT INTO tbl_periodo SELECT * FROM temp_periodo");
        $conexion->query("DROP TEMPORARY TABLE temp_periodo");
        
        // Resetear AUTO_INCREMENT
        $conexion->query("ALTER TABLE tbl_periodo AUTO_INCREMENT = $newId");
        
        $conexion->commit();
        return true;
        
    } catch (Exception $e) {
        $conexion->rollback();
        error_log("Error reorganizing IDs: " . $e->getMessage());
        return false;
    }
}

$action = $_REQUEST['action'] ?? '';

// Log de inicio de acción
$logEntry = date('Y-m-d H:i:s') . " - Periodos Action: $action, Method: " . $_SERVER['REQUEST_METHOD'] . "\n";
file_put_contents($logFile, $logEntry, FILE_APPEND);
error_log("Periodos Action: " . $action);

// Establecer header de respuesta JSON después de validaciones
header('Content-Type: application/json; charset=utf-8');

// Asegurarse de que sea una petición válida
if (isset($_GET['action']) && $_GET['action'] === 'test') {
    echo json_encode(['success' => true, 'message' => 'Endpoint funcionando', 'session' => !empty($_SESSION['usuario'])]);
    exit;
}

// --- LISTAR PERIODOS ---
if ($action === 'list') {
    if (!user_has_permission('suscripciones', PERM_READ)) {
        echo json_encode(['success' => false, 'error' => 'Sin permisos para ver períodos']);
        exit;
    }
    
    $sql = "SELECT id_periodo, nombre_periodo, dias, precio, descripcion, creado_en 
            FROM tbl_periodo 
            ORDER BY id_periodo";
    $result = $conexion->query($sql);
    
    $periodos = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $periodos[] = $row;
        }
    }
    
    echo json_encode(['success' => true, 'data' => $periodos]);
    exit;
}

// --- OBTENER UN PERIODO ---
if ($action === 'get') {
    if (!user_has_permission('suscripciones', PERM_READ)) {
        echo json_encode(['success' => false, 'error' => 'Sin permisos para ver períodos']);
        exit;
    }
    
    $id = $_GET['id'] ?? $_POST['id'] ?? '';
    if (!$id || !is_numeric($id)) {
        echo json_encode(['success' => false, 'error' => 'ID de período requerido']);
        exit;
    }
    
    $stmt = $conexion->prepare("SELECT id_periodo, nombre_periodo, dias, precio, descripcion FROM tbl_periodo WHERE id_periodo = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        echo json_encode(['success' => true, 'data' => $row]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Período no encontrado']);
    }
    exit;
}

// --- CREAR PERIODO ---
if ($action === 'add') {
    $logEntry = date('Y-m-d H:i:s') . " - Iniciando creación de período\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND);
    
    if (!user_has_permission('suscripciones', PERM_CREATE)) {
        $logEntry = date('Y-m-d H:i:s') . " - Sin permisos para crear períodos\n";
        file_put_contents($logFile, $logEntry, FILE_APPEND);
        echo json_encode(['success' => false, 'error' => 'Sin permisos para crear períodos']);
        exit;
    }
    
    try {
        error_log("periodos_actions.php: Iniciando creación de período");
        
        $nombre_periodo = trim($_POST['nombre_periodo'] ?? '');
        $dias = intval($_POST['dias'] ?? 0);
        $descripcion = trim($_POST['descripcion'] ?? '');
        $precio = floatval($_POST['precio'] ?? 0.00);
        
        error_log("periodos_actions.php: Datos recibidos - nombre: $nombre_periodo, dias: $dias, precio: $precio");
        
        // Validaciones
        if (empty($nombre_periodo)) {
            echo json_encode(['success' => false, 'error' => 'Nombre del período requerido']);
            exit;
        }
        
        if ($dias <= 0) {
            echo json_encode(['success' => false, 'error' => 'Los días deben ser un número mayor a 0']);
            exit;
        }
        
        // Verificar que el nombre no exista
        error_log("periodos_actions.php: Verificando si existe período con nombre: $nombre_periodo");
        $check = $conexion->prepare("SELECT id_periodo FROM tbl_periodo WHERE nombre_periodo = ?");
        $check->bind_param('s', $nombre_periodo);
        $check->execute();
        $checkResult = $check->get_result();
        
        if ($checkResult->num_rows > 0) {
            echo json_encode(['success' => false, 'error' => 'Ya existe un período con ese nombre']);
            exit;
        }
        
        // Insertar nuevo período
        error_log("periodos_actions.php: Insertando nuevo período");
        $stmt = $conexion->prepare("INSERT INTO tbl_periodo (nombre_periodo, dias, precio, descripcion, creado_en) VALUES (?, ?, ?, ?, NOW())");
        $stmt->bind_param('sids', $nombre_periodo, $dias, $precio, $descripcion);
        
        if ($stmt->execute()) {
            $insertedId = $conexion->insert_id;
            error_log("periodos_actions.php: Período insertado con ID: $insertedId");
            
            // Registrar en bitácora (simplificado)
            try {
                require_once __DIR__ . '/bitacora_helpers.php';
                $id_usuario = $_SESSION['usuario_id'];
                $obj_periodos_id = _get_objeto_id($conexion, 'PERIODOS');
                $descripcion_bitacora = "Se creó el período '$nombre_periodo' con $dias días (ID: $insertedId)";
                if ($obj_periodos_id) {
                    registrar_bitacora($conexion, $id_usuario, $obj_periodos_id, 'INSERT', $descripcion_bitacora);
                }
            } catch (Exception $bitacoraError) {
                error_log("periodos_actions.php: Error en bitácora: " . $bitacoraError->getMessage());
                // Continuar sin fallar
            }
            
            echo json_encode(['success' => true, 'message' => 'Período creado exitosamente', 'id' => $insertedId]);
        } else {
            error_log("periodos_actions.php: Error al insertar período: " . $conexion->error);
            echo json_encode(['success' => false, 'error' => 'Error al crear el período: ' . $conexion->error]);
        }
    } catch (Exception $e) {
        error_log("Error en periodos_actions.php add: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Error interno del servidor']);
    }
    exit;
}

// --- ACTUALIZAR PERIODO ---
if ($action === 'update') {
    if (!user_has_permission('suscripciones', PERM_UPDATE)) {
        echo json_encode(['success' => false, 'error' => 'Sin permisos para actualizar períodos']);
        exit;
    }
    
    $id = intval($_POST['id_periodo'] ?? 0);
    $nombre_periodo = trim($_POST['nombre_periodo'] ?? '');
    $dias = intval($_POST['dias'] ?? 0);
    $precio = floatval($_POST['precio'] ?? 0.00);
    $descripcion = trim($_POST['descripcion'] ?? '');
    
    // Validaciones
    if ($id <= 0) {
        echo json_encode(['success' => false, 'error' => 'ID de período requerido']);
        exit;
    }
    
    if (empty($nombre_periodo)) {
        echo json_encode(['success' => false, 'error' => 'Nombre del período requerido']);
        exit;
    }
    
    if ($dias <= 0) {
        echo json_encode(['success' => false, 'error' => 'Los días deben ser un número mayor a 0']);
        exit;
    }
    
    // Verificar que el período existe
    $check = $conexion->prepare("SELECT nombre_periodo, dias, precio, descripcion FROM tbl_periodo WHERE id_periodo = ?");
    $check->bind_param('i', $id);
    $check->execute();
    $checkResult = $check->get_result();
    
    if ($checkResult->num_rows === 0) {
        echo json_encode(['success' => false, 'error' => 'Período no encontrado']);
        exit;
    }
    
    $originalData = $checkResult->fetch_assoc();
    
    // Verificar que el nombre no exista en otro registro
    $check2 = $conexion->prepare("SELECT id_periodo FROM tbl_periodo WHERE nombre_periodo = ? AND id_periodo != ?");
    $check2->bind_param('si', $nombre_periodo, $id);
    $check2->execute();
    $check2Result = $check2->get_result();
    
    if ($check2Result->num_rows > 0) {
        echo json_encode(['success' => false, 'error' => 'Ya existe otro período con ese nombre']);
        exit;
    }
    
    // Actualizar período
    $stmt = $conexion->prepare("UPDATE tbl_periodo SET nombre_periodo = ?, dias = ?, precio = ?, descripcion = ? WHERE id_periodo = ?");
    $stmt->bind_param('sidsi', $nombre_periodo, $dias, $precio, $descripcion, $id);
    
    if ($stmt->execute()) {
        // Registrar cambios en bitácora
        require_once __DIR__ . '/../db/sql.php';
        $id_usuario = $_SESSION['usuario_id'];
        $cambios = [];
        
        if ($originalData['nombre_periodo'] !== $nombre_periodo) {
            $cambios[] = "nombre: '{$originalData['nombre_periodo']}' → '$nombre_periodo'";
        }
        if ($originalData['dias'] != $dias) {
            $cambios[] = "días: {$originalData['dias']} → $dias";
        }
        if ($originalData['precio'] != $precio) {
            $cambios[] = "precio: {$originalData['precio']} → $precio";
        }
        if ($originalData['descripcion'] !== $descripcion) {
            $cambios[] = "descripción: '{$originalData['descripcion']}' → '$descripcion'";
        }
        
        if (!empty($cambios)) {
            $obj_periodos_id = _get_objeto_id($conexion, 'PERIODOS');
            $descripcion_bitacora = "Se actualizó el período ID $id: " . implode(', ', $cambios);
            if ($obj_periodos_id) {
                registrar_bitacora($conexion, $id_usuario, $obj_periodos_id, 'UPDATE', $descripcion_bitacora);
            }
        }
        
        echo json_encode(['success' => true, 'message' => 'Período actualizado exitosamente']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Error al actualizar el período: ' . $conexion->error]);
    }
    exit;
}

// --- ELIMINAR PERIODO ---
if ($action === 'delete') {
    if (!user_has_permission('suscripciones', PERM_DELETE)) {
        echo json_encode(['success' => false, 'error' => 'Sin permisos para eliminar períodos']);
        exit;
    }
    
    $id = intval($_POST['id'] ?? 0);
    
    if ($id <= 0) {
        echo json_encode(['success' => false, 'error' => 'ID de período requerido']);
        exit;
    }
    
    // Verificar si el período está siendo usado por algún plan
    $checkUsage = $conexion->prepare("SELECT COUNT(*) as count FROM tbl_plan WHERE id_periodo = ?");
    $checkUsage->bind_param('i', $id);
    $checkUsage->execute();
    $usageResult = $checkUsage->get_result();
    $usage = $usageResult->fetch_assoc();
    
    if ($usage['count'] > 0) {
        echo json_encode(['success' => false, 'error' => 'No se puede eliminar el período porque está siendo usado por uno o más planes']);
        exit;
    }
    
    // Obtener información del período para la bitácora
    $getInfo = $conexion->prepare("SELECT nombre_periodo FROM tbl_periodo WHERE id_periodo = ?");
    $getInfo->bind_param('i', $id);
    $getInfo->execute();
    $infoResult = $getInfo->get_result();
    
    if ($infoResult->num_rows === 0) {
        echo json_encode(['success' => false, 'error' => 'Período no encontrado']);
        exit;
    }
    
    $periodoInfo = $infoResult->fetch_assoc();
    
    // Eliminar período
    $stmt = $conexion->prepare("DELETE FROM tbl_periodo WHERE id_periodo = ?");
    $stmt->bind_param('i', $id);
    
    if ($stmt->execute()) {
        // Registrar en bitácora
        require_once __DIR__ . '/bitacora_helpers.php';
        $id_usuario = $_SESSION['usuario_id'];
        $obj_periodos_id = _get_objeto_id($conexion, 'PERIODOS');
        $descripcion_bitacora = "Se eliminó el período '{$periodoInfo['nombre_periodo']}' (ID: $id)";
        if ($obj_periodos_id) {
            registrar_bitacora($conexion, $id_usuario, $obj_periodos_id, 'DELETE', $descripcion_bitacora);
        }
        
        echo json_encode(['success' => true, 'message' => 'Período eliminado exitosamente']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Error al eliminar el período: ' . $conexion->error]);
    }
    exit;
}

// --- REORGANIZAR IDS SECUENCIALES (FUNCIÓN ADMINISTRATIVA) ---
if ($action === 'reorganize_ids') {
    if (!user_has_permission('suscripciones', PERM_DELETE)) {
        echo json_encode(['success' => false, 'error' => 'Sin permisos para reorganizar IDs']);
        exit;
    }
    
    if (reorganizeSequentialIds($conexion)) {
        echo json_encode(['success' => true, 'message' => 'IDs reorganizados exitosamente']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Error al reorganizar IDs']);
    }
    exit;
}

// Si no se encontró ninguna acción válida
echo json_encode(['success' => false, 'error' => 'Acción no válida']);
?>
