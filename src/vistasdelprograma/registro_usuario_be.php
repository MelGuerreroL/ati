<?php
session_start();

// CONEXIÓN
$conex = mysqli_connect("localhost", "root", "", "gestion_suscripciones");
if ($conex->connect_error) {
    header('Location: registro.php?error=Error de conexión a la base de datos');
    exit;
}

// Incluir PHPMailer
require '../../vendor/phpmailer/phpmailer/src/Exception.php';
require '../../vendor/phpmailer/phpmailer/src/PHPMailer.php';
require '../../vendor/phpmailer/phpmailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// VALIDAR DATOS
// Esperamos: nombre_completo, correo, contraseña, confirmar_contraseña
if (!isset($_POST['nombre_completo'], $_POST['correo'], $_POST['contraseña'], $_POST['confirmar_contraseña'])) {
    header('Location: registro.php?error=Faltan datos obligatorios');
    exit;
}

// LIMPIAR DATOS
$nombre_usuario_completo = trim($_POST['nombre_completo']);
$correo_electronico = trim($_POST['correo']);
$contraseña_raw = trim($_POST['contraseña']);
$confirmar_contraseña = trim($_POST['confirmar_contraseña']);

// VERIFICAR VACÍOS
if (empty($nombre_usuario_completo) || empty($correo_electronico) || empty($contraseña_raw) || empty($confirmar_contraseña)) {
    header('Location: registro.php?error=Todos los campos son obligatorios');
    exit;
}

// Normalizar nombre: colapsar espacios y pasar a mayúsculas
$nombre_usuario_completo = preg_replace('/\s+/', ' ', $nombre_usuario_completo);
$nombre_usuario_completo = strtoupper($nombre_usuario_completo);

// Validar formato del nombre: solo letras mayúsculas A-Z y un único espacio entre palabras
if (!preg_match('/^[A-Z]+(?: [A-Z]+)*$/', $nombre_usuario_completo)) {
    header('Location: registro.php?error=' . urlencode('El nombre solo puede contener letras mayúsculas A-Z y un único espacio entre palabras'));
    exit;
}

// Validar email
if (!filter_var($correo_electronico, FILTER_VALIDATE_EMAIL)) {
    header('Location: registro.php?error=Correo electrónico inválido');
    exit;
}

// Validar longitud mínima de contraseña
if (strlen($contraseña_raw) < 8) {
    header('Location: registro.php?error=La contraseña debe tener al menos 8 caracteres');
    exit;
}

// Confirmación
if ($contraseña_raw !== $confirmar_contraseña) {
    header('Location: registro.php?error=Las contraseñas no coinciden');
    exit;
}

// Validar la contrasena
$patrón = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_])[^\s]{8,10}$/';
if (!preg_match($patrón, $contraseña_raw)) {
 header('Location: ../../public/index.php?error=Ingreso incorrecto de contraseña');
    exit();
}

// ENCRIPTAR CONTRASEÑA
$contraseña_hash = hash('sha512', $contraseña_raw);

