<?php
session_start();
require 'con_db.php';
require 'bitacora_helpers.php';
require '../../vendor/tcpdf/TCPDF-main/tcpdf.php';
date_default_timezone_set('America/Tegucigalpa');
require_once __DIR__ . '/../config/errorlogs.php';

use App\config\errorlogs;
errorlogs::activa_error_logs();

// Verificar sesión
if (empty($_SESSION['usuario']) || empty($_SESSION['usuario_id'])) {
    die('No tiene permisos para acceder a este recurso.');
}

// Obtener parámetro de filtro desde GET (que viene del campo de búsqueda)
$filtro = isset($_GET['filtro']) ? trim($_GET['filtro']) : '';

// Construir consulta base
$query_pagos = "
    SELECT p.*, 
           c.nombre_cliente,
           c.correo_electronico,
           s.precio as precio_suscripcion,
           pl.nombre_plan,
           es.nombre_estado as estado_suscripcion
    FROM tbl_pago p
    LEFT JOIN tbl_suscripcion s ON p.id_suscripcion = s.id_suscripcion
    LEFT JOIN tbl_cliente c ON s.id_cliente = c.id_cliente
    LEFT JOIN tbl_plan pl ON s.id_plan = pl.id_plan
    LEFT JOIN tbl_estado_suscripcion es ON s.id_estadoSuscripcion = es.id_estadoSuscripcion";

// Aplicar filtro si existe - BUSCAR EN TODOS LOS CAMPOS
$where_conditions = [];
$params = [];
$types = '';

if (!empty($filtro)) {
    // Buscar en múltiples campos: nombre cliente, método pago, referencia, ID pago, ID suscripción, plan, estado
    $where_conditions[] = "(c.nombre_cliente LIKE ? 
                         OR p.metodo_pago LIKE ? 
                         OR p.referencia_pago LIKE ? 
                         OR p.id_pago LIKE ? 
                         OR p.id_suscripcion LIKE ? 
                         OR pl.nombre_plan LIKE ? 
                         OR es.nombre_estado LIKE ?)";
    
    // Agregar el mismo parámetro 7 veces para buscar en todos los campos
    for ($i = 0; $i < 7; $i++) {
        $params[] = "%" . $filtro . "%";
        $types .= 's';
    }
}

// Agregar condiciones WHERE si existen
if (!empty($where_conditions)) {
    $query_pagos .= " WHERE " . implode(" AND ", $where_conditions);
}

$query_pagos .= " ORDER BY p.fecha_pago DESC";

// Preparar y ejecutar consulta
$stmt = $conexion->prepare($query_pagos);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result_pagos = $stmt->get_result();

// Calcular totales con el mismo filtro
$query_totales = "
    SELECT 
        COUNT(*) as total_pagos,
        SUM(p.monto) as monto_total,
        AVG(p.monto) as promedio_pago
    FROM tbl_pago p
    LEFT JOIN tbl_suscripcion s ON p.id_suscripcion = s.id_suscripcion
    LEFT JOIN tbl_cliente c ON s.id_cliente = c.id_cliente
    LEFT JOIN tbl_plan pl ON s.id_plan = pl.id_plan
    LEFT JOIN tbl_estado_suscripcion es ON s.id_estadoSuscripcion = es.id_estadoSuscripcion";

// Aplicar mismo filtro a totales
if (!empty($where_conditions)) {
    $query_totales .= " WHERE " . implode(" AND ", $where_conditions);
}

$stmt_totales = $conexion->prepare($query_totales);
if (!empty($params)) {
    $stmt_totales->bind_param($types, ...$params);
}
$stmt_totales->execute();
$result_totales = $stmt_totales->get_result();
$totales = $result_totales->fetch_assoc();

// Si no hay resultados, usar valores por defecto
if (!$totales) {
    $totales = ['total_pagos' => 0, 'monto_total' => 0, 'promedio_pago' => 0];
}

// Registrar en bitácora
$accion_bitacora = !empty($filtro) 
    ? "Usuario generó reporte de pagos filtrado por: '{$filtro}'" 
    : "Usuario generó reporte general de pagos en PDF";
    
registrar_bitacora($conexion, $_SESSION['usuario_id'], 10, 'REPORT', $accion_bitacora);

