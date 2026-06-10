<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require '../../fpdf182/fpdf.php';
require 'con_db.php';

class PDF extends FPDF
{
    private $totalRecords = 0;
    
    function setTotalRecords($total) {
        $this->totalRecords = $total;
    }
    
    // Cabecera
    function Header()
    {
        if (file_exists('../../assets/img/logo_empresa.png')) {
            $this->Image('../../assets/img/logo_empresa.png', 15, 10, 25);
        } elseif (file_exists('../../assets/img/Logo.jpg')) {
            $this->Image('../../assets/img/Logo.jpg', 15, 10, 25);
        }
        $this->Ln(8);
        $this->SetFont('Arial', 'B', 18);
        $this->SetTextColor(240, 173, 78);
        $this->Cell(0, 12, 'SISTEMA DE GESTION DE USUARIOS', 0, 1, 'C');
        $this->SetFont('Arial', 'B', 14);
        $this->Cell(0, 8, 'REPORTE DE NOTIFICACIONES - CALENDARIO', 0, 1, 'C');
        $this->Ln(5);
        $this->SetFont('Arial', '', 9);
        $this->SetTextColor(0, 0, 0);
        $this->Cell(100, 5, 'Generado el: ' . date('d/m/Y H:i:s'), 0, 0, 'L');
        $this->Cell(0, 5, 'Total de registros: ' . $this->totalRecords, 0, 1, 'R');
        $this->Ln(8);
        $this->SetFont('Arial', 'B', 8);
        $this->SetFillColor(240, 173, 78);
        $this->SetTextColor(255, 255, 255);
        $this->Cell(18, 8, 'N. Susc.', 1, 0, 'C', true);
        $this->Cell(38, 8, 'Cliente', 1, 0, 'C', true);
        $this->Cell(52, 8, 'Correo Electronico', 1, 0, 'C', true);
        $this->Cell(20, 8, 'Estado', 1, 0, 'C', true);
        $this->Cell(24, 8, 'Fecha Notif.', 1, 0, 'C', true);
        $this->Cell(16, 8, 'Hora', 1, 0, 'C', true);
        $this->Cell(30, 8, 'Fecha Envio', 1, 0, 'C', true);
        $this->Cell(12, 8, 'Int.', 1, 0, 'C', true);
        $this->Cell(36, 8, 'Contenido', 1, 1, 'C', true);
        $this->SetTextColor(0, 0, 0);
        $this->SetFont('Arial', '', 7);
    }