try {
    // VERIFICAR DUPLICADOS
    // Comprobar si el correo ya está registrado
    $sql_check = "SELECT id_usuario FROM tbl_ms_usuario WHERE correo_electronico = ? LIMIT 1";
    $stmt_check = $conex->prepare($sql_check);
    $stmt_check->bind_param("s", $correo_electronico);
    $stmt_check->execute();
    
    if ($stmt_check->get_result()->num_rows > 0) {
        header('Location: registro.php?error=Ya existe una cuenta con ese correo');
        exit;
    }
    $stmt_check->close();
    

    // GENERAR CÓDIGO DE VERIFICACIÓN
    $codigo_verificacion = sprintf("%06d", mt_rand(1, 999999));
    $expiracion = date('Y-m-d H:i:s', strtotime('+10 minutes'));
    
    // Crear tabla de códigos si no existe
    $crear_tabla = "CREATE TABLE IF NOT EXISTS tbl_verificacion_codigos (
        id INT PRIMARY KEY AUTO_INCREMENT,
        email VARCHAR(100) NOT NULL,
        codigo VARCHAR(6) NOT NULL,
        expiracion DATETIME NOT NULL,
        usado TINYINT DEFAULT 0,
        intentos TINYINT DEFAULT 0,
        fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_email (email),
        INDEX idx_codigo (codigo),
        INDEX idx_expiracion (expiracion)
    )";
    $conex->query($crear_tabla);
    
    // Guardar código en la base de datos
    $sql_codigo = "INSERT INTO tbl_verificacion_codigos (email, codigo, expiracion) VALUES (?, ?, ?)";
    $stmt_codigo = $conex->prepare($sql_codigo);
    $stmt_codigo->bind_param('sss', $correo_electronico, $codigo_verificacion, $expiracion);
    
    if (!$stmt_codigo->execute()) {
        throw new Exception("Error al generar código de verificación");
    }
    $stmt_codigo->close();

    // ENVIAR CÓDIGO POR CORREO
    try {
        $mail = new PHPMailer(true);
        
        // Configuración SMTP (usa la misma de recuperación de contraseña)
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'castilloemer2002@gmail.com';  // Actualiza con tu Gmail
        $mail->Password = 'qxhd jyuy dcbl wicm';      // Actualiza con tu app password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        
        // Configurar correo
        $mail->setFrom('no-reply@gestion-suscripciones.com', 'Sistema de Suscripciones');
        $mail->addAddress($correo_electronico, $nombre_usuario_completo);
        $mail->addReplyTo('soporte@gestion-suscripciones.com', 'Soporte');
        
        $mail->isHTML(true);
        $mail->Subject = 'Código de Verificación - Sistema de Suscripciones';
        $mail->CharSet = 'UTF-8';
        
        $mail->Body = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
                .codigo { background: #2d3748; color: #ffffff; padding: 20px; font-size: 32px; font-weight: bold; text-align: center; letter-spacing: 10px; border-radius: 10px; margin: 20px 0; }
                .warning { background: #fed7d7; color: #c53030; padding: 15px; border-radius: 5px; margin: 15px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🔐 Verificación de Cuenta</h1>
                </div>
                <div class='content'>
                    <h2>Hola {$nombre_usuario_completo},</h2>
                    <p>Gracias por registrarte en el <strong>Sistema de Gestión de Suscripciones</strong>.</p>
                    <p>Para completar tu registro, ingresa el siguiente código de verificación:</p>
                    
                    <div class='codigo'>{$codigo_verificacion}</div>
                    
                    <div class='warning'>
                        ⚠️ <strong>Importante:</strong> Este código expirará en 10 minutos.
                    </div>
                    
                    <p>Si no solicitaste este registro, por favor ignora este correo.</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        $mail->AltBody = "Código de verificación: {$codigo_verificacion}\n\nExpira en 10 minutos.\n\nSi no solicitaste este registro, ignora este correo.";
        
        // Enviar correo
        if (!$mail->send()) {
            throw new Exception('Error al enviar el código de verificación: ' . $mail->ErrorInfo);
        }
        
    } catch (Exception $e) {
        error_log("Error enviando código de verificación: " . $e->getMessage());
        throw new Exception("Error al enviar el código de verificación. Por favor intenta nuevamente.");
    }

    // GUARDAR DATOS EN SESIÓN PARA VERIFICACIÓN
    $_SESSION['registro_pendiente'] = [
        'nombre_completo' => $nombre_usuario_completo,
        'correo' => $correo_electronico,
        'contraseña_hash' => $contraseña_hash
    ];
    
    // REDIRIGIR A VERIFICACIÓN
    header('Location: verificacion_codigo.php');
    exit;

} catch (Exception $e) {
    header('Location: registro.php?error=' . urlencode($e->getMessage()));
} finally {
    if (isset($conex)) $conex->close();
}
?>