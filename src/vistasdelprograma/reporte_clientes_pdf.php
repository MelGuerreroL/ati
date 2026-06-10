<?php
session_start();
require 'con_db.php';
require 'bitacora_helpers.php';
require '../../vendor/tcpdf/TCPDF-main/tcpdf.php';
date_default_timezone_set('America/Tegucigalpa');
require_once __DIR__ . '/../config/errorlogs.php';// Ajusta la ruta según tu estructura

use App\config\errorlogs;
errorlogs::activa_error_logs();

if (empty($_SESSION['usuario']) || empty($_SESSION['usuario_id'])) {
    die('Debes iniciar sesión para generar el reporte.');
}

$usuarioId = intval($_SESSION['usuario_id'] ?? 0);
$rolId = intval($_SESSION['rol_id'] ?? 0);

// Debug temporal - comentar en producción
// error_log("DEBUG PDF: usuario_id = $usuarioId, rol_id = $rolId");

// Permitir a super admins (rol 1), usuarios básicos (rol 2), o el usuario de soporte (ID 25)
$esAdmin = ($rolId === 1 || $rolId === 2 || $usuarioId === 25);

$searchTerm = trim($_GET['search'] ?? '');
if ($searchTerm !== '') {
    $searchTerm = function_exists('mb_substr') ? mb_substr($searchTerm, 0, 100) : substr($searchTerm, 0, 100);
}
$searchApplied = ($searchTerm !== '');

if (!$esAdmin) {
    die('No tiene permisos para generar este reporte.');
}

$bitacoraDescripcion = 'Generó el reporte PDF de clientes registrados';
if ($searchApplied) {
    $bitacoraDescripcion .= ' (filtro: ' . $searchTerm . ')';
}
registrar_bitacora($conexion, $usuarioId, 3, 'REPORT', $bitacoraDescripcion);

$sqlBase = "SELECT c.id_cliente, c.nombre_cliente, c.correo_electronico, c.telefono_cliente, c.fecha_nacimiento, c.fecha_registro, c.observaciones, c.id_estadoCliente, ec.nombre_estado FROM tbl_cliente c LEFT JOIN tbl_estado_cliente ec ON ec.id_estadoCliente = c.id_estadoCliente";
$orderBy = ' ORDER BY c.id_cliente ASC';

if ($searchApplied) {
    $sqlClientes = $sqlBase . " WHERE c.nombre_cliente LIKE ? OR c.correo_electronico LIKE ? OR c.telefono_cliente LIKE ? OR ec.nombre_estado LIKE ?" . $orderBy;
    $stmt = $conexion->prepare($sqlClientes);
    if (!$stmt) {
        die('Error al preparar la consulta de clientes: ' . $conexion->error);
    }
    $likeParam = '%' . $searchTerm . '%';
    $stmt->bind_param('ssss', $likeParam, $likeParam, $likeParam, $likeParam);
    if (!$stmt->execute()) {
        $stmt->close();
        die('Error al ejecutar la consulta de clientes.');
    }
    $resultClientes = $stmt->get_result();
    if (!$resultClientes) {
        $stmt->close();
        die('Error al obtener los clientes filtrados.');
    }
} else {
    $sqlClientes = $sqlBase . $orderBy;
    $resultClientes = $conexion->query($sqlClientes);
    if (!$resultClientes) {
        die('Error al obtener los clientes: ' . $conexion->error);
    }
}

$clientes = [];
while ($row = $resultClientes->fetch_assoc()) {
    $clientes[] = $row;
}
if (isset($stmt)) {
    $stmt->close();
}
$resultClientes->free();

$totalClientes = count($clientes);
$activos = 0;
$inactivos = 0;
foreach ($clientes as $cliente) {
    $estadoId = intval($cliente['id_estadoCliente'] ?? 0);
    if ($estadoId === 1) {
        $activos++;
    } else {
        $inactivos++;
    }
}

class PDFClientes extends TCPDF {
    public function Footer() {
        $this->SetY(-18);
        $this->SetFont('helvetica', 'I', 8);
        $this->Cell(0, 6, 'Documento generado automáticamente por el sistema de Gestión de Clientes.', 0, 1, 'C');
        $this->Cell(0, 6, 'Fecha de emisión: ' . date('d/m/Y H:i:s'), 0, 1, 'C');
        $this->Cell(0, 6, 'Página ' . $this->getAliasNumPage() . ' de ' . $this->getAliasNbPages(), 0, 0, 'C');
    }
}