    // Pie
    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(128, 128, 128);
        $this->Cell(0, 10, 'Pagina ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }

    function TruncateText($text, $maxLength)
    {
        if (strlen($text) > $maxLength) {
            return substr($text, 0, $maxLength - 3) . '...';
        }
        return $text;
    }
}

// === Capturar filtros externos ===
$nombre = isset($_GET['nombre']) ? trim($_GET['nombre']) : '';
$estado = isset($_GET['estado']) ? trim($_GET['estado']) : '';
$contenido = isset($_GET['contenido']) ? trim($_GET['contenido']) : '';

$filtros = [];
if ($nombre !== '') {
    $filtros[] = "UPPER(cl.nombre_cliente) LIKE UPPER('%" . $conexion->real_escape_string($nombre) . "%')";
}
if ($estado !== '') {
    $mapEstados = ['PENDIENTE'=>1, 'ENVIADO'=>2, 'FALLIDO'=>3];
    if (isset($mapEstados[$estado])) {
        $filtros[] = "c.id_estadoCalendario = " . $mapEstados[$estado];
    }
}
if ($contenido !== '') {
    $filtros[] = "UPPER(c.contenido) LIKE UPPER('%" . $conexion->real_escape_string($contenido) . "%')";
}
$where = count($filtros) > 0 ? "WHERE " . implode(" AND ", $filtros) : "";

// === Contar total de registros ===
$count_sql = "SELECT COUNT(*) as total 
              FROM tbl_calendario c
              INNER JOIN tbl_suscripcion s ON c.id_suscripcion = s.id_suscripcion
              INNER JOIN tbl_cliente cl ON s.id_cliente = cl.id_cliente
              $where";
$count_result = $conexion->query($count_sql);
$total_records = $count_result ? $count_result->fetch_assoc()['total'] : 0;

// === Crear PDF ===
$pdf = new PDF('L', 'mm', 'A4');
$pdf->AliasNbPages();
$pdf->setTotalRecords($total_records);
$pdf->AddPage();

// === Consulta principal con filtros ===
$sql_calendario = "SELECT c.id_calendario, c.id_suscripcion, c.id_estadoCalendario, 
                          c.fecha_notificacion, c.hora_notificacion, c.fecha_envio, 
                          c.intento_count, c.contenido, 
                          cl.nombre_cliente, cl.correo_electronico,
                          s.id_estadoSuscripcion
                   FROM tbl_calendario c
                   INNER JOIN tbl_suscripcion s ON c.id_suscripcion = s.id_suscripcion
                   INNER JOIN tbl_cliente cl ON s.id_cliente = cl.id_cliente
                   $where
                   ORDER BY c.id_suscripcion ASC, c.fecha_notificacion ASC, c.hora_notificacion ASC";

$resultado_calendario = $conexion->query($sql_calendario);

if ($resultado_calendario && $resultado_calendario->num_rows > 0) {
    $count = 0;
    while ($fila = $resultado_calendario->fetch_assoc()) {
        if ($pdf->GetY() > 170 && $count > 0) {
            $pdf->AddPage();
        }
        $estado_texto = ($fila['id_estadoCalendario'] == 3) ? 'FALLIDO' : 
                       (($fila['id_estadoCalendario'] == 2) ? 'ENVIADO' : 'PENDIENTE');
        $nombre_truncado = $pdf->TruncateText($fila['nombre_cliente'], 23);
        $correo_truncado = $pdf->TruncateText($fila['correo_electronico'], 32);
        $contenido_truncado = $pdf->TruncateText($fila['contenido'], 24);
        $fill = ($count % 2 == 0);
        $pdf->SetFillColor(248, 249, 250);
        $cell_height = 5;
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell(18, $cell_height, $fila['id_suscripcion'], 1, 0, 'C', $fill);
        $pdf->Cell(38, $cell_height, $nombre_truncado, 1, 0, 'L', $fill);
        $pdf->Cell(52, $cell_height, $correo_truncado, 1, 0, 'L', $fill);
        if ($estado_texto == 'FALLIDO') {
            $pdf->SetTextColor(220, 53, 69);
        } elseif ($estado_texto == 'ENVIADO') {
            $pdf->SetTextColor(40, 167, 69);
        } else {
            $pdf->SetTextColor(255, 152, 0);
        }
        $pdf->Cell(20, $cell_height, $estado_texto, 1, 0, 'C', $fill);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell(24, $cell_height, $fila['fecha_notificacion'], 1, 0, 'C', $fill);
        $pdf->Cell(16, $cell_height, $fila['hora_notificacion'], 1, 0, 'C', $fill);
        $pdf->Cell(30, $cell_height, $fila['fecha_envio'], 1, 0, 'C', $fill);
        $pdf->Cell(12, $cell_height, $fila['intento_count'], 1, 0, 'C', $fill);
                $pdf->Cell(36, $cell_height, $contenido_truncado, 1, 1, 'L', $fill);
        $count++;
    }
} else {
    // Si no hay datos
    $pdf->SetFont('Arial', 'I', 12);
    $pdf->Cell(0, 20, 'No se encontraron registros en el calendario.', 0, 1, 'C');
}

// === Agregar estadísticas al final ===
$pdf->Ln(10);
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell(0, 6, 'RESUMEN DE ESTADOS:', 0, 1, 'L');

$sql_stats = "SELECT 
                SUM(CASE WHEN c.id_estadoCalendario = 1 THEN 1 ELSE 0 END) as pendientes,
                SUM(CASE WHEN c.id_estadoCalendario = 2 THEN 1 ELSE 0 END) as enviados,
                SUM(CASE WHEN c.id_estadoCalendario = 3 THEN 1 ELSE 0 END) as fallidos,
                COUNT(*) as total
              FROM tbl_calendario c
              INNER JOIN tbl_suscripcion s ON c.id_suscripcion = s.id_suscripcion
              INNER JOIN tbl_cliente cl ON s.id_cliente = cl.id_cliente
              $where";
$stats_result = $conexion->query($sql_stats);
$stats = $stats_result ? $stats_result->fetch_assoc() : ['total'=>0,'pendientes'=>0,'enviados'=>0,'fallidos'=>0];

$pdf->SetFont('Arial', '', 9);
$pdf->Cell(40, 6, 'Total de registros: ' . $stats['total'], 0, 0, 'L');
$pdf->Cell(30, 6, 'Pendientes: ' . $stats['pendientes'], 0, 0, 'L');
$pdf->Cell(25, 6, 'Enviados: ' . $stats['enviados'], 0, 0, 'L');
$pdf->Cell(25, 6, 'Fallidos: ' . $stats['fallidos'], 0, 1, 'L');

$conexion->close();

// === Mostrar PDF en navegador (no descargar) ===
$pdf->Output('I', 'reporte_calendario.pdf');
?>


?>