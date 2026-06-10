<?php
// Endpoints AJAX para el módulo de suscripciones
session_start();
require 'con_db.php';
require_once __DIR__ . '/bitacora_helpers.php';
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/errorlogs.php';// Ajusta la ruta según tu estructura

use App\config\errorlogs;
errorlogs::activa_error_logs();

// Función helper para obtener usuario actual o fallback
function getCurrentUserId($conexion) {
    // Prioridad 1: Usuario de sesión
    if (!empty($_SESSION['usuario_id'])) {
        return intval($_SESSION['usuario_id']);
    }
    
    // Prioridad 2: Primer usuario disponible
    $userResult = $conexion->query("SELECT id_usuario FROM tbl_ms_usuario ORDER BY id_usuario LIMIT 1");
    if ($userResult && ($userRow = $userResult->fetch_assoc())) {
        return intval($userRow['id_usuario']);
    }
    
    // Fallback: ID genérico (debería no llegar nunca aquí en un sistema real)
    return 1;
}

// Función helper para obtener parámetro MAX_SUSCRIPCIONES_POR_CLIENTE
function getMaxSuscripcionesPorCliente($conexion) {
    $check = $conexion->prepare("SELECT valor FROM tbl_ms_parametros WHERE parametro = 'MAX_SUSCRIPCIONES_POR_CLIENTE'");
    $check->execute();
    $result = $check->get_result();
    $row = $result->fetch_assoc();
    $check->close();
    
    // Si no existe, crearlo automáticamente
    if (!$row) {
        $usuario_id = getCurrentUserId($conexion);
        $insert = $conexion->prepare("INSERT INTO tbl_ms_parametros (parametro, valor, id_usuario, fecha_creado) VALUES (?, ?, ?, NOW())");
        $nombre_param = 'MAX_SUSCRIPCIONES_POR_CLIENTE';
        $valor_default = '1';
        $insert->bind_param('ssi', $nombre_param, $valor_default, $usuario_id);
        $insert->execute();
        $insert->close();
        error_log("DEBUG: Parámetro MAX_SUSCRIPCIONES_POR_CLIENTE creado con valor por defecto: 1");
        return 1; // Valor por defecto
    }
    
    $valor = intval($row['valor']);
    error_log("DEBUG: Parámetro MAX_SUSCRIPCIONES_POR_CLIENTE encontrado con valor: $valor");
    return $valor;
}

// Función helper para obtener parámetro DEFAULT_PAGE_SIZE
function getDefaultPageSize($conexion) {
    $check = $conexion->prepare("SELECT valor FROM tbl_ms_parametros WHERE parametro = 'DEFAULT_PAGE_SIZE'");
    $check->execute();
    $result = $check->get_result();
    $row = $result->fetch_assoc();
    $check->close();
    
    // Si no existe, crearlo automáticamente
    if (!$row) {
        $usuario_id = getCurrentUserId($conexion);
        $insert = $conexion->prepare("INSERT INTO tbl_ms_parametros (parametro, valor, id_usuario, fecha_creado) VALUES (?, ?, ?, NOW())");
        $nombre_param = 'DEFAULT_PAGE_SIZE';
        $valor_default = '10';
        $insert->bind_param('ssi', $nombre_param, $valor_default, $usuario_id);
        $insert->execute();
        $insert->close();
        return 10; // Valor por defecto
    }
    
    return intval($row['valor']);
}

// Función helper para obtener parámetro ENVIAR_RECORDATORIO_DIARIO
function getEnviarRecordatorioDiario($conexion) {
    $check = $conexion->prepare("SELECT valor FROM tbl_ms_parametros WHERE parametro = 'ENVIAR_RECORDATORIO_DIARIO'");
    $check->execute();
    $result = $check->get_result();
    $row = $result->fetch_assoc();
    $check->close();
    
    // Si no existe, crearlo automáticamente
    if (!$row) {
        $usuario_id = getCurrentUserId($conexion);
        $insert = $conexion->prepare("INSERT INTO tbl_ms_parametros (parametro, valor, id_usuario, fecha_creado) VALUES (?, ?, ?, NOW())");
        $nombre_param = 'ENVIAR_RECORDATORIO_DIARIO';
        $valor_default = '1'; // 1 = activado, 0 = desactivado
        $insert->bind_param('ssi', $nombre_param, $valor_default, $usuario_id);
        $insert->execute();
        $insert->close();
        return 1; // Valor por defecto
    }
    
    return intval($row['valor']);
}

