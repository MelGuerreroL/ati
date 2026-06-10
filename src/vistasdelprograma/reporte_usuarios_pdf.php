<?php
session_start();
require 'con_db.php';
require 'bitacora_helpers.php';
require '../../vendor/tcpdf/TCPDF-main/tcpdf.php';
date_default_timezone_set('America/Tegucigalpa');
require_once __DIR__ . '/../config/errorlogs.php';
require_once __DIR__ . '/../config/roles.php';

use App\config\errorlogs;
errorlogs::activa_error_logs();

if (empty($_SESSION['usuario']) || empty($_SESSION['usuario_id'])) {
    die('Debes iniciar sesión para generar el reporte.');
}

$usuarioId = intval($_SESSION['usuario_id'] ?? 0);
$moduleKey = 'usuarios';
$puedeGenerar = user_has_permission($moduleKey, PERM_READ);
if ($usuarioId === SUPPORT_USER_ID) {
    $puedeGenerar = true;
}
if (!$puedeGenerar) {
    die('No tiene permisos para generar este reporte.');
}

$searchTerm = trim($_GET['search'] ?? '');
if ($searchTerm !== '') {
    $searchTerm = function_exists('mb_substr') ? mb_substr($searchTerm, 0, 100) : substr($searchTerm, 0, 100);
}
$searchApplied = ($searchTerm !== '');

ensure_fecha_creacion_column($conexion);

$bitacoraDescripcion = 'Generó el reporte PDF de usuarios registrados';
if ($searchApplied) {
    $bitacoraDescripcion .= ' (filtro: ' . $searchTerm . ')';
}
registrar_bitacora($conexion, $usuarioId, 1, 'REPORT', $bitacoraDescripcion);

$fechaCreacionDisponible = false;
$columna = $conexion->query("SHOW COLUMNS FROM tbl_ms_usuario LIKE 'fecha_creacion'");
if ($columna) {
    $fechaCreacionDisponible = $columna->num_rows > 0;
    $columna->free();
}

$campoFechaCreacion = $fechaCreacionDisponible ? 'u.fecha_creacion' : 'NULL AS fecha_creacion';
$sqlBase = "SELECT u.id_usuario, u.nombre_usuario, u.usuario, u.correo_electronico, u.estado_bloqueo, u.estado_usuario, u.fecha_vencimiento, $campoFechaCreacion, r.rol AS nombre_rol FROM tbl_ms_usuario u LEFT JOIN tbl_ms_roles r ON r.id_rol = u.tbl_ms_roles_id_rol";
$orderBy = ' ORDER BY u.id_usuario ASC';

if ($searchApplied) {
    $sqlUsuarios = $sqlBase . " WHERE u.nombre_usuario LIKE ? OR u.usuario LIKE ? OR u.correo_electronico LIKE ? OR r.rol LIKE ?" . $orderBy;
    $stmt = $conexion->prepare($sqlUsuarios);
    if (!$stmt) {
        die('Error al preparar la consulta de usuarios: ' . $conexion->error);
    }
    $likeParam = '%' . $searchTerm . '%';
    $stmt->bind_param('ssss', $likeParam, $likeParam, $likeParam, $likeParam);
    if (!$stmt->execute()) {
        $stmt->close();
        die('Error al ejecutar la consulta de usuarios.');
    }
    $resultUsuarios = $stmt->get_result();
    if (!$resultUsuarios) {
        $stmt->close();
        die('Error al obtener los usuarios filtrados.');
    }
} else {
    $sqlUsuarios = $sqlBase . $orderBy;
    $resultUsuarios = $conexion->query($sqlUsuarios);
    if (!$resultUsuarios) {
        die('Error al obtener los usuarios: ' . $conexion->error);
    }
}

$usuarios = [];
while ($row = $resultUsuarios->fetch_assoc()) {
    $usuarios[] = $row;
}
$resultUsuarios->free();
if (isset($stmt)) {
    $stmt->close();
}

$totalUsuarios = count($usuarios);
$usuariosActivos = 0;
$usuariosBloqueados = 0;
$resumenRoles = [];

foreach ($usuarios as $usuario) {
    $rolNombre = $usuario['nombre_rol'] ?: 'Sin rol asignado';
    $resumenRoles[$rolNombre] = ($resumenRoles[$rolNombre] ?? 0) + 1;

    $estadoBloqueo = strtoupper($usuario['estado_bloqueo'] ?? '');
    if ($estadoBloqueo === 'BLOQUEADO') {
        $usuariosBloqueados++;
    } else {
        $usuariosActivos++;
    }
}

class PDFUsuarios extends TCPDF {
    public function Footer() {
        $this->SetY(-18);
        $this->SetFont('helvetica', 'I', 8);
        $this->Cell(0, 6, 'Documento generado automáticamente por el sistema de Gestión de Usuarios.', 0, 1, 'C');
        $this->Cell(0, 6, 'Fecha de emisión: ' . date('d/m/Y H:i:s'), 0, 1, 'C');
        $this->Cell(0, 6, 'Página ' . $this->getAliasNumPage() . ' de ' . $this->getAliasNbPages(), 0, 0, 'C');
    }
}

