<?php
session_start();
require 'con_db.php';

$token_valido = false;
$email = '';

if (isset($_GET['token'])) {
    $token = $_GET['token'];
    
    // Verificar token válido
    $sql = "SELECT email FROM tbl_reset_tokens WHERE token = ? AND usado = 0 AND expiracion > NOW()";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $data = $result->fetch_assoc();
        $email = $data['email'];
        $token_valido = true;
    } else {
        echo '<script>alert("Enlace inválido o expirado."); window.location="index.php";</script>';
        exit;
    }
} else {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $token_valido) {
    $nueva_contraseña = trim($_POST['nueva_contraseña']);
    $confirmar_contraseña = trim($_POST['confirmar_contraseña']);
    
    if ($nueva_contraseña === $confirmar_contraseña) {
        if (strlen($nueva_contraseña) < 6) {
            echo '<script>alert("La contraseña debe tener al menos 6 caracteres.");</script>';
        } else {
            $hash = hash('sha512', $nueva_contraseña);
            
            // Actualizar contraseña
            $sql_update = "UPDATE tbl_ms_usuario SET contraseña = ? WHERE correo_electronico = ?";
            $stmt_update = $conexion->prepare($sql_update);
            $stmt_update->bind_param('ss', $hash, $email);
            
            if ($stmt_update->execute()) {
                // Marcar token como usado
                $sql_token = "UPDATE tbl_reset_tokens SET usado = 1 WHERE token = ?";
                $stmt_token = $conexion->prepare($sql_token);
                $stmt_token->bind_param('s', $token);
                $stmt_token->execute();
                
                // Guardar en historial de contraseñas
                $sql_historial = "INSERT INTO tbl_ms_hist_contraseña (contraseña, tbl_ms_usuario_id_usuario) 
                                 SELECT ?, id_usuario FROM tbl_ms_usuario WHERE correo_electronico = ?";
                $stmt_historial = $conexion->prepare($sql_historial);
                $stmt_historial->bind_param('ss', $hash, $email);
                $stmt_historial->execute();
                
                echo '<script>
                    alert("Contraseña restablecida exitosamente. Ahora puedes iniciar sesión con tu nueva contraseña.");
                    window.location="index.php";
                </script>';
            } else {
                echo '<script>alert("Error al actualizar la contraseña.");</script>';
            }
        }
    } else {
        echo '<script>alert("Las contraseñas no coinciden.");</script>';
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restablecer Contraseña</title>
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
        
        .reset-container {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.2);
            width: 100%;
            max-width: 450px;
            text-align: center;
        }

        .reset-container h2 {
            color: #333;
            margin-bottom: 10px;
        }

        .reset-container p {
            color: #666;
            margin-bottom: 30px;
        }
        
        .form-group {
            margin-bottom: 20px;
            text-align: left;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }
        
        .password-container {
            position: relative;
        }
        
        .password-container input {
            width: 100%;
            padding: 15px;
            padding-right: 45px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s ease;
        }

        .password-container input:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #666;
            font-size: 18px;
        }
        
        .btn-reset {
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
            margin-bottom: 20px;
        }
        
        .btn-reset:hover {
            transform: translateY(-2px);
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

        .requirements {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            text-align: left;
            font-size: 14px;
            color: #666;
        }

        .requirements h4 {
            color: #333;
            margin-bottom: 8px;
        }
    </style>
</head>
<body>
    <div class="reset-container">
        <h2><i class="fas fa-lock"></i> Restablecer Contraseña</h2>
        <p>Crea una nueva contraseña para tu cuenta</p>
        
        <div class="requirements">
            <h4><i class="fas fa-shield-alt"></i> Requisitos de seguridad:</h4>
            <p>• Mínimo 6 caracteres</p>
            <p>• La contraseña debe coincidir en ambos campos</p>
        </div>
        
        <form method="POST">
            <div class="form-group">
                <label for="nueva_contraseña">Nueva Contraseña:</label>
                <div class="password-container">
                    <input type="password" id="nueva_contraseña" name="nueva_contraseña" placeholder="Ingresa nueva contraseña" required minlength="6">
                    <span class="password-toggle" onclick="togglePassword('nueva_contraseña', this)">
                        <i class="fas fa-eye"></i>
                    </span>
                </div>
            </div>
            
            <div class="form-group">
                <label for="confirmar_contraseña">Confirmar Contraseña:</label>
                <div class="password-container">
                    <input type="password" id="confirmar_contraseña" name="confirmar_contraseña" placeholder="Confirma tu contraseña" required minlength="6">
                    <span class="password-toggle" onclick="togglePassword('confirmar_contraseña', this)">
                        <i class="fas fa-eye"></i>
                    </span>
                </div>
            </div>
            
            <button type="submit" class="btn-reset">
                <i class="fas fa-save"></i> Restablecer Contraseña
            </button>
        </form>
        
        <a href="cerrar_sesion.php" class="back-link"><i class="fas fa-arrow-left"></i> Volver al Inicio de Sesión</a>
    </div>

    <script>
        function togglePassword(inputId, element) {
            const passwordInput = document.getElementById(inputId);
            const icon = element.querySelector('i');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.className = 'fas fa-eye-slash';
            } else {
                passwordInput.type = 'password';
                icon.className = 'fas fa-eye';
            }
        }
    </script>
</body>
</html>