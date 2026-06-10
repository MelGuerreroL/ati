<?php
require '../../fpdf182/fpdf.php';
require 'con_db.php';

class PDF extends FPDF
{
    private $totalRecords = 0;
    
    function setTotalRecords($total) {
        $this->totalRecords = $total;
    }
    
    // Cabecera de página
    function Header()
    {
        // Agregar logo si existe
        if (file_exists('../../assets/img/logo_empresa.png')) {
            $this->Image('../../assets/img/logo_empresa.png', 15, 10, 25);
        } elseif (file_exists('../../assets/img/Logo.jpg')) {
            $this->Image('../../assets/img/Logo.jpg', 15, 10, 25);
        }
        
        // Espacio después del logo
        $this->Ln(8);
        
        // Título principal
        $this->SetFont('Arial', 'B', 18);
        $this->SetTextColor(240, 173, 78);
        $this->Cell(0, 12, 'SISTEMA DE GESTION DE USUARIOS', 0, 1, 'C');
        
        $this->SetFont('Arial', 'B', 14);
        $this->Cell(0, 8, 'REPORTE DE SUSCRIPCIONES - AUTOCALENDARIO', 0, 1, 'C');
        $this->Ln(5);
        
        // Información del reporte
        $this->SetFont('Arial', '', 9);
        $this->SetTextColor(0, 0, 0);
        $this->Cell(100, 5, 'Generado el: ' . date('d/m/Y H:i:s'), 0, 0, 'L');
        $this->Cell(0, 5, 'Total de registros: ' . $this->totalRecords, 0, 1, 'R');
        $this->Ln(8);
        
        // Encabezados de tabla con anchos mejorados
        $this->SetFont('Arial', 'B', 8);
        $this->SetFillColor(240, 173, 78);
        $this->SetTextColor(255, 255, 255);
        
        $this->Cell(18, 8, 'N. Susc.', 1, 0, 'C', true);
        $this->Cell(50, 8, 'Cliente', 1, 0, 'C', true);
        $this->Cell(25, 8, 'Fecha Inicio', 1, 0, 'C', true);
        $this->Cell(25, 8, 'Fecha Fin', 1, 0, 'C', true);
        $this->Cell(22, 8, 'Plan', 1, 0, 'C', true);
        $this->Cell(18, 8, 'D. Trans.', 1, 0, 'C', true);
        $this->Cell(18, 8, 'D. Rest.', 1, 0, 'C', true);
        $this->Cell(50, 8, 'Estado Notificacion', 1, 1, 'C', true);
        
        $this->SetTextColor(0, 0, 0);
        $this->SetFont('Arial', '', 7);
    }

