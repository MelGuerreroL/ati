<?php
session_start();
require 'con_db.php';

// Si no hay sesión válida -> volver al login
if (empty($_SESSION['usuario']) || empty($_SESSION['usuario_id'])) {
    header('Location: ../../public/index.php?error=Debes iniciar sesión');
    exit;
}

// asignar nombre a mostrar
$usuario_a_mostrar = !empty($_SESSION['usuario_nombre']) ? $_SESSION['usuario_nombre'] : $_SESSION['usuario'];
$rol_id = $_SESSION['rol_id'] ?? 0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Panel de Control - Sistema de Suscripciones</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css"/>
<style>
    *{margin:0;padding:0;box-sizing:border-box}
    body{font-family:'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);min-height:100vh;display:flex;flex-direction:column}
    .sidebar{background:rgba(25,118,210,0.95);width:250px;padding:20px;height:100vh;position:fixed;top:0;left:0;box-shadow:2px 0 10px rgba(0,0,0,.1)}
    .sidebar h2{color:#fff;text-align:center;margin-bottom:30px;padding-bottom:15px;border-bottom:2px solid rgba(255,255,255,.2)}
    .sidebar a{display:flex;align-items:center;color:#fff;text-decoration:none;padding:12px 15px;margin-bottom:10px;border-radius:8px;transition:all .3s}
    .sidebar a:hover{background:rgba(255,255,255,.2);transform:translateX(5px)}
    .sidebar a i{margin-right:10px;font-size:18px}
    .main-container{margin-left:250px;width:calc(100% - 250px);display:flex;flex-direction:column;flex:1}
    .top-bar{background:rgba(255,255,255,0.95);padding:10px 30px;box-shadow:0 2px 5px rgba(0,0,0,.1);display:flex;justify-content:flex-end;align-items:center;position:sticky;top:0;z-index:1000}
    .main-content{padding:30px;flex:1;color:#333}
    .welcome-card{background:#fff;padding:30px;border-radius:15px;box-shadow:0 10px 30px rgba(0,0,0,.1);text-align:center}
    
    /* Dropdown Styles */
    .user-dropdown{position:relative;display:inline-block}
    .user-dropdown-btn{background:transparent;color:#333;border:none;padding:8px 12px;cursor:pointer;display:flex;align-items:center;font-size:16px;border-radius:20px;transition:background .3s}
    .user-dropdown-btn:hover{background:rgba(0,0,0,.05)}
    .user-dropdown-btn i{margin-right:8px;font-size:20px}
    .user-dropdown-content{display:none;position:absolute;right:0;background:#fff;min-width:280px;box-shadow:0 8px 16px rgba(0,0,0,.2);border-radius:8px;z-index:1;padding:15px;color:#333}
    .user-dropdown-content.show{display:block}
    .dropdown-header{text-align:center;border-bottom:1px solid #eee;padding-bottom:10px;margin-bottom:10px}
    .dropdown-header p{font-weight:bold;font-size:18px;margin:0}
    .dropdown-info{font-size:14px;background:#f8f9fa;padding:10px;border-radius:6px;margin-bottom:10px}
    .dropdown-info strong{display:block;margin-bottom:5px;color:#555}
    .profile-btn{background:rgba(25,118,210,.9);border:none;color:#fff;padding:10px 15px;border-radius:6px;cursor:pointer;margin-top:10px;width:100%;font-weight:600;transition:all .3s;display:flex;align-items:center;justify-content:center;gap:8px;text-decoration:none}
    .change-password-btn{background:rgba(255,193,7,.9);border:none;color:#333;padding:10px 15px;border-radius:6px;cursor:pointer;margin-top:10px;width:100%;font-weight:600;transition:all .3s;display:flex;align-items:center;justify-content:center;gap:8px;text-decoration:none}
    .change-password-btn:hover{background:rgba(255,193,7,1);transform:translateY(-2px);box-shadow:0 4px 8px rgba(0,0,0,.2)}
    .logout-btn{background:rgba(211,47,47,.9);border:none;color:white;padding:10px 15px;border-radius:6px;cursor:pointer;width:100%;margin-top:10px;font-weight:600;transition:all .3s}
    .logout-btn:hover{background:rgba(211,47,47,1)}
</style>
</head>
<body>
    <div class="sidebar">
        <h2><i class="fas fa-cogs"></i> Panel</h2>
        <a href="usuarios.php"><i class="fas fa-users"></i> Gestionar Usuarios</a>
        <a href="clientes.php"><i class="fas fa-user-tie"></i> Gestionar Clientes</a>
        <a href="valid.php"><i class="fas fa-receipt"></i> Suscripciones</a>
        <a href="#"><i class="fas fa-credit-card"></i> Pagos</a>
        <a href="#"><i class="fas fa-bell"></i> Notificaciones</a>
        <a href="#"><i class="fas fa-chart-bar"></i> Reportes</a>
    </div>

    <div class="main-container">
        <div class="top-bar">
            <div class="user-dropdown">
                <button id="userDropdownBtn" class="user-dropdown-btn">
                    <i class="fas fa-user-circle"></i>
                    <span><?php echo htmlspecialchars($usuario_a_mostrar); ?></span>
                </button>
                <div id="userDropdownContent" class="user-dropdown-content">
                    <div class="dropdown-header">
                        <p><?php echo htmlspecialchars($usuario_a_mostrar); ?></p>
                    </div>

                    <a href="perfil.php" class="profile-btn">
                        <i class="fas fa-user-cog"></i> Mi Perfil y Seguridad
                    </a>

                    <a href="cambiar_contraseña.php" class="change-password-btn">
                        <i class="fas fa-lock"></i> Cambiar Contraseña
                    </a>
                    
                    <form method="post" action="cerrar_sesion.php" style="margin:0;">
                        <button type="submit" class="logout-btn">
                            <i class="fas fa-sign-out-alt"></i> Cerrar Sesión
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="main-content">
            <div class="welcome-card">
                <h1>Gestión de Suscripciones</h1>
                <p>Bienvenido al sistema de gestión de suscripciones. Utilice el menú de la izquierda para navegar por las diferentes secciones.</p>
            </div>
        </div>
    </div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const dropdownBtn = document.getElementById('userDropdownBtn');
    const dropdownContent = document.getElementById('userDropdownContent');

    dropdownBtn.addEventListener('click', function(event) {
        event.stopPropagation();
        dropdownContent.classList.toggle('show');
    });

    window.addEventListener('click', function(event) {
        if (!dropdownBtn.contains(event.target) && !dropdownContent.contains(event.target)) {
            if (dropdownContent.classList.contains('show')) {
                dropdownContent.classList.remove('show');
            }
        }
    });
});
</script>
</body>
</html>