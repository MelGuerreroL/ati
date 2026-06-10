<?php
require_once __DIR__ . '/../src/config/errorlogs.php'; // Ajusta la ruta si tu archivo está en otra carpeta
use App\config\errorlogs;

// Activar logs de errores globalmente
errorlogs::activa_error_logs();

session_start();
if (isset($_SESSION['usuario'])) {
    header('Location: ../src/vistasdelprograma/menuprincipal.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Iniciar Sesión - Sistema de Suscripciones</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css"/>

    <style>
        :root {
            --color-principal: #ebb758ff;
            --color-secundario: #FFE5B4;
            --color-panel: #FFF9F3;
            --color-texto: #2E2E2E;
            --color-boton: #FFA500;
            --color-boton-hover: #FF8C00;
        }

        body.login-page {
            background: var(--color-secundario);
            font-family: 'Poppins', sans-serif;
            color: var(--color-texto);
            height: 100vh;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* ======== CONTENEDOR TIPO LIBRO ======== */
        .login-book {
            display: flex;
            width: 850px;
            height: 580px;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 8px 25px rgba(0,0,0,0.3);
            background: var(--color-panel);
        }

        /* ======== LADO IZQUIERDO ======== */
        .left-side {
            background: var(--color-principal);
            color: white;
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 30px;
        }

        .left-side img {
            width: 350px; /* ← Aumentado el tamaño del logo */
            height: auto;
            margin-bottom: 25px;
        }

        .left-side h1 {
            font-size: 34px;
            margin-bottom: 10px;
        }

        .left-side p {
            font-size: 18px;
            opacity: 0.9;
        }

        /* ======== LADO DERECHO (FORMULARIO) ======== */
        .right-side {
            flex: 1;
            padding: 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            background-color: var(--color-panel);
        }

        .right-side h2 {
            color: var(--color-principal);
            font-size: 26px;
            margin-bottom: 20px;
            text-align: center;
        }

        .form-group label {
            display: block;
            text-align: left;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .form-group input {
            width: 100%;
            padding: 12px;
            border: 1px solid #E0E0E0;
            border-radius: 10px;
            margin-bottom: 12px;
            background-color: #FFF3E0;
            color: var(--color-texto);
        }

        .form-group input::placeholder {
            color: #666666;
        }



        .valid { color: #28A745; }
        .error { color: #DC3545; }

        .btn-login {
            background-color: var(--color-boton);
            color: #fff;
            font-weight: 600;
            border: none;
            border-radius: 10px;
            padding: 12px;
            width: 100%;
            cursor: pointer;
            transition: 0.3s;
        }

        .btn-login:hover {
            background-color: var(--color-boton-hover);
            transform: scale(1.03);
        }

        .forgot-password {
            color: var(--color-texto);
            text-decoration: none;
            font-size: 14px;
            display: inline-block;
            margin-top: 12px;
        }

        .forgot-password:hover {
            color: var(--color-principal);
        }

        .register-section {
            margin-top: 20px;
            text-align: center;
        }

        .register-section h3 {
            font-size: 15px;
            color: var(--color-texto);
        }

        .btn-register {
            display: inline-block;
            margin-top: 8px;
            background-color: #FFF3E0;
            color: var(--color-texto);
            padding: 10px 20px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-register:hover {
            background-color: var(--color-boton);
            color: #fff;
        }

        .alert {
            background-color: #F8D7DA;
            color: #721C24;
            padding: 10px;
            border-radius: 10px;
            margin-bottom: 15px;
            animation: slideDown 0.3s ease-out;
        }

        .success-alert {
            background-color: #D4EDDA;
            color: #155724;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        #mensaje {
            font-size: 13px;
            margin-bottom: 15px;
            height: 18px;
            transition: all 0.3s ease;
        }

        .password-container {
            position: relative;
        }
        
        /* Ocultar iconos por defecto del navegador */
        .password-container input::-ms-reveal,
        .password-container input::-ms-clear {
            display: none;
        }
        
        .password-container input::-webkit-credentials-auto-fill-button,
        .password-container input::-webkit-strong-password-auto-fill-button {
            display: none !important;
        }

        .password-toggle {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #666666;
        }

        .password-toggle:hover {
            color: var(--color-principal);
        }
    </style>

    <script>
      function validarCorreo(input) {
        const mensaje = document.getElementById("mensaje");
        const valor = input.value.toLowerCase();
        input.value = valor;

        const regex = /^[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$/;

        if (valor.length < 4) {
          mensaje.textContent = "";
          mensaje.className = "";
        } else if (regex.test(valor)) {
          mensaje.textContent = "Correo válido";
          mensaje.className = "valid";
        } else {
          mensaje.textContent = "Correo inválido. Solo minúsculas y símbolos permitidos";
          mensaje.className = "error";
        }
      }
    </script>
</head>

<body class="login-page">
    <div class="login-book">
        <!-- LADO IZQUIERDO -->
        <div class="left-side">
            <img src="../assets/img/logo_empresa.png" alt="Logo Empresa">
        </div>

        <!-- LADO DERECHO -->
        <div class="right-side">
            <h2>Iniciar Sesión</h2>

            <?php 
            // Manejar errores tanto de GET como de SESSION
            $error_message = '';
            if (isset($_GET['error'])) {
                $error_message = $_GET['error'];
            } elseif (isset($_SESSION['login_error'])) {
                $error_message = $_SESSION['login_error'];
                unset($_SESSION['login_error']); // Limpiar el error de la sesión
            }
            
            if (!empty($error_message)): ?>
                <div class="alert"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>

            <?php if (isset($_GET['success'])): ?>
                <div class="alert success-alert"><?php echo htmlspecialchars($_GET['success']); ?></div>
            <?php endif; ?>

            <form action="../src/vistasdelprograma/login_usuario_be.php" method="POST" autocomplete="off" id="loginForm">
                <div class="form-group">
                    <label for="correo">Correo Electrónico</label>
                    <input type="text" id="correo" name="correo" placeholder="Ingresa tu correo electrónico" required oninput="validarCorreo(this)">
                    <p id="mensaje"></p>
                </div>

                <div class="form-group">
                    <label for="contraseña">Contraseña</label>
                    <div class="password-container">
                        <input type="password" id="contraseña" name="contraseña" placeholder="Ingresa tu contraseña" required>
                        <span class="password-toggle" onclick="togglePassword()"><i class="fas fa-eye"></i></span>
                        <p id="mensajeContrasena"></p>
                    </div>
                </div>

                <button type="submit" class="btn-login">Entrar</button>
            </form>

            <a href="../src/vistasdelprograma/recuperar_contraseña.php" class="forgot-password">¿Olvidaste tu contraseña?</a>

            <div class="register-section">
                <h3>¿Aún no tienes una cuenta?</h3>
                <a href="../src/vistasdelprograma/registro.php" class="btn-register">Registrarse</a>
            </div>
        </div>
    </div>

    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('contraseña');
            const toggleIcon = document.querySelector('.password-toggle i');
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.className = 'fas fa-eye-slash';
            } else {
                passwordInput.type = 'password';
                toggleIcon.className = 'fas fa-eye';
            }
        }

        // limpiar params tras 5s
        if (window.location.search.includes('error=') || window.location.search.includes('success=')) {
            setTimeout(()=>{window.history.replaceState({}, document.title, window.location.pathname);}, 5000);
        }

        const inputContrasena = document.getElementById('contraseña');
        const mensajeContrasena = document.getElementById('mensajeContrasena');

        inputContrasena.addEventListener('input', () => {
            if (/\s/.test(inputContrasena.value)) {
                mensajeContrasena.textContent = 'La contraseña no debe contener espacios en blanco';
                mensajeContrasena.style.color = '#a00';
            } else {
                mensajeContrasena.textContent = '';
            }
        });
    </script>
</body>
</html>
