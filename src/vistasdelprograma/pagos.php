<?php
session_start();
require 'con_db.php';
require 'bitacora_helpers.php';
date_default_timezone_set('America/Tegucigalpa');
require_once __DIR__ . '/../config/errorlogs.php';
require_once __DIR__ . '/../config/roles.php';

// Incluir PHPMailer manualmente
require '../../vendor/phpmailer/phpmailer/src/Exception.php';
require '../../vendor/phpmailer/phpmailer/src/PHPMailer.php';
require '../../vendor/phpmailer/phpmailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use App\config\errorlogs;
errorlogs::activa_error_logs();

// Función para enviar correo con comprobante
function enviarComprobantePago($conexion, $id_pago, $correo_cliente, $nombre_cliente) {
    try {
        // Configurar PHPMailer
        $mail = new PHPMailer(true);
        
        // Configuración del servidor SMTP
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'castilloemer2002@gmail.com';
        $mail->Password = 'qxhd jyuy dcbl wicm';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        $mail->Timeout = 30;
        
        // Configuración del correo
        $mail->setFrom('castilloemer2002@gmail.com', 'GYM CLUB');
        $mail->addAddress($correo_cliente, $nombre_cliente);
        $mail->addReplyTo('castilloemer2002@gmail.com', 'GYM CLUB');
        
        // Contenido del correo
        $mail->isHTML(true);
        $mail->Subject = 'GRACIAS POR REALIZAR TU PAGO - GYM CLUB';
        
        $mail->Body = '
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; color: #333; line-height: 1.6; }
                .header { background: linear-gradient(135deg, #f0ad4e, #ec971f); color: white; padding: 25px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { padding: 25px; background: #f9f9f9; }
                .details { background: white; padding: 20px; border-radius: 8px; margin: 15px 0; border-left: 4px solid #f0ad4e; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
                .footer { background: #333; color: white; padding: 20px; text-align: center; border-radius: 0 0 10px 10px; }
                .thank-you { font-size: 28px; color: #f0ad4e; font-weight: bold; margin: 25px 0; text-align: center; }
                .gym-logo { font-size: 32px; font-weight: bold; margin-bottom: 10px; }
                .highlight { background: #fff3cd; padding: 10px; border-radius: 5px; border: 1px solid #ffeaa7; }
            </style>
        </head>
        <body>
            <div class="header">
                <div class="gym-logo">💪 GYM CLUB</div>
                <h2>¡Confirmación de Pago Recibido!</h2>
            </div>
            
            <div class="content">
                <div class="thank-you">¡GRACIAS POR REALIZAR TU PAGO!</div>
                
                <div class="details">
                    <h3>Estimado(a) ' . htmlspecialchars($nombre_cliente) . ',</h3>
                    <p>Nos complace informarte que hemos recibido tu pago exitosamente. Tu membresía en <strong>GYM CLUB</strong> ha sido activada/renovada y ya puedes disfrutar de todos nuestros servicios.</p>
                    
                    <div class="highlight">
                        <p><strong>📋 Detalles de tu pago:</strong></p>
                        <ul>
                            <li><strong>Número de comprobante:</strong> PAGO-' . str_pad($id_pago, 6, '0', STR_PAD_LEFT) . '</li>
                            <li><strong>Fecha de pago:</strong> ' . date('d/m/Y H:i:s') . '</li>
                            <li><strong>Estado:</strong> ✅ COMPLETADO EXITOSAMENTE</li>
                        </ul>
                    </div>
                    
                    <p>Hemos adjuntado tu comprobante de pago en formato PDF para que lo conserves en tus registros.</p>
                </div>
                
                <div class="details">
                    <h4>🏋️‍♂️ ¡Tu Acceso Está Listo!</h4>
                    <p>Puedes acudir a nuestras instalaciones y presentar este comprobante para acceder a todos los servicios de <strong>GYM CLUB</strong>.</p>
                </div>
                
                <div class="details">
                    <h4>📞 ¿Necesitas Ayuda?</h4>
                    <p>Estamos aquí para apoyarte en todo momento:</p>
                    <p>📧 Email: gymclub882@gmail.com<br>
                       📞 Teléfono: +504 9464-7616<br>
                       🏢 Dirección: La bodega, Calle Principal, Km 14,5 CA-5 Francisco Morazán <br>
                       🕒 Horario: Lunes a Viernes 6:00 AM - 9:00 PM, 
                                    Sábados 8:00 AM - 8:00 PM y 
                                    Domingos 9:00 AM - 3:00 PM</p>
                </div>
                
                <div class="details">
                    <h4>💪 ¡Sigue Alcanzando Tus Metas!</h4>
                    <p>En <strong>GYM CLUB</strong> estamos comprometidos con tu bienestar y desarrollo físico. 
                    ¡Sigue esforzándote para alcanzar tus objetivos deportivos!</p>
                    <p><em>"El esfuerzo de hoy es el éxito de mañana"</em></p>
                </div>
            </div>
            
            <div class="footer">
                <p>&copy; ' . date('Y') . ' <strong>GYM CLUB</strong>. Todos los derechos reservados.</p>
                <p>Este es un correo automático, por favor no respondas a este mensaje.</p>
            </div>
        </body>
        </html>';
        
        $mail->AltBody = "GRACIAS POR REALIZAR TU PAGO - GYM CLUB\n\n" .
                        "Estimado(a) " . $nombre_cliente . ",\n\n" .
                        "Nos complace informarte que hemos recibido tu pago exitosamente. Tu membresía en GYM CLUB ha sido activada/renovada.\n\n" .
                        "DETALLES DEL PAGO:\n" .
                        "• Número de comprobante: PAGO-" . str_pad($id_pago, 6, '0', STR_PAD_LEFT) . "\n" .
                        "• Fecha de pago: " . date('d/m/Y H:i:s') . "\n" .
                        "• Estado: COMPLETADO EXITOSAMENTE\n\n" .
                        "Hemos adjuntado tu comprobante de pago en formato PDF.\n\n" .
                        "¡Puedes acudir a nuestras instalaciones y presentar este comprobante para acceder a todos los servicios!\n\n" .
                        "Para cualquier consulta:\n" .
                        "Email: gymclub882@gmail.com\n" .
                        "Teléfono: +504 9464-7616\n\n" .
                        "¡Gracias por confiar en GYM CLUB!\n\n" .
                        "---\n" .
                        "Este es un correo automático, por favor no respondas a este mensaje.";
        
        // Generar PDF temporal
        $temp_pdf_path = generarPDFTemporal($conexion, $id_pago);
        if ($temp_pdf_path) {
            $mail->addAttachment($temp_pdf_path, 'Comprobante_Pago_GYM_CLUB_' . $id_pago . '.pdf');
        }
        
        // Enviar correo
        $mail->send();
        
        // Limpiar archivo temporal
        if ($temp_pdf_path && file_exists($temp_pdf_path)) {
            unlink($temp_pdf_path);
        }
        
        return true;
        
    } catch (Exception $e) {
        error_log("Error enviando correo de pago #{$id_pago}: " . $e->getMessage());
        
        if (isset($temp_pdf_path) && file_exists($temp_pdf_path)) {
            unlink($temp_pdf_path);
        }
        
        return false;
    }
}

// Función para generar PDF temporal
function generarPDFTemporal($conexion, $id_pago) {
    try {
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
            return false;
        }
        
        $pago = $result->fetch_assoc();
        $stmt->close();
        
        // Generar PDF
        require_once '../../vendor/tcpdf/TCPDF-main/tcpdf.php';
        
        class PDFComprobante extends TCPDF {
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
        
        $pdf = new PDFComprobante('P', 'mm', 'LETTER', true, 'UTF-8', false);
        $pdf->SetCreator('Sistema de Gestión');
        $pdf->SetAuthor('GYM CLUB');
        $pdf->SetTitle('Comprobante de Pago #' . $pago['id_pago']);
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
        
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 7, 'No. Comprobante:', 0, 1, 'C');
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(0, 7, 'PAGO-' . str_pad($pago['id_pago'], 6, '0', STR_PAD_LEFT), 0, 1, 'C');

        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 7, 'Fecha de Emisión: ' . date('d/m/Y H:i:s'), 0, 1, 'C');
        $pdf->Ln(8);

        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 8, 'INFORMACIÓN DEL CLIENTE', 0, 1, 'C');
        $pdf->Ln(4);

        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 6, 'Nombre: ' . $pago['nombre_cliente'], 0, 1, 'C');
        $pdf->Cell(0, 6, 'Correo: ' . $pago['correo_electronico'], 0, 1, 'C');
        $pdf->Ln(8);

        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 8, 'DETALLES DE LA SUSCRIPCIÓN', 0, 1, 'C');
        $pdf->Ln(4);

        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 6, 'Plan: ' . $pago['nombre_plan'], 0, 1, 'C');
        $pdf->Cell(0, 6, 'Fecha Inicio: ' . date('d/m/Y', strtotime($pago['fecha_inicio'])), 0, 1, 'C');
        $pdf->Cell(0, 6, 'Fecha Fin: ' . date('d/m/Y', strtotime($pago['fecha_fin'])), 0, 1, 'C');
        $pdf->Ln(8);

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
        
        $temp_pdf_path = sys_get_temp_dir() . '/comprobante_pago_' . $id_pago . '_' . time() . '.pdf';
        $pdf->Output($temp_pdf_path, 'F');
        
        return $temp_pdf_path;
        
    } catch (Exception $e) {
        error_log("Error generando PDF temporal para pago #{$id_pago}: " . $e->getMessage());
        return false;
    }
}

