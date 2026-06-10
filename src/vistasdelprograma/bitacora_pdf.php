<?php
session_start();
ob_start();

// Incluir archivos necesarios
require_once __DIR__ . '/con_db.php';
require_once __DIR__ . '/bitacora_helpers.php';
require_once __DIR__ . '/../../vendor/tcpdf/TCPDF-main/tcpdf.php';

date_default_timezone_set('America/Tegucigalpa');

// Verificar sesión
if (empty($_SESSION['usuario']) || empty($_SESSION['usuario_id'])) {
    die("No autorizado");
}

// Registrar bitácora
registrar_bitacora($conexion, $_SESSION['usuario_id'], 2, "PDF", "Descargó el PDF de la Bitácora");

// Filtros
$f1 = $_GET['f1'] ?? "";
$f2 = $_GET['f2'] ?? "";
$q  = $_GET['q'] ?? "";

$where = "1=1";

if ($f1 != "") $where .= " AND DATE(b.fecha) >= '$f1'";
if ($f2 != "") $where .= " AND DATE(b.fecha) <= '$f2'";

// Si el usuario busca un número exacto (solo dígitos)
    if (ctype_digit($q)) {

        // Búsqueda exacta SOLO en campos numéricos
        $where .= " AND (
            id_bitacora = $q OR
            id_objetos = $q OR
            id_usuario = $q
        )";

    } else {

        // Si el usuario escribe texto, se usa LIKE normal
        $q2 = "%$q%";
        $where .= " AND (
            fecha LIKE '$q2' OR
            accion LIKE '$q2' OR
            descripcion LIKE '$q2'
        )";
    }

$result = $conexion->query("
    SELECT 
        b.id_bitacora,
        b.fecha,
        b.id_objetos,
        b.accion,
        b.id_usuario,
        b.descripcion
    FROM tbl_ms_bitacora b
    WHERE $where
    ORDER BY b.fecha DESC
");

// =============================
//   CLASE PERSONALIZADA
// =============================
class PDFConPaginacion extends TCPDF {

    public function Header() {
        if ($this->PageNo() == 1) {
            // Logo
            $logo_path = __DIR__ . "/../../assets/img/logo.jpg";
            if (file_exists($logo_path)) {
                $this->Image($logo_path, 11, 8, 17);
            }

            // Título
            $this->SetFont('helvetica', 'B', 16);
            $this->SetY(8.5);
            $this->Cell(0, 9, 'GYM CLUB', 0, 1, 'C');
            
            // Espacio adicional después del título
            $this->Ln(3);

            // Fecha
            $this->SetFont('helvetica', '', 8);
            $this->Cell(0, 8, 'Generado el ' . date('d/m/Y H:i:s'), 0, 1, 'C');

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
$pdf->SetTitle('Reporte de Bitácora');
$pdf->SetMargins(10, 15, 10);
$pdf->SetAutoPageBreak(TRUE, 20);

$pdf->AddPage();

$pdf->SetFont('helvetica', 'B', 14);
$pdf->Cell(0, 10, 'REPORTE DE BITÁCORA DEL SISTEMA', 0, 1, 'C');
$pdf->Ln(5);

// Fecha y hora
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(40, 6, 'Fecha de generación:', 0, 0);
$pdf->Cell(0, 6, date('d/m/Y H:i:s'), 0, 1);
$pdf->Ln(8);

// Encabezado de tabla
$pdf->SetFillColor(240, 173, 78);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('helvetica', 'B', 9);

$pdf->Cell(23, 8, 'Núm. Bitácora', 1, 0, 'C', true);
$pdf->Cell(35, 8, 'Fecha', 1, 0, 'C', true);
$pdf->Cell(22, 8, 'Objeto', 1, 0, 'C', true);
$pdf->Cell(30, 8, 'Acción', 1, 0, 'C', true);
$pdf->Cell(23, 8, 'Núm. Usuario', 1, 0, 'C', true);
$pdf->Cell(142, 8, 'Descripción', 1, 1, 'C', true);

// Contenido
$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('helvetica', '', 8);

while ($row = $result->fetch_assoc()) {
    $pdf->Cell(23, 7, $row['id_bitacora'], 1);
    $pdf->Cell(35, 7, date('d/m/Y H:i:s', strtotime($row['fecha'])), 1);
    $pdf->Cell(22, 7, $row['id_objetos'], 1);
    $pdf->Cell(30, 7, $row['accion'], 1);
    $pdf->Cell(23, 7, $row['id_usuario'], 1);
    $pdf->MultiCell(142, 7, $row['descripcion'], 1, 'L', 0, 1);
}

ob_end_clean();
$pdf->Output('bitacora_' . date('Ymd_His') . '.pdf', 'I');
exit;
?>