// Control de acceso: requiere sesión válida
if (empty($_SESSION['usuario']) || empty($_SESSION['usuario_id'])) {
    // Si la petición es AJAX, devolver JSON de error
    if (isset($_GET['action']) || isset($_POST['action'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Debes iniciar sesión']);
        exit;
    }
    // Si se accede por navegador, redirigir
    header('Location: ../../public/index.php?error=Debes iniciar sesión');
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Resolver el ID de objeto en bitácora para este módulo (Suscripciones)
function _get_objeto_id($conexion, $nombre_objeto) {
    // Buscar objeto
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
    // Si no se pudo insertar (faltan columnas obligatorias), devolver null para omitir la bitácora sin romper
    return null;
}

$OBJ_SUSCRIPCIONES_ID = _get_objeto_id($conexion, 'SUSCRIPCIONES');

// Helper: asegurar que existe registro en tbl_plan para el id_plan y nombre_plan dados
function asegurarRegistroPlan($id_plan, $nombre_plan) {
    global $conexion;
    
    if (empty($id_plan) || empty($nombre_plan)) {
        return false;
    }
    
    // Verificar si ya existe el registro en tbl_plan
    $check = $conexion->prepare("SELECT id_plan FROM tbl_plan WHERE id_plan = ? LIMIT 1");
    $check->bind_param('i', $id_plan);
    $check->execute();
    $result = $check->get_result();
    $existe = $result->num_rows > 0;
    $check->close();
    
    if ($existe) {
        // El registro ya existe, actualizar nombre_plan si es necesario
        $update = $conexion->prepare("UPDATE tbl_plan SET nombre_plan = ? WHERE id_plan = ?");
        $update->bind_param('si', $nombre_plan, $id_plan);
        $ok = $update->execute();
        $update->close();
        
        error_log("Registro en tbl_plan actualizado - ID: $id_plan, Nombre: $nombre_plan, Éxito: " . ($ok ? 'SI' : 'NO'));
        return $ok;
    } else {
        // El registro no existe, crearlo
        $insert = $conexion->prepare("INSERT INTO tbl_plan (id_plan, nombre_plan, id_periodo, activo) VALUES (?, ?, ?, 1)");
        $insert->bind_param('isi', $id_plan, $nombre_plan, $id_plan); // Usar id_plan como id_periodo para mantener consistencia
        $ok = $insert->execute();
        $insert->close();
        
        error_log("Registro en tbl_plan creado - ID: $id_plan, Nombre: $nombre_plan, Éxito: " . ($ok ? 'SI' : 'NO'));
        return $ok;
    }
}

// Helper: obtener precio desde la base de datos según el nombre del plan
function getPrecioPlan($nombre_plan) {
    global $conexion;
    
    if (empty($nombre_plan)) {
        return 0;
    }
    
    // Buscar primero en tbl_periodo (exacto)
    $stmt = $conexion->prepare("SELECT precio FROM tbl_periodo WHERE nombre_periodo = ? LIMIT 1");
    $stmt->bind_param('s', $nombre_plan);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $precio = floatval($row['precio']);
        $stmt->close();
        return $precio;
    }
    $stmt->close();
    
    // Buscar en tbl_periodo con conversión a mayúsculas
    $nombre_mayus = strtoupper($nombre_plan);
    $stmt = $conexion->prepare("SELECT precio FROM tbl_periodo WHERE nombre_periodo = ? LIMIT 1");
    $stmt->bind_param('s', $nombre_mayus);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $precio = floatval($row['precio']);
        $stmt->close();
        return $precio;
    }
    $stmt->close();
    
    // Si no se encuentra, buscar en tbl_plan a través de tbl_periodo
    $stmt2 = $conexion->prepare("SELECT per.precio FROM tbl_plan tp 
        JOIN tbl_periodo per ON per.id_periodo = tp.id_periodo 
        WHERE tp.nombre_plan = ? LIMIT 1");
    $stmt2->bind_param('s', $nombre_plan);
    $stmt2->execute();
    $result2 = $stmt2->get_result();
    
    if ($row2 = $result2->fetch_assoc()) {
        $precio = floatval($row2['precio']);
        $stmt2->close();
        return $precio;
    }
    $stmt2->close();
    
    // Fallback con precios fijos si no se encuentra en BD
    $precios = [
        'Diario' => 70, 'DIARIO' => 70,
        'Semanal' => 400, 'SEMANAL' => 400,
        'Quincenal' => 500, 'QUINCENAL' => 500,
        'Mensual' => 700, 'MENSUAL' => 700,
        'Semestral' => 3500, 'SEMESTRAL' => 3500,
        'Anual' => 7000, 'ANUAL' => 7000
    ];
    return $precios[$nombre_plan] ?? 0;
}

// Helper: obtener duración en días desde la base de datos según el nombre del plan
function getDuracionPlan($nombre_plan) {
    global $conexion;
    
    if (empty($nombre_plan)) {
        return 30;
    }
    
    // Buscar primero en tbl_periodo (exacto)
    $stmt = $conexion->prepare("SELECT dias FROM tbl_periodo WHERE nombre_periodo = ? LIMIT 1");
    $stmt->bind_param('s', $nombre_plan);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $dias = intval($row['dias']);
        $stmt->close();
        return $dias;
    }
    $stmt->close();
    
    // Buscar en tbl_periodo con conversión a mayúsculas
    $nombre_mayus = strtoupper($nombre_plan);
    $stmt = $conexion->prepare("SELECT dias FROM tbl_periodo WHERE nombre_periodo = ? LIMIT 1");
    $stmt->bind_param('s', $nombre_mayus);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $dias = intval($row['dias']);
        $stmt->close();
        return $dias;
    }
    $stmt->close();
    
    // Si no se encuentra, buscar en tbl_plan a través de tbl_periodo
    $stmt2 = $conexion->prepare("SELECT per.dias FROM tbl_plan tp 
        JOIN tbl_periodo per ON per.id_periodo = tp.id_periodo 
        WHERE tp.nombre_plan = ? LIMIT 1");
    $stmt2->bind_param('s', $nombre_plan);
    $stmt2->execute();
    $result2 = $stmt2->get_result();
    
    if ($row2 = $result2->fetch_assoc()) {
        $dias = intval($row2['dias']);
        $stmt2->close();
        return $dias;
    }
    $stmt2->close();
    
    // Fallback con duraciones fijas si no se encuentra en BD
    $duraciones = [
        'Diario' => 1, 'DIARIO' => 1,
        'Semanal' => 7, 'SEMANAL' => 7,
        'Quincenal' => 15, 'QUINCENAL' => 15,
        'Mensual' => 30, 'MENSUAL' => 30,
        'Semestral' => 180, 'SEMESTRAL' => 180,
        'Anual' => 365, 'ANUAL' => 365
    ];
    return $duraciones[$nombre_plan] ?? 30;
}

// Helper: actualizar estados de suscripción y clientes según pagos y fecha_fin
function updatePaymentStatuses($conexion) {
    // Obtener ids de estados
    $rVenc = $conexion->query("SELECT id_estadoSuscripcion FROM tbl_estado_suscripcion WHERE nombre_estado='VENCIDA' LIMIT 1");
    $idVenc = ($rVenc && ($rv=$rVenc->fetch_assoc())) ? intval($rv['id_estadoSuscripcion']) : null;
    $rAct = $conexion->query("SELECT id_estadoSuscripcion FROM tbl_estado_suscripcion WHERE nombre_estado='ACTIVA' LIMIT 1");
    $idAct = ($rAct && ($ra=$rAct->fetch_assoc())) ? intval($ra['id_estadoSuscripcion']) : null;
    $rCan = $conexion->query("SELECT id_estadoSuscripcion FROM tbl_estado_suscripcion WHERE nombre_estado='CANCELADA' LIMIT 1");
    $idCan = ($rCan && ($rc=$rCan->fetch_assoc())) ? intval($rc['id_estadoSuscripcion']) : null;

    // NUEVA LÓGICA: Marcar como no pagado las suscripciones que han vencido
    // Eliminar pagos de suscripciones cuya fecha_fin ya pasó
    $sqlEliminarPagosVencidos = "DELETE p FROM tbl_pago p 
        INNER JOIN tbl_suscripcion s ON p.id_suscripcion = s.id_suscripcion 
        WHERE DATE(s.fecha_fin) < CURDATE()";
    $resElimPagos = $conexion->query($sqlEliminarPagosVencidos);
    $logPath = __DIR__ . '/../../logs/sus_actions.log';
    if ($resElimPagos === false) {
        @file_put_contents($logPath, date('c') . " | updatePaymentStatuses ERROR eliminar_pagos_vencidos: " . $conexion->error . " -- SQL: " . $sqlEliminarPagosVencidos . "\n", FILE_APPEND);
    } else {
        @file_put_contents($logPath, date('c') . " | updatePaymentStatuses eliminar_pagos_vencidos affected_rows=" . $conexion->affected_rows . "\n", FILE_APPEND);
    }

    // 0) Primero, establecer ACTIVA para los casos claramente dentro del periodo (fecha_fin > CURDATE()) Y que tengan pago
    if ($idAct) {
        $sqlAct = "UPDATE tbl_suscripcion s
            INNER JOIN tbl_pago p ON p.id_suscripcion = s.id_suscripcion
            JOIN tbl_estado_suscripcion es ON s.id_estadoSuscripcion = es.id_estadoSuscripcion
            SET s.id_estadoSuscripcion = $idAct
            WHERE s.fecha_fin > CURDATE()
              AND es.nombre_estado <> 'CANCELADA'
              AND es.nombre_estado <> 'ACTIVA'";
        $resAct = $conexion->query($sqlAct);
        if ($resAct === false) {
            @file_put_contents($logPath, date('c') . " | updatePaymentStatuses ERROR sqlAct: " . $conexion->error . " -- SQL: " . $sqlAct . "\n", FILE_APPEND);
        } else {
            @file_put_contents($logPath, date('c') . " | updatePaymentStatuses sqlAct affected_rows=" . $conexion->affected_rows . "\n", FILE_APPEND);
        }
    }

    // 1) Si fecha_fin <= hoy Y no tiene pago -> VENCIDA
    if ($idVenc) {
        $sqlVencToday = "UPDATE tbl_suscripcion s
            LEFT JOIN tbl_pago p ON p.id_suscripcion = s.id_suscripcion
            JOIN tbl_estado_suscripcion es ON s.id_estadoSuscripcion = es.id_estadoSuscripcion
            SET s.id_estadoSuscripcion = $idVenc
            WHERE DATE(s.fecha_fin) <= CURDATE()
              AND es.nombre_estado <> 'CANCELADA'
              AND p.id_pago IS NULL";
        $resVToday = $conexion->query($sqlVencToday);
        if ($resVToday === false) {
            @file_put_contents($logPath, date('c') . " | updatePaymentStatuses ERROR sqlVencToday: " . $conexion->error . " -- SQL: " . $sqlVencToday . "\n", FILE_APPEND);
        } else {
            @file_put_contents($logPath, date('c') . " | updatePaymentStatuses sqlVencToday affected_rows=" . $conexion->affected_rows . "\n", FILE_APPEND);
        }
    }
}

if ($action === 'update_auto_status') {
    // Actualizar estados automáticamente
    updatePaymentStatuses($conexion);
    echo json_encode(['success' => true, 'message' => 'Estados actualizados automáticamente']);
    exit;
}

if ($action === 'clients') {
    // Asegurar que los estados reflejen pagos pendientes antes de listar
    updatePaymentStatuses($conexion);
    $res = $conexion->query("SELECT id_cliente, nombre_cliente, correo_electronico, id_estadoCliente FROM tbl_cliente ORDER BY nombre_cliente");
    $out = [];
    while ($r = $res->fetch_assoc()) $out[] = $r;
    echo json_encode($out);
    exit;
}

if ($action === 'search_clients') {
    // Endpoint para búsqueda de clientes con paginación (para Select2 AJAX)
    updatePaymentStatuses($conexion);
    
    $search = $_GET['search'] ?? '';
    $page = intval($_GET['page'] ?? 1);
    $page_size = intval($_GET['page_size'] ?? 20);
    $offset = ($page - 1) * $page_size;
    
    // Construir consulta con búsqueda
    if (!empty($search)) {
        $sql = "SELECT id_cliente, nombre_cliente, correo_electronico, id_estadoCliente 
                FROM tbl_cliente 
                WHERE nombre_cliente LIKE ? OR correo_electronico LIKE ?
                ORDER BY nombre_cliente 
                LIMIT ? OFFSET ?";
        $search_param = '%' . $search . '%';
        $stmt = $conexion->prepare($sql);
        $stmt->bind_param('ssii', $search_param, $search_param, $page_size, $offset);
    } else {
        $sql = "SELECT id_cliente, nombre_cliente, correo_electronico, id_estadoCliente 
                FROM tbl_cliente 
                ORDER BY nombre_cliente 
                LIMIT ? OFFSET ?";
        $stmt = $conexion->prepare($sql);
        $stmt->bind_param('ii', $page_size, $offset);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $out = [];
    while ($r = $result->fetch_assoc()) {
        $out[] = $r;
    }
    $stmt->close();
    
    // Verificar si hay más resultados
    if (!empty($search)) {
        $count_sql = "SELECT COUNT(*) as total FROM tbl_cliente WHERE nombre_cliente LIKE ? OR correo_electronico LIKE ?";
        $count_stmt = $conexion->prepare($count_sql);
        $count_stmt->bind_param('ss', $search_param, $search_param);
    } else {
        $count_sql = "SELECT COUNT(*) as total FROM tbl_cliente";
        $count_stmt = $conexion->prepare($count_sql);
    }
    
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $total = $count_result->fetch_assoc()['total'];
    $count_stmt->close();
    
    $has_more = ($offset + $page_size) < $total;
    
    echo json_encode([
        'data' => $out,
        'pagination' => [
            'more' => $has_more,
            'total' => $total,
            'page' => $page,
            'page_size' => $page_size
        ]
    ]);
    exit;
}

if ($action === 'plans') {
    // Obtener todos los períodos disponibles de la tabla tbl_periodo, incluyendo el precio real
    $stmt = $conexion->prepare("SELECT id_periodo as id_plan, nombre_periodo, dias, precio as precio_base FROM tbl_periodo ORDER BY dias ASC");
    $stmt->execute();
    $result = $stmt->get_result();
    $out = [];
    while ($row = $result->fetch_assoc()) {
        // Convertir nombre a formato título (Primera letra mayúscula, resto minúsculas)
        $nombre_titulo = ucfirst(strtolower($row['nombre_periodo']));
        $out[] = [
            'id_plan' => intval($row['id_plan']),
            'nombre_plan' => $nombre_titulo,
            'dias' => intval($row['dias']),
            'precio_base' => floatval($row['precio_base'])
        ];
    }
    $stmt->close();
    
    error_log("Planes obtenidos de tbl_periodo (formato título): " . json_encode($out));
    echo json_encode($out);
    exit;
}

if ($action === 'get_precio') {
    // Devolver el precio de un plan específico
    $plan_name = $_GET['plan'] ?? '';
    $precio = getPrecioPlan($plan_name);
    echo json_encode(['precio' => $precio]);
    exit;
}

if ($action === 'get_duracion') {
    // Devolver la duración en días de un plan específico
    $plan_name = $_GET['plan'] ?? '';
    $duracion = getDuracionPlan($plan_name);
    echo json_encode(['duracion' => $duracion]);
    exit;
}

if ($action === 'debug_limits') {
    // Acción especial para debuggear el sistema de límites
    $correo_electronico = $_GET['email'] ?? $_POST['email'] ?? '';
    
    echo "<h3>Debug del sistema de límites</h3>";
    
    // Verificar parámetro
    $max_permitidas = getMaxSuscripcionesPorCliente($conexion);
    echo "<p><strong>MAX_SUSCRIPCIONES_POR_CLIENTE:</strong> $max_permitidas</p>";
    
    if ($correo_electronico) {
        // Buscar cliente
        $checkClient = $conexion->prepare("SELECT id_cliente, nombre_cliente FROM tbl_cliente WHERE correo_electronico = ? LIMIT 1");
        $checkClient->bind_param('s', $correo_electronico);
        $checkClient->execute();
        $clientResult = $checkClient->get_result();
        
        if ($clientResult->num_rows > 0) {
            $clientData = $clientResult->fetch_assoc();
            $id_cliente = $clientData['id_cliente'];
            $nombre_cliente = $clientData['nombre_cliente'];
            
            echo "<p><strong>Cliente encontrado:</strong> $nombre_cliente (ID: $id_cliente)</p>";
            
            // Verificar todas las suscripciones
            $all_subs = $conexion->prepare("
                SELECT s.id_suscripcion, s.fecha_inicio, s.fecha_fin, 
                       es.nombre_estado, es.id_estadoSuscripcion,
                       CASE WHEN s.fecha_fin >= CURDATE() THEN 'Vigente' ELSE 'Vencida' END as vigencia
                FROM tbl_suscripcion s 
                JOIN tbl_estado_suscripcion es ON s.id_estadoSuscripcion = es.id_estadoSuscripcion 
                WHERE s.id_cliente = ?
                ORDER BY s.fecha_fin DESC
            ");
            $all_subs->bind_param('i', $id_cliente);
            $all_subs->execute();
            $all_result = $all_subs->get_result();
            
            echo "<h4>Todas las suscripciones:</h4><ul>";
            $total_count = 0;
            while ($sub = $all_result->fetch_assoc()) {
                $total_count++;
                echo "<li>ID: {$sub['id_suscripcion']} | Estado: {$sub['nombre_estado']} | Vigencia: {$sub['vigencia']} | Fin: {$sub['fecha_fin']}</li>";
            }
            echo "</ul>";
            
            echo "<p><strong>Total suscripciones del cliente:</strong> $total_count</p>";
            echo "<p><strong>¿Puede agregar más?</strong> " . ($total_count < $max_permitidas ? "SÍ" : "NO") . " (Límite: $max_permitidas por cliente)</p>";
            echo "<p><em>Nota: Se cuentan TODAS las suscripciones, sin importar el estado.</em></p>";
            
        } else {
            echo "<p><strong>Cliente no encontrado para el email:</strong> $correo_electronico</p>";
        }
    } else {
        echo "<p>Agregue ?email=correo@ejemplo.com para debuggear un cliente específico</p>";
    }
    
    exit;
}

if ($action === 'check_limit') {
    // Verificar límite de suscripciones por cliente antes de crear nueva
    $correo_electronico = trim($_POST['correo_electronico'] ?? '');
    
    if (empty($correo_electronico)) {
        echo json_encode(['success' => false, 'error' => 'Correo electrónico requerido']);
        exit;
    }
    
    // Buscar cliente por correo
    $checkClient = $conexion->prepare("SELECT id_cliente, nombre_cliente FROM tbl_cliente WHERE correo_electronico = ? LIMIT 1");
    $checkClient->bind_param('s', $correo_electronico);
    $checkClient->execute();
    $clientResult = $checkClient->get_result();
    
    if ($clientResult->num_rows === 0) {
        // Cliente nuevo, puede proceder
        echo json_encode([
            'success' => true, 
            'can_add' => true, 
            'message' => 'Cliente nuevo, puede crear suscripción',
            'is_new_client' => true
        ]);
        $checkClient->close();
        exit;
    }
    
    $clientData = $clientResult->fetch_assoc();
    $id_cliente = intval($clientData['id_cliente']);
    $nombre_cliente = $clientData['nombre_cliente'];
    $checkClient->close();
    
    // Obtener límite máximo permitido
    $max_permitidas = getMaxSuscripcionesPorCliente($conexion);
    
    // Verificar TODAS las suscripciones del cliente (sin importar estado)
    $checkActive = $conexion->prepare("
        SELECT COUNT(*) as total_suscripciones, 
               GROUP_CONCAT(CONCAT('ID:', s.id_suscripcion, ' Estado:', es.nombre_estado, ' Fin:', DATE_FORMAT(s.fecha_fin, '%d/%m/%Y')) SEPARATOR '; ') as detalles
        FROM tbl_suscripcion s 
        JOIN tbl_estado_suscripcion es ON s.id_estadoSuscripcion = es.id_estadoSuscripcion 
        WHERE s.id_cliente = ?
    ");
    $checkActive->bind_param('i', $id_cliente);
    $checkActive->execute();
    $activeResult = $checkActive->get_result();
    $activeData = $activeResult->fetch_assoc();
    $checkActive->close();
    
    $total_suscripciones = intval($activeData['total_suscripciones']);
    $detalles_suscripciones = $activeData['detalles'] ?? '';
    
    // Log adicional para check_limit
    error_log("DEBUG CHECK_LIMIT: Cliente $id_cliente tiene $total_suscripciones suscripciones en total. Detalles: $detalles_suscripciones");
    
    if ($total_suscripciones >= $max_permitidas) {
        // No puede agregar más
        echo json_encode([
            'success' => true,
            'can_add' => false,
            'message' => "El cliente {$nombre_cliente} ya tiene {$total_suscripciones} suscripción(es) registrada(s). Límite máximo: {$max_permitidas}.",
            'details' => [
                'cliente' => $nombre_cliente,
                'total_suscripciones' => $total_suscripciones,
                'max_permitidas' => $max_permitidas,
                'suscripciones' => $detalles_suscripciones
            ]
        ]);
    } else {
        // Puede agregar más
        echo json_encode([
            'success' => true,
            'can_add' => true,
            'message' => "Puede crear suscripción. Cliente tiene {$total_suscripciones}/{$max_permitidas} suscripciones registradas.",
            'details' => [
                'cliente' => $nombre_cliente,
                'total_suscripciones' => $total_suscripciones,
                'max_permitidas' => $max_permitidas
            ]
        ]);
    }
    exit;
}

if ($action === 'get_plan_info') {
    // Devolver precio y duración de un plan específico
    $plan_name = $_GET['plan'] ?? '';
    
    // Intentar obtener directamente del nombre
    $precio = getPrecioPlan($plan_name);
    $duracion = getDuracionPlan($plan_name);
    
    // Si no se encontró, intentar con formato en mayúsculas
    if ($precio == 0 && $duracion == 30) {
        $plan_name_mayus = strtoupper($plan_name);
        $precio = getPrecioPlan($plan_name_mayus);
        $duracion = getDuracionPlan($plan_name_mayus);
    }
    
    error_log("get_plan_info - Plan: '$plan_name', Precio: $precio, Duración: $duracion");
    
    echo json_encode(['precio' => $precio, 'duracion' => $duracion]);
    exit;
}

if ($action === 'list') {
    // Asegurar que los estados reflejen pagos pendientes antes de listar
    updatePaymentStatuses($conexion);
    
    $client = $_GET['client'] ?? '';
    $plan = $_GET['plan'] ?? '';
    $status = $_GET['status'] ?? '';
    $from = $_GET['from'] ?? '';
    $to = $_GET['to'] ?? '';
    
    // Parámetros de paginación
    $page = intval($_GET['page'] ?? 1);
    $pageSize = intval($_GET['pageSize'] ?? 0);
    
    // Si no se especifica pageSize, usar el parámetro por defecto
    if ($pageSize <= 0) {
        $pageSize = getDefaultPageSize($conexion);
    }
    
    $offset = ($page - 1) * $pageSize;
    
    // Log de debug
    error_log("=== INICIO CONSULTA PAGINACIÓN ===");
    error_log("Parámetros recibidos: " . print_r($_GET, true));
    error_log("Paginación - Página: $page, Tamaño: $pageSize, Offset: $offset");
    error_log("Filtros - Client: '$client', Plan: '$plan', Status: '$status', From: '$from', To: '$to'");

    $sql = "SELECT s.id_suscripcion, s.id_cliente, c.nombre_cliente, 
        s.id_plan,
        tp.nombre_plan as plan_nombre,
        per.nombre_periodo as periodo_nombre,
        CASE 
            WHEN tp.nombre_plan IS NOT NULL THEN tp.nombre_plan
            WHEN per.nombre_periodo IS NOT NULL THEN CONCAT(UPPER(SUBSTRING(per.nombre_periodo, 1, 1)), LOWER(SUBSTRING(per.nombre_periodo, 2)))
            ELSE 'Sin plan' 
        END as nombre_plan, 
        s.fecha_inicio, s.fecha_fin, s.precio, es.nombre_estado,
               CASE WHEN pago.id_pago IS NOT NULL THEN 1 ELSE 0 END as pagado,
               DATEDIFF(s.fecha_fin, CURDATE()) as dias_restantes,
               CASE 
                 WHEN DATEDIFF(s.fecha_fin, CURDATE()) > 0 THEN CONCAT('Quedan ', DATEDIFF(s.fecha_fin, CURDATE()), ' días')
                 WHEN DATEDIFF(s.fecha_fin, CURDATE()) = 0 THEN 'Vence hoy'
                 ELSE CONCAT('Vencida hace ', ABS(DATEDIFF(s.fecha_fin, CURDATE())), ' días')
               END as estado_tiempo
        FROM tbl_suscripcion s
            JOIN tbl_cliente c ON c.id_cliente = s.id_cliente
            LEFT JOIN tbl_plan tp ON tp.id_plan = s.id_plan
            LEFT JOIN tbl_periodo per ON per.id_periodo = s.id_plan
            LEFT JOIN tbl_estado_suscripcion es ON es.id_estadoSuscripcion = s.id_estadoSuscripcion
            LEFT JOIN tbl_pago pago ON pago.id_suscripcion = s.id_suscripcion
            WHERE 1=1";
    $params = [];
    if ($client !== '') { $sql .= " AND s.id_cliente = ?"; $params[] = $client; }
    if ($plan !== '') {
        if (is_numeric($plan)) {
            $sql .= " AND s.id_plan = ?"; $params[] = $plan;
        } else {
            // Filtrar por nombre_plan de tbl_plan o nombre_periodo de tbl_periodo
            $sql .= " AND (tp.nombre_plan = ? OR per.nombre_periodo = ?)";
            $params[] = $plan;
            $params[] = strtoupper($plan); // tbl_periodo generalmente tiene nombres en mayúsculas
        }
    }
    if ($status !== '') { $sql .= " AND s.id_estadoSuscripcion = ?"; $params[] = $status; }
    if ($from !== '') { $sql .= " AND s.fecha_inicio >= ?"; $params[] = $from; }
    if ($to !== '') { $sql .= " AND s.fecha_fin <= ?"; $params[] = $to; }
    
    // Obtener total de registros para metadatos de paginación
    $countSql = "SELECT COUNT(DISTINCT s.id_suscripcion) as total FROM tbl_suscripcion s
        JOIN tbl_cliente c ON c.id_cliente = s.id_cliente
        LEFT JOIN tbl_plan tp ON tp.id_plan = s.id_plan
        LEFT JOIN tbl_periodo per ON per.id_periodo = s.id_plan
        LEFT JOIN tbl_estado_suscripcion es ON es.id_estadoSuscripcion = s.id_estadoSuscripcion
        LEFT JOIN tbl_pago pago ON pago.id_suscripcion = s.id_suscripcion
        WHERE 1=1";
    if ($client !== '') { $countSql .= " AND s.id_cliente = ?"; }
    if ($plan !== '') {
        if (is_numeric($plan)) {
            $countSql .= " AND s.id_plan = ?";
        } else {
            $countSql .= " AND (tp.nombre_plan = ? OR per.nombre_periodo = ?)";
        }
    }
    if ($status !== '') { $countSql .= " AND s.id_estadoSuscripcion = ?"; }
    if ($from !== '') { $countSql .= " AND s.fecha_inicio >= ?"; }
    if ($to !== '') { $countSql .= " AND s.fecha_fin <= ?"; }
    
    $countStmt = $conexion->prepare($countSql);
    if (!$countStmt) {
        error_log("Error preparando consulta de conteo: " . $conexion->error);
        echo json_encode(['data' => [], 'meta' => ['total' => 0, 'page' => $page, 'pageSize' => $pageSize, 'totalPages' => 0]]); 
        exit;
    }
    
    if (!empty($params)) {
        // Crear parámetros para countSql considerando los parámetros duplicados para filtro de plan
        $countParams = [];
        $paramIndex = 0;
        
        if ($client !== '') { 
            $countParams[] = $params[$paramIndex++];
        }
        if ($plan !== '') {
            if (is_numeric($plan)) {
                $countParams[] = $params[$paramIndex++];
            } else {
                // Para búsqueda por nombre, se duplica el parámetro
                $countParams[] = $params[$paramIndex];
                $countParams[] = $params[$paramIndex++];
            }
        }
        if ($status !== '') { 
            $countParams[] = $params[$paramIndex++];
        }
        if ($from !== '') { 
            $countParams[] = $params[$paramIndex++];
        }
        if ($to !== '') { 
            $countParams[] = $params[$paramIndex++];
        }
        
        $types = str_repeat('s', count($countParams));
        $countStmt->bind_param($types, ...$countParams);
    }
    
    if (!$countStmt->execute()) {
        error_log("Error ejecutando consulta de conteo: " . $countStmt->error);
        echo json_encode(['data' => [], 'meta' => ['total' => 0, 'page' => $page, 'pageSize' => $pageSize, 'totalPages' => 0]]); 
        exit;
    }
    
    $totalRecords = $countStmt->get_result()->fetch_assoc()['total'];
    $totalPages = ceil($totalRecords / $pageSize);
    
    // Agregar LIMIT y OFFSET para paginación
    $sql .= " ORDER BY s.id_suscripcion ASC LIMIT ? OFFSET ?";
    
    // Log de la consulta SQL final
    error_log("SQL final construida: " . $sql);
    
    // Preparar statement para consulta principal
    $stmt = $conexion->prepare($sql);
    if (!$stmt) { 
        error_log("Error preparando consulta: " . $conexion->error);
        echo json_encode(['data' => [], 'meta' => ['total' => 0, 'page' => $page, 'pageSize' => $pageSize, 'totalPages' => 0]]); 
        exit; 
    }
    
    // Bind parameters: todos los filtros como strings, luego LIMIT y OFFSET como enteros
    $allParams = $params; // Copia los parámetros de filtros
    $allParams[] = $pageSize; // Agregar LIMIT
    $allParams[] = $offset;   // Agregar OFFSET
    
    // Siempre hay al menos 2 parámetros (LIMIT y OFFSET)
    // Construir tipos: filtros como 's', LIMIT y OFFSET como 'i'
    $filterTypes = str_repeat('s', count($params)); // Parámetros de filtros
    $paginationTypes = 'ii'; // LIMIT y OFFSET
    $types = $filterTypes . $paginationTypes;
    
    error_log("Binding params: " . print_r($allParams, true) . " with types: " . $types);
    $stmt->bind_param($types, ...$allParams);
    
    if (!$stmt->execute()) {
        error_log("Error ejecutando consulta: " . $stmt->error);
        echo json_encode(['data' => [], 'meta' => ['total' => 0, 'page' => $page, 'pageSize' => $pageSize, 'totalPages' => 0]]); 
        exit;
    }
    
    $res = $stmt->get_result();
    $out = [];
    while ($r = $res->fetch_assoc()) {
        $out[] = $r;
    }
    
    // Log de debug después de ejecutar consulta
    error_log("Consulta ejecutada - Registros obtenidos: " . count($out));
    error_log("Total de registros (count): $totalRecords");
    
    if (count($out) === 0 && $page > 1) {
        error_log("⚠️ ADVERTENCIA: Página $page no devuelve registros pero total es $totalRecords");
        error_log("SQL utilizado: " . $sql);
        error_log("Parámetros utilizados: " . print_r($allParams, true));
    }
    
    // Retornar datos con metadatos de paginación
    $result = [
        'data' => $out,
        'meta' => [
            'total' => intval($totalRecords),
            'page' => $page,
            'pageSize' => $pageSize,
            'totalPages' => $totalPages,
            'hasNextPage' => $page < $totalPages,
            'hasPreviousPage' => $page > 1
        ]
    ];
    
    error_log("=== RESULTADO FINAL ===");
    error_log("Resultado paginación: " . json_encode($result['meta']));
    error_log("Cantidad de registros devueltos: " . count($result['data']));
    error_log("=== FIN CONSULTA PAGINACIÓN ===");
    
    echo json_encode($result);
    exit;
}

if ($action === 'summary') {
    // period: month, 3months, year, all
    $period = $_GET['period'] ?? 'month';
    $where = '';
    if ($period === 'month') $where = "AND YEAR(s.fecha_inicio)=YEAR(CURDATE()) AND MONTH(s.fecha_inicio)=MONTH(CURDATE())";
    if ($period === '3months') $where = "AND s.fecha_inicio >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)";
    if ($period === 'year') $where = "AND s.fecha_inicio >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)";
    $sql = "SELECT 
            CASE 
                WHEN tp.nombre_plan IS NOT NULL THEN tp.nombre_plan
                WHEN per.nombre_periodo IS NOT NULL THEN CONCAT(UPPER(SUBSTRING(per.nombre_periodo, 1, 1)), LOWER(SUBSTRING(per.nombre_periodo, 2)))
                ELSE 'Sin plan' 
            END as nombre_plan, 
            COUNT(*) AS total 
        FROM tbl_suscripcion s 
        LEFT JOIN tbl_plan tp ON tp.id_plan = s.id_plan
        LEFT JOIN tbl_periodo per ON per.id_periodo = s.id_plan
        WHERE 1=1 $where 
        GROUP BY 
            CASE 
                WHEN tp.nombre_plan IS NOT NULL THEN tp.nombre_plan
                WHEN per.nombre_periodo IS NOT NULL THEN CONCAT(UPPER(SUBSTRING(per.nombre_periodo, 1, 1)), LOWER(SUBSTRING(per.nombre_periodo, 2)))
                ELSE 'Sin plan' 
            END
        ORDER BY total DESC";
    $res = $conexion->query($sql);
    $out = [];
    while ($r = $res->fetch_assoc()) $out[] = $r;
    echo json_encode($out);
    exit;
}

if ($action === 'add') {
    // Log de datos recibidos para debugging
    error_log("POST data recibido: " . print_r($_POST, true));
    
    // Crear nueva suscripción
    $id_cliente = $_POST['id_cliente'] ?? null;
    $nombre_cliente = $_POST['nombre_cliente'] ?? null;
    $correo_electronico = $_POST['correo_electronico'] ?? null;
    $id_plan = $_POST['id_plan'] ?? null;
    $fecha_inicio = $_POST['fecha_inicio'] ?? null;
    $observaciones = $_POST['observaciones'] ?? '';
        // Validaciones con mensajes más claras. Permitimos id_cliente vacío si se proporciona correo.
        $id_cliente = isset($_POST['id_cliente']) && $_POST['id_cliente'] !== '' ? $_POST['id_cliente'] : null;
        $nombre_cliente = trim($_POST['nombre_cliente'] ?? '');
        $correo_electronico = trim($_POST['correo_electronico'] ?? '');
        $has_client_info = (is_numeric($id_cliente) && intval($id_cliente) > 0) || ($correo_electronico !== '');
        if ($nombre_cliente === '') { echo json_encode(['success'=>false,'error'=>'Nombre de cliente requerido']); exit; }
        if ($id_plan === '' || $id_plan === null) { echo json_encode(['success'=>false,'error'=>'Plan requerido']); exit; }
        if ($fecha_inicio === '' || $fecha_inicio === null) { echo json_encode(['success'=>false,'error'=>'Fecha de inicio requerida']); exit; }
        if (!$has_client_info) { echo json_encode(['success'=>false,'error'=>'Proporcione id_cliente o correo_electronico']); exit; }
    // Gestionar cliente: aceptar varios flujos:
    // 1) si el id existe -> usarlo
    // 2) si no existe pero el correo existe -> usar el cliente existente por correo
    // 3) si no existe ni id ni correo -> insertar cliente. Si inserción falla por conflicto, devolver error claro
    $id_cliente = intval($id_cliente);
    $email = trim($correo_electronico ?? '');

    // 1) Buscar por id
    $qcli = $conexion->prepare("SELECT id_cliente FROM tbl_cliente WHERE id_cliente = ? LIMIT 1");
    $qcli->bind_param('i', $id_cliente);
    $qcli->execute();
    $rescli = $qcli->get_result();
    if ($rescli && $rescli->num_rows > 0) {
        // cliente existe por id -> ok
        $qcli->close();
    } else {
        $qcli->close();
        // 2) Si se proporcionó correo, buscar por correo
        if ($email !== '') {
            $qbyemail = $conexion->prepare("SELECT id_cliente FROM tbl_cliente WHERE correo_electronico = ? LIMIT 1");
            $qbyemail->bind_param('s', $email);
            $qbyemail->execute();
            $resem = $qbyemail->get_result();
            if ($resem && $resem->num_rows > 0) {
                $rowe = $resem->fetch_assoc();
                $id_cliente = intval($rowe['id_cliente']);
                $qbyemail->close();
            } else {
                $qbyemail->close();
                // 3) insertar nuevo cliente con id explícito (si id > 0) o sin id (AUTO_INCREMENT)
                if ($id_cliente > 0) {
                    if ($email === '') $email = 'cliente' . $id_cliente . '@local.local';
                    $insc = $conexion->prepare("INSERT INTO tbl_cliente (id_cliente, nombre_cliente, correo_electronico, id_estadoCliente) VALUES (?,?,?,1)");
                    $insc->bind_param('iss', $id_cliente, $nombre_cliente, $email);
                } else {
                    if ($email == '') $email = 'cliente_' . time() . '@local.local';
                    $insc = $conexion->prepare("INSERT INTO tbl_cliente (nombre_cliente, correo_electronico, id_estadoCliente) VALUES (?,?,1)");
                    $insc->bind_param('ss', $nombre_cliente, $email);
                }
                if (!$insc->execute()) {
                    // intentar detectar si el error viene de correo duplicado
                    $errno = $conexion->errno;
                    $err = $conexion->error;
                    $insc->close();
                    if (stripos($err, 'Duplicate') !== false && $email !== '') {
                        // obtener cliente por correo y usar su id
                        $qb = $conexion->prepare("SELECT id_cliente FROM tbl_cliente WHERE correo_electronico = ? LIMIT 1");
                        $qb->bind_param('s', $email);
                        $qb->execute();
                        $rb = $qb->get_result();
                        if ($rb && $rb->num_rows > 0) {
                            $rowb = $rb->fetch_assoc();
                            $id_cliente = intval($rowb['id_cliente']);
                            $qb->close();
                        } else {
                            $qb->close();
                            echo json_encode(['success'=>false,'error'=>'Error al insertar cliente: '.$err]); exit;
                        }
                    } else {
                        echo json_encode(['success'=>false,'error'=>'Error al insertar cliente: '.$err]); exit;
                    }
                } else {
                    // si se insertó con AUTO_INCREMENT, recuperar id
                    if ($id_cliente <= 0) $id_cliente = intval($conexion->insert_id);
                    $insc->close();
                }
            }
        } else {
            // sin correo y sin cliente previo: insertar con id si se dio, o fallar si no hay id
            if ($id_cliente > 0) {
                $insc = $conexion->prepare("INSERT INTO tbl_cliente (id_cliente, nombre_cliente, correo_electronico, id_estadoCliente) VALUES (?,?,?,1)");
                $email = 'cliente' . $id_cliente . '@local.local';
                $insc->bind_param('iss', $id_cliente, $nombre_cliente, $email);
                if (!$insc->execute()) { echo json_encode(['success'=>false,'error'=>'Error al insertar cliente: '.$conexion->error]); exit; }
                $insc->close();
            } else {
                echo json_encode(['success'=>false,'error'=>'Debe especificar correo o un ID de cliente válido']); exit;
            }
        }
    }

    // Verificar límite de suscripciones por cliente usando parámetro
    $max_permitidas = getMaxSuscripcionesPorCliente($conexion);
    
    // Log de depuración
    error_log("DEBUG ADD: id_cliente = $id_cliente, max_permitidas = $max_permitidas");
    
    // Contar TODAS las suscripciones del cliente (sin importar estado)
    $check = $conexion->prepare("SELECT COUNT(*) as total_suscripciones,
        GROUP_CONCAT(CONCAT('ID:', s.id_suscripcion, ' Estado:', es.nombre_estado, ' Fin:', s.fecha_fin) SEPARATOR '; ') as detalles
        FROM tbl_suscripcion s 
        JOIN tbl_estado_suscripcion es ON s.id_estadoSuscripcion = es.id_estadoSuscripcion 
        WHERE s.id_cliente = ?");
    $check->bind_param('i', $id_cliente);
    $check->execute();
    $result = $check->get_result();
    $row = $result->fetch_assoc();
    
    // Log de depuración adicional
    error_log("DEBUG ADD: Cliente $id_cliente tiene " . $row['total_suscripciones'] . " suscripciones en total");
    error_log("DEBUG ADD: Detalles: " . $row['detalles']);
    
    if ($row['total_suscripciones'] >= $max_permitidas) {
        error_log("DEBUG ADD: BLOQUEANDO - Cliente $id_cliente ya tiene " . $row['total_suscripciones'] . " suscripciones (límite: $max_permitidas)");
        
        // Obtener detalles de la suscripción más reciente para el mensaje
        $detalle = $conexion->prepare("SELECT s.id_suscripcion, s.fecha_fin, es.nombre_estado 
            FROM tbl_suscripcion s 
            JOIN tbl_estado_suscripcion es ON s.id_estadoSuscripcion = es.id_estadoSuscripcion 
            WHERE s.id_cliente = ?
            ORDER BY s.fecha_fin DESC LIMIT 1");
        $detalle->bind_param('i', $id_cliente);
        $detalle->execute();
        $res_detalle = $detalle->get_result();
        $det = $res_detalle->fetch_assoc();
        $detalle->close();
        
        $check->close();
        
        echo json_encode([
            'success' => false, 
            'error' => 'El cliente ya tiene una suscripción registrada (Estado: ' . $det['nombre_estado'] . ', vence: ' . $det['fecha_fin'] . '). Solo se permite ' . $max_permitidas . ' suscripción por cliente.'
        ]); 
        exit;
    }
    $check->close();

    // Si el plan viene como nombre, intentar mapear a id; si viene como id, usarlo
    $plan_name = '';
    if (!is_numeric($id_plan)) {
        $plan_name = trim($id_plan);
        error_log("Plan recibido como nombre: '$plan_name'");
        
        // Buscar primero en tbl_periodo con nombre exacto
        $q = $conexion->prepare("SELECT id_periodo as id_plan FROM tbl_periodo WHERE nombre_periodo = ? LIMIT 1");
        $q->bind_param('s', $plan_name);
        $q->execute();
        $resq = $q->get_result();
        $r = $resq ? $resq->fetch_assoc() : null;
        
        if ($r && !empty($r['id_plan'])) {
            $plan_id = intval($r['id_plan']);
            error_log("Plan encontrado en tbl_periodo (exacto), ID: $plan_id");
            $q->close();
        } else {
            $q->close();
            // Buscar en tbl_periodo con conversión a mayúsculas
            $nombre_mayus = strtoupper($plan_name);
            $q = $conexion->prepare("SELECT id_periodo as id_plan FROM tbl_periodo WHERE nombre_periodo = ? LIMIT 1");
            $q->bind_param('s', $nombre_mayus);
            $q->execute();
            $resq = $q->get_result();
            $r = $resq ? $resq->fetch_assoc() : null;
            
            if ($r && !empty($r['id_plan'])) {
                $plan_id = intval($r['id_plan']);
                error_log("Plan encontrado en tbl_periodo (mayúsculas), ID: $plan_id");
                $q->close();
            } else {
                $q->close();
                // Si no se encuentra en tbl_periodo, buscar en tbl_plan (sistema anterior)
                $q2 = $conexion->prepare("SELECT id_plan FROM tbl_plan WHERE nombre_plan = ? LIMIT 1");
                $q2->bind_param('s', $plan_name);
                $q2->execute();
                $resq2 = $q2->get_result();
                $r2 = $resq2 ? $resq2->fetch_assoc() : null;
                
                if ($r2 && !empty($r2['id_plan'])) {
                    $plan_id = intval($r2['id_plan']);
                    error_log("Plan encontrado en tbl_plan, ID: $plan_id");
                    $q2->close();
                } else {
                    $q2->close();
                    error_log("Plan '$plan_name' no encontrado en ninguna tabla");
                    echo json_encode(['success'=>false,'error'=>'Plan no encontrado: ' . $plan_name]); exit;
                }
            }
        }
    } else {
        $plan_id = intval($id_plan);
        error_log("Plan recibido como ID: $plan_id");
        
        // Obtener el nombre del plan: buscar en tbl_periodo primero, luego tbl_plan
        $q = $conexion->prepare("SELECT nombre_periodo FROM tbl_periodo WHERE id_periodo = ? LIMIT 1");
        $q->bind_param('i', $plan_id);
        $q->execute();
        $resq = $q->get_result();
        $r = $resq ? $resq->fetch_assoc() : null;
        
        if ($r) {
            // Convertir a formato título para consistencia
            $plan_name = ucfirst(strtolower($r['nombre_periodo']));
            error_log("Nombre del plan obtenido de tbl_periodo (formato título): '$plan_name'");
            $q->close();
        } else {
            $q->close();
            // Buscar en tbl_plan
            $q2 = $conexion->prepare("SELECT nombre_plan FROM tbl_plan WHERE id_plan = ? LIMIT 1");
            $q2->bind_param('i', $plan_id);
            $q2->execute();
            $resq2 = $q2->get_result();
            $r2 = $resq2 ? $resq2->fetch_assoc() : null;
            
            if ($r2) {
                $plan_name = $r2['nombre_plan'];
                error_log("Nombre del plan obtenido de tbl_plan: '$plan_name'");
                $q2->close();
            } else {
                $q2->close();
                error_log("Plan ID $plan_id no encontrado en ninguna tabla");
                echo json_encode(['success'=>false,'error'=>'Plan ID no encontrado: ' . $plan_id]); exit;
            }
        }
    }

    // Obtener precio y duración: priorizar valores del frontend, luego calcular automáticamente
    $precio_frontend = floatval($_POST['precio'] ?? 0);
    $duracion_frontend = intval($_POST['duracion_dias'] ?? 0);
    
    if ($precio_frontend > 0) {
        $precio = $precio_frontend;
    } else {
        $precio = getPrecioPlan($plan_name);
    }
    
    if ($duracion_frontend > 0) {
        $duracion = $duracion_frontend;
    } else {
        $duracion = getDuracionPlan($plan_name);
    }
    
    // Log para debugging
    error_log("Suscripción - Plan: $plan_name, Precio frontend: $precio_frontend, Precio final: $precio, Duración frontend: $duracion_frontend, Duración final: $duracion");

    // Asegurar que existe el registro en tbl_plan
    if (!asegurarRegistroPlan($plan_id, $plan_name)) {
        error_log("Advertencia: No se pudo asegurar el registro en tbl_plan para ID: $plan_id, Nombre: $plan_name");
    }

    // Calcular fecha_fin: usar la del frontend si está presente, sino calcular
    $fecha_fin_frontend = trim($_POST['fecha_fin'] ?? '');
    if (!empty($fecha_fin_frontend)) {
        $fecha_fin = $fecha_fin_frontend;
        error_log("Usando fecha_fin del frontend: $fecha_fin");
    } else {
        $fecha_fin = date('Y-m-d', strtotime($fecha_inicio . " + " . ($duracion - 1) . " days"));
        error_log("Calculando fecha_fin automáticamente: $fecha_fin");
    }
    $estado = 1; // ACTIVA
    $stmt = $conexion->prepare("INSERT INTO tbl_suscripcion (id_cliente, id_plan, fecha_inicio, fecha_fin, precio, id_estadoSuscripcion, duracion_dias, observaciones) VALUES (?,?,?,?,?,?,?,?)");
    if (!$stmt) { echo json_encode(['success'=>false,'error'=>$conexion->error]); exit; }
    // tipos: id_cliente(i), id_plan(i), fecha_inicio(s), fecha_fin(s), precio(d), estado(i), duracion(i), observaciones(s)
    $stmt->bind_param('iissdiis', $id_cliente, $plan_id, $fecha_inicio, $fecha_fin, $precio, $estado, $duracion, $observaciones);
    $ok = $stmt->execute();
    if ($ok) {
        // Bitácora: suscripción creada
        if (!empty($OBJ_SUSCRIPCIONES_ID)) {
            $id_usuario = $_SESSION['usuario_id'] ?? null;
            $nuevo_id = intval($conexion->insert_id);
            $desc = sprintf(
                'Se creó la suscripción ID %d para cliente ID %d, plan ID %d, inicio %s, fin %s, precio %.2f, duración %d días.',
                $nuevo_id, $id_cliente, $plan_id, $fecha_inicio, $fecha_fin, $precio, $duracion
            );
            registrar_bitacora($conexion, $id_usuario, $OBJ_SUSCRIPCIONES_ID, 'INSERT', $desc);
        }
        echo json_encode(['success'=>true]);
    } else {
        echo json_encode(['success'=>false,'error'=>$stmt->error]);
    }
    exit;
}

if ($action === 'generate_reminders') {
    // Verificar si el envío de recordatorios diarios está activado
    $recordatorioActivado = getEnviarRecordatorioDiario($conexion);
    
    if (!$recordatorioActivado) {
        echo json_encode([
            'success' => false, 
            'error' => 'Los recordatorios diarios están desactivados. Configure ENVIAR_RECORDATORIO_DIARIO=1 para activarlos.',
            'parameter_status' => 'DESACTIVADO'
        ]);
        exit;
    }
    
    // Intentar usar el procedimiento almacenado si existe
    $r = $conexion->query("SELECT COUNT(*) as c FROM information_schema.routines WHERE routine_schema = DATABASE() AND routine_name = 'sp_generar_calendario_nocturno'");
    $has = ($r->fetch_assoc()['c'] ?? 0) > 0;
    if ($has) {
        // Ejecutar generación de calendario
        if ($conexion->query("CALL sp_generar_calendario_nocturno()")) {
            // Intentar también procesar las notificaciones pendientes (marcar ENVIADO/VENCIDA/INACTIVO)
            $r2 = $conexion->query("SELECT COUNT(*) as c FROM information_schema.routines WHERE routine_schema = DATABASE() AND routine_name = 'sp_procesar_notificaciones_pendientes'");
            $hasProc = ($r2 && ($r2->fetch_assoc()['c'] ?? 0) > 0);
            $procMsg = '';
            $processed = 0;
            if ($hasProc) {
                if ($conexion->query("CALL sp_procesar_notificaciones_pendientes()")) {
                    $procMsg = ' Procesadas notificaciones pendientes (procedimiento ejecutado).';
                    // No easy way to get affected rows from inside SP; leave processed=unknown
                } else {
                    $procMsg = ' Error al ejecutar sp_procesar_notificaciones_pendientes: ' . $conexion->error;
                }
            } else {
                // Fallback: marcar suscripciones vencidas como VENCIDA y clientes como INACTIVO
                // Obtener ids de estados
                $idVencidaRes = $conexion->query("SELECT id_estadoSuscripcion FROM tbl_estado_suscripcion WHERE nombre_estado='VENCIDA' LIMIT 1");
                $idVencida = ($idVencidaRes && ($row=$idVencidaRes->fetch_assoc())) ? intval($row['id_estadoSuscripcion']) : null;
                $idInactivoRes = $conexion->query("SELECT id_estadoCliente FROM tbl_estado_cliente WHERE nombre_estado='INACTIVO' LIMIT 1");
                $idInactivo = ($idInactivoRes && ($row2=$idInactivoRes->fetch_assoc())) ? intval($row2['id_estadoCliente']) : null;
                $cntUpdated = 0;
                if ($idVencida) {
                    $upd = $conexion->query("UPDATE tbl_suscripcion s JOIN tbl_estado_suscripcion es ON s.id_estadoSuscripcion = es.id_estadoSuscripcion SET s.id_estadoSuscripcion = $idVencida WHERE s.fecha_fin < CURDATE() AND es.nombre_estado NOT IN ('VENCIDA', 'CANCELADA')");
                    if ($upd !== false) $cntUpdated += $conexion->affected_rows;
                }
                if ($idInactivo && $idVencida) {
                    // Actualizar clientes que tienen suscripciones ahora marcadas
                    $upd2 = $conexion->query("UPDATE tbl_cliente c JOIN tbl_suscripcion s ON s.id_cliente = c.id_cliente SET c.id_estadoCliente = $idInactivo WHERE s.fecha_fin < CURDATE() AND s.id_estadoSuscripcion = $idVencida");
                    if ($upd2 !== false) $cntUpdated += $conexion->affected_rows;
                }
                $procMsg = ' Fallback aplicado: suscripciones/cliente actualizados: ' . $cntUpdated;
                $processed = $cntUpdated;
            }

            // Aplicar regla de estados: cualquier día de atraso -> VENCIDA
            $changedSus = 0;
            $changedClients = 0;
            // Obtener ids de estados necesarios
            $rVenc = $conexion->query("SELECT id_estadoSuscripcion FROM tbl_estado_suscripcion WHERE nombre_estado='VENCIDA' LIMIT 1");
            $idVenc = ($rVenc && ($rowV=$rVenc->fetch_assoc())) ? intval($rowV['id_estadoSuscripcion']) : null;
            $rInac = $conexion->query("SELECT id_estadoCliente FROM tbl_estado_cliente WHERE nombre_estado='INACTIVO' LIMIT 1");
            $idInac = ($rInac && ($rowI=$rInac->fetch_assoc())) ? intval($rowI['id_estadoCliente']) : null;
            if ($idVenc) {
                $q2 = $conexion->query("UPDATE tbl_suscripcion s JOIN tbl_estado_suscripcion es ON s.id_estadoSuscripcion = es.id_estadoSuscripcion SET s.id_estadoSuscripcion = $idVenc WHERE DATEDIFF(CURDATE(), s.fecha_fin) >= 1 AND es.nombre_estado NOT IN ('VENCIDA', 'CANCELADA')");
                if ($q2 !== false) $changedSus += $conexion->affected_rows;
            }
            if ($idInac && $idVenc) {
                $q3 = $conexion->query("UPDATE tbl_cliente c JOIN tbl_suscripcion s ON s.id_cliente = c.id_cliente SET c.id_estadoCliente = $idInac WHERE s.id_estadoSuscripcion = $idVenc");
                if ($q3 !== false) $changedClients += $conexion->affected_rows;
            }

            echo json_encode(['success'=>true,'message'=>'Recordatorios generados (procedimiento ejecutado).'.$procMsg,'processed'=>$processed,'sus_updated'=>$changedSus,'clients_updated'=>$changedClients]);
        } else {
            echo json_encode(['success'=>false,'error'=>$conexion->error]);
        }
    } else {
        // Fallback: insertar recordatorios para suscripciones que vencen en <=3 días y para ya vencidas.
        // Usar NOT EXISTS para evitar duplicados en el mismo día.
        $idEstadoPendienteQ = "(SELECT id_estadoCalendario FROM tbl_estado_calendario WHERE nombre_estado = 'PENDIENTE' LIMIT 1)";
        
        $sqlIns1 = "INSERT INTO tbl_calendario (id_suscripcion, id_cliente, id_tipoNotificacion, id_estadoCalendario, fecha_notificacion, hora_notificacion, contenido) 
            SELECT s.id_suscripcion, s.id_cliente, 1, $idEstadoPendienteQ, CURDATE(), '09:00:00', CONCAT('Su suscripción vence el ', DATE_FORMAT(s.fecha_fin, '%Y-%m-%d')) 
            FROM tbl_suscripcion s 
            JOIN tbl_estado_suscripcion es ON s.id_estadoSuscripcion = es.id_estadoSuscripcion 
            WHERE es.nombre_estado = 'ACTIVA' AND DATEDIFF(s.fecha_fin, CURDATE()) BETWEEN 0 AND 3 
            AND NOT EXISTS (SELECT 1 FROM tbl_calendario c WHERE c.id_suscripcion = s.id_suscripcion AND c.fecha_notificacion = CURDATE())";

        $sqlIns2 = "INSERT INTO tbl_calendario (id_suscripcion, id_cliente, id_tipoNotificacion, id_estadoCalendario, fecha_notificacion, hora_notificacion, contenido) 
            SELECT s.id_suscripcion, s.id_cliente, 1, $idEstadoPendienteQ, CURDATE(), '09:00:00', CONCAT('Su suscripción venció el ', DATE_FORMAT(s.fecha_fin, '%Y-%m-%d')) 
            FROM tbl_suscripcion s 
            JOIN tbl_estado_suscripcion es ON s.id_estadoSuscripcion = es.id_estadoSuscripcion 
            WHERE es.nombre_estado IN ('ACTIVA','VENCIDA') AND s.fecha_fin < CURDATE() 
            AND NOT EXISTS (SELECT 1 FROM tbl_calendario c WHERE c.id_suscripcion = s.id_suscripcion AND c.fecha_notificacion = CURDATE())";

        $ok1 = $conexion->query($sqlIns1);
        $count1 = $conexion->affected_rows;
        $ok2 = $conexion->query($sqlIns2);
        $count2 = $conexion->affected_rows;
        $total = max(0,$count1) + max(0,$count2);
        if ($ok1 !== false && $ok2 !== false) {
            // Ahora aplicar la regla VENCIDA inmediatamente
            $changedSus = 0; $changedClients = 0;
            $rVenc = $conexion->query("SELECT id_estadoSuscripcion FROM tbl_estado_suscripcion WHERE nombre_estado='VENCIDA' LIMIT 1");
            $idVenc = ($rVenc && ($rowV=$rVenc->fetch_assoc())) ? intval($rowV['id_estadoSuscripcion']) : null;
            $rInac = $conexion->query("SELECT id_estadoCliente FROM tbl_estado_cliente WHERE nombre_estado='INACTIVO' LIMIT 1");
            $idInac = ($rInac && ($rowI=$rInac->fetch_assoc())) ? intval($rowI['id_estadoCliente']) : null;
            if ($idVenc) {
                $q2 = $conexion->query("UPDATE tbl_suscripcion s JOIN tbl_estado_suscripcion es ON s.id_estadoSuscripcion = es.id_estadoSuscripcion SET s.id_estadoSuscripcion = $idVenc WHERE DATEDIFF(CURDATE(), s.fecha_fin) >= 1 AND es.nombre_estado NOT IN ('VENCIDA', 'CANCELADA')");
                if ($q2 !== false) $changedSus += $conexion->affected_rows;
            }
            if ($idInac && $idVenc) {
                $q3 = $conexion->query("UPDATE tbl_cliente c JOIN tbl_suscripcion s ON s.id_cliente = c.id_cliente SET c.id_estadoCliente = $idInac WHERE s.id_estadoSuscripcion = $idVenc");
                if ($q3 !== false) $changedClients += $conexion->affected_rows;
            }

            echo json_encode(['success'=>true,'message'=>'Recordatorios generados.','inserted'=>$total,'sus_updated'=>$changedSus,'clients_updated'=>$changedClients]);
        } else {
            $err = $conexion->error;
            echo json_encode(['success'=>false,'error'=>$err]);
        }
    }
    exit;
}

if ($action === 'alerts') {
    // Asegurar que los estados reflejen pagos pendientes antes de obtener alertas
    updatePaymentStatuses($conexion);
    // Devolver suscripciones próximas (0..7 días) o que ya estén VENCIDA
    $sql = "SELECT s.id_suscripcion, c.nombre_cliente, 
                   COALESCE(tp.nombre_plan, 'Sin plan') as nombre_plan, 
                   s.fecha_fin, es.nombre_estado 
            FROM tbl_suscripcion s 
            JOIN tbl_cliente c ON c.id_cliente = s.id_cliente 
            LEFT JOIN tbl_plan tp ON tp.id_plan = s.id_plan
            LEFT JOIN tbl_estado_suscripcion es ON es.id_estadoSuscripcion = s.id_estadoSuscripcion 
            WHERE (DATEDIFF(s.fecha_fin, CURDATE()) BETWEEN 0 AND 7) OR es.nombre_estado IN ('VENCIDA') 
            ORDER BY s.fecha_fin ASC LIMIT 50";
    $res = $conexion->query($sql);
    $out = [];
    while ($r = $res->fetch_assoc()) $out[] = $r;
    echo json_encode($out);
    exit;
}

if ($action === 'toggle_payment') {
    $id = intval($_POST['id'] ?? 0);
    $nuevoEstado = intval($_POST['estado'] ?? 0); // 0 = no pagado, 1 = pagado
    
    if (!$id) { 
        echo json_encode(['success'=>false,'error'=>'ID inválido']); 
        exit; 
    }
    
    // Log attempt
    $logPath = __DIR__ . '/../../logs/sus_actions.log';
    $accionTexto = $nuevoEstado == 1 ? 'MARCAR_PAGADO' : 'DESMARCAR_PAGADO';
    $logEntry = date('c') . " | $accionTexto request | session_user=" . ($_SESSION['usuario_id'] ?? 'none') . " | id=" . $id . "\n";
    @file_put_contents($logPath, $logEntry, FILE_APPEND);

    $conexion->begin_transaction();
    try {
        // Obtener información de la suscripción
        $getSub = $conexion->prepare("SELECT s.*, 
            CASE 
                WHEN p.nombre_plan IS NOT NULL THEN p.nombre_plan
                WHEN per.nombre_periodo IS NOT NULL THEN CONCAT(UPPER(SUBSTRING(per.nombre_periodo, 1, 1)), LOWER(SUBSTRING(per.nombre_periodo, 2)))
                ELSE 'Sin plan' 
            END as nombre_plan 
            FROM tbl_suscripcion s 
            LEFT JOIN tbl_plan p ON s.id_plan = p.id_plan 
            LEFT JOIN tbl_periodo per ON per.id_periodo = s.id_plan 
            WHERE s.id_suscripcion = ?");
        if (!$getSub) throw new Exception('Error al preparar consulta de suscripción');
        
        $getSub->bind_param('i', $id);
        $getSub->execute();
        $result = $getSub->get_result();
        
        if (!$result || $result->num_rows === 0) {
            $getSub->close();
            throw new Exception('Suscripción no encontrada');
        }
        
        $subscription = $result->fetch_assoc();
        $getSub->close();
        
        $mensaje = '';
        $fechasActualizadas = false;
        
        if ($nuevoEstado == 1) {
            // MARCAR COMO PAGADO
            // Verificar si ya existe un pago para esta suscripción
            $checkPago = $conexion->prepare("SELECT id_pago FROM tbl_pago WHERE id_suscripcion = ?");
            $checkPago->bind_param('i', $id);
            $checkPago->execute();
            $existePago = $checkPago->get_result()->num_rows > 0;
            $checkPago->close();
            
            if (!$existePago) {
                // Registrar el pago
                $insertPago = $conexion->prepare("INSERT INTO tbl_pago (id_suscripcion, fecha_pago, monto, metodo_pago) VALUES (?, NOW(), ?, 'Sistema')");
                $insertPago->bind_param('id', $id, $subscription['precio']);
                if (!$insertPago->execute()) {
                    $insertPago->close();
                    throw new Exception('Error al registrar el pago');
                }
                $insertPago->close();
                
                // Verificar si la suscripción está vencida
                $fechaFin = new DateTime($subscription['fecha_fin']);
                $fechaHoy = new DateTime();
                $estaVencida = $fechaHoy > $fechaFin;
                
                if ($estaVencida) {
                    // Si está vencida, actualizar las fechas
                    $duracionDias = intval($subscription['duracion_dias'] ?? 30);
                    
                    // Nueva fecha de inicio es hoy
                    $nuevaFechaInicio = $fechaHoy->format('Y-m-d');
                    // Nueva fecha de fin es hoy + duración del plan
                    $nuevaFechaFin = clone $fechaHoy;
                    $nuevaFechaFin->add(new DateInterval('P' . $duracionDias . 'D'));
                    $nuevaFechaFinStr = $nuevaFechaFin->format('Y-m-d');
                    
                    // Actualizar fechas en la suscripción
                    $updateFechas = $conexion->prepare("UPDATE tbl_suscripcion SET fecha_inicio = ?, fecha_fin = ? WHERE id_suscripcion = ?");
                    $updateFechas->bind_param('ssi', $nuevaFechaInicio, $nuevaFechaFinStr, $id);
                    if (!$updateFechas->execute()) {
                        $updateFechas->close();
                        throw new Exception('Error al actualizar fechas de suscripción');
                    }
                    $updateFechas->close();
                    
                    $fechasActualizadas = true;
                    $mensaje = sprintf('Marcado como pagado. La suscripción estaba vencida, se actualizaron las fechas: desde %s hasta %s (%d días)', 
                        $nuevaFechaInicio, $nuevaFechaFinStr, $duracionDias);
                } else {
                    $mensaje = 'Marcado como pagado. La suscripción está vigente, no se actualizaron las fechas.';
                }
                
                // Activar la suscripción
                $activar = $conexion->prepare("UPDATE tbl_suscripcion SET id_estadoSuscripcion = (SELECT id_estadoSuscripcion FROM tbl_estado_suscripcion WHERE nombre_estado = 'ACTIVA' LIMIT 1) WHERE id_suscripcion = ?");
                $activar->bind_param('i', $id);
                $activar->execute();
                $activar->close();
                
            } else {
                $mensaje = 'Esta suscripción ya tiene un pago registrado.';
            }
            
        } else {
            // DESMARCAR COMO PAGADO - Eliminar el pago
            $deletePago = $conexion->prepare("DELETE FROM tbl_pago WHERE id_suscripcion = ?");
            $deletePago->bind_param('i', $id);
            if (!$deletePago->execute()) {
                $deletePago->close();
                throw new Exception('Error al eliminar el pago');
            }
            $deletePago->close();
            
            $mensaje = 'Marcado como no pagado. Se eliminó el registro de pago.';
        }
        
        $conexion->commit();
        
        // Bitácora
        if (!empty($OBJ_SUSCRIPCIONES_ID)) {
            $id_usuario = $_SESSION['usuario_id'] ?? null;
            $desc = sprintf('Se %s el pago para la suscripción ID %d.', 
                $nuevoEstado == 1 ? 'registró' : 'eliminó', $id);
            if ($fechasActualizadas) {
                $desc .= ' Se actualizaron las fechas por vencimiento.';
            }
            registrar_bitacora($conexion, $id_usuario, $OBJ_SUSCRIPCIONES_ID, $nuevoEstado == 1 ? 'INSERT' : 'DELETE', $desc);
        }
        
        echo json_encode(['success' => true, 'message' => $mensaje]);
        
    } catch (Exception $e) {
        $conexion->rollback();
        $errEntry = date('c') . " | TOGGLE_PAYMENT error | " . $e->getMessage() . "\n";
        @file_put_contents($logPath, $errEntry, FILE_APPEND);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'cancel') {
    $id = intval($_POST['id'] ?? 0);
    if (!$id) { echo json_encode(['success'=>false,'error'=>'ID inválido']); exit; }
    // Log attempt
    $logPath = __DIR__ . '/../../logs/sus_actions.log';
    $logEntry = date('c') . " | CANCEL request | session_user=" . ($_SESSION['usuario_id'] ?? 'none') . " | id=" . $id . "\n";
    @file_put_contents($logPath, $logEntry, FILE_APPEND);

    $upd = $conexion->prepare("UPDATE tbl_suscripcion SET id_estadoSuscripcion = (SELECT id_estadoSuscripcion FROM tbl_estado_suscripcion WHERE nombre_estado = 'CANCELADA' LIMIT 1) WHERE id_suscripcion = ?");
    if (!$upd) { echo json_encode(['success'=>false,'error'=>'prepare_failed','db_error'=>$conexion->error]); exit; }
    $upd->bind_param('i', $id);
    $ok = $upd->execute();
    if (!$ok) {
        $errEntry = date('c') . " | CANCEL execute_failed | stmt_error=" . $upd->error . " | db_error=" . $conexion->error . "\n";
        @file_put_contents($logPath, $errEntry, FILE_APPEND);
        echo json_encode(['success'=>false,'error'=>'execute_failed','stmt_error'=>$upd->error,'db_error'=>$conexion->error]);
        exit;
    }
    $affected = $upd->affected_rows;
    $okEntry = date('c') . " | CANCEL result | affected_rows=" . $affected . "\n";
    @file_put_contents($logPath, $okEntry, FILE_APPEND);
    if ($affected > 0) {
        // Bitácora: suscripción cancelada
        if (!empty($OBJ_SUSCRIPCIONES_ID)) {
            $id_usuario = $_SESSION['usuario_id'] ?? null;
            $desc = sprintf('Se canceló la suscripción ID %d.', $id);
            registrar_bitacora($conexion, $id_usuario, $OBJ_SUSCRIPCIONES_ID, 'UPDATE', $desc);
        }
        echo json_encode(['success'=>true,'affected_rows'=>$affected]);
    } else {
        echo json_encode(['success'=>false,'error'=>'No se encontró la suscripción o no hubo cambios','affected_rows'=>$affected]);
    }
    exit;
}

if ($action === 'activate') {
    $id = intval($_POST['id'] ?? 0);
    if (!$id) { echo json_encode(['success'=>false,'error'=>'ID inválido']); exit; }
    // Log attempt
    $logPath = __DIR__ . '/../../logs/sus_actions.log';
    $logEntry = date('c') . " | ACTIVATE request | session_user=" . ($_SESSION['usuario_id'] ?? 'none') . " | id=" . $id . "\n";
    @file_put_contents($logPath, $logEntry, FILE_APPEND);

    $upd = $conexion->prepare("UPDATE tbl_suscripcion SET id_estadoSuscripcion = (SELECT id_estadoSuscripcion FROM tbl_estado_suscripcion WHERE nombre_estado = 'ACTIVA' LIMIT 1) WHERE id_suscripcion = ?");
    if (!$upd) { echo json_encode(['success'=>false,'error'=>'prepare_failed','db_error'=>$conexion->error]); exit; }
    $upd->bind_param('i', $id);
    $ok = $upd->execute();
    if (!$ok) {
        $errEntry = date('c') . " | ACTIVATE execute_failed | stmt_error=" . $upd->error . " | db_error=" . $conexion->error . "\n";
        @file_put_contents($logPath, $errEntry, FILE_APPEND);
        echo json_encode(['success'=>false,'error'=>'execute_failed','stmt_error'=>$upd->error,'db_error'=>$conexion->error]);
        exit;
    }
    $affected = $upd->affected_rows;
    $okEntry = date('c') . " | ACTIVATE result | affected_rows=" . $affected . "\n";
    @file_put_contents($logPath, $okEntry, FILE_APPEND);

    if ($affected > 0) {
        // Bitácora: activar suscripción
        if (!empty($OBJ_SUSCRIPCIONES_ID)) {
            $id_usuario = $_SESSION['usuario_id'] ?? null;
            $desc = sprintf('Se activó la suscripción ID %d.', $id);
            registrar_bitacora($conexion, $id_usuario, $OBJ_SUSCRIPCIONES_ID, 'UPDATE', $desc);
        }
        echo json_encode(['success'=>true,'affected_rows'=>$affected]);
    } else {
        echo json_encode(['success'=>false,'error'=>'No se encontró la suscripción o no hubo cambios','affected_rows'=>$affected]);
    }
    exit;
}

if ($action === 'delete') {
    $id = intval($_POST['id'] ?? 0);
    if (!$id) { echo json_encode(['success'=>false,'error'=>'ID inválido']); exit; }
    // Log attempt
    $logPath = __DIR__ . '/../../logs/sus_actions.log';
    $logEntry = date('c') . " | DELETE (cascade) request | session_user=" . ($_SESSION['usuario_id'] ?? 'none') . " | id_suscripcion=" . $id . "\n";
    @file_put_contents($logPath, $logEntry, FILE_APPEND);

    // Empezar transacción para borrar suscripción y todos los datos del cliente asociado
    $conexion->begin_transaction();
    try {
        // Obtener id_cliente asociado
        $q = $conexion->prepare("SELECT id_cliente FROM tbl_suscripcion WHERE id_suscripcion = ? LIMIT 1");
        if (!$q) throw new Exception('prepare_select_client_failed: ' . $conexion->error);
        $q->bind_param('i', $id);
        $q->execute();
        $res = $q->get_result();
        if (!$res || $res->num_rows === 0) {
            $q->close();
            $conexion->rollback();
            @file_put_contents($logPath, date('c') . " | DELETE abort | suscripción no encontrada id=" . $id . "\n", FILE_APPEND);
            echo json_encode(['success'=>false,'error'=>'Suscripción no encontrada']);
            exit;
        }
        $row = $res->fetch_assoc();
        $id_cliente = intval($row['id_cliente']);
        $q->close();

        $totalAffected = 0;

        // 1) Borrar pagos asociados a la suscripción
        $delPago = $conexion->prepare("DELETE FROM tbl_pago WHERE id_suscripcion = ?");
        if ($delPago) {
            $delPago->bind_param('i', $id);
            $delPago->execute();
            $totalAffected += $delPago->affected_rows;
            $delPago->close();
        }

        // 2) Borrar calendarios/recordatorios asociados a la suscripción o al cliente
        $delCal = $conexion->prepare("DELETE FROM tbl_calendario WHERE id_suscripcion = ? OR id_cliente = ?");
        if ($delCal) {
            $delCal->bind_param('ii', $id, $id_cliente);
            $delCal->execute();
            $totalAffected += $delCal->affected_rows;
            $delCal->close();
        }

        // 3) Borrar la suscripción
        $delSus = $conexion->prepare("DELETE FROM tbl_suscripcion WHERE id_suscripcion = ?");
        if (!$delSus) throw new Exception('prepare_delete_suscripcion_failed: ' . $conexion->error);
        $delSus->bind_param('i', $id);
        $delSus->execute();
        $totalAffected += $delSus->affected_rows;
        $delSus->close();

        // 4) Borrar otros registros relacionados que dependan del cliente (opcional)
        // Aquí podríamos borrar tbl_pago (por cliente), tbl_verificacion_codigos, etc. Borramos el cliente si ya no tiene suscripciones.
        $qsubs = $conexion->prepare("SELECT COUNT(*) AS c FROM tbl_suscripcion WHERE id_cliente = ?");
        if (!$qsubs) throw new Exception('prepare_count_subs_failed: ' . $conexion->error);
        $qsubs->bind_param('i', $id_cliente);
        $qsubs->execute();
        $cres = $qsubs->get_result();
        $cnum = 0;
        if ($cres) {
            $rowc = $cres->fetch_assoc();
            $cnum = intval($rowc['c'] ?? 0);
        }
        $qsubs->close();

        if ($cnum === 0) {
            // Borrar cliente y cualquier pago restante por cliente
            $delPagoCliente = $conexion->prepare("DELETE FROM tbl_pago WHERE id_suscripcion IN (SELECT id_suscripcion FROM tbl_suscripcion WHERE id_cliente = ?) ");
            if ($delPagoCliente) {
                $delPagoCliente->bind_param('i', $id_cliente);
                $delPagoCliente->execute();
                $totalAffected += $delPagoCliente->affected_rows;
                $delPagoCliente->close();
            }

            $delCliente = $conexion->prepare("DELETE FROM tbl_cliente WHERE id_cliente = ?");
            if (!$delCliente) throw new Exception('prepare_delete_cliente_failed: ' . $conexion->error);
            $delCliente->bind_param('i', $id_cliente);
            $delCliente->execute();
            $totalAffected += $delCliente->affected_rows;
            $delCliente->close();
        }

        $conexion->commit();
        $okEntry = date('c') . " | DELETE cascade success | total_affected=" . $totalAffected . " | id_suscripcion=" . $id . " | id_cliente=" . $id_cliente . "\n";
        @file_put_contents($logPath, $okEntry, FILE_APPEND);
        // Bitácora: suscripción eliminada
        if (!empty($OBJ_SUSCRIPCIONES_ID)) {
            $id_usuario = $_SESSION['usuario_id'] ?? null;
            $desc = sprintf('Se eliminó la suscripción ID %d (cliente ID %d). Registros afectados: %d.', $id, $id_cliente, $totalAffected);
            registrar_bitacora($conexion, $id_usuario, $OBJ_SUSCRIPCIONES_ID, 'DELETE', $desc);
        }
        echo json_encode(['success'=>true,'total_affected'=>$totalAffected,'affected_rows'=>$totalAffected]);
        exit;
    } catch (Exception $ex) {
        $conexion->rollback();
        @file_put_contents($logPath, date('c') . " | DELETE cascade error | msg=" . $ex->getMessage() . "\n", FILE_APPEND);
        echo json_encode(['success'=>false,'error'=>'exception','message'=>$ex->getMessage()]);
        exit;
    }
}

if ($action === 'get_subscription') {
    // Obtener datos de una suscripción para edición
    $id = $_GET['id'] ?? $_POST['id'] ?? '';
    
    // Logging para debugging
    error_log("Get subscription request - ID: $id, Action: $action");
    
    if (!$id || !is_numeric($id)) {
        error_log("Get subscription error: Invalid ID - $id");
        echo json_encode(['success' => false, 'error' => 'ID de suscripción requerido y debe ser numérico']);
        exit;
    }
    
    try {
        // Consulta más completa para obtener todos los datos necesarios
        $stmt = $conexion->prepare("
            SELECT 
                s.id_suscripcion,
                s.id_cliente,
                c.nombre_cliente,
                c.correo_electronico,
                s.id_plan,
                CASE 
                    WHEN p.nombre_plan IS NOT NULL THEN p.nombre_plan
                    WHEN per.nombre_periodo IS NOT NULL THEN CONCAT(UPPER(SUBSTRING(per.nombre_periodo, 1, 1)), LOWER(SUBSTRING(per.nombre_periodo, 2)))
                    ELSE 'Sin plan' 
                END as nombre_plan,
                s.fecha_inicio,
                s.fecha_fin,
                s.precio,
                s.duracion_dias,
                s.id_estadoSuscripcion,
                es.nombre_estado,
                s.observaciones
            FROM tbl_suscripcion s
            JOIN tbl_cliente c ON c.id_cliente = s.id_cliente
            LEFT JOIN tbl_plan p ON p.id_plan = s.id_plan
            LEFT JOIN tbl_periodo per ON per.id_periodo = s.id_plan
            LEFT JOIN tbl_estado_suscripcion es ON es.id_estadoSuscripcion = s.id_estadoSuscripcion
            WHERE s.id_suscripcion = ?
            LIMIT 1
        ");
        
        if (!$stmt) {
            error_log("Get subscription error: Prepare failed - " . $conexion->error);
            echo json_encode(['success' => false, 'error' => 'Error en consulta SQL: ' . $conexion->error]);
            exit;
        }
        
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            // Log para debugging
            error_log("Get subscription success for ID $id: " . json_encode($row));
            echo json_encode(['success' => true, 'data' => $row]);
        } else {
            error_log("Get subscription error: No data found for ID $id");
            echo json_encode(['success' => false, 'error' => 'Suscripción no encontrada con ID: ' . $id]);
        }
        
        $stmt->close();
    } catch (Exception $e) {
        error_log("Get subscription exception: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Error interno: ' . $e->getMessage()]);
    }
    exit;
}

if ($action === 'edit') {
    // Editar suscripción existente
    error_log("Edit subscription request received");
    
    $id_suscripcion = $_POST['id_suscripcion'] ?? '';
    $nombre_cliente = trim($_POST['nombre_cliente'] ?? '');
    $correo_electronico = trim($_POST['correo_electronico'] ?? '');
    $id_plan = $_POST['id_plan'] ?? '';
    $fecha_inicio = $_POST['fecha_inicio'] ?? '';
    $precio = $_POST['precio'] ?? '';
    $duracion_dias = $_POST['duracion_dias'] ?? '';
    $id_estado = $_POST['id_estado'] ?? '';
    $observaciones = $_POST['observaciones'] ?? '';
    
    // Log de datos recibidos
    error_log("Edit subscription data: " . json_encode([
        'id_suscripcion' => $id_suscripcion,
        'nombre_cliente' => $nombre_cliente,
        'correo_electronico' => $correo_electronico,
        'id_plan' => $id_plan,
        'fecha_inicio' => $fecha_inicio,
        'precio' => $precio,
        'duracion_dias' => $duracion_dias,
        'id_estado' => $id_estado
    ]));
    
    // Validaciones
    if (!$id_suscripcion || !is_numeric($id_suscripcion)) {
        echo json_encode(['success' => false, 'error' => 'ID de suscripción requerido']);
        exit;
    }
    if ($nombre_cliente === '') {
        echo json_encode(['success' => false, 'error' => 'Nombre de cliente requerido']);
        exit;
    }
    if ($correo_electronico === '') {
        echo json_encode(['success' => false, 'error' => 'Correo electrónico requerido']);
        exit;
    }
    if (!$id_plan || !is_numeric($id_plan)) {
        echo json_encode(['success' => false, 'error' => 'Plan requerido']);
        exit;
    }
    if ($fecha_inicio === '') {
        echo json_encode(['success' => false, 'error' => 'Fecha de inicio requerida']);
        exit;
    }
    
    // Obtener datos originales para bitácora
    $getOriginal = $conexion->prepare("
        SELECT 
            s.id_cliente,
            c.nombre_cliente as nombre_original,
            c.correo_electronico as correo_original,
            s.id_plan as plan_original,
            CASE 
                WHEN p.nombre_plan IS NOT NULL THEN p.nombre_plan
                WHEN per.nombre_periodo IS NOT NULL THEN CONCAT(UPPER(SUBSTRING(per.nombre_periodo, 1, 1)), LOWER(SUBSTRING(per.nombre_periodo, 2)))
                ELSE 'Sin plan' 
            END as nombre_plan_original,
            s.fecha_inicio as fecha_inicio_original,
            s.fecha_fin as fecha_fin_original,
            s.precio as precio_original,
            s.duracion_dias as duracion_original,
            s.id_estadoSuscripcion as estado_original,
            s.observaciones as observaciones_original
        FROM tbl_suscripcion s
        JOIN tbl_cliente c ON c.id_cliente = s.id_cliente
        LEFT JOIN tbl_plan p ON p.id_plan = s.id_plan
        LEFT JOIN tbl_periodo per ON per.id_periodo = s.id_plan
        WHERE s.id_suscripcion = ?
        LIMIT 1
    ");
    $getOriginal->bind_param('i', $id_suscripcion);
    $getOriginal->execute();
    $originalData = $getOriginal->get_result()->fetch_assoc();
    
    if (!$originalData) {
        echo json_encode(['success' => false, 'error' => 'Suscripción no encontrada']);
        exit;
    }
    
    $id_cliente = $originalData['id_cliente'];
    
    // Actualizar cliente
    $updateCliente = $conexion->prepare("UPDATE tbl_cliente SET nombre_cliente = ?, correo_electronico = ? WHERE id_cliente = ?");
    $updateCliente->bind_param('ssi', $nombre_cliente, $correo_electronico, $id_cliente);
    
    if (!$updateCliente->execute()) {
        echo json_encode(['success' => false, 'error' => 'Error al actualizar cliente: ' . $updateCliente->error]);
        exit;
    }
    
    // Obtener el nombre del plan para asegurar registro en tbl_plan
    $plan_name = '';
    $getPlanName = $conexion->prepare("SELECT nombre_periodo FROM tbl_periodo WHERE id_periodo = ? LIMIT 1");
    $getPlanName->bind_param('i', $id_plan);
    $getPlanName->execute();
    $planResult = $getPlanName->get_result();
    if ($planRow = $planResult->fetch_assoc()) {
        $plan_name = ucfirst(strtolower($planRow['nombre_periodo']));
    }
    $getPlanName->close();
    
    // Asegurar que existe el registro en tbl_plan
    if (!empty($plan_name)) {
        if (!asegurarRegistroPlan($id_plan, $plan_name)) {
            error_log("Advertencia: No se pudo asegurar el registro en tbl_plan para ID: $id_plan, Nombre: $plan_name");
        }
    }

    // Calcular fecha_fin basada en fecha_inicio y duracion_dias
    // Duración completa sin restar días (si dura 30 días, debe durar exactamente 30 días)
    $fecha_fin = date('Y-m-d', strtotime($fecha_inicio . " + " . $duracion_dias . " days"));
    
    // Actualizar suscripción
    $updateSuscripcion = $conexion->prepare("
        UPDATE tbl_suscripcion 
        SET id_plan = ?, fecha_inicio = ?, fecha_fin = ?, precio = ?, duracion_dias = ?, id_estadoSuscripcion = ?, observaciones = ?
        WHERE id_suscripcion = ?
    ");
    $updateSuscripcion->bind_param('issdiisi', $id_plan, $fecha_inicio, $fecha_fin, $precio, $duracion_dias, $id_estado, $observaciones, $id_suscripcion);
    
    if ($updateSuscripcion->execute()) {
        // Registrar en bitácora los cambios
        if (!empty($OBJ_SUSCRIPCIONES_ID)) {
            $id_usuario = $_SESSION['usuario_id'] ?? null;
            $cambios = [];
            
            if ($originalData['nombre_original'] !== $nombre_cliente) {
                $cambios[] = "nombre cliente: '{$originalData['nombre_original']}' → '$nombre_cliente'";
            }
            if ($originalData['correo_original'] !== $correo_electronico) {
                $cambios[] = "correo: '{$originalData['correo_original']}' → '$correo_electronico'";
            }
            if ($originalData['plan_original'] != $id_plan) {
                $cambios[] = "plan ID: {$originalData['plan_original']} → $id_plan";
            }
            if ($originalData['fecha_inicio_original'] !== $fecha_inicio) {
                $cambios[] = "fecha inicio: '{$originalData['fecha_inicio_original']}' → '$fecha_inicio'";
            }
            if ($originalData['fecha_fin_original'] !== $fecha_fin) {
                $cambios[] = "fecha fin: '{$originalData['fecha_fin_original']}' → '$fecha_fin'";
            }
            if ($originalData['precio_original'] != $precio) {
                $cambios[] = "precio: {$originalData['precio_original']} → $precio";
            }
            if ($originalData['duracion_original'] != $duracion_dias) {
                $cambios[] = "duración: {$originalData['duracion_original']} → $duracion_dias días";
            }
            if ($originalData['estado_original'] != $id_estado) {
                $cambios[] = "estado ID: {$originalData['estado_original']} → $id_estado";
            }
            if (trim($originalData['observaciones_original'] ?? '') !== trim($observaciones)) {
                $cambios[] = "observaciones actualizadas";
            }
            
            if (!empty($cambios)) {
                $desc = sprintf(
                    'Se editó la suscripción ID %d (cliente ID %d). Cambios: %s',
                    $id_suscripcion,
                    $id_cliente,
                    implode(', ', $cambios)
                );
                registrar_bitacora($conexion, $id_usuario, $OBJ_SUSCRIPCIONES_ID, 'UPDATE', $desc);
            }
        }
        
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Error al actualizar suscripción: ' . $updateSuscripcion->error]);
    }
    exit;
}

if ($action === 'stats') {
    // Estadísticas para el panel de alertas
    $stats = [];
    
    // Suscripciones vencidas
    $vencidas = $conexion->query("
        SELECT COUNT(*) as count 
        FROM tbl_suscripcion s 
        JOIN tbl_estado_suscripcion es ON s.id_estadoSuscripcion = es.id_estadoSuscripcion 
        WHERE es.nombre_estado = 'VENCIDA'
    ");
    $stats['vencidas'] = $vencidas ? $vencidas->fetch_assoc()['count'] : 0;
    
    // Suscripciones próximas a vencer (próximos 7 días)
    $proximas = $conexion->query("
        SELECT COUNT(*) as count 
        FROM tbl_suscripcion s 
        JOIN tbl_estado_suscripcion es ON s.id_estadoSuscripcion = es.id_estadoSuscripcion 
        WHERE es.nombre_estado = 'ACTIVA' AND DATEDIFF(s.fecha_fin, CURDATE()) BETWEEN 0 AND 7
    ");
    $stats['proximas'] = $proximas ? $proximas->fetch_assoc()['count'] : 0;
    
    // Suscripciones activas
    $activas = $conexion->query("
        SELECT COUNT(*) as count 
        FROM tbl_suscripcion s 
        JOIN tbl_estado_suscripcion es ON s.id_estadoSuscripcion = es.id_estadoSuscripcion 
        WHERE es.nombre_estado = 'ACTIVA'
    ");
    $stats['activas'] = $activas ? $activas->fetch_assoc()['count'] : 0;
    
    // Total de suscripciones
    $total = $conexion->query("SELECT COUNT(*) as count FROM tbl_suscripcion");
    $stats['total'] = $total ? $total->fetch_assoc()['count'] : 0;
    
    echo json_encode($stats);
    exit;
}

if ($action === 'alerts_detailed') {
    // Alertas detalladas para la tabla
    $sql = "SELECT 
        s.id_suscripcion, 
        c.nombre_cliente,
        CASE 
            WHEN tp.nombre_plan IS NOT NULL THEN tp.nombre_plan
            WHEN per.nombre_periodo IS NOT NULL THEN CONCAT(UPPER(SUBSTRING(per.nombre_periodo, 1, 1)), LOWER(SUBSTRING(per.nombre_periodo, 2)))
            ELSE 'Sin plan' 
        END as nombre_plan, 
        s.fecha_fin, 
        es.nombre_estado,
        DATEDIFF(s.fecha_fin, CURDATE()) as dias_restantes
    FROM tbl_suscripcion s 
    JOIN tbl_cliente c ON c.id_cliente = s.id_cliente 
    LEFT JOIN tbl_plan tp ON tp.id_plan = s.id_plan
    LEFT JOIN tbl_periodo per ON per.id_periodo = s.id_plan
    LEFT JOIN tbl_estado_suscripcion es ON es.id_estadoSuscripcion = s.id_estadoSuscripcion 
    WHERE (DATEDIFF(s.fecha_fin, CURDATE()) BETWEEN 0 AND 7) OR es.nombre_estado IN ('VENCIDA') 
    ORDER BY s.id_suscripcion ASC";
    
    $res = $conexion->query($sql);
    $out = [];
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $out[] = $r;
        }
    }
    echo json_encode($out);
    exit;
}

// Acción desconocida
echo json_encode([]);
exit;
