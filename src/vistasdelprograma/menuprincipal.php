<?php
session_start();
require 'con_db.php';
require_once __DIR__ . '/../config/errorlogs.php';// Ajusta la ruta según tu estructura
//require 'procedimiento.php'; //llenado automatico de tablas
//require 'envioautomatico.php'; //Envio automatico de correos

use App\config\errorlogs;
errorlogs::activa_error_logs();

if (empty($_SESSION['usuario']) || empty($_SESSION['usuario_id'])) {
    header('Location: ../../public/index.php?error=Debes iniciar sesión');
    exit;
}

$usuario_a_mostrar = !empty($_SESSION['usuario_nombre']) ? $_SESSION['usuario_nombre'] : $_SESSION['usuario'];
$rol_id = $_SESSION['rol_id'] ?? 0;

$planes = ['Mensual'=>0, 'Semestral'=>0, 'Anual'=>0];
if (isset($conexion)) {
    foreach ($planes as $plan => $count) {
        $stmt = $conexion->prepare(
            "SELECT COUNT(*) AS total
             FROM tbl_suscripcion s
             INNER JOIN tbl_plan p ON p.id_plan = s.id_plan
             INNER JOIN tbl_estado_suscripcion e ON e.id_estadoSuscripcion = s.id_estadoSuscripcion
             WHERE p.nombre_plan = ? AND e.nombre_estado <> 'CANCELADA'"
        );
        if ($stmt) {
            $stmt->bind_param('s', $plan);
            $stmt->execute();
            $res = $stmt->get_result();
            $row = $res ? $res->fetch_assoc() : null;
            $planes[$plan] = $row['total'] ?? 0;
            $stmt->close();
        } else {
            $planes[$plan] = 0;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Panel de Control - Sistema de Suscripciones</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css"/>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
*{margin:0;padding:0;box-sizing:border-box;}
body{
    font-family:'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background:#E6F0FA; /* Azul muy claro de fondo */
    min-height:100vh;
    display:flex;
    flex-direction:column;
}
.sidebar{
    background:#1E3A5F; /* Azul profundo elegante */
    color:#F0F4F8;
    width:250px;
    padding:20px;
    height:100vh;
    position:fixed;
    top:0;left:0;
    box-shadow:2px 0 10px rgba(0,0,0,.1);
    overflow-y:auto;
}
.panel-header{
    display:flex;
    align-items:center;
    justify-content:center;
    flex-direction:column;
    color:#F0F4F8;
    margin-bottom:30px;
    padding-bottom:15px;
    border-bottom:2px solid rgba(255,255,255,.2);
    text-align:center;
}
.panel-header .logo-panel{
    width:130px;
    height:130px;
    border-radius:20px;
    object-fit:cover;
    box-shadow:0 4px 10px rgba(0,0,0,0.3);
    margin-bottom:10px;
}
.panel-header span{
    font-size:26px;
    font-weight:bold;
}
.sidebar a{
    display:flex;align-items:center;
    color:#F0F4F8;text-decoration:none;
    padding:12px 15px;margin-bottom:10px;
    border-radius:8px;transition:all .3s;
}
.sidebar a:hover{
    background:rgba(255,255,255,.15);
    transform:translateX(5px);
}
.sidebar a i{margin-right:10px;font-size:18px;}
.submenu{margin-top:10px;}
.submenu .submenu-toggle{
    cursor:pointer;display:flex;align-items:center;
    color:#F0F4F8;padding:12px 15px;border-radius:8px;
    transition:all .3s;text-decoration:none;
}
.submenu .submenu-toggle:hover{
    background:rgba(255,255,255,.15);
    transform:translateX(5px);
}
.submenu-content{
    display:none;margin-left:25px;flex-direction:column;
}
.submenu-content a{
    background:rgba(255,255,255,0.1);
    margin:5px 0;color:#F0F4F8;
    padding:12px 15px;
    border-radius:8px;
    display:flex;
    align-items:center;
    text-decoration:none;
    transition:all .3s;
}
.submenu-content a i{
    margin-right:10px;
}
.submenu-content a:hover{
    background:rgba(255,255,255,0.25);
    transform:translateX(5px);
}

/* 🔹 Botón Restaurar BD */
.btn-restaurar_bd {
    display:flex;
    align-items:center;
    color:#F0F4F8;
    background:rgba(255,255,255,0.1);
    border:none;
    padding:12px 15px;
    border-radius:8px;
    cursor:pointer;
    font-size:16px;
    margin:5px 0;
    width:100%;
    text-align:left;
    transition:all .3s;
}
.btn-restaurar_bd i{
    margin-right:10px;
    font-size:18px;
}
.btn-restaurar_bd:hover{
    background:rgba(255,255,255,0.25);
    transform:translateX(5px);
}

.main-container{
    margin-left:250px;width:calc(100% - 250px);
    display:flex;flex-direction:column;flex:1;
}
.top-bar{
    background:#F8FAFC;
    padding:10px 30px;box-shadow:0 2px 5px rgba(0,0,0,.1);
    display:flex;justify-content:flex-end;align-items:center;
    position:sticky;top:0;z-index:1000;gap:20px;
}
.main-content{padding:30px;flex:1;color:#2E2E2E;}
.welcome-card{
    background:#fff;padding:30px;border-radius:15px;
    box-shadow:0 10px 30px rgba(0,0,0,.1);
    text-align:center;margin-bottom:30px;
}
.stats-card{
    background:#fff;padding:20px;border-radius:15px;
    box-shadow:0 10px 30px rgba(0,0,0,.1);
    margin-bottom:30px;
}
.dashboard-cards{
    display:flex;gap:20px;flex-wrap:wrap;margin-bottom:30px;
}
.dashboard-card{
    flex:1 1 calc(16.66% - 20px);
    min-width:150px;
    background:#fff;border-radius:15px;
    padding:20px;text-align:center;
    box-shadow:0 10px 30px rgba(0,0,0,.1);
    transition:all .3s;
}
.dashboard-card i{font-size:30px;margin-bottom:10px;color:#1E88E5;}
.dashboard-card h3{font-size:20px;margin-bottom:5px;}
.dashboard-card p{font-size:18px;color:#555;margin:0;}
.user-dropdown{position:relative;display:inline-block;}
.user-dropdown-btn{
    background:transparent;color:#333;border:none;
    padding:8px 12px;cursor:pointer;display:flex;align-items:center;
    font-size:16px;border-radius:20px;transition:background .3s;
}
.user-dropdown-btn:hover{background:rgba(0,0,0,.05);}
.user-dropdown-btn i{margin-right:8px;font-size:20px;}
.user-dropdown-content{
    display:none;position:absolute;right:0;background:#fff;
    min-width:260px;box-shadow:0 8px 16px rgba(0,0,0,.2);
    border-radius:8px;z-index:1;padding:15px;color:#333;
}
.user-dropdown-content.show{display:block;}
.dropdown-header{text-align:center;border-bottom:1px solid #eee;padding-bottom:10px;margin-bottom:10px;}
.dropdown-header p{font-weight:bold;font-size:18px;margin:0;}
.profile-btn{
    background:#1E88E5;border:none;color:#fff;
    padding:10px 15px;border-radius:6px;cursor:pointer;margin-top:10px;
    width:100%;font-weight:600;display:flex;align-items:center;justify-content:center;gap:8px;text-decoration:none;
}
.change-password-btn{
    background:#64B5F6;border:none;color:#fff;
    padding:10px 15px;border-radius:6px;cursor:pointer;margin-top:10px;
    width:100%;font-weight:600;display:flex;align-items:center;justify-content:center;gap:8px;text-decoration:none;
}
.change-password-btn:hover{
    background:#42A5F5;
    transform:translateY(-2px);box-shadow:0 4px 8px rgba(0,0,0,.2);
}
.logout-btn{
    background:#1565C0;border:none;color:white;
    padding:10px 15px;border-radius:6px;cursor:pointer;width:100%;
    margin-top:10px;font-weight:600;transition:all .3s;
}
.logout-btn:hover{background:#0D47A1;}

</style>
</head>
<body>
<div class="sidebar">
    <h2 class="panel-header">
        
    </h2>
    <a href="pre_usuarios.php"><i class="fas fa-users"></i> Gestionar Usuarios</a>
    <a href="pre_clientes.php"><i class="fas fa-user-tie"></i> Gestionar Clientes</a>
    <a href="pre_suscripciones.php"><i class="fas fa-receipt"></i> Suscripciones</a>
    <a href="pagos.php"><i class="fas fa-credit-card"></i> Pagos</a>
    
    <a href="pre_administracion.php"><i class="fas fa-users"></i> Administración</a>
<div class="submenu">
    <a href="#" class="submenu-toggle"><i class="fas fa-cog"></i> Configuración</a>
    <div class="submenu-content">
        <?php if ($rol_id === 1): // Solo superADMIN ?>
            <a href="backup_bd.php"><i class="fas fa-database"></i> Backup BD</a>
            <button id="btnrestaurar" class="btn-restaurar_bd"><i class="fas fa-upload"></i> Restaurar BD</button>
            <form id="formRestaurar" style="display:none;" enctype="multipart/form-data">
                <input type="file" name="sql_file" id="fileRestaurar" accept=".sql">
            </form>
        <?php else: ?>
            <p style="color:#fff; padding:10px;">No tienes permisos para esta acción</p>
        <?php endif; ?>
    </div>
</div>
</div>

<div class="main-container">
    <div class="top-bar">
        <div class="user-dropdown">
            <button id="userDropdownBtn" class="user-dropdown-btn">
                <i class="fas fa-user-circle"></i>
                <span><?php echo htmlspecialchars($usuario_a_mostrar); ?></span>
            </button>
            <div id="userDropdownContent" class="user-dropdown-content">
                <div class="dropdown-header"><p><?php echo htmlspecialchars($usuario_a_mostrar); ?></p></div>
                <a href="perfil.php" class="profile-btn"><i class="fas fa-user-cog"></i> Mi Perfil y Seguridad</a>
                <a href="cambiar_contraseña.php" class="change-password-btn"><i class="fas fa-lock"></i> Cambiar Contraseña</a>
                <form method="post" action="cerrar_sesion.php" style="margin:0;">
                    <button type="submit" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Cerrar Sesión</button>
                </form>
            </div>
        </div>
    </div>

    <div class="main-content">
        <div class="welcome-card">
            <h1>Estadísticas Suscripciones</h1>
            <p>Bienvenido al sistema de gestión de suscripciones de ATI. Aquí puedes ver la cantidad de suscriptores a antivirus .</p>
        </div>

        <div class="dashboard-cards">
            <?php foreach($planes as $plan => $count): ?>
            <div class="dashboard-card">
                <i class="fas fa-calendar-alt"></i>
                <h3><?php echo $plan; ?></h3>
                <p><?php echo $count; ?> suscripciones</p>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="stats-card">
            <h2>Distribución de Suscripciones</h2>
            <canvas id="planesChart" width="400" height="200"></canvas>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const submenuToggle = document.querySelector('.submenu-toggle');
    const submenuContent = document.querySelector('.submenu-content');
    submenuToggle.addEventListener('click', e => {
        e.preventDefault();
        submenuContent.style.display = submenuContent.style.display === 'flex' ? 'none' : 'flex';
    });

    const userBtn = document.getElementById('userDropdownBtn');
    const userContent = document.getElementById('userDropdownContent');
    userBtn.addEventListener('click', e => { e.stopPropagation(); userContent.classList.toggle('show'); });
    window.addEventListener('click', e => { if (!userBtn.contains(e.target) && !userContent.contains(e.target)) userContent.classList.remove('show'); });

    const ctx = document.getElementById('planesChart').getContext('2d');
    new Chart(ctx, {
        type:'bar',
        data:{
            labels: <?php echo json_encode(array_keys($planes)); ?>,
            datasets:[{
                label:'Suscripciones',
                data: <?php echo json_encode(array_values($planes)); ?>,
                backgroundColor:['rgba(255,99,132,0.7)','rgba(255,165,0,0.7)','rgba(255,177,40,0.7)','rgba(255,189,80,0.7)','rgba(255,201,120,0.7)','rgba(255,213,160,0.7)'],
                borderColor:['rgba(255,99,132,1)','rgba(255,165,0,1)','rgba(255,177,40,1)','rgba(255,189,80,1)','rgba(255,201,120,1)','rgba(255,213,160,1)'],
                borderWidth:1
            }]
        },
        options:{responsive:true,plugins:{legend:{display:false},title:{display:true,text:'Suscripciones por Plan'}},scales:{y:{beginAtZero:true,precision:0}}}
    });

    const btnRestaurar = document.getElementById('btnrestaurar');
    const fileRestaurar = document.getElementById('fileRestaurar');
    const formRestaurar = document.getElementById('formRestaurar');

    btnRestaurar.addEventListener('click', () => fileRestaurar.click());
    fileRestaurar.addEventListener('change', () => {
        if(fileRestaurar.files.length > 0){
            const formData = new FormData(formRestaurar);
            fetch('restaurar_bd.php',{method:'POST',body:formData})
            .then(res => res.json())
            .then(data => { alert(data.message); if(data.status==='success'||data.status==='warning'){location.reload();} })
            .catch(err=>alert('Error al restaurar BD: '+err.message));
        }
    });
});
</script>
</body>
</html>