// Verificar sesión
if (empty($_SESSION['usuario']) || empty($_SESSION['usuario_id'])) {
    header('Location: ../../public/index.php?error=Debes iniciar sesión');
    exit;
}

require_permission('pagos', PERM_READ);

$mensaje = '';
$modo_edicion = false;
$pago_a_editar = null;
$suscripcion_activa_info = null;

// --- ACCIÓN: CARGAR DATOS PARA EDICIÓN ---
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['accion']) && $_GET['accion'] === 'editar' && isset($_GET['id'])) {
    $id_pago_edicion = intval($_GET['id']);
    $stmt = $conexion->prepare("SELECT * FROM tbl_pago WHERE id_pago = ?");
    $stmt->bind_param('i', $id_pago_edicion);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $modo_edicion = true;
        $pago_a_editar = $result->fetch_assoc();

        $stmt_suscripcion = $conexion->prepare("SELECT s.id_estadoSuscripcion, s.fecha_fin, p.nombre_plan 
                                               FROM tbl_suscripcion s 
                                               LEFT JOIN tbl_plan p ON s.id_plan = p.id_plan 
                                               WHERE s.id_suscripcion = ?");
        $stmt_suscripcion->bind_param('i', $pago_a_editar['id_suscripcion']);
        $stmt_suscripcion->execute();
        $result_suscripcion = $stmt_suscripcion->get_result();
        if ($result_suscripcion->num_rows > 0) {
            $suscripcion_activa_info = $result_suscripcion->fetch_assoc();
        }
        $stmt_suscripcion->close();

    } else {
        $mensaje = '<div class="mensaje error">Error: Pago no encontrado.</div>';
    }
    $stmt->close();
}

// Función para calcular fecha de vencimiento
function calcularFechaFin($tipo_plan, $fecha_inicio) {
    $fecha_fin = new DateTime($fecha_inicio);
    
    switch($tipo_plan) {
        case 'Diario':
            $fecha_fin->modify('+1 day');
            break;
        case 'Semanal':
            $fecha_fin->modify('+1 week');
            break;
        case 'Quincenal':
            $fecha_fin->modify('+15 days');
            break;
        case 'Mensual':
            $fecha_fin->modify('+1 month');
            break;
        case 'Semestral':
            $fecha_fin->modify('+6 months');
            break;    
        case 'Anual':
            $fecha_fin->modify('+1 year');
            break;
        default:
            $fecha_fin->modify('+1 month');
    }
    
    return $fecha_fin->format('Y-m-d');
}

// Función para generar referencia única
function generarReferenciaUnica($conexion, $id_suscripcion) {
    $base_referencia = $id_suscripcion . '000';
    
    $stmt = $conexion->prepare("SELECT COUNT(*) as count FROM tbl_pago WHERE referencia_pago = ?");
    $stmt->bind_param('s', $base_referencia);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    if ($row['count'] == 0) {
        return $base_referencia;
    }
    
    $contador = 1;
    $max_intentos = 999;
    
    while ($contador <= $max_intentos) {
        $nueva_referencia = $id_suscripcion . str_pad($contador, 3, '0', STR_PAD_LEFT);
        
        $stmt = $conexion->prepare("SELECT COUNT(*) as count FROM tbl_pago WHERE referencia_pago = ?");
        $stmt->bind_param('s', $nueva_referencia);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        
        if ($row['count'] == 0) {
            return $nueva_referencia;
        }
        
        $contador++;
    }
    
    return $id_suscripcion . rand(100, 999);
}