// === Clase personalizada con pie de página ===
class PDFConPaginacion extends TCPDF {
    public function Footer() {
        $this->SetY(-18);
        $this->SetFont('helvetica', 'I', 8);
        $this->Cell(0, 6, 'Este documento es un comprobante de pago generado automáticamente.', 0, 1, 'C');
        $this->Cell(0, 6, 'Fecha de generación: ' . date('d/m/Y H:i:s'), 0, 1, 'C');
        $this->Cell(0, 6, 'Página ' . $this->getAliasNumPage() . ' de ' . $this->getAliasNbPages(), 0, 0, 'C');
    }
}

// Crear PDF usando la nueva clase
$pdf = new PDFConPaginacion('L', 'mm', 'A4', true, 'UTF-8', false);

// Información del documento
$pdf->SetCreator('Sistema de Gestión');
$pdf->SetAuthor('Gym Club');
$pdf->SetTitle(!empty($filtro) ? 'Reporte de Pagos - Filtrado' : 'Reporte General de Pagos');
$pdf->SetSubject('Reporte de Pagos');

// Margenes
$pdf->SetMargins(10, 15, 10);
$pdf->SetAutoPageBreak(TRUE, 20);

// Agregar página
$pdf->AddPage();

// Logo y encabezado
$preferredLogo = '../../assets/img/logo_empresa.png';
$fallbackLogo = '../../public/logo.jpg';
$logo_path = $preferredLogo;
$logo_type = 'PNG';
if (!file_exists($preferredLogo) || !function_exists('imagecreatefrompng')) {
    $logo_path = $fallbackLogo;
    $logo_type = 'JPG';
}

// Primera línea: GYM CLUB
$pdf->SetFont('helvetica', 'B', 16);
$pdf->Cell(0, 10, 'GYM CLUB', 0, 1, 'C');

// Verificar si el archivo de logo existe y agregarlo centrado
if (file_exists($logo_path)) {
    $pageWidth = 297;
    $logoWidth = 25;
    $xPosition = ($pageWidth - $logoWidth) / 2;
    $pdf->Image($logo_path, $xPosition, $pdf->GetY(), $logoWidth, 0, $logo_type, '', 'T', false, 300, '', false, false, 0, false, false, true);
    $pdf->SetY($pdf->GetY() + 26);
} else {
    $pdf->Ln(5);
}

// Título del reporte
$pdf->SetFont('helvetica', 'B', 14);
if (!empty($filtro)) {
    $pdf->Cell(0, 10, 'REPORTE DE PAGOS', 0, 1, 'C');
} else {
    $pdf->Cell(0, 10, 'REPORTE GENERAL DE PAGOS', 0, 1, 'C');
}
$pdf->Ln(5);

// Información del reporte
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(40, 6, 'Fecha de generación:', 0, 0);
$pdf->Cell(0, 6, date('d/m/Y H:i:s'), 0, 1);

// Mostrar filtro aplicado si existe
if (!empty($filtro)) {
    $pdf->Cell(40, 6, 'Filtro aplicado:', 0, 0);
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(0, 6, '"' . $filtro . '"', 0, 1);
    $pdf->SetFont('helvetica', '', 10);
}

$pdf->Cell(40, 6, 'Total de pagos:', 0, 0);
$pdf->Cell(0, 6, $totales['total_pagos'], 0, 1);

$pdf->Cell(40, 6, 'Monto total:', 0, 0);
$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(0, 6, 'Lps ' . number_format($totales['monto_total'], 2), 0, 1);

$pdf->SetFont('helvetica', '', 10);
if ($totales['total_pagos'] > 0) {
    $pdf->Cell(40, 6, 'Promedio por pago:', 0, 0);
    $pdf->Cell(0, 6, 'Lps ' . number_format($totales['promedio_pago'], 2), 0, 1);
}

$pdf->Ln(10);

