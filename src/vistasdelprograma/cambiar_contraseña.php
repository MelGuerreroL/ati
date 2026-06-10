<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Si no hay sesión válida -> volver al login
if (empty($_SESSION['usuario']) || empty($_SESSION['usuario_id'])) {
    header('Location: ../../public/index.php?error=Debes iniciar sesión');
    exit;
}

// Conectar a la base de datos
require 'con_db.php';

// Asignar nombre a mostrar
$usuario_a_mostrar = !empty($_SESSION['usuario_nombre']) ? $_SESSION['usuario_nombre'] : $_SESSION['usuario'];
$rol_id = $_SESSION['rol_id'] ?? 0;

// Procesar el formulario
$mensaje = '';
$tipo_mensaje = ''; // success o error

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $contrasena_actual = $_POST['contrasena_actual'] ?? '';
    $nueva_contrasena = $_POST['nueva_contrasena'] ?? '';
    $confirmar_contrasena = $_POST['confirmar_contrasena'] ?? '';

    if (empty($contrasena_actual) || empty($nueva_contrasena) || empty($confirmar_contrasena)) {
        $mensaje = 'Todos los campos son requeridos';
        $tipo_mensaje = 'error';
    } elseif ($nueva_contrasena !== $confirmar_contrasena) {
        $mensaje = 'Las nuevas contraseñas no coinciden';
        $tipo_mensaje = 'error';
    } else {
        // Validar fortaleza
        if (strlen($nueva_contrasena) < 8 || strlen($nueva_contrasena) > 10) {
            $mensaje = 'La contraseña debe tener entre 8 y 10 caracteres';
            $tipo_mensaje = 'error';
        } elseif (!preg_match('/[a-z]/', $nueva_contrasena)) {
            $mensaje = 'Debe contener al menos 1 letra minúscula';
            $tipo_mensaje = 'error';
        } elseif (!preg_match('/[A-Z]/', $nueva_contrasena)) {
            $mensaje = 'Debe contener al menos 1 letra mayúscula';
            $tipo_mensaje = 'error';
        } elseif (!preg_match('/[0-9]/', $nueva_contrasena)) {
            $mensaje = 'Debe contener al menos 1 número';
            $tipo_mensaje = 'error';
        } elseif (!preg_match('/[!@#$%^&*()_+\-=\[\]{};\':"\\|,.<>\/?]/', $nueva_contrasena)) {
            $mensaje = 'Debe contener al menos 1 carácter especial';
            $tipo_mensaje = 'error';
        } elseif (preg_match('/\s/', $nueva_contrasena)) {
            $mensaje = 'No puede contener espacios';
            $tipo_mensaje = 'error';
        } else {
            try {
                // Verificar usuario
                $query = "SELECT ID_USUARIO, USUARIO, CONTRASEÑA, ESTADO_BLOQUEO 
                          FROM TBL_MS_USUARIO 
                          WHERE USUARIO = ? AND ID_USUARIO = ?";
                $stmt = $conexion->prepare($query);
                $stmt->bind_param("si", $_SESSION['usuario'], $_SESSION['usuario_id']);
                $stmt->execute();
                $result = $stmt->get_result();
                $usuarioData = $result->fetch_assoc();
                $stmt->close();

                if (!$usuarioData) {
                    $mensaje = 'Usuario no encontrado';
                    $tipo_mensaje = 'error';
                } elseif ($usuarioData['ESTADO_BLOQUEO'] !== 'ACTIVO') {
                    $mensaje = 'Usuario no activo. Contacte al administrador.';
                    $tipo_mensaje = 'error';
                } else {
                    // VERIFICAR CONTRASEÑA ACTUAL CON SHA-512
                    $hash_actual = hash('sha512', $contrasena_actual);
                    
                    if ($hash_actual !== $usuarioData['CONTRASEÑA']) {
                        $mensaje = 'La contraseña actual es incorrecta';
                        $tipo_mensaje = 'error';
                    } else {
                        // Verificar si la nueva contraseña ya fue usada (historial)
                        $hash_nueva = hash('sha512', $nueva_contrasena);
                        
                        // Verificar en el historial de contraseñas si existe
                        $queryHist = "SELECT * FROM TBL_MS_HIST_CONTRASEÑA 
                                      WHERE TBL_MS_USUARIO_ID_USUARIO = ? 
                                      AND CONTRASEÑA = ?";
                        $stmtHist = $conexion->prepare($queryHist);
                        $stmtHist->bind_param("is", $usuarioData['ID_USUARIO'], $hash_nueva);
                        $stmtHist->execute();
                        $resultadoHist = $stmtHist->get_result();

                        if ($resultadoHist->num_rows > 0) {
                            $mensaje = 'No puede reutilizar una contraseña anterior';
                            $tipo_mensaje = 'error';
                        } elseif ($hash_nueva === $usuarioData['CONTRASEÑA']) {
                            $mensaje = 'La nueva contraseña no puede ser igual a la actual';
                            $tipo_mensaje = 'error';
                        } else {
                            // SOLUCIÓN: Actualizar SOLO la contraseña sin campos de fecha
                            $update = "UPDATE TBL_MS_USUARIO 
                                       SET CONTRASEÑA = ? 
                                       WHERE ID_USUARIO = ?";
                            $stmtUpdate = $conexion->prepare($update);
                            $stmtUpdate->bind_param("si", $hash_nueva, $usuarioData['ID_USUARIO']);

                            if ($stmtUpdate->execute()) {
                                // Insertar en historial de contraseñas (si la tabla existe)
                                try {
                                    $insertHist = "INSERT INTO TBL_MS_HIST_CONTRASEÑA (CONTRASEÑA, TBL_MS_USUARIO_ID_USUARIO, FECHA_CREACION) 
                                                   VALUES (?, ?, NOW())";
                                    $stmtInsert = $conexion->prepare($insertHist);
                                    $stmtInsert->bind_param("si", $hash_nueva, $usuarioData['ID_USUARIO']);
                                    $stmtInsert->execute();
                                    $stmtInsert->close();
                                } catch (Exception $e) {
                                    // Si la tabla de historial no existe, continuar sin error
                                    error_log("Tabla de historial no encontrada, continuando...");
                                }

                                $mensaje = '✅ Contraseña cambiada exitosamente';
                                $tipo_mensaje = 'success';
                                $_POST = array(); // Limpiar el formulario
                            } else {
                                $mensaje = '❌ Error al actualizar la contraseña en la base de datos: ' . $stmtUpdate->error;
                                $tipo_mensaje = 'error';
                            }
                            $stmtUpdate->close();
                        }
                        $stmtHist->close();
                    }
                }
            } catch (Exception $e) {
                error_log("Error al cambiar contraseña: " . $e->getMessage());
                $mensaje = '❌ Error interno del servidor: ' . $e->getMessage();
                $tipo_mensaje = 'error';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Cambiar Contraseña - Sistema de Suscripciones</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css"/>
<style>
    *{margin:0;padding:0;box-sizing:border-box}
    body{font-family:'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);min-height:100vh;display:flex}
    .sidebar{background:rgba(25,118,210,0.95);width:250px;padding:20px;height:100vh;position:fixed;box-shadow:2px 0 10px rgba(0,0,0,.1)}
    .sidebar h2{color:#fff;text-align:center;margin-bottom:30px;padding-bottom:15px;border-bottom:2px solid rgba(255,255,255,.2)}
    .sidebar a{display:flex;align-items:center;color:#fff;text-decoration:none;padding:12px 15px;margin-bottom:10px;border-radius:8px;transition:all .3s}
    .sidebar a:hover{background:rgba(255,255,255,.2);transform:translateX(5px)}
    .sidebar a i{margin-right:10px;font-size:18px}
    .user-info{margin-top:30px;padding:15px;background:rgba(255,255,255,.1);border-radius:8px;color:#fff}
    .main-content{margin-left:250px;padding:30px;flex:1;color:#333}
    
    .password-form-container {
        background: #fff;
        padding: 40px;
        border-radius: 15px;
        box-shadow: 0 10px 30px rgba(0,0,0,.1);
        max-width: 500px;
        margin: 0 auto;
    }
    
    .password-form-container h1 {
        text-align: center;
        margin-bottom: 10px;
        color: #333;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
    }
    
    .password-form-container p {
        text-align: center;
        color: #666;
        margin-bottom: 30px;
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
    
    .password-toggle {
        position: relative;
    }
    
    .toggle-btn {
        position: absolute;
        right: 12px;
        top: 50%;
        transform: translateY(-50%);
        background: none;
        border: none;
        cursor: pointer;
        color: #666;
        padding: 5px;
    }
    
    .btn-change {
        width: 100%;
        padding: 15px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border: none;
        border-radius: 8px;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
    }
    
    .btn-change:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(0,0,0,0.2);
    }
    
    .btn-back {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        color: #667eea;
        text-decoration: none;
        font-weight: 600;
        transition: color 0.3s ease;
    }
    
    .btn-back:hover {
        color: #764ba2;
        text-decoration: underline;
    }
    
    .message {
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 25px;
        text-align: center;
        font-weight: 500;
    }
    
    .message.success {
        background: #d4edda;
        border: 1px solid #c3e6cb;
        color: #155724;
    }
    
    .message.error {
        background: #f8d7da;
        border: 1px solid #f5c6cb;
        color: #721c24;
    }
    
    .requirements {
        background: #f8f9fa;
        border: 1px solid #e9ecef;
        border-radius: 8px;
        padding: 15px;
        margin-top: 20px;
        font-size: 14px;
    }
    
    .requirements h4 {
        margin-bottom: 10px;
        color: #495057;
    }
    
    .requirements ul {
        padding-left: 20px;
        color: #666;
    }
    
    .requirements li {
        margin-bottom: 5px;
    }
    
    .user-display {
        background: #e7f3ff;
        border: 1px solid #b3d9ff;
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 20px;
        text-align: center;
    }
    
    .user-display strong {
        color: #0066cc;
    }
</style>
</head>
<body>
    <div class="sidebar">
        <h2><i class="fas fa-cogs"></i> Panel</h2>
        <a href="menuprincipal.php"><i class="fas fa-home"></i> Inicio</a>
        <a href="usuarios.php"><i class="fas fa-users"></i> Gestionar Usuarios</a>
        <a href="#"><i class="fas fa-receipt"></i> Suscripciones</a>
        <a href="#"><i class="fas fa-credit-card"></i> Pagos</a>
        <a href="#"><i class="fas fa-bell"></i> Notificaciones</a>
        <a href="#"><i class="fas fa-chart-bar"></i> Reportes</a>

        <div class="user-info">
            <p><strong>Usuario:</strong><br><?php echo htmlspecialchars($usuario_a_mostrar); ?></p>
            <form method="post" action="cerrar_sesion.php" style="margin-top:12px;text-align:center;">
                <button type="submit" style="background:rgba(255,255,255,0.2);border:none;color:white;padding:8px 10px;border-radius:6px;cursor:pointer;width:100%;">
                    <i class="fas fa-sign-out-alt"></i> Cerrar Sesión
                </button>
            </form>
        </div>
    </div>

    <div class="main-content">
        <div class="password-form-container">
            <h1><i class="fas fa-lock"></i> Cambiar Contraseña</h1>
            <p>Actualice su contraseña para mantener la seguridad de su cuenta</p>
            
            <!-- Mostrar mensajes -->
            <?php if ($mensaje): ?>
                <div class="message <?php echo $tipo_mensaje; ?>">
                    <?php echo htmlspecialchars($mensaje); ?>
                </div>
            <?php endif; ?>
            
            <div class="user-display">
                <strong>Usuario actual:</strong> <?php echo htmlspecialchars($_SESSION['usuario']); ?>
            </div>
            
            <form method="POST" action="">
                <div class="form-group password-toggle">
                    <label for="contrasena_actual"><i class="fas fa-key"></i> Contraseña Actual</label>
                    <input type="password" id="contrasena_actual" name="contrasena_actual" 
                           placeholder="Ingrese su contraseña actual" required
                           value="<?php echo htmlspecialchars($_POST['contrasena_actual'] ?? ''); ?>">
                    <button type="button" class="toggle-btn" onclick="togglePassword('contrasena_actual')">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>

                <div class="form-group password-toggle">
                    <label for="nueva_contrasena"><i class="fas fa-lock"></i> Nueva Contraseña</label>
                    <input type="password" id="nueva_contrasena" name="nueva_contrasena" 
                           placeholder="Ingrese nueva contraseña" required
                           minlength="8" maxlength="10"
                           value="<?php echo htmlspecialchars($_POST['nueva_contrasena'] ?? ''); ?>"
                           oninput="validarContrasenaEnTiempoReal(this.value)">
                    <button type="button" class="toggle-btn" onclick="togglePassword('nueva_contrasena')">
                        <i class="fas fa-eye"></i>
                    </button>
                    <div id="fortalezaContrasena" style="margin-top:5px;font-size:12px;"></div>
                </div>

                <div class="form-group password-toggle">
                    <label for="confirmar_contrasena"><i class="fas fa-lock"></i> Confirmar Nueva Contraseña</label>
                    <input type="password" id="confirmar_contrasena" name="confirmar_contrasena" 
                           placeholder="Confirme la nueva contraseña" required
                           minlength="8" maxlength="10"
                           value="<?php echo htmlspecialchars($_POST['confirmar_contrasena'] ?? ''); ?>"
                           oninput="validarCoincidenciaEnTiempoReal()">
                    <button type="button" class="toggle-btn" onclick="togglePassword('confirmar_contrasena')">
                        <i class="fas fa-eye"></i>
                    </button>
                    <div id="mensajeCoincidencia" style="margin-top:5px;font-size:12px;"></div>
                </div>

                <button type="submit" class="btn-change">
                    <i class="fas fa-sync-alt"></i> Cambiar Contraseña
                </button>
            </form>
            
            <div style="text-align:center;">
                <a href="menuprincipal.php" class="btn-back">
                    <i class="fas fa-arrow-left"></i> Volver al Menu Principal
                </a>
            </div>
            
            <div class="requirements">
                <h4><i class="fas fa-shield-alt"></i> Requisitos de Seguridad</h4>
                <ul>
                    <li>Mínimo 8 caracteres, máximo 10 caracteres</li>
                    <li>Al menos 1 letra minúscula y 1 mayúscula</li>
                    <li>Al menos 1 número y 1 carácter especial</li>
                    <li>No puede contener espacios</li>
                    <li>No puede ser igual a contraseñas anteriores</li>
                </ul>
            </div>
        </div>
    </div>

    <script>
        // Función para mostrar/ocultar contraseña
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const icon = input.parentNode.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.className = 'fas fa-eye-slash';
            } else {
                input.type = 'password';
                icon.className = 'fas fa-eye';
            }
        }

        // Validación en tiempo real de la contraseña
        function validarContrasenaEnTiempoReal(contrasena) {
            const fortalezaDiv = document.getElementById('fortalezaContrasena');
            
            if (!contrasena) {
                fortalezaDiv.textContent = '';
                return;
            }

            const tieneMinuscula = /[a-z]/.test(contrasena);
            const tieneMayuscula = /[A-Z]/.test(contrasena);
            const tieneNumero = /[0-9]/.test(contrasena);
            const tieneEspecial = /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(contrasena);
            const tieneEspacios = /\s/.test(contrasena);
            const longitudValida = contrasena.length >= 8 && contrasena.length <= 10;

            const todosCumplidos = tieneMinuscula && tieneMayuscula && tieneNumero && 
                                 tieneEspecial && !tieneEspacios && longitudValida;
            
            if (contrasena.length < 8) {
                fortalezaDiv.textContent = '❌ Débil (mínimo 8 caracteres)';
                fortalezaDiv.style.color = '#dc3545';
            } else if (!todosCumplidos) {
                fortalezaDiv.textContent = '⚠️ Media (cumple longitud básica)';
                fortalezaDiv.style.color = '#ffc107';
            } else {
                fortalezaDiv.textContent = '✅ Fuerte (cumple todos los requisitos)';
                fortalezaDiv.style.color = '#28a745';
            }
        }

        // Validar coincidencia en tiempo real
        function validarCoincidenciaEnTiempoReal() {
            const nuevaContrasena = document.getElementById('nueva_contrasena').value;
            const confirmarContrasena = document.getElementById('confirmar_contrasena').value;
            const mensajeDiv = document.getElementById('mensajeCoincidencia');
            
            if (!confirmarContrasena) {
                mensajeDiv.textContent = '';
                return;
            }
            
            if (nuevaContrasena === confirmarContrasena) {
                mensajeDiv.textContent = '✅ Las contraseñas coinciden';
                mensajeDiv.style.color = '#28a745';
            } else {
                mensajeDiv.textContent = '❌ Las contraseñas no coinciden';
                mensajeDiv.style.color = '#dc3545';
            }
        }
    </script>
</body>
</html>