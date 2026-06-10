<?php
session_start();
require 'con_db.php';
require '../../fpdf182/fpdf.php';
date_default_timezone_set('America/Tegucigalpa');
require_once __DIR__ . '/../config/errorlogs.php';
require_once __DIR__ . '/../config/roles.php';

use App\config\errorlogs;
errorlogs::activa_error_logs();

if (empty($_SESSION['usuario']) || empty($_SESSION['usuario_id'])) {
    die('Debes iniciar sesión para generar el reporte.');
}

require_permission('notificaciones', PERM_READ);

// Obtener el tipo de reporte
$tipo = $_GET['tipo'] ?? 'notificaciones';

if ($tipo !== 'notificaciones') {
    die('Tipo de reporte no válido.');
}

// Consulta para obtener las notificaciones
$tabla = "tbl_calendario ca
          JOIN tbl_cliente c ON ca.id_cliente = c.id_cliente
          JOIN tbl_estado_calendario ec ON ca.id_estadoCalendario = ec.id_estadoCalendario";
$campos = "c.nombre_cliente AS Cliente,
           ca.contenido AS Contenido,
           ec.nombre_estado AS Estado,
           ca.fecha_notificacion AS Fecha,
           ca.hora_notificacion AS Hora";

$query = "SELECT $campos FROM $tabla ORDER BY ca.fecha_notificacion DESC, ca.hora_notificacion DESC";
$result = $conexion->query($query);

if (!$result) {
    die('Error al obtener las notificaciones: ' . $conexion->error);
}

$notificaciones = [];
while ($row = $result->fetch_assoc()) {
    $notificaciones[] = $row;
}
$result->free();

// Clase personalizada para el PDF
class PDFNotificaciones extends FPDF {
    function Header() {
        // Fondo del header con gradiente simulado
        $this->SetFillColor(236, 151, 31); // Naranja oscuro
        $this->Rect(0, 0, 297, 35, 'F');
        
        // Logo (si existe)
        $logoPath = '../../assets/img/logo_empresa.png';
        $fallbackLogo = '../../public/logo.jpg';
        
        if (file_exists($logoPath)) {
            $this->Image($logoPath, 15, 8, 20, 20);
        } elseif (file_exists($fallbackLogo)) {
            $this->Image($fallbackLogo, 15, 8, 20, 20);
        }
        
        // Título principal
        $this->SetFont('Arial', 'B', 20);
        $this->SetTextColor(255, 255, 255);
        $this->SetXY(45, 12);
        $this->Cell(0, 8, 'GYM CLUB', 0, 1, 'L');
        
        // Subtítulo
        $this->SetFont('Arial', 'B', 14);
        $this->SetXY(45, 20);
        $this->Cell(0, 6, 'REPORTE DE NOTIFICACIONES', 0, 1, 'L');
        
        // Información de la empresa (lado derecho)
        $this->SetFont('Arial', '', 9);
        $this->SetXY(200, 10);
        $this->Cell(0, 4, 'Sistema de Gestion de Usuarios', 0, 1, 'R');
        $this->SetXY(200, 14);
        $this->Cell(0, 4, 'Modulo de Notificaciones', 0, 1, 'R');
        $this->SetXY(200, 18);
        $this->Cell(0, 4, 'Fecha: ' . date('d/m/Y H:i:s'), 0, 1, 'R');
        
        // Línea decorativa
        $this->SetDrawColor(255, 255, 255);
        $this->SetLineWidth(1);
        $this->Line(15, 32, 282, 32);
        
        $this->Ln(40);
        
        // Resetear colores para el contenido
        $this->SetTextColor(0, 0, 0);
    }
    
    function Footer() {
        $this->SetY(-20);
        
        // Línea decorativa superior
        $this->SetDrawColor(236, 151, 31);
        $this->SetLineWidth(0.5);
        $this->Line(15, $this->GetY(), 282, $this->GetY());
        
        $this->Ln(3);
        
        // Información del pie
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(100, 100, 100);
        $this->Cell(0, 4, 'Documento generado automaticamente por el Sistema de Gestion de Usuarios', 0, 1, 'C');
        $this->Cell(0, 4, 'Pagina ' . $this->PageNo() . ' - Generado el ' . date('d/m/Y H:i:s'), 0, 0, 'C');
    }
    
    // Función para crear secciones con estilo
    function CrearSeccion($titulo, $contenido = '') {
        $this->SetFont('Arial', 'B', 12);
        $this->SetFillColor(253, 245, 230); // Naranja muy claro
        $this->SetTextColor(236, 151, 31); // Naranja oscuro
        $this->Cell(0, 8, $titulo, 0, 1, 'L', true);
        $this->SetTextColor(0, 0, 0);
        
        if ($contenido) {
            $this->SetFont('Arial', '', 10);
            $this->Cell(0, 6, $contenido, 0, 1, 'L');
        }
        $this->Ln(3);
    }
}

// Crear el PDF
$pdf = new PDFNotificaciones('L', 'mm', 'A4');
$pdf->AddPage();

// Obtener total para uso posterior
$totalNotificaciones = count($notificaciones);

