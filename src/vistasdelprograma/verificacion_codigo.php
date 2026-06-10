<?php
require_once __DIR__ . '/bitacora_helpers.php'; // Incluir el helper de la bitácora

// Incluir PHPMailer para la función de reenviar código
require '../../vendor/phpmailer/phpmailer/src/Exception.php';
require '../../vendor/phpmailer/phpmailer/src/PHPMailer.php';
require '../../vendor/phpmailer/phpmailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

session_start();

// CONEXIÓN
$conex = mysqli_connect("localhost", "root", "", "gestion_suscripciones");
if ($conex->connect_error) {
    header('Location: registro.php?error=Error de conexión a la base de datos');
    exit;
}

// Verificar que hay un registro pendiente
if (!isset($_SESSION['registro_pendiente'])) {
    header('Location: registro.php');
    exit;
}

$datos_registro = $_SESSION['registro_pendiente'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['codigo'])) {
        $codigo_ingresado = trim($_POST['codigo']);
        
        // Validar código
        if (empty($codigo_ingresado) || strlen($codigo_ingresado) !== 6) {
            header('Location: verificacion_codigo.php?error=Por favor ingresa un código válido de 6 dígitos');
            exit;
        }
        
        // Verificar código en la base de datos
        $sql = "SELECT id, expiracion, usado, intentos FROM tbl_verificacion_codigos 
                WHERE email = ? AND codigo = ? AND usado = 0";
        $stmt = $conex->prepare($sql);
        $stmt->bind_param('ss', $datos_registro['correo'], $codigo_ingresado);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $codigo_data = $result->fetch_assoc();
            
            // Verificar expiración
            if (strtotime($codigo_data['expiracion']) < time()) {
                header('Location: verificacion_codigo.php?error=El código ha expirado. Por favor solicita uno nuevo');
                exit;
            }
            
            // Marcar código como usado
            $sql_update = "UPDATE tbl_verificacion_codigos SET usado = 1 WHERE id = ?";
            $stmt_update = $conex->prepare($sql_update);
            $stmt_update->bind_param('i', $codigo_data['id']);
            $stmt_update->execute();
            $stmt_update->close();
            
            // CREAR EL USUARIO EN LA BASE DE DATOS
            try {
                // Si no se indicó 'usuario' en el registro (ahora el formulario no lo pide), generar uno único
                if (empty($datos_registro['usuario'])) {
                    // Usar la parte local del correo como base
                    $local = preg_replace('/[^a-z0-9_.-]/i', '', strstr($datos_registro['correo'], '@', true) ?: $datos_registro['nombre_completo']);
                    $local = substr($local, 0, 20);
                    if (empty($local)) $local = 'user';
                    $base = strtolower($local);

                    // Generar username único
                    $attempt = 0;
                    $candidate = $base;
                    while (true) {
                        $check = $conex->prepare("SELECT id_usuario FROM tbl_ms_usuario WHERE usuario = ? LIMIT 1");
                        $check->bind_param('s', $candidate);
                        $check->execute();
                        $resc = $check->get_result();
                        $exists = ($resc && $resc->num_rows > 0);
                        $check->close();
                        if (!$exists) break;
                        $attempt++;
                        $candidate = $base . ($attempt);
                        if ($attempt > 100) { // fallback
                            $candidate = $base . '_' . bin2hex(random_bytes(3));
                            break;
                        }
                    }
                    $datos_registro['usuario'] = $candidate;
                }
                // INSERTAR USUARIO
                $sql_insert = "INSERT INTO tbl_ms_usuario (
                    usuario, nombre_usuario, contraseña, primer_ingreso, 
                    fecha_vencimiento, correo_electronico, tbl_ms_roles_id_rol,
                    fecha_ultima_conexion
                ) VALUES (?, ?, ?, 1, DATE_ADD(CURDATE(), INTERVAL 365 DAY), ?, 2, NOW())";
                
                $stmt_insert = $conex->prepare($sql_insert);
                $stmt_insert->bind_param("ssss", 
                    $datos_registro['usuario'], 
                    $datos_registro['nombre_completo'], 
                    $datos_registro['contraseña_hash'], 
                    $datos_registro['correo']
                );

                if ($stmt_insert->execute()) {
                    $id_usuario_nuevo = $stmt_insert->insert_id;
                    
                    // Registrar en bitácora la creación del usuario
                    registrar_bitacora($conex, $id_usuario_nuevo, 1, 'INSERT', "Se registró un nuevo usuario: {$datos_registro['usuario']} (ID: {$id_usuario_nuevo}).");

                    // 1. GUARDAR EN HISTORIAL DE CONTRASEÑAS
                    $sql_historial = "INSERT INTO tbl_ms_hist_contraseña (contraseña, tbl_ms_usuario_id_usuario) VALUES (?, ?)";
                    $stmt_historial = $conex->prepare($sql_historial);
                    $stmt_historial->bind_param('si', $datos_registro['contraseña_hash'], $id_usuario_nuevo);
                    $stmt_historial->execute();
                    $stmt_historial->close();
                    
                    /*
                    // 2. CREAR REGISTRO DE SEGURIDAD
                    $sql_seguridad = "INSERT INTO tbl_ms_seguridad_login (tbl_ms_usuario_id_usuario, intentos_fallidos, estado_bloqueo) VALUES (?, 0, 'ACTIVO')";
                    $stmt_seguridad = $conex->prepare($sql_seguridad);
                    $stmt_seguridad->bind_param('i', $id_usuario_nuevo);
                    $stmt_seguridad->execute();
                    $stmt_seguridad->close();
                    */
                    // LIMPIAR SESIÓN
                    unset($_SESSION['registro_pendiente']);
                    
                    // REDIRIGIR AL LOGIN CON MENSAJE DE ÉXITO
                    header('Location: ../../public/index.php?success=Cuenta verificada y creada exitosamente. Ahora puedes iniciar sesión.');
                    
                } else {
                    throw new Exception($stmt_insert->error);
                }
                
                $stmt_insert->close();
                
            } catch (Exception $e) {
                header('Location: registro.php?error=Error al registrar usuario: ' . urlencode($e->getMessage()));
            }
            
        } else {
            // Incrementar intentos fallidos
            $sql_intentos = "UPDATE tbl_verificacion_codigos SET intentos = intentos + 1 
                            WHERE email = ? AND usado = 0 ORDER BY fecha_creacion DESC LIMIT 1";
            $stmt_intentos = $conex->prepare($sql_intentos);
            $stmt_intentos->bind_param('s', $datos_registro['correo']);
            $stmt_intentos->execute();
            $stmt_intentos->close();
            
            header('Location: verificacion_codigo.php?error=Código incorrecto. Por favor verifica e intenta nuevamente');
        }
        
        $stmt->close();
        
    } elseif (isset($_POST['reenviar_codigo'])) {
        // Lógica para reenviar código
        $nuevo_codigo = sprintf("%06d", mt_rand(1, 999999));
        $expiracion = date('Y-m-d H:i:s', strtotime('+10 minutes'));
        
        $sql_nuevo = "INSERT INTO tbl_verificacion_codigos (email, codigo, expiracion) VALUES (?, ?, ?)";
        $stmt_nuevo = $conex->prepare($sql_nuevo);
        $stmt_nuevo->bind_param('sss', $datos_registro['correo'], $nuevo_codigo, $expiracion);
        
        if ($stmt_nuevo->execute()) {
            // Enviar correo (usar la misma lógica de PHPMailer del registro)
            try {
                $mail = new PHPMailer(true);
                
                // Configuración SMTP (misma que arriba)
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'castilloemer2002@gmail.com';
                $mail->Password = 'qxhd jyuy dcbl wicm';
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = 587;
                $mail->SMTPOptions = array(
                    'ssl' => array(
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true
                    )
                );
                
                $mail->setFrom('no-reply@gestion-suscripciones.com', 'Sistema de Suscripciones');
                $mail->addAddress($datos_registro['correo'], $datos_registro['nombre_completo']);
                
                $mail->isHTML(true);
                $mail->Subject = 'Nuevo Código de Verificación - Sistema de Suscripciones';
                $mail->CharSet = 'UTF-8';
                
                $mail->Body = "
                <html>
                <body>
                    <h2>Hola {$datos_registro['nombre_completo']},</h2>
                    <p>Has solicitado un nuevo código de verificación:</p>
                    <div style='background: #2d3748; color: white; padding: 20px; font-size: 32px; text-align: center; letter-spacing: 10px; border-radius: 10px; margin: 20px 0;'>
                        {$nuevo_codigo}
                    </div>
                    <p><strong>⚠️ Este código expirará en 10 minutos.</strong></p>
                </body>
                </html>
                ";
                
                $mail->send();
                
                header('Location: verificacion_codigo.php?success=Se ha enviado un nuevo código de verificación');
                
            } catch (Exception $e) {
                header('Location: verificacion_codigo.php?error=Error al enviar el nuevo código');
            }
        } else {
            header('Location: verificacion_codigo.php?error=Error al generar nuevo código');
        }
        
        $stmt_nuevo->close();
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificación de Código</title>
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
        
        .verification-container {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.2);
            width: 100%;
            max-width: 450px;
            text-align: center;
        }

        .verification-container h2 {
            color: #333;
            margin-bottom: 10px;
        }

        .verification-container p {
            color: #666;
            margin-bottom: 30px;
            line-height: 1.5;
        }
        
        .code-inputs {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
        }
        
        .code-input {
            width: 50px;
            height: 60px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            text-align: center;
            font-size: 24px;
            font-weight: bold;
            transition: all 0.3s ease;
        }
        
        .code-input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 10px rgba(102, 126, 234, 0.3);
        }
        
        .btn-verify {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s ease;
            margin-bottom: 15px;
        }
        
        .btn-verify:hover {
            transform: translateY(-2px);
        }

        .btn-resend {
            width: 100%;
            padding: 12px;
            background: transparent;
            color: #667eea;
            border: 2px solid #667eea;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-bottom: 20px;
        }
        
        .btn-resend:hover {
            background: #667eea;
            color: white;
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

        .timer {
            font-size: 14px;
            color: #ff6b6b;
            font-weight: bold;
            margin-bottom: 15px;
        }

        .alert {
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 600;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>
    <div class="verification-container">
        <h2><i class="fas fa-shield-alt"></i> Verificación de Código</h2>
        <p>Hemos enviado un código de 6 dígitos a:<br><strong><?php echo $datos_registro['correo']; ?></strong></p>
        
        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($_GET['success']); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($_GET['error']); ?>
            </div>
        <?php endif; ?>
        
        <div class="info-box">
            <h4><i class="fas fa-info-circle"></i> Instrucciones</h4>
            <p>• Ingresa el código de 6 dígitos que recibiste por correo</p>
            <p>• El código expirará en 10 minutos</p>
            <p>• Si no recibiste el código, puedes solicitar uno nuevo</p>
        </div>

        <form action="verificacion_codigo.php" method="POST" id="verificationForm">
            <div class="code-inputs">
                <input type="text" class="code-input" name="code1" maxlength="1" required autofocus>
                <input type="text" class="code-input" name="code2" maxlength="1" required>
                <input type="text" class="code-input" name="code3" maxlength="1" required>
                <input type="text" class="code-input" name="code4" maxlength="1" required>
                <input type="text" class="code-input" name="code5" maxlength="1" required>
                <input type="text" class="code-input" name="code6" maxlength="1" required>
            </div>
            
            <input type="hidden" name="codigo" id="fullCode">
            
            <button type="submit" class="btn-verify" id="submitBtn">
                <i class="fas fa-check-circle"></i> Verificar y Completar Registro
            </button>
        </form>
        
        <form action="verificacion_codigo.php" method="POST">
            <button type="submit" name="reenviar_codigo" class="btn-resend">
                <i class="fas fa-redo"></i> Reenviar Código
            </button>
        </form>
        
        <div class="timer" id="timer">10:00</div>
        
        <a href="registro.php" class="back-link">← Volver al Registro</a>
    </div>

    <script>
        // Combinar inputs individuales en un solo código
        const codeInputs = document.querySelectorAll('.code-input');
        const fullCodeInput = document.getElementById('fullCode');
        
        codeInputs.forEach((input, index) => {
            input.addEventListener('input', function() {
                if (this.value.length === 1 && index < codeInputs.length - 1) {
                    codeInputs[index + 1].focus();
                }
                updateFullCode();
            });
            
            input.addEventListener('keydown', function(e) {
                if (e.key === 'Backspace' && this.value.length === 0 && index > 0) {
                    codeInputs[index - 1].focus();
                }
            });
        });
        
        function updateFullCode() {
            const code = Array.from(codeInputs).map(input => input.value).join('');
            fullCodeInput.value = code;
        }
        
        // Timer de expiración
        let timeLeft = 600; // 10 minutos en segundos
        const timerElement = document.getElementById('timer');
        
        const timer = setInterval(() => {
            timeLeft--;
            const minutes = Math.floor(timeLeft / 60);
            const seconds = timeLeft % 60;
            timerElement.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
            
            if (timeLeft <= 0) {
                clearInterval(timer);
                timerElement.textContent = 'Código expirado';
                timerElement.style.color = '#ff6b6b';
            }
        }, 1000);
        
        // Validación del formulario
        document.getElementById('verificationForm').addEventListener('submit', function(e) {
            const fullCode = fullCodeInput.value;
            const btn = document.getElementById('submitBtn');
            
            if (fullCode.length !== 6) {
                alert('Por favor ingresa un código completo de 6 dígitos.');
                e.preventDefault();
                return;
            }
            
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Verificando...';
        });
    </script>
</body>
</html>