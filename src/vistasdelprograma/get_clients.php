<?php
require_once __DIR__ . '/../config/errorlogs.php';// Ajusta la ruta según tu estructura

use App\config\errorlogs;
errorlogs::activa_error_logs();

require 'con_db.php';
header('Content-Type: application/json');

try {
    if (!isset($conexion)) {
        throw new Exception("No hay conexión a la base de datos");
    }

    // Verificar si la conexión está activa
    if (!mysqli_ping($conexion)) {
        throw new Exception("La conexión a la base de datos no está activa");
    }

    // Consulta para obtener correos y nombres de clientes
    $sql = "SELECT correo_electronico, nombre_cliente FROM tbl_cliente 
            WHERE correo_electronico IS NOT NULL 
            AND correo_electronico != '' 
            AND nombre_cliente IS NOT NULL 
            AND nombre_cliente != '' 
            ORDER BY nombre_cliente";
    $result = mysqli_query($conexion, $sql);

    if (!$result) {
        throw new Exception("Error en la consulta MySQL: " . mysqli_error($conexion));
    }

    $clients = array();
    while ($row = mysqli_fetch_assoc($result)) {
        if (!empty($row['correo_electronico']) && !empty($row['nombre_cliente'])) {
            $clients[] = array(
                'correo_electronico' => trim($row['correo_electronico']),
                'nombre_cliente' => trim($row['nombre_cliente'])
            );
        }
    }

    echo json_encode([
        'success' => true,
        'data' => $clients,
        'debug' => [
            'total_clients' => count($clients),
            'sql' => $sql,
            'connection_info' => [
                'host' => $host ?? 'unknown',
                'database' => $db ?? 'unknown',
                'connected' => mysqli_ping($conexion)
            ]
        ]
    ]);

} catch (Exception $e) {
    // Devolver error en formato JSON
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'debug' => [
            'file' => __FILE__,
            'line' => __LINE__
        ]
    ]);
    // Asegurarnos de no imprimir nada más
    exit;
}

// Cerrar la conexión si existe (usamos la variable $conexion desde con_db.php)
if (isset($conexion)) {
    @mysqli_close($conexion);
}
