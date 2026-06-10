<?php
session_start();
require 'con_db.php';

// Incluir PHPMailer manualmente
require '../../vendor/phpmailer/phpmailer/src/Exception.php';
require '../../vendor/phpmailer/phpmailer/src/PHPMailer.php';
require '../../vendor/phpmailer/phpmailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $correo = trim($_POST['correo']);
    
    if (empty($correo)) {
        echo '<script>alert("Por favor ingresa tu correo electrónico."); window.location="recuperar_contraseña.php";</script>';
        exit;
    }

    // Validar formato de correo
    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        echo '<script>alert("Por favor ingresa un correo electrónico válido."); window.location="recuperar_contraseña.php";</script>';
        exit;
    }

    // Verificar si es Gmail y validar formato
    if (strpos($correo, '@gmail.com') !== false) {
        $localPart = explode('@', $correo)[0];
        if (strlen($localPart) < 6 || strlen($localPart) > 30) {
            echo '<script>alert("El correo Gmail debe tener entre 6 y 30 caracteres antes del @."); window.location="recuperar_contraseña.php";</script>';
            exit;
        }
        
        if (!preg_match('/^[a-zA-Z0-9\.]+$/', $localPart)) {
            echo '<script>alert("El correo Gmail solo puede contener letras, números y puntos."); window.location="recuperar_contraseña.php";</script>';
            exit;
        }
    }

    // Verificar si el correo existe en la base de datos
    $sql = "SELECT id_usuario, nombre_usuario, usuario FROM tbl_ms_usuario WHERE correo_electronico = ? LIMIT 1";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param('s', $correo);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $usuario = $result->fetch_assoc();
        $token = bin2hex(random_bytes(32));
        $expiracion = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        // Crear tabla de tokens si no existe
        $crear_tabla = "CREATE TABLE IF NOT EXISTS tbl_reset_tokens (
            id INT PRIMARY KEY AUTO_INCREMENT,
            email VARCHAR(100) NOT NULL,
            token VARCHAR(64) NOT NULL,
            expiracion DATETIME NOT NULL,
            usado TINYINT DEFAULT 0,
            fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_email (email),
            INDEX idx_token (token),
            INDEX idx_expiracion (expiracion)
        )";
        $conexion->query($crear_tabla);
        
        // Guardar token en la base de datos
        $sql_token = "INSERT INTO tbl_reset_tokens (email, token, expiracion) VALUES (?, ?, ?) 
                     ON DUPLICATE KEY UPDATE token = ?, expiracion = ?, usado = 0";
        $stmt_token = $conexion->prepare($sql_token);
        $stmt_token->bind_param('sssss', $correo, $token, $expiracion, $token, $expiracion);
        
        if ($stmt_token->execute()) {
            // Configurar el enlace de reset
            $enlace_reset = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/reset_contraseña.php?token=" . $token;
            
            try {
                // Configurar PHPMailer
                $mail = new PHPMailer(true);
                
                // Configuración del servidor SMTP (Gmail)
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'castilloemer2002@gmail.com';  // CAMBIA ESTO
                $mail->Password = 'qxhd jyuy dcbl wicm';      // CAMBIA ESTO
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = 587;
                $mail->SMTPOptions = array(
                    'ssl' => array(
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true
                    )
                );
                
                // Configurar remitente y destinatario
                $mail->setFrom('castilloemer2002@gmail.com', 'Sistema de Suscripciones');
                $mail->addAddress($correo, $usuario['nombre_usuario']);
                $mail->addReplyTo('castilloemer2002@gmail.com', 'Soporte');
                
                // Contenido del correo
                $mail->isHTML(true);
                $mail->Subject = 'Recuperación de Contraseña - Sistema de Suscripciones';
                $mail->CharSet = 'UTF-8';
                
                $mail->Body = "
                <html>
                <head>
                    <title>Recuperación de Contraseña</title>
                    <style>
                        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 20px; }
                        .container { max-width: 600px; margin: 0 auto; background: #f9f9f9; border-radius: 10px; overflow: hidden; }
                        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; }
                        .content { padding: 30px; background: white; }
                        .button { display: inline-block; padding: 12px 30px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; text-decoration: none; border-radius: 5px; margin: 20px 0; }
                        .footer { padding: 20px; background: #f1f1f1; text-align: center; font-size: 12px; color: #666; }
                        .warning { color: #ff6b6b; font-weight: bold; background: #fff5f5; padding: 10px; border-radius: 5px; border-left: 4px solid #ff6b6b; }
                        .code { background: #f8f9fa; padding: 15px; border: 1px solid #e9ecef; border-radius: 5px; font-family: monospace; word-break: break-all; margin: 15px 0; }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <div class='header'>
                            <h1>🔐 Recuperación de Contraseña</h1>
                        </div>
                        <div class='content'>
                            <h2>Hola " . htmlspecialchars($usuario['nombre_usuario']) . ",</h2>
                            <p>Has solicitado restablecer tu contraseña en el <strong>Sistema de Gestión de Suscripciones</strong>.</p>
                            
                            <p>Para restablecer tu contraseña, haz clic en el siguiente botón:</p>
                            <p style='text-align: center;'>
                                <a href='" . $enlace_reset . "' class='button'>🔄 Restablecer Contraseña</a>
                            </p>
                            
                            <p>O copia y pega el siguiente enlace en tu navegador:</p>
                            <div class='code'>" . $enlace_reset . "</div>
                            
                            <div class='warning'>
                                ⚠️ <strong>Importante:</strong> Este enlace expirará en 1 hora.
                            </div>
                            
                            <p>Si no solicitaste este cambio, por favor ignora este correo. Tu cuenta permanecerá segura.</p>
                        </div>
                        <div class='footer'>
                            <p>Este es un mensaje automático, por favor no respondas a este correo.</p>
                            <p>Si necesitas ayuda, contacta a nuestro equipo de soporte.</p>
                            <p><strong>Equipo de Soporte - Sistema de Suscripciones</strong></p>
                        </div>
                    </div>
                </body>
                </html>
                ";
                
                // Texto alternativo para clientes de correo que no soportan HTML
                $mail->AltBody = "RECUPERACIÓN DE CONTRASEÑA\n\n" .
                    "Hola " . $usuario['nombre_usuario'] . ",\n\n" .
                    "Has solicitado restablecer tu contraseña en el Sistema de Gestión de Suscripciones.\n\n" .
                    "Para restablecer tu contraseña, visita el siguiente enlace:\n" .
                    $enlace_reset . "\n\n" .
                    "Este enlace expirará en 1 hora.\n\n" .
                    "Si no solicitaste este cambio, ignora este correo.\n\n" .
                    "Saludos,\nEquipo de Soporte";
                
                // Enviar correo
                $mail->send();
                
                echo '<script>
                    alert("✅ Se ha enviado un enlace de recuperación a ' . $correo . '. Revisa tu bandeja de entrada y carpeta de spam.");
                    window.location="../../public/index.php";
                </script>';
                
            } catch (Exception $e) {
                error_log("Error PHPMailer: " . $e->getMessage());
                
                echo '<script>
                    alert("❌ Error al enviar el correo: ' . addslashes($e->getMessage()) . '");
                    window.location="recuperar_contraseña.php";
                </script>';
            }
        } else {
            echo '<script>alert("Error al generar el token de recuperación."); window.location="recuperar_contraseña.php";</script>';
        }
        $stmt_token->close();
    } else {
        // Por seguridad, no revelar si el correo existe o no
        echo '<script>
            alert("Si el correo electrónico está registrado en nuestro sistema, recibirás un enlace de recuperación. Revisa tu bandeja de entrada y carpeta de spam.");
            window.location="../../public/index.php";
        </script>';
    }
    
    $stmt->close();
    $conexion->close();
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Contraseña</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        
        .recovery-container {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.2);
            width: 100%;
            max-width: 450px;
            text-align: center;
        }

        .recovery-container h2 {
            color: #333;
            margin-bottom: 10px;
        }

        .recovery-container p {
            color: #666;
            margin-bottom: 30px;
            line-height: 1.5;
        }
        
        .form-group {
            margin-bottom: 25px;
            text-align: left;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }
        
        .form-group input {
            width: 100%;
            padding: 15px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .btn-recovery {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s ease;
            margin-bottom: 20px;
        }
        
        .btn-recovery:hover {
            transform: translateY(-2px);
        }

        .btn-recovery:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }

        .back-link {
            display: inline-block;
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s ease;
        }

        .back-link:hover {
            color: #764ba2;
            text-decoration: underline;
        }

        .info-box {
            background: #e7f3ff;
            border: 1px solid #b3d9ff;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            text-align: left;
        }

        .info-box h4 {
            color: #0066cc;
            margin-bottom: 8px;
        }

        .info-box p {
            color: #666;
            margin-bottom: 0;
            font-size: 14px;
        }

        .gmail-info {
            background: #ffeaa7;
            border: 1px solid #fdcb6e;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 15px;
            font-size: 13px;
            text-align: left;
        }

        .loading {
            display: none;
            margin: 10px 0;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <div class="recovery-container">
        <h2><i class="fas fa-key"></i> Recuperar Contraseña</h2>
        <p>Ingresa tu correo electrónico y te enviaremos un enlace para restablecer tu contraseña.</p>
        
        <div class="info-box">
            <h4><i class="fas fa-info-circle"></i> Información Importante</h4>
            <p>• El enlace de recuperación tiene validez de 1 hora</p>
            <p>• Revisa tu carpeta de spam si no encuentras el correo</p>
            <p>• Usa el correo electrónico con el que te registraste</p>
        </div>

        <?php if (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false): ?>
        <div class="gmail-info">
            <i class="fas fa-exclamation-triangle"></i> 
            <strong>Modo desarrollo:</strong> Asegúrate de configurar las credenciales de Gmail en el código.
        </div>
        <?php endif; ?>
        
        <form action="recuperar_contraseña.php" method="POST" id="recoveryForm">
            <div class="form-group">
                <label for="correo"><i class="fas fa-envelope"></i> Correo Electrónico</label>
                <input type="email" id="correo" name="correo" placeholder="ejemplo@gmail.com" required>
            </div>
            
            <button type="submit" class="btn-recovery" id="submitBtn">
                <i class="fas fa-paper-plane"></i> Enviar Enlace de Recuperación
            </button>
            
            <div class="loading" id="loading">
                <i class="fas fa-spinner fa-spin"></i> Enviando correo, por favor espera...
            </div>
        </form>
        
        <a href="../../public/index.php" class="back-link">← Volver al Inicio de Sesión</a>
    </div>

    <script>
        document.getElementById('recoveryForm').addEventListener('submit', function(e) {
            const btn = document.getElementById('submitBtn');
            const loading = document.getElementById('loading');
            const correo = document.getElementById('correo').value;
            
            // Validación básica del correo
            if (!correo.includes('@')) {
                alert('Por favor ingresa un correo electrónico válido.');
                e.preventDefault();
                return;
            }
            
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enviando...';
            loading.style.display = 'block';
        });
    </script>
</body>
</html>