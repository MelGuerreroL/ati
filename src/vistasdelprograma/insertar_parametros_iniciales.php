<?php
session_start();
require 'con_db.php';
date_default_timezone_set('America/Tegucigalpa');
require_once __DIR__ . '/../config/errorlogs.php';

use App\config\errorlogs;
errorlogs::activa_error_logs();

// =======================================
// 1️⃣ VALIDAR SESIÓN DE USUARIO
// =======================================
if (empty($_SESSION['usuario_id'])) {
    header('Location: ../../public/index.php?error=Por favor inicia sesión para continuar.');
    exit();
}

// ID del usuario que ejecuta el proceso
$id_usuario = intval($_SESSION['usuario_id']);

// =======================================
// 2️⃣ FUNCIÓN PARA CONTAR REGISTROS
// =======================================
function contar($conexion, $tabla) {
    $query = "SELECT COUNT(*) AS total FROM $tabla";
    $res = $conexion->query($query);
    if ($res && $fila = $res->fetch_assoc()) {
        return intval($fila['total']);
    }
    return 0;
}

// =======================================
// 3️⃣ PARÁMETROS BASE DEL SISTEMA
// =======================================
// 🔹 Ya NO se incluye correo_usuario, nombre_usuario ni IMPUESTO
$parametros = [
    ['ADMIN_CPUERTO', '3000'], 
    ['ADMIN_CPASS', '********'],
    ['SYS_NOMBRE', 'GESTIÓN DE SUSCRIPCIONES']
];

// =======================================
// 4️⃣ PARÁMETROS AUTOMÁTICOS DE OTRAS TABLAS
// =======================================
$totalUsuarios = contar($conexion, 'tbl_ms_usuario');
$totalClientes = contar($conexion, 'tbl_cliente');
$totalPlanes = contar($conexion, 'tbl_plan');
$totalPagos = contar($conexion, 'tbl_pago');
$totalSuscripciones = contar($conexion, 'tbl_suscripcion');

// Total del monto pagado
$queryMonto = $conexion->query("SELECT SUM(monto) AS total FROM tbl_pago");
$totalMonto = 0;
if ($queryMonto && $fila = $queryMonto->fetch_assoc()) {
    $totalMonto = number_format($fila['total'] ?? 0, 2);
}

// Último pago y última suscripción
$ultimoPago = '';
$resPago = $conexion->query("SELECT fecha_pago FROM tbl_pago ORDER BY fecha_pago DESC LIMIT 1");
if ($resPago && $row = $resPago->fetch_assoc()) {
    $ultimoPago = $row['fecha_pago'];
}

$ultimaSuscripcion = '';
$resSus = $conexion->query("SELECT fecha_inicio FROM tbl_suscripcion ORDER BY fecha_inicio DESC LIMIT 1");
if ($resSus && $row = $resSus->fetch_assoc()) {
    $ultimaSuscripcion = $row['fecha_inicio'];
}

// Parámetros extras automáticos
$parametrosExtra = [
    ['TOTAL_USUARIOS', strval($totalUsuarios)],
    ['TOTAL_CLIENTES', strval($totalClientes)],
    ['TOTAL_PLANES', strval($totalPlanes)],
    ['TOTAL_PAGOS', strval($totalPagos)],
    ['TOTAL_SUSCRIPCIONES', strval($totalSuscripciones)],
    ['MONTO_TOTAL_PAGOS', $totalMonto],
    ['ULTIMO_PAGO', $ultimoPago],
    ['ULTIMA_SUSCRIPCION', $ultimaSuscripcion]
];

$parametros = array_merge($parametros, $parametrosExtra);

// =======================================
// 5️⃣ INSERTAR O ACTUALIZAR PARÁMETROS
// =======================================
foreach ($parametros as $p) {
    list($parametro, $valor) = $p;

    $check = $conexion->prepare("SELECT id_parametro FROM tbl_ms_parametros WHERE parametro = ?");
    $check->bind_param("s", $parametro);
    $check->execute();
    $check->store_result();

    if ($check->num_rows == 0) {
        // Insertar nuevo
        $stmt = $conexion->prepare("
            INSERT INTO tbl_ms_parametros 
            (parametro, valor, id_usuario, fecha_creado, fecha_modificado) 
            VALUES (?, ?, ?, NOW(), NOW())
        ");
        $stmt->bind_param("ssi", $parametro, $valor, $id_usuario);
        $stmt->execute();
        $stmt->close();
    } else {
        // Actualizar existente
        $stmt = $conexion->prepare("
            UPDATE tbl_ms_parametros 
            SET valor = ?, id_usuario = ?, fecha_modificado = NOW()
            WHERE parametro = ?
        ");
        $stmt->bind_param("sis", $valor, $id_usuario, $parametro);
        $stmt->execute();
        $stmt->close();
    }

    $check->close();
}

$conexion->close();

echo "<br><strong>✅ Proceso completado correctamente.</strong>";
?>