// Verificar si hay resultados
if ($result_pagos->num_rows === 0) {
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'NO SE ENCONTRARON PAGOS' . (!empty($filtro) ? ' PARA: "' . $filtro . '"' : ''), 0, 1, 'C');
    $pdf->Ln(10);
} else {
    // Encabezado de la tabla
    $pdf->SetFillColor(240, 173, 78);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('helvetica', 'B', 9);

    // Ajustar anchos de columnas para mejor visualización
    $pdf->Cell(15, 8, 'ID', 1, 0, 'C', true);
    $pdf->Cell(20, 8, 'Suscripción', 1, 0, 'C', true);
    $pdf->Cell(45, 8, 'Cliente', 1, 0, 'C', true);
    $pdf->Cell(30, 8, 'Plan', 1, 0, 'C', true);
    $pdf->Cell(25, 8, 'Monto', 1, 0, 'C', true);
    $pdf->Cell(30, 8, 'Método', 1, 0, 'C', true);
    $pdf->Cell(30, 8, 'Referencia', 1, 0, 'C', true);
    $pdf->Cell(25, 8, 'Fecha Pago', 1, 0, 'C', true);
    $pdf->Cell(25, 8, 'Estado', 1, 1, 'C', true);

    // Datos de la tabla
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('helvetica', '', 8);

    while ($pago = $result_pagos->fetch_assoc()) {
        // ID
        $pdf->Cell(15, 6, $pago['id_pago'], 1, 0, 'C');
        
        // Suscripción
        $pdf->Cell(20, 6, '#' . $pago['id_suscripcion'], 1, 0, 'C');
        
        // Cliente
        $pdf->Cell(45, 6, substr($pago['nombre_cliente'], 0, 22), 1, 0);
        
        // Plan
        $pdf->Cell(30, 6, substr($pago['nombre_plan'], 0, 18), 1, 0);
        
        // Monto
        $pdf->Cell(25, 6, 'Lps ' . number_format($pago['monto'], 2), 1, 0, 'R');
        
        // Método de Pago
        $metodo = $pago['metodo_pago'] ?: 'N/A';
        $pdf->Cell(30, 6, substr($metodo, 0, 12), 1, 0, 'C');
        
        // Referencia
        $referencia = $pago['referencia_pago'] ?: 'N/A';
        $pdf->Cell(30, 6, substr($referencia, 0, 10), 1, 0, 'C');
        
        // Fecha Pago
        $pdf->Cell(25, 6, date('d/m/Y', strtotime($pago['fecha_pago'])), 1, 0, 'C');
        
        // Estado
        $estado = $pago['estado_suscripcion'] ?: 'N/A';
        $pdf->Cell(25, 6, substr($estado, 0, 10), 1, 1, 'C');
    }

    $pdf->Ln(10);

    // Resumen por método de pago
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 8, 'RESUMEN POR MÉTODO DE PAGO', 0, 1);

    $query_metodos = "
        SELECT 
            p.metodo_pago,
            COUNT(*) as cantidad,
            SUM(p.monto) as total
        FROM tbl_pago p
        LEFT JOIN tbl_suscripcion s ON p.id_suscripcion = s.id_suscripcion
        LEFT JOIN tbl_cliente c ON s.id_cliente = c.id_cliente
        LEFT JOIN tbl_plan pl ON s.id_plan = pl.id_plan
        LEFT JOIN tbl_estado_suscripcion es ON s.id_estadoSuscripcion = es.id_estadoSuscripcion";

    // Aplicar mismo filtro a métodos de pago
    if (!empty($where_conditions)) {
        $query_metodos .= " WHERE " . implode(" AND ", $where_conditions);
    }

    $query_metodos .= " GROUP BY p.metodo_pago ORDER BY total DESC";

    $stmt_metodos = $conexion->prepare($query_metodos);
    if (!empty($params)) {
        $stmt_metodos->bind_param($types, ...$params);
    }
    $stmt_metodos->execute();
    $result_metodos = $stmt_metodos->get_result();

    $pdf->SetFont('helvetica', '', 10);
    $encontrados_metodos = false;
    while ($metodo = $result_metodos->fetch_assoc()) {
        $pdf->Cell(50, 6, $metodo['metodo_pago'] . ':', 0, 0);
        $pdf->Cell(30, 6, $metodo['cantidad'] . ' pagos', 0, 0);
        $pdf->Cell(0, 6, 'Lps ' . number_format($metodo['total'], 2), 0, 1);
        $encontrados_metodos = true;
    }
    
    if (!$encontrados_metodos) {
        $pdf->Cell(0, 6, 'No hay datos de métodos de pago para el filtro aplicado', 0, 1);
    }
}

// Cerrar statements
if (isset($stmt)) $stmt->close();
if (isset($stmt_totales)) $stmt_totales->close();
if (isset($stmt_metodos)) $stmt_metodos->close();

// Nombre del archivo PDF
$nombre_archivo = !empty($filtro) 
    ? 'reporte_pagos_filtrado_' . date('Y-m-d') . '.pdf' 
    : 'reporte_pagos_general_' . date('Y-m-d') . '.pdf';

// Salida del PDF
$pdf->Output($nombre_archivo, 'I');
?>