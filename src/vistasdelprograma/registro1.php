<?php
require_once __DIR__ . '/../config/errorlogs.php';// Ajusta la ruta según tu estructura

use App\config\errorlogs;
errorlogs::activa_error_logs();
session_start();
if(isset($_SESSION['usuario'])) {
    header('Location: menuprincipal.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro - Sistema de Suscripciones</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #FFE5B4;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        
        .register-container {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.2);
            width: 100%;
            max-width: 500px;
        }
        
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }

        .logo h1 {
            color: #333;
            font-size: 28px;
            margin-bottom: 5px;
        }

        .logo p {
            color: #666;
            font-size: 14px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
            font-size: 14px;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus {
            outline: none;
            border-color: #FFAA66;
            box-shadow: 0 0 0 3px rgba(255, 170, 102, 0.2);
        }
        
        .password-container {
            position: relative;
            display: flex;
            flex-direction: column;
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
        
        .password-container input {
            padding-right: 50px !important;
        }
        
        .password-toggle {
            position: absolute;
            right: 15px;
            top: 12px;
            cursor: pointer;
            user-select: none;
            color: #FF8833;
            font-size: 16px;
            width: 25px;
            height: 25px;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10;
            border-radius: 4px;
            transition: all 0.3s ease;
        }

        .password-toggle:hover {
            color: #FFAA66;
            background-color: rgba(255, 170, 102, 0.1);
        }
        
        .password-container small {
            margin-top: 8px;
            font-size: 12px;
            line-height: 1.3;
            min-height: 16px;
        }
        
        .btn-register {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #FFAA66 0%, #FF8833 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-bottom: 20px;
            box-shadow: 0 4px 8px rgba(255, 170, 102, 0.3);
        }
        
        .btn-register:hover {
            background: linear-gradient(135deg, #FF9944 0%, #FF7722 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 18px rgba(255, 136, 51, 0.4);
        }

        .back-link {
            display: inline-block;
            color: #FF8833;
            text-decoration: none;
            font-weight: 600;
            text-align: center;
            width: 100%;
            transition: color 0.3s ease;
        }

        .back-link:hover {
            color: #FF7722;
            text-decoration: underline;
        }

        .alert {
            padding: 12px;
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
        }

        .alert-success {
            background: #FFF8E7;
            color: #FF8833;
            border-color: #FFAA66;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .info-box {
            background: #FFF8E7;
            border: 1px solid #FFAA66;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            text-align: left;
        }

        .info-box h4 {
            color: #FF8833;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .info-box p {
            color: #666;
            margin-bottom: 0;
            font-size: 12px;
            line-height: 1.4;
        }

        @media (max-width: 600px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

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
      mensaje.style.color = '#0a0';
      mensaje.className = "valid";
    } else {
      mensaje.textContent = "Correo inválido. Solo minúsculas y símbolos permitidos";
      mensaje.style.color = '#a00';
      mensaje.className = "error";
    }
  }
</script>


<body>
    <div class="register-container">
        <div class="logo">
            <h1>Crear Cuenta</h1>
            <p>Regístrate en el sistema</p>
        </div>
        
        <?php if(isset($_GET['error'])): ?>
            <div class="alert">
                <?php echo htmlspecialchars($_GET['error']); ?>
            </div>
        <?php endif; ?>

        <?php if(isset($_GET['success'])): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($_GET['success']); ?>
            </div>
        <?php endif; ?>

        <div class="info-box">
            <h4><i class="fas fa-info-circle"></i> Verificación Requerida</h4>
            <p>• Después de registrarte, recibirás un código de 6 dígitos por correo</p>
            <p>• Debes ingresar el código para activar tu cuenta</p>
            <p>• El código expira en 10 minutos</p>
        </div>
        
        <form action="registro_usuario_be.php" method="POST" id="registerForm">
            <div class="form-group">
                <label for="nombre_completo">Nombre Completo</label>
                <!-- Solo letras mayúsculas A-Z y un único espacio entre palabras -->
                <input type="text" id="nombre_completo" name="nombre_completo" placeholder="JUAN PEREZ" required
                       pattern="[A-Z]+(?: [A-Z]+)*" title="Solo letras mayúsculas y un solo espacio entre palabras (ej: JUAN PEREZ)">
            </div>
            
            <div class="form-group">
                <label for="correo">Correo Electrónico</label>
                <input type="email" id="correo" name="correo" placeholder="Ingresa tu correo electrónico" required
                 oninput="validarCorreo(this)">
                 <p id="mensaje"></p>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="contraseña">Contraseña</label>
                    <div class="password-container">
                        <input type="password" id="contraseña" name="contraseña" placeholder="Crea una contraseña" required minlength="8" autocomplete="new-password">
                        <span class="password-toggle" onclick="togglePassword('contraseña','toggleIcon1')">
                            <i id="toggleIcon1" class="fas fa-eye"></i>
                        </span>
                        <small id="mensajeError" style="color: red;"></small>
                    </div>
                </div>

                <div class="form-group">
                    <label for="confirmar_contraseña">Confirmar Contraseña</label>
                    <div class="password-container">
                        <input type="password" id="confirmar_contraseña" name="confirmar_contraseña" placeholder="Confirma tu contraseña" required minlength="8" autocomplete="new-password">
                        <span class="password-toggle" onclick="togglePassword('confirmar_contraseña','toggleIcon2')">
                           <i id="toggleIcon2" class="fas fa-eye"></i>                          
                        </span>
                        <small id="mensajeComparacion" style="color: red;"></small>
                    </div>
                </div>
            </div>
            
            <button type="submit" class="btn-register" id="submitBtn">
                <i class="fas fa-user-plus"></i> Registrarse y Verificar
            </button>
        </form>
        
        <a href="../../public/index.php" class="back-link">← Volver al Inicio de Sesión</a>
    </div>

    <script>
        function togglePassword(inputId, iconId) {
            const input = document.getElementById(inputId);
            const icon = document.getElementById(iconId);
            if (!input) return;
            if (input.type === 'password') {
                input.type = 'text';
                if (icon) icon.className = 'fas fa-eye-slash';
            } else {
                input.type = 'password';
                if (icon) icon.className = 'fas fa-eye';
            }
        }

        // Validación cliente antes de enviar
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const btn = document.getElementById('submitBtn');
            const nombre = document.getElementById('nombre_completo').value.trim();
            const correo = document.getElementById('correo').value.trim();
            const pass = document.getElementById('contraseña').value;
            const pass2 = document.getElementById('confirmar_contraseña').value;

            // Limpiar alertas previas
            const oldAlert = document.querySelector('.alert');
            if (oldAlert) oldAlert.remove();

            // Validaciones simples
            // Validar nombre: solo mayúsculas A-Z y un solo espacio entre palabras
            const nombreRegex = /^[A-Z]+(?: [A-Z]+)*$/;
            if (nombre.length === 0) {
                e.preventDefault();
                showError('El nombre completo es obligatorio');
                return;
            }

            if (!nombreRegex.test(nombre)) {
                e.preventDefault();
                showError('El nombre debe contener solo letras mayúsculas y un único espacio entre palabras');
                return;
            }

            if (!validateEmail(correo)) {
                e.preventDefault();
                showError('Introduce un correo electrónico válido');
                return;
            }

            if (pass.length < 8) {
                e.preventDefault();
                showError('La contraseña debe tener al menos 8 caracteres');
                return;
            }

            if (pass !== pass2) {
                e.preventDefault();
                showError('Las contraseñas no coinciden');
                return;
            }

            // Si pasa validaciones, deshabilitar botón y mostrar spinner
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Procesando...';
        });

        function showError(msg) {
            const container = document.querySelector('.register-container');
            const div = document.createElement('div');
            div.className = 'alert';
            div.textContent = msg;
            container.insertBefore(div, container.children[2]);
        }

        function validateEmail(email) {
            // Simple email regex
            const re = /^[^@\s]+@[^@\s]+\.[^@\s]+$/;
            return re.test(email);
        }

        // Forzar mayúsculas y colapsar múltiples espacios en el campo Nombre Completo
        const nombreInput = document.getElementById('nombre_completo');
        if (nombreInput) {
            nombreInput.addEventListener('input', function (e) {
                // Eliminar cualquier caracter que no sea letra (A-Z,a-z) ni espacio
                let v = this.value.replace(/[^a-zA-Z\s]/g, '');
                // Reemplazar múltiples espacios por uno solo
                v = v.replace(/\s+/g, ' ');
                // Pasar a mayúsculas
                v = v.toUpperCase();
                this.value = v;
            });
        }
    </script>

    <script>
