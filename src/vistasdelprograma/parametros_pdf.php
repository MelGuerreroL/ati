<?php
session_start();
ob_start();

require_once __DIR__ . '/con_db.php';
require_once __DIR__ . '/bitacora_helpers.php';
require_once __DIR__ . '/../../vendor/tcpdf/TCPDF-main/tcpdf.php';

date_default_timezone_set('America/Tegucigalpa');

// Verificar sesión
if (empty($_SESSION['usuario']) || empty($_SESSION['usuario_id'])) {
    die('No tiene permisos para acceder a este recurso.');
}

// Registrar en bitácora
registrar_bitacora($conexion, $_SESSION['usuario_id'], 5, "PDF", "Descargó el PDF de Parámetros");

// Filtro
$q = $_GET['q'] ?? "";
$where = "1=1";

if ($q != "") {
    $q = "%".$conexion->real_escape_string($q)."%";
    $where .= " AND (p.parametro LIKE '$q' OR p.valor LIKE '$q')";
}

$res = $conexion->query("
    SELECT p.id_parametro, p.parametro, p.valor, p.id_usuario, p.fecha_creado, p.fecha_modificado
    FROM tbl_ms_parametros p
    WHERE $where
    ORDER BY p.fecha_modificado DESC
");

// =============================
//   CLASE CON PAGINACIÓN
// =============================
class PDFConPaginacion extends TCPDF {

    public function Header() {
        if ($this->PageNo() == 1) {
            $logo_path = __DIR__ . "/../../assets/img/logo.jpg";
            if (file_exists($logo_path)) {
                $this->Image($logo_path, 11, 8, 17);
            }

            $this->SetFont('helvetica','B',16);
            $this->SetY(8.5);
            $this->Cell(0,9,'GYM CLUB',0,1,'C');

            $this->Ln(3);

            $this->SetFont('helvetica','', 8);
            $this->Cell(0,8,'Generado el ' . date('d/m/Y H:i:s'), 0, 1, 'C');

            $this->Ln(18);
        }
    }

    public function Footer() {
        $this->SetY(-18);
        $this->SetFont('helvetica', 'I', 8);
        $this->Cell(0, 6, 'Documento generado automáticamente.', 0, 1, 'C');
        $this->Cell(0, 6, 'Fecha de generación: ' . date('d/m/Y H:i:s'), 0, 1, 'C');
        $this->Cell(0, 6, 'Página ' . $this->getAliasNumPage() . ' de ' . $this->getAliasNbPages(), 0, 0, 'C');
    }
}

// =============================
//      CREAR PDF
// =============================
$pdf = new PDFConPaginacion('L', 'mm', 'A4', true, 'UTF-8', false);

$pdf->SetCreator('Sistema');
$pdf->SetAuthor('Gym Club');
$pdf->SetTitle('Parámetros del Sistema');
$pdf->SetMargins(10, 15, 10);
$pdf->SetAutoPageBreak(TRUE, 20);

$pdf->AddPage();

$pdf->SetFont('helvetica', 'B', 14);
$pdf->Cell(0, 10, 'REPORTE DE PARÁMETROS DEL SISTEMA', 0, 1, 'C');
$pdf->Ln(5);

$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(40, 6, 'Fecha de generación:', 0, 0);
$pdf->Cell(0, 6, date('d/m/Y H:i:s'), 0, 1);
$pdf->Ln(8);

// ============================
//  CONFIGURACIÓN TABLA
// ============================

// Anchos adaptados al formato A4 horizontal (277mm útiles)
$w_id = 45;
$w_parametro = 85;
$w_valor     = 40;
$w_usuario   = 35;
$w_creado    = 37;
$w_modificado = 35;

// Encabezados
$pdf->SetFillColor(240, 173, 78);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('helvetica', 'B', 9);

$pdf->Cell($w_id, 8, 'Núm. Parámetro', 1, 0, 'C', true);
$pdf->Cell($w_parametro, 8, 'Parámetro', 1, 0, 'C', true);
$pdf->Cell($w_valor, 8, 'Valor', 1, 0, 'C', true);
$pdf->Cell($w_usuario, 8, 'Núm. Usuario', 1, 0, 'C', true);
$pdf->Cell($w_creado, 8, 'Creado', 1, 0, 'C', true);
$pdf->Cell($w_modificado, 8, 'Modificado', 1, 1, 'C', true);

// Contenido
$pdf->SetFont('helvetica', '', 9);
$pdf->SetTextColor(0, 0, 0);

while ($row = $res->fetch_assoc()) {

    $parametro = $row['parametro'];
    $valor = $row['valor'];
    $usuario = $row['id_usuario'];

    // Calcular alturas
    $h1 = $pdf->getStringHeight($w_parametro, $parametro);
    $h2 = $pdf->getStringHeight($w_valor, $valor);
    $h3 = $pdf->getStringHeight($w_usuario, $usuario);

    $h_final = max($h1, $h2, $h3, 7);

    $x = $pdf->GetX();
    $y = $pdf->GetY();

    // ID
    $pdf->MultiCell($w_id, $h_final, $row['id_parametro'], 1, 'C', 0, 0);

    // Parámetro
    $pdf->MultiCell($w_parametro, $h_final, $parametro, 1, 'C', 0, 0);

    // Valor
    $pdf->MultiCell($w_valor, $h_final, $valor, 1, 'C', 0, 0);

    // Número Usuario
    $pdf->MultiCell($w_usuario, $h_final, $usuario, 1, 'C', 0, 0);

    // Creado
    $pdf->SetXY($x + $w_id + $w_parametro + $w_valor + $w_usuario, $y);
    $pdf->MultiCell($w_creado, $h_final, $row['fecha_creado'], 1, 'C', 0, 0);

    // Modificado
    $pdf->MultiCell($w_modificado, $h_final, $row['fecha_modificado'], 1, 'C', 0, 1);
}


ob_end_clean();
$pdf->Output('parametros_' . date('Ymd_His') . '.pdf', 'I');
exit;
?>
