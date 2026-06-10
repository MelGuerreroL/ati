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

if (!isset($_GET['id']) || empty($_GET['id'])) {
    die('ID de pago no especificado.');
}

$id_pago = intval($_GET['id']);

// Obtener información del pago
$query_pago = "
    SELECT p.*, 
           c.nombre_cliente, c.correo_electronico,
           s.precio as precio_suscripcion, s.fecha_inicio, s.fecha_fin,
           pl.nombre_plan
    FROM tbl_pago p
    LEFT JOIN tbl_suscripcion s ON p.id_suscripcion = s.id_suscripcion
    LEFT JOIN tbl_cliente c ON s.id_cliente = c.id_cliente
    LEFT JOIN tbl_plan pl ON s.id_plan = pl.id_plan
    WHERE p.id_pago = ?";
    
$stmt = $conexion->prepare($query_pago);
$stmt->bind_param('i', $id_pago);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die('Pago no encontrado.');
}

$pago = $result->fetch_assoc();
$stmt->close();

// Registrar en bitácora
registrar_bitacora($conexion, $_SESSION['usuario_id'], 10, 'PRINT', 
    "Usuario generó comprobante PDF del pago #{$id_pago} - Cliente: {$pago['nombre_cliente']}");

// ==== CLASE PERSONALIZADA ====
class PDFConEncabezadoPie extends TCPDF {
    public function Header() {
        $this->SetY(10);
        $this->SetFont('helvetica', '', 10);
        $this->Cell(0, 0, '', 'T', 1, 'C');
        $this->Ln(5);
    }

    public function Footer() {
        $this->SetY(-18);
        $this->SetFont('helvetica', 'I', 8);
        $this->Cell(0, 5, '___________________________________________________________', 0, 1, 'C');
        $this->Cell(0, 5, 'Este documento es un comprobante de pago generado automáticamente.', 0, 1, 'C');
        $this->Cell(0, 5, 'Fecha de generación: ' . date('d/m/Y H:i:s'), 0, 1, 'C');
        $this->Cell(0, 5, 'Página ' . $this->getAliasNumPage() . ' de ' . $this->getAliasNbPages(), 0, 0, 'C');
    }
}

// Crear PDF
$pdf = new PDFConEncabezadoPie('P', 'mm', 'LETTER', true, 'UTF-8', false);
$pdf->SetCreator('Sistema de Gestión');
$pdf->SetAuthor('GYM CLUB');
$pdf->SetTitle('Comprobante de Pago #' . $pago['id_pago']);
$pdf->SetSubject('Comprobante de Pago');
$pdf->SetMargins(15, 25, 15);
$pdf->SetAutoPageBreak(TRUE, 25);
$pdf->AddPage();

// ==== ENCABEZADO CON LOGO ====
$pdf->SetFont('helvetica', 'B', 16);
$pdf->Cell(0, 10, 'GYM CLUB', 0, 1, 'C');

// Buscar el logo en diferentes ubicaciones
$logo_path = '';
$possible_paths = [
    '../../public/logo.jpg',
    '../../assets/img/logo_empresa.png',
    '../../public/logo.png',
    '../../assets/img/logo.jpg'
];

foreach ($possible_paths as $path) {
    if (file_exists($path)) {
        $logo_path = $path;
        break;
    }
}

if (!empty($logo_path) && file_exists($logo_path)) {
    $pageWidth = 216;
    $logoWidth = 25;
    $xPosition = ($pageWidth - $logoWidth) / 2;
    $pdf->Image($logo_path, $xPosition, $pdf->GetY(), $logoWidth);
    $pdf->Ln(26);
} else {
    $pdf->Ln(10);
}

$pdf->SetFont('helvetica', 'B', 14);
$pdf->Cell(0, 10, 'COMPROBANTE DE PAGO', 0, 1, 'C');
$pdf->Ln(5);

// ==== CONTENIDO CENTRADO ====
// Información del comprobante
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(0, 7, 'No. Comprobante:', 0, 1, 'C');
$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(0, 7, 'PAGO-' . str_pad($pago['id_pago'], 6, '0', STR_PAD_LEFT), 0, 1, 'C');

$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(0, 7, 'Fecha de Emisión: ' . date('d/m/Y H:i:s'), 0, 1, 'C');
$pdf->Ln(8);

// INFORMACIÓN DEL CLIENTE - NEGRITA
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(0, 8, 'INFORMACIÓN DEL CLIENTE', 0, 1, 'C');
$pdf->Ln(4);

$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(0, 6, 'Nombre: ' . $pago['nombre_cliente'], 0, 1, 'C');
$pdf->Cell(0, 6, 'Correo: ' . $pago['correo_electronico'], 0, 1, 'C');
$pdf->Ln(8);

// DETALLES DE LA SUSCRIPCIÓN - NEGRITA
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(0, 8, 'DETALLES DE LA SUSCRIPCIÓN', 0, 1, 'C');
$pdf->Ln(4);

$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(0, 6, 'Plan: ' . $pago['nombre_plan'], 0, 1, 'C');
$pdf->Cell(0, 6, 'Fecha Inicio: ' . date('d/m/Y', strtotime($pago['fecha_inicio'])), 0, 1, 'C');
$pdf->Cell(0, 6, 'Fecha Fin: ' . date('d/m/Y', strtotime($pago['fecha_fin'])), 0, 1, 'C');
$pdf->Ln(8);

// DETALLES DEL PAGO - NEGRITA
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(0, 8, 'DETALLES DEL PAGO', 0, 1, 'C');
$pdf->Ln(4);

$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(0, 6, 'Monto Pagado:', 0, 1, 'C');
$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(0, 6, 'Lps ' . number_format($pago['monto'], 2), 0, 1, 'C');

$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(0, 6, 'Método de Pago: ' . ($pago['metodo_pago'] ?: 'No especificado'), 0, 1, 'C');

if (!empty($pago['referencia_pago'])) {
    $pdf->Cell(0, 6, 'Referencia: ' . $pago['referencia_pago'], 0, 1, 'C');
}

$pdf->Cell(0, 6, 'Fecha de Pago: ' . date('d/m/Y H:i', strtotime($pago['fecha_pago'])), 0, 1, 'C');
$pdf->Cell(0, 6, 'Fecha de Registro: ' . date('d/m/Y H:i', strtotime($pago['creado_en'])), 0, 1, 'C');

// ==== SALIDA ====
$pdf->Output('comprobante_pago_' . $pago['id_pago'] . '.pdf', 'I');
?>