    // Pie de página
    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(128, 128, 128);
        $this->Cell(0, 10, 'Pagina ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }
    
    // Función para truncar texto
    function TruncateText($text, $maxLength)
    {
        if (strlen($text) > $maxLength) {
            return substr($text, 0, $maxLength - 3) . '...';
        }
        return $text;
    }
}

// Contar total de registros primero
$count_sql = "SELECT COUNT(*) FROM tbl_suscripcion s
              INNER JOIN tbl_cliente cl ON s.id_cliente = cl.id_cliente
              WHERE s.fecha_inicio IS NOT NULL";
$count_result = mysqli_query($conexion, $count_sql);
$total_records = mysqli_fetch_row($count_result)[0];

// Crear instancia del PDF
$pdf = new PDF('L', 'mm', 'A4'); // Landscape para más espacio
$pdf->AliasNbPages();
$pdf->setTotalRecords($total_records);
$pdf->AddPage();

// Consulta SQL para obtener los datos (orden ascendente)
$sql = "SELECT s.id_suscripcion, 
               s.id_plan, 
               s.id_cliente,
               s.fecha_inicio,
               s.fecha_fin,
               DATEDIFF(CURDATE(), s.fecha_inicio) AS dias_transcurridos,
               DATEDIFF(s.fecha_fin, CURDATE()) AS dias_restantes,
               cl.nombre_cliente
        FROM tbl_suscripcion s
        INNER JOIN tbl_cliente cl ON s.id_cliente = cl.id_cliente
        WHERE s.fecha_inicio IS NOT NULL
        ORDER BY s.id_suscripcion ASC, s.fecha_inicio ASC";

$resultado = mysqli_query($conexion, $sql);

if ($resultado && mysqli_num_rows($resultado) > 0) {
    $count = 0;
    
    while ($row = mysqli_fetch_assoc($resultado)) {
        // Verificar si necesitamos nueva página
        if ($pdf->GetY() > 170 && $count > 0) {
            $pdf->AddPage();
        }
        
        $id_suscripcion = htmlspecialchars($row['id_suscripcion']);
        $id_plan_raw = (int)$row['id_plan'];
        $id_cliente = (int)$row['id_cliente'];
        $fecha_inicio = htmlspecialchars($row['fecha_inicio']);
        $fecha_fin = htmlspecialchars($row['fecha_fin']);
        $dias_transcurridos = (int)$row['dias_transcurridos'];
        $dias_restantes = (int)$row['dias_restantes'];
        $nombre_cliente = htmlspecialchars($row['nombre_cliente']);

        // Buscar periodicidad en tbl_tipo_notificacion usando id_plan
        $sql_tipo = "SELECT periodicidad FROM tbl_tipo_notificacion WHERE id_plan = $id_plan_raw LIMIT 1";
        $resultado_tipo = mysqli_query($conexion, $sql_tipo);

        $estado_texto = "Sin datos";
        $estado_color = [128, 128, 128]; // Gris por defecto

        if ($resultado_tipo && mysqli_num_rows($resultado_tipo) > 0) {
            $row_tipo = mysqli_fetch_assoc($resultado_tipo);
            $periodicidad = (int)$row_tipo['periodicidad'];

            // Buscar valor en tbl_ms_parametros usando periodicidad como id_parametro
            $sql_param = "SELECT valor FROM tbl_ms_parametros WHERE id_parametro = $periodicidad LIMIT 1";
            $resultado_param = mysqli_query($conexion, $sql_param);

            if ($resultado_param && mysqli_num_rows($resultado_param) > 0) {
                $row_param = mysqli_fetch_assoc($resultado_param);
                $valor_parametro = (int)$row_param['valor'];

                if ($dias_transcurridos === $valor_parametro) {
                    $estado_texto = "Notificacion hoy";
                    $estado_color = [21, 87, 36]; // Verde
                } else {
                    $diff = $valor_parametro - $dias_transcurridos;
                    if ($diff > 0) {
                        $estado_texto = "Faltan " . intval($diff) . " dia(s)";
                        $estado_color = [133, 100, 4]; // Amarillo
                    } else {
                        $estado_texto = "Pasaron " . intval(abs($diff)) . " dia(s)";
                        $estado_color = [114, 28, 36]; // Rojo
                    }
                }
            } else {
                $estado_texto = "Param. no encontrado";
                $estado_color = [255, 152, 0]; // Naranja
            }
        } else {
            $estado_texto = "Tipo notif. no encontr.";
            $estado_color = [255, 152, 0]; // Naranja
        }

        // Mapear id_plan a texto
        switch ($id_plan_raw) {
            case 123: $plan_label = "Mensual"; break;
            case 131: $plan_label = "Quincenal"; break;
            case 147: $plan_label = "Semanal"; break;
            case 148: $plan_label = "Anual"; break;
            case 149: $plan_label = "Diario"; break;
            case 150: $plan_label = "Semestral"; break;
            default: $plan_label = "Adquirido";
        }

        // Truncar nombre del cliente si es muy largo
        $nombre_truncado = $pdf->TruncateText($nombre_cliente, 30);
        $estado_truncado = $pdf->TruncateText($estado_texto, 30);
        
        // Color de fondo alternado para las filas
        $fill = ($count % 2 == 0);
        $pdf->SetFillColor(248, 249, 250);
        
        // Altura de celda mejorada
        $cell_height = 5;
        
        // Escribir las celdas
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell(18, $cell_height, $id_suscripcion, 1, 0, 'C', $fill);
        $pdf->Cell(50, $cell_height, $nombre_truncado, 1, 0, 'L', $fill);
        $pdf->Cell(25, $cell_height, $fecha_inicio, 1, 0, 'C', $fill);
        $pdf->Cell(25, $cell_height, $fecha_fin, 1, 0, 'C', $fill);
        $pdf->Cell(22, $cell_height, $plan_label, 1, 0, 'C', $fill);
        $pdf->Cell(18, $cell_height, $dias_transcurridos, 1, 0, 'C', $fill);
        
        // Color especial para días restantes
        if ($dias_restantes < 0) {
            $pdf->SetTextColor(220, 53, 69); // Rojo si vencido
            $dias_text = "Venc(" . abs($dias_restantes) . ")";
        } elseif ($dias_restantes <= 7) {
            $pdf->SetTextColor(255, 152, 0); // Naranja si quedan pocos días
            $dias_text = (string)$dias_restantes;
        } else {
            $pdf->SetTextColor(40, 167, 69); // Verde si están bien
            $dias_text = (string)$dias_restantes;
        }
        $pdf->Cell(18, $cell_height, $dias_text, 1, 0, 'C', $fill);
        
        // Color para estado de notificación
        $pdf->SetTextColor($estado_color[0], $estado_color[1], $estado_color[2]);
        $pdf->Cell(50, $cell_height, $estado_truncado, 1, 1, 'C', $fill);
        
        $count++;
    }
    
    mysqli_free_result($resultado);
} else {
    // Si no hay datos
    $pdf->SetFont('Arial', 'I', 12);
    $pdf->Cell(0, 20, 'No se encontraron suscripciones registradas.', 0, 1, 'C');
}

// Agregar estadísticas al final
$pdf->Ln(10);
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell(0, 6, 'RESUMEN DE SUSCRIPCIONES:', 0, 1, 'L');

// Estadísticas por plan
$sql_stats = "SELECT 
                s.id_plan,
                COUNT(*) as total,
                SUM(CASE WHEN DATEDIFF(s.fecha_fin, CURDATE()) < 0 THEN 1 ELSE 0 END) as vencidas,
                SUM(CASE WHEN DATEDIFF(s.fecha_fin, CURDATE()) BETWEEN 0 AND 7 THEN 1 ELSE 0 END) as por_vencer
              FROM tbl_suscripcion s
              WHERE s.fecha_inicio IS NOT NULL
              GROUP BY s.id_plan
              ORDER BY total DESC";

$stats_result = mysqli_query($conexion, $sql_stats);

$pdf->SetFont('Arial', '', 9);

while ($stat = mysqli_fetch_assoc($stats_result)) {
    $plan_id = $stat['id_plan'];
    $plan_name = '';
    switch ($plan_id) {
        case 123: $plan_name = "Mensual"; break;
        case 131: $plan_name = "Quincenal"; break;
        case 147: $plan_name = "Semanal"; break;
        case 148: $plan_name = "Anual"; break;
        case 149: $plan_name = "Diario"; break;
        case 150: $plan_name = "Semestral"; break;
        default: $plan_name = "Plan " . $plan_id;
    }
    
    $pdf->Cell(50, 6, $plan_name . ': ' . $stat['total'] . ' suscripciones', 0, 0, 'L');
    $pdf->Cell(40, 6, 'Vencidas: ' . $stat['vencidas'], 0, 0, 'L');
    $pdf->Cell(40, 6, 'Por vencer: ' . $stat['por_vencer'], 0, 1, 'L');
}

$conexion->close();

// Generar PDF
$pdf->Output('D', 'reporte_autocalendario_' . date('Y-m-d_H-i-s') . '.pdf');
?>