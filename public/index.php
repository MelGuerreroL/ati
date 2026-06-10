<?php
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
        /* (mantengo tu estilo tal como lo enviaste) */
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:'Segoe UI',Tahoma, Geneva, Verdana, sans-serif;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);display:flex;justify-content:center;align-items:center;min-height:100vh}
        .login-container{background:#fff;padding:40px;border-radius:15px;box-shadow:0 15px 35px rgba(0,0,0,.2);width:100%;max-width:450px}
        .logo{text-align:center;margin-bottom:30px}
        .logo h1{color:#333;font-size:28px;margin-bottom:5px}
        .logo p{color:#666;font-size:14px}
        .form-group{margin-bottom:25px}
        .form-group label{display:block;margin-bottom:8px;font-weight:600;color:#333;font-size:14px}
        .form-group input{width:100%;padding:15px;border:2px solid #e1e5e9;border-radius:8px;font-size:16px;transition:border-color .3s}
        .form-group input:focus{outline:none;border-color:#667eea}
        .password-container{position:relative}
        .password-toggle{position:absolute;right:15px;top:50%;transform:translateY(-50%);cursor:pointer;user-select:none;color:#666;font-size:18px}
        .btn-login{width:100%;padding:15px;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:#fff;border:none;border-radius:8px;font-size:16px;font-weight:600;cursor:pointer;transition:all .3s;margin-bottom:20px}
        .btn-login:hover{transform:translateY(-2px);box-shadow:0 5px 15px rgba(0,0,0,.2)}
        .links-section{text-align:center;margin-top:25px;padding-top:25px;border-top:1px solid #e1e5e9}
        .forgot-password{display:block;color:#667eea;text-decoration:none;margin-bottom:20px;font-size:14px}
        .register-section{text-align:center}
        .btn-register{display:inline-block;padding:12px 25px;background:#28a745;color:#fff;text-decoration:none;border-radius:8px;font-weight:600;transition:all .3s}
        .btn-register:hover{background:#218838;transform:translateY(-2px)}
        .alert{padding:12px;background:#f8d7da;color:#721c24;border:1px solid #f5c6cb;border-radius:5px;margin-bottom:20px;text-align:center}
        .success-alert{background:#d4edda;color:#155724;border-color:#c3e6cb}
    </style>

<style>
  .error { color: red; }
  .valid { color: green; }
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
<body>
    <div class="login-container">
        <div class="logo">
            <h1>ATI</h1>
            <p>Ingresa a tu cuenta</p>
        </div>

        <?php if (isset($_GET['error'])): ?>
            <div class="alert"><?php echo htmlspecialchars($_GET['error']); ?></div>
        <?php endif; ?>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert success-alert"><?php echo htmlspecialchars($_GET['success']); ?></div>
        <?php endif; ?>

        <form action="../src/vistasdelprograma/login_usuario_be.php" method="POST" autocomplete="off" id="loginForm">
            <div class="form-group">
                <label for="correo">Correo Electrónico</label>
                <input type="text" id="correo" name="correo" placeholder="Ingresa tu correo electrónico" required
                 oninput="validarCorreo(this)">
                 <p id="mensaje"></p>

            </div>

            <div class="form-group">
                <label for="contraseña">Contraseña</label>
                <div class="password-container">
                    <input type="password" id="contraseña" name="contraseña" placeholder="Ingresa tu contraseña" required>
                    <span class="password-toggle" onclick="togglePassword()"><i class="fas fa-eye"></i></span>
                    <p id="mensajeContrasena">
                </div>
            </div>

            <button type="submit" class="btn-login">Entrar</button>
        </form>

        <div class="links-section">
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
    </script>

<script>
  const inputContrasena = document.getElementById('contraseña');
  const mensajeContrasena = document.getElementById('mensajeContrasena');

  inputContrasena.addEventListener('input', () => {
    if (/\s/.test(inputContrasena.value)) {
      mensajeContrasena.textContent = 'La contraseña no debe contener espacios en blanco';
      mensajeContrasena.style.color = '#a00';
    } else {
      mensajeContrasena.textContent = ' ';
      mensajeContrasena.style.color = '#0a0';
    }
  });
</script>


</body>
</html>