// Tabla de notificaciones con diseño mejorado
if (!empty($notificaciones)) {
    $pdf->CrearSeccion('DETALLE DE NOTIFICACIONES');
    
    // Encabezados de tabla con diseño corporativo
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->SetFillColor(236, 151, 31); // Naranja corporativo
    $pdf->SetTextColor(255, 255, 255); // Texto blanco
    $pdf->SetDrawColor(200, 200, 200); // Bordes grises
    $pdf->SetLineWidth(0.3);
    
    $pdf->Cell(55, 10, 'CLIENTE', 1, 0, 'C', true);
    $pdf->Cell(85, 10, 'CONTENIDO', 1, 0, 'C', true);
    $pdf->Cell(35, 10, 'ESTADO', 1, 0, 'C', true);
    $pdf->Cell(30, 10, 'FECHA', 1, 0, 'C', true);
    $pdf->Cell(25, 10, 'HORA', 1, 1, 'C', true);

    // Datos de la tabla con alternado de colores
    $pdf->SetFont('Arial', '', 8);
    $pdf->SetTextColor(0, 0, 0); // Texto negro
    $fill = false;
    
    foreach ($notificaciones as $index => $notif) {
        // Alternar colores de fondo
        if ($fill) {
            $pdf->SetFillColor(253, 245, 230); // Naranja muy claro
        } else {
            $pdf->SetFillColor(255, 255, 255); // Blanco
        }
        
        // Procesar y truncar textos largos
        $cliente = iconv('UTF-8', 'ISO-8859-1//IGNORE', $notif['Cliente']);
        if (strlen($cliente) > 35) {
            $cliente = substr($cliente, 0, 32) . '...';
        }
        
        $contenido = iconv('UTF-8', 'ISO-8859-1//IGNORE', $notif['Contenido']);
        if (strlen($contenido) > 65) {
            $contenido = substr($contenido, 0, 62) . '...';
        }
        
        $estado = iconv('UTF-8', 'ISO-8859-1//IGNORE', $notif['Estado']);
        if (strlen($estado) > 25) {
            $estado = substr($estado, 0, 22) . '...';
        }
        
        // Formatear fecha
        $fechaFormateada = date('d/m/Y', strtotime($notif['Fecha']));
        
        // Dibujar celdas con altura dinámica si es necesario
        $alturaFila = 7;
        
        $pdf->Cell(55, $alturaFila, $cliente, 1, 0, 'L', $fill);
        $pdf->Cell(85, $alturaFila, $contenido, 1, 0, 'L', $fill);
        $pdf->Cell(35, $alturaFila, $estado, 1, 0, 'C', $fill);
        $pdf->Cell(30, $alturaFila, $fechaFormateada, 1, 0, 'C', $fill);
        $pdf->Cell(25, $alturaFila, $notif['Hora'], 1, 1, 'C', $fill);
        
        $fill = !$fill;
        
        // Verificar si necesitamos una nueva página
        if ($pdf->GetY() > 180) {
            $pdf->AddPage();
            // Redibujar encabezados
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->SetFillColor(236, 151, 31);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->Cell(55, 10, 'CLIENTE', 1, 0, 'C', true);
            $pdf->Cell(85, 10, 'CONTENIDO', 1, 0, 'C', true);
            $pdf->Cell(35, 10, 'ESTADO', 1, 0, 'C', true);
            $pdf->Cell(30, 10, 'FECHA', 1, 0, 'C', true);
            $pdf->Cell(25, 10, 'HORA', 1, 1, 'C', true);
            $pdf->SetFont('Arial', '', 8);
            $pdf->SetTextColor(0, 0, 0);
        }
    }
    
    // Línea final de la tabla
    $pdf->Ln(5);
    $pdf->SetFont('Arial', 'I', 8);
    $pdf->SetTextColor(100, 100, 100);
    $pdf->Cell(0, 4, "Mostrando $totalNotificaciones registros totales", 0, 1, 'R');
    
} else {
    $pdf->CrearSeccion('DETALLE DE NOTIFICACIONES');
    $pdf->SetFont('Arial', '', 12);
    $pdf->SetTextColor(150, 150, 150);
    $pdf->Cell(0, 10, 'No se encontraron notificaciones para mostrar en este periodo.', 0, 1, 'C');
}

// Información adicional al final del documento
$pdf->Ln(10);
$pdf->CrearSeccion('INFORMACION DEL REPORTE');
$pdf->SetFont('Arial', '', 9);
$pdf->Cell(0, 4, 'Usuario que genero el reporte: ' . ($_SESSION['usuario'] ?? 'Sistema'), 0, 1, 'L');
$pdf->Cell(0, 4, 'Fecha y hora de generacion: ' . date('d/m/Y H:i:s'), 0, 1, 'L');
$pdf->Cell(0, 4, 'Tipo de reporte: Notificaciones del Sistema', 0, 1, 'L');
$pdf->Cell(0, 4, 'Total de registros: ' . $totalNotificaciones, 0, 1, 'L');

// Generar el PDF
$nombreArchivo = 'reporte_notificaciones_' . date('Y-m-d_H-i-s') . '.pdf';
$pdf->Output('D', $nombreArchivo); // 'D' para forzar descarga

$conexion->close();
?>