document.getElementById("contraseña").addEventListener("input", function () {
  const contrasena = this.value;
  const mensajeError = document.getElementById("mensajeError");

  const requisitos = [
    { regex: /^.{8,15}$/, mensaje: "Debe tener minimo 8 caracteres," },
    { regex: /[a-z]/, mensaje: "Debe contener al menos una letra minúscula.   " },
    { regex: /[A-Z]/, mensaje: "Debe contener al menos una letra mayúscula." },
    { regex: /[0-9]/, mensaje: "Debe contener al menos un número." },
    { regex: /[^A-Za-z0-9]/, mensaje: "Debe contener al menos un carácter especial." },
    { regex: /^\S*$/, mensaje: "No puede contener espacios." }
  ];

  const errores = requisitos
    .filter(req => !req.regex.test(contrasena))
    .map(req => req.mensaje);

  if (errores.length > 0) {
    mensajeError.textContent = errores.join(" ");
    this.setCustomValidity("Contraseña inválida");
  } else {
    mensajeError.textContent = "";
    this.setCustomValidity("");
  }
});

</script>


<script>
document.addEventListener("DOMContentLoaded", function () {
    const campoOriginal = document.getElementById("contraseña");
    const campoConfirmacion = document.getElementById("confirmar_contraseña");
    const mensajeComparacion = document.getElementById("mensajeComparacion");

    function compararContraseñas() {
        if (campoConfirmacion.value === "") {
            mensajeComparacion.textContent = "";
            return;
        }

        if (campoOriginal.value === campoConfirmacion.value) {
            mensajeComparacion.textContent = "✔ Las contraseñas coinciden";
            mensajeComparacion.style.color = "green";
        } else {
            mensajeComparacion.textContent = "✖ Las contraseñas no coinciden";
            mensajeComparacion.style.color = "red";
        }
    }

    campoOriginal.addEventListener("input", compararContraseñas);
    campoConfirmacion.addEventListener("input", compararContraseñas);
});
</script>

</body>
</html>