// Manejar acciones POST (AJAX y formularios)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['accion'])) {
        $accion = $_POST['accion'];

        if ($accion === 'registrar_pago' && !user_has_permission('pagos', PERM_CREATE)) {
            $mensaje = '<div class="mensaje error">No tiene permisos para registrar pagos.</div>';
            $accion = null;
        }

        if ($accion === 'actualizar_pago' && !user_has_permission('pagos', PERM_UPDATE)) {
            $mensaje = '<div class="mensaje error">No tiene permisos para actualizar pagos.</div>';
            $accion = null;
        }

        if ($accion === 'eliminar_pago' && !user_has_permission('pagos', PERM_DELETE)) {
            $mensaje = '<div class="mensaje error">No tiene permisos para eliminar pagos.</div>';
            $accion = null;
        }

        // --- ACCIÓN: REGISTRAR NUEVO PAGO ---
        if ($accion === 'registrar_pago') {
            $id_suscripcion = intval($_POST['id_suscripcion'] ?? 0);
            $monto = floatval($_POST['monto'] ?? 0);
            $metodo_pago = trim($_POST['metodo_pago'] ?? '');
            $referencia_pago = trim($_POST['referencia_pago'] ?? null);

            if (empty($referencia_pago)) {
                $referencia_pago = generarReferenciaUnica($conexion, $id_suscripcion);
            }

            if (empty($metodo_pago)) {
                $mensaje = '<div class="mensaje error">El método de pago es obligatorio.</div>';
            } elseif (empty($id_suscripcion) || empty($monto) || $monto <= 0) {
                $mensaje = '<div class="mensaje error">La suscripción y el monto son obligatorios.</div>';
            } else {
                $stmt_check_suscripcion = $conexion->prepare("SELECT s.id_suscripcion, p.nombre_plan, s.id_estadoSuscripcion 
                                                             FROM tbl_suscripcion s 
                                                             LEFT JOIN tbl_plan p ON s.id_plan = p.id_plan 
                                                             WHERE s.id_suscripcion = ?");
                $stmt_check_suscripcion->bind_param('i', $id_suscripcion);
                $stmt_check_suscripcion->execute();
                $result_suscripcion = $stmt_check_suscripcion->get_result();

                if ($result_suscripcion->num_rows === 0) {
                    $mensaje = '<div class="mensaje error">Error: La suscripción seleccionada no existe.</div>';
                    $stmt_check_suscripcion->close();
                } else {
                    $suscripcion_info = $result_suscripcion->fetch_assoc();
                    $stmt_check_suscripcion->close();
                    
                    $stmt_check_suscripcion_pago = $conexion->prepare("SELECT p.id_pago, c.nombre_cliente, p.metodo_pago, p.fecha_pago 
                                                                     FROM tbl_pago p 
                                                                     INNER JOIN tbl_suscripcion s ON p.id_suscripcion = s.id_suscripcion 
                                                                     INNER JOIN tbl_cliente c ON s.id_cliente = c.id_cliente 
                                                                     WHERE p.id_suscripcion = ?");
                    $stmt_check_suscripcion_pago->bind_param('i', $id_suscripcion);
                    $stmt_check_suscripcion_pago->execute();
                    $result_suscripcion_pago = $stmt_check_suscripcion_pago->get_result();
                    
                    if ($result_suscripcion_pago->num_rows > 0) {
                        $pago_existente = $result_suscripcion_pago->fetch_assoc();
                        $mensaje = '<div class="mensaje error">Error: Ya existe un pago registrado para esta suscripción (Pago #' . $pago_existente['id_pago'] . ' - Cliente: ' . $pago_existente['nombre_cliente'] . ' - Método: ' . $pago_existente['metodo_pago'] . ' - Fecha: ' . date("d/m/Y", strtotime($pago_existente['fecha_pago'])) . ').</div>';
                        $stmt_check_suscripcion_pago->close();
                    } else {
                        $stmt_check_suscripcion_pago->close();
                        
                        if (!empty($referencia_pago)) {
                            $stmt_check_ref = $conexion->prepare("SELECT p.id_pago, c.nombre_cliente 
                                                                FROM tbl_pago p 
                                                                INNER JOIN tbl_suscripcion s ON p.id_suscripcion = s.id_suscripcion 
                                                                INNER JOIN tbl_cliente c ON s.id_cliente = c.id_cliente 
                                                                WHERE p.referencia_pago = ?");
                            $stmt_check_ref->bind_param('s', $referencia_pago);
                            $stmt_check_ref->execute();
                            $result_ref = $stmt_check_ref->get_result();
                            
                            if ($result_ref->num_rows > 0) {
                                $pago_existente = $result_ref->fetch_assoc();
                                $mensaje = '<div class="mensaje error">Error: La referencia de pago "' . htmlspecialchars($referencia_pago) . '" ya está registrada en el pago #' . $pago_existente['id_pago'] . '.</div>';
                                $stmt_check_ref->close();
                            } else {
                                $stmt_check_ref->close();
                                
                                $fecha_actual = date('Y-m-d H:i:s');
                                
                                $conexion->begin_transaction();
                                
                                try {
                                    $stmt = $conexion->prepare("INSERT INTO tbl_pago (id_suscripcion, monto, metodo_pago, referencia_pago, fecha_pago) VALUES (?, ?, ?, ?, ?)");
                                    $stmt->bind_param('idsss', $id_suscripcion, $monto, $metodo_pago, $referencia_pago, $fecha_actual);
                                    
                                    if (!$stmt->execute()) {
                                        throw new Exception('Error al registrar el pago: ' . $stmt->error);
                                    }

                                    $nuevo_id_pago = $conexion->insert_id;
                                    $stmt->close();
                                    
                                    $stmt_update_suscripcion = $conexion->prepare("UPDATE tbl_suscripcion SET id_estadoSuscripcion = 1 WHERE id_suscripcion = ?");
                                    $stmt_update_suscripcion->bind_param('i', $id_suscripcion);
                                    
                                    if (!$stmt_update_suscripcion->execute()) {
                                        throw new Exception('Error al actualizar el estado de la suscripción: ' . $stmt_update_suscripcion->error);
                                    }
                                    $stmt_update_suscripcion->close();
                                    
                                    $fecha_inicio = date('Y-m-d');
                                    $fecha_fin = calcularFechaFin($suscripcion_info['nombre_plan'], $fecha_inicio);
                                    
                                    $stmt_update_fechas = $conexion->prepare("UPDATE tbl_suscripcion SET fecha_inicio = ?, fecha_fin = ? WHERE id_suscripcion = ?");
                                    $stmt_update_fechas->bind_param('ssi', $fecha_inicio, $fecha_fin, $id_suscripcion);
                                    
                                    if (!$stmt_update_fechas->execute()) {
                                        throw new Exception('Error al actualizar las fechas de la suscripción: ' . $stmt_update_fechas->error);
                                    }
                                    $stmt_update_fechas->close();

                                    $stmt_activar_ciclo = $conexion->prepare("UPDATE tbl_calendario SET meta = '0' WHERE id_suscripcion = ?");
                                    $stmt_activar_ciclo->bind_param('i', $id_suscripcion);
                                    $stmt_activar_ciclo->execute();
                                    $stmt_activar_ciclo->close();
                                    
                                    $conexion->commit();

                                    // ENVIAR CORREO AL CLIENTE
                                    $stmt_cliente_info = $conexion->prepare("
                                        SELECT c.correo_electronico, c.nombre_cliente 
                                        FROM tbl_suscripcion s 
                                        JOIN tbl_cliente c ON s.id_cliente = c.id_cliente 
                                        WHERE s.id_suscripcion = ?
                                    ");
                                    $stmt_cliente_info->bind_param('i', $id_suscripcion);
                                    $stmt_cliente_info->execute();
                                    $result_cliente_info = $stmt_cliente_info->get_result();

                                    $mensaje_correo = "";
                                    if ($result_cliente_info->num_rows > 0) {
                                        $cliente_info = $result_cliente_info->fetch_assoc();
                                        $correo_cliente = $cliente_info['correo_electronico'];
                                        $nombre_cliente = $cliente_info['nombre_cliente'];
                                        
                                        $correo_enviado = enviarComprobantePago($conexion, $nuevo_id_pago, $correo_cliente, $nombre_cliente);
                                        
                                        if ($correo_enviado) {
                                            $mensaje_correo = " ✅ COMPROBANTE ENVIADO AL CLIENTE";
                                            registrar_bitacora($conexion, $_SESSION['usuario_id'], 10, 'EMAIL', 
                                                "Comprobante enviado por correo - Pago #{$nuevo_id_pago} - Cliente: {$nombre_cliente}");
                                        } else {
                                            $mensaje_correo = " ⚠️ Pago registrado pero no se pudo enviar el correo automático";
                                        }
                                    } else {
                                        $mensaje_correo = " ⚠️ No se encontró información del cliente para enviar correo";
                                    }
                                    $stmt_cliente_info->close();

                                    registrar_bitacora($conexion, $_SESSION['usuario_id'], 10, 'INSERT', 
                                        "Nuevo pago registrado #{$nuevo_id_pago} - Suscripción: {$id_suscripcion}, Monto: Lps " . number_format($monto, 2) . ", Método: {$metodo_pago}, Referencia: {$referencia_pago}");
                                    
                                    $mensaje = '<div class="mensaje exito">✅ PAGO REGISTRADO EXITOSAMENTE. Estado de suscripción actualizado a ACTIVA y fechas configuradas.' . $mensaje_correo . '</div>';
                                    
                                } catch (Exception $e) {
                                    $conexion->rollback();
                                    $mensaje = '<div class="mensaje error">' . $e->getMessage() . '</div>';
                                }
                            }
                        } else {
                            $fecha_actual = date('Y-m-d H:i:s');
                            
                            $conexion->begin_transaction();
                            
                            try {
                                $stmt = $conexion->prepare("INSERT INTO tbl_pago (id_suscripcion, monto, metodo_pago, referencia_pago, fecha_pago) VALUES (?, ?, ?, ?, ?)");
                                $stmt->bind_param('idsss', $id_suscripcion, $monto, $metodo_pago, $referencia_pago, $fecha_actual);
                                
                                if (!$stmt->execute()) {
                                    throw new Exception('Error al registrar el pago: ' . $stmt->error);
                                }

                                $nuevo_id_pago = $conexion->insert_id;
                                $stmt->close();
                                
                                $stmt_update_suscripcion = $conexion->prepare("UPDATE tbl_suscripcion SET id_estadoSuscripcion = 1 WHERE id_suscripcion = ?");
                                $stmt_update_suscripcion->bind_param('i', $id_suscripcion);
                                
                                if (!$stmt_update_suscripcion->execute()) {
                                    throw new Exception('Error al actualizar el estado de la suscripción: ' . $stmt_update_suscripcion->error);
                                }
                                $stmt_update_suscripcion->close();
                                
                                $fecha_inicio = date('Y-m-d');
                                $fecha_fin = calcularFechaFin($suscripcion_info['nombre_plan'], $fecha_inicio);
                                
                                $stmt_update_fechas = $conexion->prepare("UPDATE tbl_suscripcion SET fecha_inicio = ?, fecha_fin = ? WHERE id_suscripcion = ?");
                                $stmt_update_fechas->bind_param('ssi', $fecha_inicio, $fecha_fin, $id_suscripcion);
                                
                                if (!$stmt_update_fechas->execute()) {
                                    throw new Exception('Error al actualizar las fechas de la suscripción: ' . $stmt_update_fechas->error);
                                }
                                $stmt_update_fechas->close();
                                
                                $conexion->commit();

                                // ENVIAR CORREO AL CLIENTE
                                $stmt_cliente_info = $conexion->prepare("
                                    SELECT c.correo_electronico, c.nombre_cliente 
                                    FROM tbl_suscripcion s 
                                    JOIN tbl_cliente c ON s.id_cliente = c.id_cliente 
                                    WHERE s.id_suscripcion = ?
                                ");
                                $stmt_cliente_info->bind_param('i', $id_suscripcion);
                                $stmt_cliente_info->execute();
                                $result_cliente_info = $stmt_cliente_info->get_result();

                                $mensaje_correo = "";
                                if ($result_cliente_info->num_rows > 0) {
                                    $cliente_info = $result_cliente_info->fetch_assoc();
                                    $correo_cliente = $cliente_info['correo_electronico'];
                                    $nombre_cliente = $cliente_info['nombre_cliente'];
                                    
                                    $correo_enviado = enviarComprobantePago($conexion, $nuevo_id_pago, $correo_cliente, $nombre_cliente);
                                    
                                    if ($correo_enviado) {
                                        $mensaje_correo = " ✅ COMPROBANTE ENVIADO AL CLIENTE";
                                        registrar_bitacora($conexion, $_SESSION['usuario_id'], 10, 'EMAIL', 
                                            "Comprobante enviado por correo - Pago #{$nuevo_id_pago} - Cliente: {$nombre_cliente}");
                                    } else {
                                        $mensaje_correo = " ⚠️ Pago registrado pero no se pudo enviar el correo automático";
                                    }
                                } else {
                                    $mensaje_correo = " ⚠️ No se encontró información del cliente para enviar correo";
                                }
                                $stmt_cliente_info->close();

                                registrar_bitacora($conexion, $_SESSION['usuario_id'], 10, 'INSERT', 
                                    "Nuevo pago registrado #{$nuevo_id_pago} - Suscripción: {$id_suscripcion}, Monto: Lps " . number_format($monto, 2) . ", Método: {$metodo_pago}, Referencia: {$referencia_pago}");

                                
                                $mensaje = '<div class="mensaje exito">✅ PAGO REGISTRADO EXITOSAMENTE. Estado de suscripción actualizado a ACTIVA y fechas configuradas.' . $mensaje_correo . '</div>';
                                
                            } catch (Exception $e) {
                                $conexion->rollback();
                                $mensaje = '<div class="mensaje error">' . $e->getMessage() . '</div>';
                            }
                        }
                    }
                }
            }
        }
        
        // --- ACCIÓN: ACTUALIZAR PAGO ---
        if ($accion === 'actualizar_pago') {
            $id_pago = intval($_POST['id_pago'] ?? 0);
            $id_suscripcion = intval($_POST['id_suscripcion'] ?? 0);
            $monto = floatval($_POST['monto'] ?? 0);
            $metodo_pago = trim($_POST['metodo_pago'] ?? '');
            $referencia_pago = trim($_POST['referencia_pago'] ?? null);

            if (empty($metodo_pago)) {
                $mensaje = '<div class="mensaje error">El método de pago es obligatorio.</div>';
                $modo_edicion = true;
                $pago_a_editar = $_POST;
                $pago_a_editar['id_pago'] = $id_pago;
            } elseif ($id_pago > 0 && !empty($id_suscripcion) && !empty($monto) && $monto > 0) {
                $stmt_check_suscripcion = $conexion->prepare("SELECT s.id_suscripcion, s.id_estadoSuscripcion, p.nombre_plan 
                                                             FROM tbl_suscripcion s 
                                                             LEFT JOIN tbl_plan p ON s.id_plan = p.id_plan 
                                                             WHERE s.id_suscripcion = ?");
                $stmt_check_suscripcion->bind_param('i', $id_suscripcion);
                $stmt_check_suscripcion->execute();
                $result_suscripcion = $stmt_check_suscripcion->get_result();

                if ($result_suscripcion->num_rows === 0) {
                    $mensaje = '<div class="mensaje error">Error: La suscripción seleccionada no existe.</div>';
                    $stmt_check_suscripcion->close();
                } else {
                    $suscripcion_info = $result_suscripcion->fetch_assoc();
                    $stmt_check_suscripcion->close();
                    
                    $stmt_check_suscripcion_pago = $conexion->prepare("SELECT p.id_pago, c.nombre_cliente, p.metodo_pago, p.fecha_pago 
                                                                     FROM tbl_pago p 
                                                                     INNER JOIN tbl_suscripcion s ON p.id_suscripcion = s.id_suscripcion 
                                                                     INNER JOIN tbl_cliente c ON s.id_cliente = c.id_cliente 
                                                                     WHERE p.id_suscripcion = ? AND p.id_pago != ?");
                    $stmt_check_suscripcion_pago->bind_param('ii', $id_suscripcion, $id_pago);
                    $stmt_check_suscripcion_pago->execute();
                    $result_suscripcion_pago = $stmt_check_suscripcion_pago->get_result();
                    
                    if ($result_suscripcion_pago->num_rows > 0) {
                        $pago_existente = $result_suscripcion_pago->fetch_assoc();
                        $mensaje = '<div class="mensaje error">Error: Ya existe otro pago registrado para esta suscripción (Pago #' . $pago_existente['id_pago'] . ' - Cliente: ' . $pago_existente['nombre_cliente'] . ' - Método: ' . $pago_existente['metodo_pago'] . ' - Fecha: ' . date("d/m/Y", strtotime($pago_existente['fecha_pago'])) . ').</div>';
                        $modo_edicion = true;
                        $pago_a_editar = $_POST;
                        $pago_a_editar['id_pago'] = $id_pago;
                        $stmt_check_suscripcion_pago->close();
                    } else {
                        $stmt_check_suscripcion_pago->close();
                        
                        if (!empty($referencia_pago)) {
                            $stmt_check_ref = $conexion->prepare("SELECT p.id_pago, c.nombre_cliente 
                                                                FROM tbl_pago p 
                                                                INNER JOIN tbl_suscripcion s ON p.id_suscripcion = s.id_suscripcion 
                                                                INNER JOIN tbl_cliente c ON s.id_cliente = c.id_cliente 
                                                                WHERE p.referencia_pago = ? AND p.id_pago != ?");
                            $stmt_check_ref->bind_param('si', $referencia_pago, $id_pago);
                            $stmt_check_ref->execute();
                            $result_ref = $stmt_check_ref->get_result();
                            
                            if ($result_ref->num_rows > 0) {
                                $pago_existente = $result_ref->fetch_assoc();
                                $mensaje = '<div class="mensaje error">Error: La referencia de pago "' . htmlspecialchars($referencia_pago) . '" ya está en uso por el pago #' . $pago_existente['id_pago'] . '.</div>';
                                $modo_edicion = true;
                                $pago_a_editar = $_POST;
                                $pago_a_editar['id_pago'] = $id_pago;
                                $stmt_check_ref->close();
                            } else {
                                $stmt_check_ref->close();
                                
                                $conexion->begin_transaction();
                                
                                try {
                                    $stmt = $conexion->prepare("UPDATE tbl_pago SET id_suscripcion = ?, monto = ?, metodo_pago = ?, referencia_pago = ? WHERE id_pago = ?");
                                    $stmt->bind_param('idssi', $id_suscripcion, $monto, $metodo_pago, $referencia_pago, $id_pago);

                                    if (!$stmt->execute()) {
                                        throw new Exception('Error al actualizar el pago: ' . $stmt->error);
                                    }
                                    $stmt->close();
                                    
                                    if ($suscripcion_info['id_estadoSuscripcion'] == 2 || $suscripcion_info['id_estadoSuscripcion'] == 3) {
                                        $stmt_update_suscripcion = $conexion->prepare("UPDATE tbl_suscripcion SET id_estadoSuscripcion = 1 WHERE id_suscripcion = ?");
                                        $stmt_update_suscripcion->bind_param('i', $id_suscripcion);
                                        
                                        if (!$stmt_update_suscripcion->execute()) {
                                            throw new Exception('Error al actualizar el estado de la suscripción: ' . $stmt_update_suscripcion->error);
                                        }
                                        $stmt_update_suscripcion->close();
                                        
                                        $fecha_inicio = date('Y-m-d');
                                        $fecha_fin = calcularFechaFin($suscripcion_info['nombre_plan'], $fecha_inicio);
                                        
                                        $stmt_update_fechas = $conexion->prepare("UPDATE tbl_suscripcion SET fecha_inicio = ?, fecha_fin = ? WHERE id_suscripcion = ?");
                                        $stmt_update_fechas->bind_param('ssi', $fecha_inicio, $fecha_fin, $id_suscripcion);
                                        
                                        if (!$stmt_update_fechas->execute()) {
                                            throw new Exception('Error al actualizar las fechas de la suscripción: ' . $stmt_update_fechas->error);
                                        }
                                        $stmt_update_fechas->close();
                                        
                                        $mensaje_extra = " Estado de suscripción actualizado a ACTIVA y fechas configuradas.";
                                    } else {
                                        $mensaje_extra = "";
                                    }
                                    
                                    $conexion->commit();

                                    // ENVIAR CORREO TAMBIÉN EN ACTUALIZACIÓN
                                    $mensaje_correo = "";
                                    $stmt_cliente_info = $conexion->prepare("
                                        SELECT c.correo_electronico, c.nombre_cliente 
                                        FROM tbl_suscripcion s 
                                        JOIN tbl_cliente c ON s.id_cliente = c.id_cliente 
                                        WHERE s.id_suscripcion = ?
                                    ");
                                    $stmt_cliente_info->bind_param('i', $id_suscripcion);
                                    $stmt_cliente_info->execute();
                                    $result_cliente_info = $stmt_cliente_info->get_result();

                                    if ($result_cliente_info->num_rows > 0) {
                                        $cliente_info = $result_cliente_info->fetch_assoc();
                                        $correo_enviado = enviarComprobantePago($conexion, $id_pago, $cliente_info['correo_electronico'], $cliente_info['nombre_cliente']);
                                        
                                        if ($correo_enviado) {
                                            $mensaje_correo = " ✅ COMPROBANTE ACTUALIZADO ENVIADO AL CLIENTE";
                                        } else {
                                            $mensaje_correo = " ⚠️ Pago actualizado pero no se pudo enviar el correo automático";
                                        }
                                    }
                                    $stmt_cliente_info->close();

                                    registrar_bitacora($conexion, $_SESSION['usuario_id'], 10, 'UPDATE', 
                                        "Pago actualizado #{$id_pago} - Nueva suscripción: {$id_suscripcion}, Monto: Lps " . number_format($monto, 2) . ", Método: {$metodo_pago}, Referencia: {$referencia_pago}");
                                    
                                    $mensaje = '<div class="mensaje exito">✅ PAGO ACTUALIZADO CORRECTAMENTE.' . $mensaje_extra . $mensaje_correo . '</div>';
                                    
                                } catch (Exception $e) {
                                    $conexion->rollback();
                                    $mensaje = '<div class="mensaje error">' . $e->getMessage() . '</div>';
                                }
                            }
                        } else {
                            $conexion->begin_transaction();
                            
                            try {
                                $stmt = $conexion->prepare("UPDATE tbl_pago SET id_suscripcion = ?, monto = ?, metodo_pago = ?, referencia_pago = ? WHERE id_pago = ?");
                                $stmt->bind_param('idssi', $id_suscripcion, $monto, $metodo_pago, $referencia_pago, $id_pago);

                                if (!$stmt->execute()) {
                                    throw new Exception('Error al actualizar el pago: ' . $stmt->error);
                                }
                                $stmt->close();
                                
                                if ($suscripcion_info['id_estadoSuscripcion'] == 2 || $suscripcion_info['id_estadoSuscripcion'] == 3) {
                                    $stmt_update_suscripcion = $conexion->prepare("UPDATE tbl_suscripcion SET id_estadoSuscripcion = 1 WHERE id_suscripcion = ?");
                                    $stmt_update_suscripcion->bind_param('i', $id_suscripcion);
                                    
                                    if (!$stmt_update_suscripcion->execute()) {
                                        throw new Exception('Error al actualizar el estado de la suscripción: ' . $stmt_update_suscripcion->error);
                                    }
                                    $stmt_update_suscripcion->close();
                                    
                                    $fecha_inicio = date('Y-m-d');
                                    $fecha_fin = calcularFechaFin($suscripcion_info['nombre_plan'], $fecha_inicio);
                                    
                                    $stmt_update_fechas = $conexion->prepare("UPDATE tbl_suscripcion SET fecha_inicio = ?, fecha_fin = ? WHERE id_suscripcion = ?");
                                    $stmt_update_fechas->bind_param('ssi', $fecha_inicio, $fecha_fin, $id_suscripcion);
                                    
                                    if (!$stmt_update_fechas->execute()) {
                                        throw new Exception('Error al actualizar las fechas de la suscripción: ' . $stmt_update_fechas->error);
                                    }
                                    $stmt_update_fechas->close();
                                    
                                    $mensaje_extra = " Estado de suscripción actualizado a ACTIVA y fechas configuradas.";
                                } else {
                                    $mensaje_extra = "";
                                }
                                
                                $conexion->commit();

                                // ENVIAR CORREO TAMBIÉN EN ACTUALIZACIÓN
                                $mensaje_correo = "";
                                $stmt_cliente_info = $conexion->prepare("
                                    SELECT c.correo_electronico, c.nombre_cliente 
                                    FROM tbl_suscripcion s 
                                    JOIN tbl_cliente c ON s.id_cliente = c.id_cliente 
                                    WHERE s.id_suscripcion = ?
                                ");
                                $stmt_cliente_info->bind_param('i', $id_suscripcion);
                                $stmt_cliente_info->execute();
                                $result_cliente_info = $stmt_cliente_info->get_result();

                                if ($result_cliente_info->num_rows > 0) {
                                    $cliente_info = $result_cliente_info->fetch_assoc();
                                    $correo_enviado = enviarComprobantePago($conexion, $id_pago, $cliente_info['correo_electronico'], $cliente_info['nombre_cliente']);
                                    
                                    if ($correo_enviado) {
                                        $mensaje_correo = " ✅ COMPROBANTE ACTUALIZADO ENVIADO AL CLIENTE";
                                    } else {
                                        $mensaje_correo = " ⚠️ Pago actualizado pero no se pudo enviar el correo automático";
                                    }
                                }
                                $stmt_cliente_info->close();

                                registrar_bitacora($conexion, $_SESSION['usuario_id'], 10, 'UPDATE', 
                                    "Pago actualizado #{$id_pago} - Nueva suscripción: {$id_suscripcion}, Monto: Lps " . number_format($monto, 2) . ", Método: {$metodo_pago}, Referencia: {$referencia_pago}");

                                $mensaje = '<div class="mensaje exito">✅ PAGO ACTUALIZADO CORRECTAMENTE.' . $mensaje_extra . $mensaje_correo . '</div>';

                            } catch (Exception $e) {
                                $conexion->rollback();
                                $mensaje = '<div class="mensaje error">' . $e->getMessage() . '</div>';
                            }
                        }
                    }
                }
            } else {
                $mensaje = '<div class="mensaje error">Faltan datos para actualizar o el ID es inválido.</div>';
            }
        }

        // --- ACCIÓN: ELIMINAR PAGO (AJAX) ---
        if ($accion === 'eliminar_pago') {
            header('Content-Type: application/json');
            $pago_id = intval($_POST['id_pago'] ?? 0);
            if ($pago_id <= 0) {
                echo json_encode(['success' => false, 'error' => 'ID de pago inválido.']);
                exit;
            }

            $stmt_info = $conexion->prepare("SELECT id_suscripcion, monto, metodo_pago, referencia_pago FROM tbl_pago WHERE id_pago = ?");
            $stmt_info->bind_param('i', $pago_id);
            $stmt_info->execute();
            $result_info = $stmt_info->get_result();
            
            if ($result_info->num_rows > 0) {
                $pago_info = $result_info->fetch_assoc();
                $id_suscripcion = $pago_info['id_suscripcion'];
                $monto = $pago_info['monto'];
                $metodo_pago = $pago_info['metodo_pago'];
                $referencia_pago = $pago_info['referencia_pago'];
            }
            $stmt_info->close();

            $stmt_delete = $conexion->prepare("DELETE FROM tbl_pago WHERE id_pago = ?");
            $stmt_delete->bind_param('i', $pago_id);
            if ($stmt_delete->execute()) {
                registrar_bitacora($conexion, $_SESSION['usuario_id'], 10, 'DELETE', 
                    "Pago eliminado #{$pago_id} - Suscripción: {$id_suscripcion}, Monto: Lps " . number_format($monto, 2) . ", Método: {$metodo_pago}, Referencia: {$referencia_pago}");

                echo json_encode(['success' => true]);

            } else {
                echo json_encode(['success' => false, 'error' => 'Error al eliminar el pago.']);
            }
            $stmt_delete->close();
            exit;
        }
    }
}

// Obtener la lista de pagos para mostrar en la tabla
$query_pagos = "
    SELECT p.id_pago, p.id_suscripcion, p.monto, p.metodo_pago, p.referencia_pago, p.fecha_pago, p.creado_en,
           CONCAT('Suscripción #', s.id_suscripcion, ' - Cliente: ', c.nombre_cliente) as descripcion_suscripcion,
           c.nombre_cliente, s.precio as precio_suscripcion,
           es.nombre_estado as estado_suscripcion
    FROM tbl_pago p
    LEFT JOIN tbl_suscripcion s ON p.id_suscripcion = s.id_suscripcion
    LEFT JOIN tbl_cliente c ON s.id_cliente = c.id_cliente
    LEFT JOIN tbl_estado_suscripcion es ON s.id_estadoSuscripcion = es.id_estadoSuscripcion
    ORDER BY p.fecha_pago DESC";
$result_pagos = $conexion->query($query_pagos);

// Obtener suscripciones para el dropdown - SOLO mostrar suscripciones que NO tengan pagos registrados o estén CANCELADAS/VENCIDAS
$suscripciones = [];
$precios_suscripciones = [];
$estados_suscripciones = [];
$suscripciones_simplificadas = [];

$sql_suscripciones = "
    SELECT s.id_suscripcion, 
           CONCAT('Suscripción #', s.id_suscripcion, ' - Cliente: ', c.nombre_cliente) as descripcion,
           p.nombre_plan,
           s.precio,
           es.nombre_estado,
           s.id_estadoSuscripcion,
           c.nombre_cliente
    FROM tbl_suscripcion s
    LEFT JOIN tbl_cliente c ON s.id_cliente = c.id_cliente
    LEFT JOIN tbl_plan p ON s.id_plan = p.id_plan
    LEFT JOIN tbl_estado_suscripcion es ON s.id_estadoSuscripcion = es.id_estadoSuscripcion
    WHERE s.id_estadoSuscripcion IN (2, 3) -- SOLO VENCIDAS (2) y CANCELADAS (3)
       OR s.id_suscripcion NOT IN (SELECT DISTINCT id_suscripcion FROM tbl_pago) -- O que no tengan pagos registrados
    ORDER BY s.id_suscripcion DESC";
$result_suscripciones = $conexion->query($sql_suscripciones);
while ($row = $result_suscripciones->fetch_assoc()) {
    $suscripciones[$row['id_suscripcion']] = $row['descripcion'] . ' (' . $row['nombre_plan'] . ' - Lps ' . number_format($row['precio'], 2) . ' - Estado: ' . $row['nombre_estado'] . ')';
    $suscripciones_simplificadas[$row['id_suscripcion']] = $row['id_suscripcion'] . ' - ' . $row['nombre_cliente'] . ' - ' . $row['nombre_plan'] . ' - ' . $row['nombre_estado'];
    $precios_suscripciones[$row['id_suscripcion']] = $row['precio'];
    $estados_suscripciones[$row['id_suscripcion']] = $row['id_estadoSuscripcion'];
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Gestión de Pagos</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css"/>
<style>
    :root {
        --blue-primary: #1E88E5;   /* Azul elegante principal */
        --blue-dark: #1565C0;      /* Azul profundo para hover */
        --blue-light: #90CAF9;     /* Azul claro para acentos */
        --text-dark: #333;
        --text-light: #fff;
        --background-light: #f4f8fb;
        --border-color: #ccd6e0;
        --status-active: #1565C0;
        --status-inactive: #dc3545;
        --payment-cash: #1E88E5;
        --payment-card: #1565C0;
        --payment-transfer: #90CAF9;
    }
    body{font-family:'Segoe UI', sans-serif;background:var(--background-light);margin:0;padding:20px;color:var(--text-dark);}
    .page-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;}
    .page-header h1{color:var(--text-dark);font-size:2em;display:flex;align-items:center;gap:10px;}
    .container{background:var(--text-light);padding:25px;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,.08);margin-bottom:20px;border-top:4px solid var(--blue-primary);}
    .page-layout{display:flex;gap:20px;}
    .left-column{flex:1;min-width:320px;}
    .right-column{flex:2;}
    table{width:100%;border-collapse:collapse;margin-top:12px;}
    th,td{padding:12px 15px;border-bottom:1px solid var(--border-color);text-align:left;vertical-align:middle;}
    th{background:var(--blue-dark);color:var(--text-light);text-transform:uppercase;font-size:0.85em;letter-spacing:0.5px;}
    input, textarea, select, button{font-family:inherit;font-size:1rem;}
    input, textarea, select{padding:10px;border-radius:5px;border:1px solid #ccc;transition:all .3s ease;box-sizing:border-box;width:100%;}
    input:focus, textarea:focus, select:focus{border-color:var(--blue-primary);box-shadow:0 0 0 3px rgba(30,136,229,.25);}
    .button{background:var(--blue-primary);color:var(--text-light);border:none;cursor:pointer;font-weight:600;padding:10px 15px;border-radius:5px;text-decoration:none;display:inline-flex;align-items:center;gap:8px;}
    .button:hover{background:var(--blue-dark);transform:translateY(-1px);}
    .button-secondary{background:#6c757d;color:var(--text-light);}
    .button-secondary:hover{background:#5a6268;}
    .button-success{background:#28a745;color:var(--text-light);}
    .button-success:hover{background:#218838;}
    .icon-button{background:none;border:none;cursor:pointer;font-size:1.2em;margin:0 4px;padding:5px;border-radius:50%;transition:background .3s;}
    .icon-button:hover{background:rgba(0,0,0,0.1);}
    .icon-button.green{color:var(--status-active);}
    .icon-button.red{color:var(--status-inactive);}
    .icon-button.blue{color:var(--blue-dark);}
    .back-button{background:transparent;color:var(--text-dark);border:none;text-decoration:none;display:inline-flex;align-items:center;gap:8px;margin-bottom:15px;font-weight:600;}
    .back-button:hover{color:var(--blue-dark);}
    .form-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:15px;}
    .form-group{display:flex;flex-direction:column;gap:5px;}
    .form-group label{font-weight:600;}
    .full-width{grid-column:1 / -1;}
    .mensaje{padding:15px;border-radius:5px;margin: 20px 0;font-weight:bold;}
    .exito{background-color:#d4edda;color:#155724;border:1px solid #c3e6cb;}
    .error{background-color:#f8d7da;color:#721c24;border:1px solid #f5c6cb;}
    .payment-badge{padding:6px 12px;border-radius:15px;font-weight:bold;color:var(--text-light);font-size:0.85em;text-transform:uppercase;}
    .payment-cash{background-color:var(--payment-cash);}
    .payment-card{background-color:var(--payment-card);}
    .payment-transfer{background-color:var(--payment-transfer);}
    .payment-other{background-color:#6c757d;}
    .form-error { color: #dc3545; font-size: 0.85em; margin-top: 5px; }
    .amount-positive { color: var(--blue-dark); font-weight: bold; }
    .suscripcion-info { font-size: 0.9em; color: #666; }
    .form-container { display: none; margin-bottom: 20px; }
    .form-container.show { display: block; }
    .header-actions { display: flex; gap: 10px; align-items: center; }
    .monto-auto { background-color: #f8f9fa; color: #495057; }
    .referencia-auto { background-color: #e9f7ef; color: #28a745; font-weight: bold; }
    .estado-badge { padding: 4px 8px; border-radius: 12px; font-size: 0.75em; font-weight: bold; }
    .estado-activa { background-color: #28a745; color: white; }
    .estado-vencida { background-color: #ffc107; color: black; }
    .estado-cancelada { background-color: #dc3545; color: white; }
    .search-container { display: flex; gap: 10px; margin-bottom: 20px; align-items: center; }
    .search-box { flex: 1; max-width: 400px; position: relative; }
    .search-box input { padding-left: 40px; }
    .search-icon { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #666; }
    .table-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
    .table-actions { display: flex; gap: 10px; align-items: center; }
    .results-count { color: #666; font-size: 0.9em; margin-left: 10px; }
    
    /* Select2 Custom Styles */
    .select2-container--default .select2-selection--single {
        border: 1px solid #ccc;
        border-radius: 5px;
        height: 42px;
        padding: 5px;
    }
    .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: 30px;
        padding-left: 0;
    }
    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 40px;
    }
    .select2-container--default .select2-results__option--highlighted[aria-selected] {
        background-color: var(--blue-primary);
    }
    .select2-container--default .select2-search--dropdown .select2-search__field {
        border: 1px solid #ccc;
        border-radius: 5px;
        padding: 8px;
    }
    
    @media (max-width: 992px) { 
        .page-layout { flex-direction: column; }
        .header-actions { flex-direction: column; align-items: flex-start; }
        .table-header { flex-direction: column; align-items: flex-start; gap: 10px; }
        .search-box { max-width: 100%; }
    }
</style>

</head>
<body>

<div class="page-header">
    <h1><i class="fas fa-credit-card"></i> Gestión de Pagos</h1>
    <div class="header-actions">
        <button class="button button-success" onclick="toggleForm()" id="toggleFormBtn">
            <i class="fas fa-plus-circle"></i> Nuevo Pago
        </button>
        <a href="reporte_pagos_pdf.php" class="button button-secondary" target="_blank" id="btnReportePDF">
    <i class="fas fa-file-pdf"></i> Reporte General PDF
</a>
        <a class="back-button" href="menuprincipal.php">
            <i class="fas fa-arrow-left"></i> Volver al Menú
        </a>
    </div>
</div>

<?php if($mensaje) { echo $mensaje; } ?>

<!-- Formulario de Pago (Oculto inicialmente) -->
<div class="form-container <?php echo $modo_edicion ? 'show' : ''; ?>" id="formContainer">
    <div class="container">
        <h2><i class="fas fa-plus-circle"></i> <?php echo $modo_edicion ? 'Editar Pago' : 'Registrar Nuevo Pago'; ?></h2>
        
        <?php if ($modo_edicion && $suscripcion_activa_info && $suscripcion_activa_info['id_estadoSuscripcion'] == 1): ?>
            <div class="mensaje error">
                <i class="fas fa-info-circle"></i> 
                <strong>No se puede actualizar el pago en este momento</strong><br>
                La suscripción se encuentra <span class="estado-badge estado-activa">ACTIVA</span>.<br>
                La edición estará disponible una vez finalice el período actual 
                (<?php echo $suscripcion_activa_info['nombre_plan']; ?> - Vence: <?php echo date('d/m/Y', strtotime($suscripcion_activa_info['fecha_fin'])); ?>).
            </div>
            
            <div class="form-grid" style="opacity: 0.6; pointer-events: none;">
                <div class="form-group">
                    <label for="id_suscripcion">Suscripción *</label>
                    <select id="id_suscripcion" name="id_suscripcion" required class="select2-suscripcion" disabled>
                        <option value="<?php echo $pago_a_editar['id_suscripcion']; ?>" selected>
                            <?php 
                            $suscripcion_desc = isset($suscripciones_simplificadas[$pago_a_editar['id_suscripcion']]) 
                                ? $suscripciones_simplificadas[$pago_a_editar['id_suscripcion']] 
                                : 'Suscripción #' . $pago_a_editar['id_suscripcion'];
                            echo htmlspecialchars($suscripcion_desc); 
                            ?>
                        </option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="monto">Monto (Lps) *</label>
                    <input type="number" id="monto" name="monto" step="0.01" min="0.01" 
                           value="<?php echo htmlspecialchars($pago_a_editar['monto'] ?? ''); ?>" required disabled>
                </div>
                
                <div class="form-group">
                    <label for="metodo_pago">Método de Pago *</label>
                    <select id="metodo_pago" name="metodo_pago" required disabled>
                        <option value="<?php echo htmlspecialchars($pago_a_editar['metodo_pago'] ?? ''); ?>" selected>
                            <?php echo htmlspecialchars($pago_a_editar['metodo_pago'] ?? ''); ?>
                        </option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="referencia_pago">Referencia de Pago</label>
                    <input type="text" id="referencia_pago" name="referencia_pago" 
                           value="<?php echo htmlspecialchars($pago_a_editar['referencia_pago'] ?? ''); ?>" disabled>
                </div>
            </div>
            <br>
            <button type="button" class="button" disabled style="opacity: 0.6;">
                <i class="fas fa-save"></i> Actualizar Pago (No disponible)
            </button>
            <a href="pagos.php" class="button button-secondary">
                <i class="fas fa-times"></i> Volver a la lista
            </a>
            
        <?php else: ?>
            <form id="formPago" action="pagos.php" method="POST" onsubmit="return validarFormularioPago()">
                <?php if ($modo_edicion): ?>
                    <input type="hidden" name="accion" value="actualizar_pago">
                    <input type="hidden" name="id_pago" value="<?php echo htmlspecialchars($pago_a_editar['id_pago']); ?>">
                <?php else: ?>
                    <input type="hidden" name="accion" value="registrar_pago">
                <?php endif; ?>

                <div class="form-grid">
                    <div class="form-group">
                        <label for="id_suscripcion">Suscripción *</label>
                        <select id="id_suscripcion" name="id_suscripcion" required onchange="actualizarMontoYReferencia()" class="select2-suscripcion">
                            <option value="">Seleccionar suscripción</option>
                            <?php foreach($suscripciones_simplificadas as $id => $descripcion_simple): 
                                $estado_clase = '';
                                switch($estados_suscripciones[$id]) {
                                    case 1: $estado_clase = 'estado-activa'; break;
                                    case 2: $estado_clase = 'estado-vencida'; break;
                                    case 3: $estado_clase = 'estado-cancelada'; break;
                                }
                            ?>
                                <option value="<?php echo $id; ?>" 
                                    data-precio="<?php echo $precios_suscripciones[$id]; ?>"
                                    data-estado="<?php echo $estados_suscripciones[$id]; ?>"
                                    <?php echo (isset($pago_a_editar['id_suscripcion']) && $pago_a_editar['id_suscripcion'] == $id) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($descripcion_simple); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div id="error_suscripcion" class="form-error"></div>
                        <small id="info_estado" style="font-size: 0.85em; margin-top: 5px;">
                            Solo se muestran suscripciones VENCIDAS, CANCELADAS o que no tengan pagos registrados. Al registrar/editar el pago, el estado se actualizará a ACTIVA si estaba cancelado/vencido.
                        </small>
                    </div>
                    
                    <div class="form-group">
                        <label for="monto">Monto (Lps) *</label>
                        <input type="number" id="monto" name="monto" step="0.01" min="0.01" placeholder="0.00" 
                               value="<?php echo htmlspecialchars($pago_a_editar['monto'] ?? ''); ?>" required
                               class="<?php echo !$modo_edicion ? 'monto-auto' : ''; ?>">
                        <div id="error_monto" class="form-error"></div>
                        <small id="monto_info" style="color: #666; font-size: 0.85em;">
                            <?php echo $modo_edicion ? 'El monto se puede modificar manualmente en edición' : 'El monto se llenará automáticamente al seleccionar una suscripción'; ?>
                        </small>
                    </div>
                    
                    <div class="form-group">
                        <label for="metodo_pago">Método de Pago *</label>
                        <select id="metodo_pago" name="metodo_pago" required>
                            <option value="">Seleccionar método</option>
                            <option value="Efectivo" <?php echo (isset($pago_a_editar['metodo_pago']) && $pago_a_editar['metodo_pago'] == 'Efectivo') ? 'selected' : ''; ?>>Efectivo</option>
                            <option value="Tarjeta de Crédito" <?php echo (isset($pago_a_editar['metodo_pago']) && $pago_a_editar['metodo_pago'] == 'Tarjeta de Crédito') ? 'selected' : ''; ?>>Tarjeta de Crédito</option>
                            <option value="Tarjeta de Débito" <?php echo (isset($pago_a_editar['metodo_pago']) && $pago_a_editar['metodo_pago'] == 'Tarjeta de Débito') ? 'selected' : ''; ?>>Tarjeta de Débito</option>
                            <option value="Transferencia Bancaria" <?php echo (isset($pago_a_editar['metodo_pago']) && $pago_a_editar['metodo_pago'] == 'Transferencia Bancaria') ? 'selected' : ''; ?>>Transferencia Bancaria</option>
                            <option value="PayPal" <?php echo (isset($pago_a_editar['metodo_pago']) && $pago_a_editar['metodo_pago'] == 'PayPal') ? 'selected' : ''; ?>>PayPal</option>
                            <option value="Otro" <?php echo (isset($pago_a_editar['metodo_pago']) && $pago_a_editar['metodo_pago'] == 'Otro') ? 'selected' : ''; ?>>Otro</option>
                        </select>
                        <div id="error_metodo_pago" class="form-error"></div>
                    </div>
                    
                    <div class="form-group">
                        <label for="referencia_pago">Referencia de Pago</label>
                        <input type="text" id="referencia_pago" name="referencia_pago" 
                               placeholder="Se generará automáticamente"
                               value="<?php echo htmlspecialchars($pago_a_editar['referencia_pago'] ?? ''); ?>"
                               class="<?php echo !$modo_edicion ? 'referencia-auto' : ''; ?>">
                        <div id="error_referencia" class="form-error"></div>
                        <small id="referencia_info" style="color: #666; font-size: 0.85em;">
                            <?php echo $modo_edicion ? 'Puede modificar la referencia manualmente' : 'La referencia se generará automáticamente'; ?>
                        </small>
                    </div>
                </div>
                <br>
                <button type="submit" class="button">
                    <i class="fas fa-save"></i> <?php echo $modo_edicion ? 'Actualizar Pago' : 'Registrar Pago'; ?>
                </button>
                <?php if ($modo_edicion): ?>
                    <a href="pagos.php" class="button button-secondary">Cancelar Edición</a>
                <?php else: ?>
                    <button type="button" class="button button-secondary" onclick="toggleForm()">Cancelar</button>
                <?php endif; ?>
            </form>
        <?php endif; ?>
    </div>
</div>


<!-- Lista de Pagos -->
<div class="container">
    <div class="table-header">
        <h2><i class="fas fa-list"></i> Lista de Pagos Registrados</h2>
        <div class="table-actions">
            <div class="search-container">
                <div class="search-box">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" id="searchInput" placeholder="Buscar en pagos..." onkeyup="filtrarPagos()">
                </div>
                <span id="resultsCount" class="results-count"></span>
            </div>
        </div>
    </div>
    
    <div style="overflow-x:auto;">
        <table id="tablaPagos">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Suscripción</th>
                    <th>Monto</th>
                    <th>Método</th>
                    <th>Referencia</th>
                    <th>Fecha Pago</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody id="tablaPagosBody">
            <?php 
            $total_pagos = 0;
            while ($row = $result_pagos->fetch_assoc()): 
                $total_pagos++;
                $estado_clase = '';
                switch($row['estado_suscripcion']) {
                    case 'ACTIVA': $estado_clase = 'estado-activa'; break;
                    case 'VENCIDA': $estado_clase = 'estado-vencida'; break;
                    case 'CANCELADA': $estado_clase = 'estado-cancelada'; break;
                }
            ?>
                <tr id="pago-<?php echo $row['id_pago']; ?>">
                    <td><?php echo $row['id_pago']; ?></td>
                    <td>
                        <div>Suscripción #<?php echo $row['id_suscripcion']; ?></div>
                        <div class="suscripcion-info"><?php echo htmlspecialchars($row['descripcion_suscripcion'] ?? 'N/A'); ?></div>
                    </td>
                    <td class="amount-positive">Lps <?php echo number_format($row['monto'], 2); ?></td>
                    <td>
                        <?php if (!empty($row['metodo_pago'])): 
                            $badge_class = '';
                            switch($row['metodo_pago']) {
                                case 'Efectivo': $badge_class = 'payment-cash'; break;
                                case 'Tarjeta de Crédito':
                                case 'Tarjeta de Débito': $badge_class = 'payment-card'; break;
                                case 'Transferencia Bancaria': $badge_class = 'payment-transfer'; break;
                                default: $badge_class = 'payment-other';
                            }
                        ?>
                            <span class="payment-badge <?php echo $badge_class; ?>">
                                <?php echo htmlspecialchars($row['metodo_pago']); ?>
                            </span>
                        <?php else: ?>
                            N/A
                        <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars($row['referencia_pago'] ?? 'N/A'); ?></td>
                    <td><?php echo date("d/m/Y H:i", strtotime($row['fecha_pago'])); ?></td>
                    <td>
                        <span class="estado-badge <?php echo $estado_clase; ?>">
                            <?php echo htmlspecialchars($row['estado_suscripcion']); ?>
                        </span>
                    </td>
                    <td class="action-cell">
                        <a href="imprimir_pago_pdf.php?id=<?php echo $row['id_pago']; ?>" class="icon-button blue" title="Imprimir Comprobante" target="_blank">
                            <i class="fas fa-print"></i>
                        </a>
                        <a href="pagos.php?accion=editar&id=<?php echo $row['id_pago']; ?>#formContainer" class="icon-button orange" title="Editar Pago">
                            <i class="fas fa-edit"></i>
                        </a>
                        <button class="icon-button red" onclick="eliminarPago(<?php echo $row['id_pago']; ?>)" title="Eliminar">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
            <?php endwhile; ?>
            <?php if ($result_pagos->num_rows === 0): ?>
                <tr>
                    <td colspan="8" style="text-align:center;">No hay pagos registrados.</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>


<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/i18n/es.js"></script>

<script>
// Datos de precios de suscripciones
const preciosSuscripciones = <?php echo json_encode($precios_suscripciones); ?>;

// Inicializar Select2 para el dropdown de suscripciones
$(document).ready(function() {
    $('.select2-suscripcion').select2({
        placeholder: "Buscar suscripción...",
        allowClear: true,
        language: "es",
        width: '100%',
        templateResult: function(data) {
            if (!data.id) {
                return data.text;
            }
            
            var $result = $(
                '<div>' + data.text + '</div>'
            );
            return $result;
        }
    });
    
    actualizarContadorResultados();
});

function actualizarMontoYReferencia() {
    const suscripcionSelect = document.getElementById('id_suscripcion');
    const montoInput = document.getElementById('monto');
    const referenciaInput = document.getElementById('referencia_pago');
    const montoInfo = document.getElementById('monto_info');
    const referenciaInfo = document.getElementById('referencia_info');
    const infoEstado = document.getElementById('info_estado');
    const selectedOption = suscripcionSelect.options[suscripcionSelect.selectedIndex];
    
    if (selectedOption.value && preciosSuscripciones[selectedOption.value]) {
        const precio = parseFloat(preciosSuscripciones[selectedOption.value]);
        const estado = selectedOption.getAttribute('data-estado');
        
        // Actualizar monto
        montoInput.value = precio.toFixed(2);
        montoInput.classList.add('monto-auto');
        montoInfo.textContent = 'Monto cargado automáticamente desde la suscripción seleccionada';
        montoInfo.style.color = '#28a745';
        
        // Generar referencia automática
        const referenciaBase = selectedOption.value + '000';
        referenciaInput.value = referenciaBase;
        referenciaInput.classList.add('referencia-auto');
        referenciaInfo.textContent = 'Referencia generada automáticamente';
        referenciaInfo.style.color = '#28a745';
        
        // Mostrar información sobre el cambio de estado
        if (estado && estado != '1') {
            infoEstado.innerHTML = '<strong>Nota:</strong> Al registrar/editar el pago, el estado se actualizará a <span class="estado-badge estado-activa">ACTIVA</span> y se configurarán las fechas automáticamente.';
            infoEstado.style.color = '#dc3545';
        } else {
            infoEstado.innerHTML = 'Al registrar/editar el pago, se mantendrá el estado ACTIVA y se configurarán las fechas automáticamente.';
            infoEstado.style.color = '#666';
        }
    } else {
        montoInput.value = '';
        montoInput.classList.remove('monto-auto');
        montoInfo.textContent = 'Selecciona una suscripción para cargar el monto automáticamente';
        montoInfo.style.color = '#666';
        
        referenciaInput.value = '';
        referenciaInput.classList.remove('referencia-auto');
        referenciaInfo.textContent = 'La referencia se generará automáticamente';
        referenciaInfo.style.color = '#666';
        
        infoEstado.innerHTML = 'Al registrar/editar el pago, el estado se actualizará a ACTIVA y se configurarán las fechas automáticamente.';
        infoEstado.style.color = '#666';
    }
}

function toggleForm() {
    const formContainer = document.getElementById('formContainer');
    const toggleBtn = document.getElementById('toggleFormBtn');
    
    if (formContainer.classList.contains('show')) {
        formContainer.classList.remove('show');
        toggleBtn.innerHTML = '<i class="fas fa-plus-circle"></i> Nuevo Pago';
        // Resetear el formulario al cerrar
        document.getElementById('formPago').reset();
        document.getElementById('monto_info').textContent = 'El monto se llenará automáticamente al seleccionar una suscripción';
        document.getElementById('monto_info').style.color = '#666';
        document.getElementById('monto').classList.remove('monto-auto');
        document.getElementById('referencia_info').textContent = 'La referencia se generará automáticamente';
        document.getElementById('referencia_info').style.color = '#666';
        document.getElementById('referencia_pago').classList.remove('referencia-auto');
        document.getElementById('info_estado').innerHTML = 'Al registrar/editar el pago, el estado se actualizará a ACTIVA y se configurarán las fechas automáticamente.';
        document.getElementById('info_estado').style.color = '#666';
        // Limpiar Select2
        $('.select2-suscripcion').val('').trigger('change');
    } else {
        formContainer.classList.add('show');
        toggleBtn.innerHTML = '<i class="fas fa-times"></i> Cerrar Formulario';
        // Scroll to form
        formContainer.scrollIntoView({ behavior: 'smooth' });
    }
}

function validarFormularioPago() {
    let valido = true;
    document.querySelectorAll('.form-error').forEach(el => el.textContent = '');

    const suscripcion = document.getElementById('id_suscripcion').value;
    if (!suscripcion) {
        document.getElementById('error_suscripcion').textContent = 'Debe seleccionar una suscripción.';
        valido = false;
    }

    const monto = parseFloat(document.getElementById('monto').value);
    if (!monto || monto <= 0) {
        document.getElementById('error_monto').textContent = 'El monto debe ser mayor a 0.';
        valido = false;
    }

    const metodoPago = document.getElementById('metodo_pago').value;
    if (!metodoPago) {
        document.getElementById('error_metodo_pago').textContent = 'Debe seleccionar un método de pago.';
        valido = false;
    }

    const referencia = document.getElementById('referencia_pago').value.trim();
    if (referencia && referencia.length > 120) {
        document.getElementById('error_referencia').textContent = 'La referencia no puede exceder 120 caracteres.';
        valido = false;
    }

    return valido;
}

function eliminarPago(pagoId) {
    if (!confirm('¿Estás seguro de que quieres eliminar este pago? Esta acción no se puede deshacer.')) {
        return;
    }

    const formData = new FormData();
    formData.append('accion', 'eliminar_pago');
    formData.append('id_pago', pagoId);

    fetch('pagos.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const row = document.getElementById('pago-' + pagoId);
            if (row) {
                row.remove();
            }
            // Mostrar mensaje de éxito
            const mensaje = document.createElement('div');
            mensaje.className = 'mensaje exito';
            mensaje.textContent = 'Pago eliminado correctamente.';
            document.querySelector('.page-header').after(mensaje);
            
            // Remover el mensaje después de 3 segundos
            setTimeout(() => mensaje.remove(), 3000);
            
            // Actualizar contador de resultados
            actualizarContadorResultados();
            
            // Si no quedan filas, mostrar mensaje
            if (document.querySelectorAll('#tablaPagosBody tr').length === 0) {
                document.querySelector('#tablaPagosBody').innerHTML = '<tr><td colspan="8" style="text-align:center;">No hay pagos registrados.</td></tr>';
            }
        } else {
            alert('Error: ' + data.error);
        }
    })
    .catch(error => {
        console.error('Error en la solicitud:', error);
        alert('Ocurrió un error de red. Por favor, inténtalo de nuevo.');
    });
}

function filtrarPagos() {
    const input = document.getElementById('searchInput');
    const filter = input.value.toLowerCase();
    const table = document.getElementById('tablaPagosBody');
    const rows = table.getElementsByTagName('tr');
    
    let visibleCount = 0;
    
    for (let i = 0; i < rows.length; i++) {
        const row = rows[i];
        const cells = row.getElementsByTagName('td');
        let found = false;
        
        // Buscar en todas las celdas de la fila
        for (let j = 0; j < cells.length; j++) {
            const cell = cells[j];
            if (cell) {
                const text = cell.textContent || cell.innerText;
                if (text.toLowerCase().indexOf(filter) > -1) {
                    found = true;
                    break;
                }
            }
        }
        
        if (found) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    }
    
    // Actualizar contador de resultados
    document.getElementById('resultsCount').textContent = `${visibleCount} de ${rows.length} resultados`;
}

function actualizarContadorResultados() {
    const table = document.getElementById('tablaPagosBody');
    const rows = table.getElementsByTagName('tr');
    let visibleCount = 0;
    
    for (let i = 0; i < rows.length; i++) {
        if (rows[i].style.display !== 'none') {
            visibleCount++;
        }
    }
    
    document.getElementById('resultsCount').textContent = `${visibleCount} de ${rows.length} resultados`;
}

// Auto-reload después de acciones exitosas
<?php if($mensaje): ?>
    setTimeout(() => {
        window.location.href = 'pagos.php';
    }, 4000);
<?php endif; ?>

// Mostrar formulario si estamos en modo edición
<?php if($modo_edicion): ?>
    document.addEventListener('DOMContentLoaded', function() {
        document.getElementById('formContainer').classList.add('show');
        document.getElementById('toggleFormBtn').innerHTML = '<i class="fas fa-times"></i> Cerrar Formulario';
        // En modo edición, permitir modificar el monto manualmente
        document.getElementById('monto').classList.remove('monto-auto');
        document.getElementById('referencia_pago').classList.remove('referencia-auto');
    });
<?php endif; ?>
// Modificar el botón de Reporte PDF para que use el filtro actual
function generarReporteConFiltro() {
    const filtroActual = document.getElementById('searchInput').value.trim();
    let url = 'reporte_pagos_pdf.php';
    
    // Si hay un filtro activo, agregarlo a la URL
    if (filtroActual) {
        url += '?filtro=' + encodeURIComponent(filtroActual);
    }
    
    // Abrir en nueva pestaña
    window.open(url, '_blank');
    return false;
}

// Reemplazar el botón actual para usar la nueva función
document.addEventListener('DOMContentLoaded', function() {
    const btnReportePDF = document.getElementById('btnReportePDF');
    if (btnReportePDF && btnReportePDF.tagName === 'A') {
        // Cambiar el enlace por un botón que ejecute la función
        const nuevoBoton = document.createElement('button');
        nuevoBoton.className = btnReportePDF.className;
        nuevoBoton.innerHTML = btnReportePDF.innerHTML;
        nuevoBoton.onclick = generarReporteConFiltro;
        nuevoBoton.type = 'button';
        nuevoBoton.style.cursor = 'pointer';
        
        btnReportePDF.parentNode.replaceChild(nuevoBoton, btnReportePDF);
    }
});
</script>

</body>
</html>