$pdf = new PDFUsuarios('L', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetCreator('Sistema de Gestión');
$pdf->SetAuthor('Gym Club');
$pdf->SetTitle('Reporte de Usuarios Registrados');
$pdf->SetSubject('Usuarios registrados');
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
$pdf->Cell(0, 10, 'REPORTE DE USUARIOS REGISTRADOS', 0, 1, 'C');
$pdf->Ln(4);

$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(50, 6, 'Fecha de generación:', 0, 0);
$pdf->Cell(0, 6, date('d/m/Y H:i:s'), 0, 1);
$pdf->Cell(50, 6, 'Total de usuarios:', 0, 0);
$pdf->Cell(0, 6, $totalUsuarios, 0, 1);
$pdf->Cell(50, 6, 'Usuarios activos:', 0, 0);
$pdf->Cell(0, 6, $usuariosActivos, 0, 1);
$pdf->Cell(50, 6, 'Usuarios bloqueados:', 0, 0);
$pdf->Cell(0, 6, $usuariosBloqueados, 0, 1);
$pdf->Cell(50, 6, 'Filtro aplicado:', 0, 0);
$pdf->Cell(0, 6, $searchApplied ? preg_replace('/\s+/', ' ', $searchTerm) : '—', 0, 1);

if (!empty($resumenRoles)) {
    $pdf->Ln(4);
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell(0, 7, 'Distribución por rol', 0, 1);
    $pdf->SetFont('helvetica', '', 10);
    foreach ($resumenRoles as $rol => $cantidad) {
        $pdf->Cell(80, 6, $rol . ':', 0, 0);
        $pdf->Cell(0, 6, $cantidad . ' usuario(s)', 0, 1);
    }
}

$pdf->Ln(6);

$pdf->SetFillColor(240, 173, 78);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('helvetica', 'B', 9);

// Anchos optimizados para A4 horizontal (277mm útiles) - Mismo formato que clientes
$pdf->Cell(15, 8, 'Núm.', 1, 0, 'C', true);
$pdf->Cell(40, 8, 'Nombre', 1, 0, 'C', true);
$pdf->Cell(30, 8, 'Usuario', 1, 0, 'C', true);
$pdf->Cell(55, 8, 'Correo Electrónico', 1, 0, 'C', true);
$pdf->Cell(35, 8, 'Rol', 1, 0, 'C', true);
$pdf->Cell(25, 8, 'Estado', 1, 0, 'C', true);
$pdf->Cell(25, 8, 'Bloqueo', 1, 0, 'C', true);
$pdf->Cell(27, 8, 'F. Creación', 1, 0, 'C', true);
$pdf->Cell(25, 8, 'F. Vencimiento', 1, 1, 'C', true);

$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('helvetica', '', 8);

if ($totalUsuarios === 0) {
    $pdf->SetFont('helvetica', 'I', 10);
    $mensaje = $searchApplied
        ? 'No se encontraron usuarios que coincidan con el filtro aplicado.'
        : 'No se encontraron usuarios registrados.';
    $pdf->MultiCell(0, 8, $mensaje, 0, 'C');
    $pdf->Output('reporte_usuarios_registrados.pdf', 'I');
    $conexion->close();
    exit;
}

foreach ($usuarios as $indice => $usuario) {
    $numeroSecuencial = $indice + 1;
    $fechaCreacion = '—';
    if (!empty($usuario['fecha_creacion']) && $usuario['fecha_creacion'] !== '0000-00-00') {
        $timestampCreacion = strtotime($usuario['fecha_creacion']);
        if ($timestampCreacion !== false) {
            $fechaCreacion = date('d/m/Y', $timestampCreacion);
        }
    }

    $fechaVencimiento = '—';
    if (!empty($usuario['fecha_vencimiento']) && $usuario['fecha_vencimiento'] !== '0000-00-00') {
        $timestampVenc = strtotime($usuario['fecha_vencimiento']);
        if ($timestampVenc !== false) {
            $fechaVencimiento = date('d/m/Y', $timestampVenc);
        }
    }

    $estadoUsuario = $usuario['estado_usuario'] ?: 'N/D';
    $estadoBloqueo = $usuario['estado_bloqueo'] ?: 'N/D';
    $rolNombre = $usuario['nombre_rol'] ?: 'Sin rol';

    $pdf->Cell(15, 7, $numeroSecuencial, 1, 0, 'C');
    $pdf->Cell(40, 7, mb_strimwidth($usuario['nombre_usuario'], 0, 22, '...'), 1, 0);
    $pdf->Cell(30, 7, mb_strimwidth($usuario['usuario'], 0, 16, '...'), 1, 0);
    $pdf->Cell(55, 7, mb_strimwidth($usuario['correo_electronico'], 0, 33, '...'), 1, 0);
    $pdf->Cell(35, 7, mb_strimwidth($rolNombre, 0, 20, '...'), 1, 0);
    $pdf->Cell(25, 7, mb_strimwidth($estadoUsuario, 0, 12, '...'), 1, 0, 'C');
    $pdf->Cell(25, 7, mb_strimwidth($estadoBloqueo, 0, 12, '...'), 1, 0, 'C');
    $pdf->Cell(27, 7, $fechaCreacion, 1, 0, 'C');
    $pdf->Cell(25, 7, $fechaVencimiento, 1, 1, 'C');
}

$pdf->Ln(6);
$pdf->SetFont('helvetica', 'I', 9);
$pdf->MultiCell(0, 6, 'Nota: Este reporte incluye a todos los usuarios registrados en el sistema, independientemente de su estado o rol. Los datos se generan en tiempo real.', 0, 'L');

$pdf->Output('reporte_usuarios_registrados.pdf', 'I');

$conexion->close();
?>