$pdf = new PDFClientes('L', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetCreator('Sistema de Gestión');
$pdf->SetAuthor('Gym Club');
$pdf->SetTitle('Reporte de Clientes Registrados');
$pdf->SetSubject('Clientes registrados');
$pdf->SetMargins(12, 18, 12);
$pdf->SetAutoPageBreak(true, 20);
$pdf->AddPage();

$preferredLogo = '../../assets/img/logo_empresa.png';
$fallbackLogo = '../../public/logo.jpg';
$logoPath = $preferredLogo;
$logoType = 'PNG';
if (!file_exists($preferredLogo) || !function_exists('imagecreatefrompng')) {
    $logoPath = $fallbackLogo;
    $logoType = 'JPG';
}

$pdf->SetFont('helvetica', 'B', 16);
$pdf->Cell(0, 10, 'GYM CLUB', 0, 1, 'C');

if (file_exists($logoPath)) {
    $pageWidth = 297;
    $logoWidth = 25;
    $xPos = ($pageWidth - $logoWidth) / 2;
    $pdf->Image($logoPath, $xPos, $pdf->GetY(), $logoWidth, 0, $logoType);
    $pdf->SetY($pdf->GetY() + 26);
} else {
    $pdf->Ln(5);
}

$pdf->SetFont('helvetica', 'B', 14);
$pdf->Cell(0, 10, 'REPORTE DE CLIENTES REGISTRADOS', 0, 1, 'C');
$pdf->Ln(4);

$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(50, 6, 'Fecha de generación:', 0, 0);
$pdf->Cell(0, 6, date('d/m/Y H:i:s'), 0, 1);
$pdf->Cell(50, 6, 'Total de clientes:', 0, 0);
$pdf->Cell(0, 6, $totalClientes, 0, 1);
$pdf->Cell(50, 6, 'Clientes activos:', 0, 0);
$pdf->Cell(0, 6, $activos, 0, 1);
$pdf->Cell(50, 6, 'Clientes inactivos:', 0, 0);
$pdf->Cell(0, 6, $inactivos, 0, 1);
$pdf->Cell(50, 6, 'Filtro aplicado:', 0, 0);
$pdf->Cell(0, 6, $searchApplied ? preg_replace('/\s+/', ' ', $searchTerm) : '—', 0, 1);
$pdf->Ln(6);

$pdf->SetFillColor(240, 173, 78);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('helvetica', 'B', 9);

// Anchos optimizados para A4 horizontal (277mm útiles)
$pdf->Cell(15, 8, 'Núm.', 1, 0, 'C', true);
$pdf->Cell(48, 8, 'Nombre', 1, 0, 'C', true);
$pdf->Cell(55, 8, 'Correo Electrónico', 1, 0, 'C', true);
$pdf->Cell(26, 8, 'Teléfono', 1, 0, 'C', true);
$pdf->Cell(32, 8, 'F. Nacimiento', 1, 0, 'C', true);
$pdf->Cell(30, 8, 'F. Registro', 1, 0, 'C', true);
$pdf->Cell(24, 8, 'Estado', 1, 0, 'C', true);
$pdf->Cell(47, 8, 'Observaciones', 1, 1, 'C', true);

$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('helvetica', '', 8);

if ($totalClientes === 0) {
    $pdf->SetFont('helvetica', 'I', 10);
    $mensaje = $searchApplied
        ? 'No se encontraron clientes que coincidan con el filtro aplicado.'
        : 'No se encontraron clientes registrados.';
    $pdf->MultiCell(0, 8, $mensaje, 0, 'C');
    $pdf->Output('reporte_clientes_registrados.pdf', 'I');
    $conexion->close();
    exit;
}

foreach ($clientes as $index => $cliente) {
    $numeroSecuencial = $index + 1;
    $fechaNacimiento = '—';
    if (!empty($cliente['fecha_nacimiento']) && $cliente['fecha_nacimiento'] !== '0000-00-00') {
        $tsNac = strtotime($cliente['fecha_nacimiento']);
        if ($tsNac !== false) {
            $fechaNacimiento = date('d/m/Y', $tsNac);
        }
    }

    $fechaRegistro = '—';
    if (!empty($cliente['fecha_registro']) && $cliente['fecha_registro'] !== '0000-00-00') {
        $tsReg = strtotime($cliente['fecha_registro']);
        if ($tsReg !== false) {
            $fechaRegistro = date('d/m/Y', $tsReg);
        }
    }

    $estadoNombre = $cliente['nombre_estado'] ?: ($cliente['id_estadoCliente'] === 1 ? 'ACTIVO' : 'INACTIVO');
    $observaciones = $cliente['observaciones'] ?: '—';

    $pdf->Cell(15, 7, $numeroSecuencial, 1, 0, 'C');
    $pdf->Cell(48, 7, mb_strimwidth($cliente['nombre_cliente'], 0, 26, '...'), 1, 0);
    $pdf->Cell(55, 7, mb_strimwidth($cliente['correo_electronico'], 0, 33, '...'), 1, 0);
    $pdf->Cell(26, 7, mb_strimwidth($cliente['telefono_cliente'] ?: 'N/A', 0, 14, '...'), 1, 0, 'C');
    $pdf->Cell(32, 7, $fechaNacimiento, 1, 0, 'C');
    $pdf->Cell(30, 7, $fechaRegistro, 1, 0, 'C');
    $pdf->Cell(24, 7, mb_strimwidth($estadoNombre, 0, 11, '...'), 1, 0, 'C');
    $pdf->Cell(47, 7, mb_strimwidth($observaciones, 0, 26, '...'), 1, 1);
}

$pdf->Ln(6);
$pdf->SetFont('helvetica', 'I', 9);
$pdf->MultiCell(0, 6, 'Nota: Este reporte muestra los clientes registrados junto con su estado actual y datos de contacto disponibles. Generado en tiempo real desde el sistema.', 0, 'L');

$pdf->Output('reporte_clientes_registrados.pdf', 'I');

$conexion->close();
?>
