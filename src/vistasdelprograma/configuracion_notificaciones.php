<?php
session_start();
require 'con_db.php';
require_once __DIR__ . '/../config/errorlogs.php';// Ajusta la ruta según tu estructura

use App\config\errorlogs;
errorlogs::activa_error_logs();

ob_start();
// Verificar sesión
if (empty($_SESSION['usuario']) || empty($_SESSION['usuario_id'])) {
    header('Location: ../../public/index.php?error=Debes iniciar sesión');
    exit;
}
 
$mensaje = '';
$modo_edicion = false;
$cliente_a_editar = null;


// Obtener la lista de clientes para mostrar en la tabla tipos de notificacion
$query_clientes = "

    SELECT c.id_tipoNotificacion, c.nombre_tipoNotificacion, c.periodicidad, c.id_plan, c.id_configuracion, c.creado_en
    FROM tbl_tipo_notificacion c
    ORDER BY c.id_tipoNotificacion DESC";


$result_clientes = $conexion->query($query_clientes);

// Obtener la lista de clientes para mostrar en la tabla configuracion de noftificaciones
$query_confign = "

    SELECT c.id_configuracion, c.nombre_configuracion, c.parametros, c.creado_en
    FROM tbl_configuracion_notificaciones c
    ORDER BY c.id_configuracion DESC";


$result_confign = $conexion->query($query_confign);


$accion = $_POST['accion'] ?? '';
// Reemplazado para enrutar según "tabla" y soportar la segunda tabla con campos (nombre_configuracion, parametros)
if ($accion === 'insertar') {
    // Limpiar cualquier salida previa que pueda contener HTML/err (evita "Unexpected token <")
    if (ob_get_length()) { ob_clean(); }

    header('Content-Type: application/json');

    $tabla = $_POST['tabla'] ?? 'tipo_notificacion';

    if ($tabla === 'configuracion') {
        // Campos específicos para la segunda tabla
        $nombre_conf = trim($_POST['nombre_configuracion'] ?? '');
        $parametros = trim($_POST['parametros'] ?? '');

        if ($nombre_conf === '' ) {
            echo json_encode(['success' => false, 'error' => 'El nombre de configuración es obligatorio.']);
            exit;
        }

        // Verificar existencia (ajusta el nombre de la tabla/columna si tu BD usa otro)
        $stmt_check = $conexion->prepare("SELECT id_configuracion FROM tbl_configuracion_notificaciones WHERE nombre_configuracion = ?");
        if (!$stmt_check) {
            echo json_encode(['success' => false, 'error' => 'Error DB (prepare): ' . $conexion->error]);
            exit;
        }
        $stmt_check->bind_param('s', $nombre_conf);
        $stmt_check->execute();
        $stmt_check->store_result();
        if ($stmt_check->num_rows > 0) {
            echo json_encode(['success' => false, 'error' => 'Ya existe una configuración con ese nombre.']);
            $stmt_check->close();
            exit;
        }
        $stmt_check->close();

        // Insertar (ajusta nombre de tabla y columnas según tu esquema real)
        $stmt = $conexion->prepare("INSERT INTO tbl_configuracion_notificaciones (nombre_configuracion, parametros, creado_en) VALUES (?, ?, NOW())");
        if (!$stmt) {
            echo json_encode(['success' => false, 'error' => 'Error DB (prepare insert): ' . $conexion->error]);
            exit;
        }
        $stmt->bind_param('ss', $nombre_conf, $parametros);
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Error al registrar configuración: ' . $stmt->error]);
        }
        $stmt->close();
        exit;
    }

    // tipo_notificacion 
    $nombre = trim($_POST['nombre_tipoNotificacion'] ?? '');
    $periodicidad = trim($_POST['periodicidad'] ?? '');
    $id_plan_raw = $_POST['id_plan'] ?? null;
    $id_configuracion = trim($_POST['id_configuracion'] ?? '');

    if ($nombre === '' || $periodicidad === '' || $id_configuracion === '') {
        echo json_encode(['success' => false, 'error' => 'Campos obligatorios vacíos']);
        exit;
    }

    // Normalizar valores numéricos
    $periodicidad_int = is_numeric($periodicidad) ? intval($periodicidad) : 0;
    $id_plan_int = ($id_plan_raw === null || $id_plan_raw === '') ? null : (is_numeric($id_plan_raw) ? intval($id_plan_raw) : null);
    $id_configuracion_int = is_numeric($id_configuracion) ? intval($id_configuracion) : 0;

    // Verificar existencia
    $stmt_check = $conexion->prepare("SELECT id_tipoNotificacion FROM tbl_tipo_notificacion WHERE nombre_tipoNotificacion = ?");
    $stmt_check->bind_param('s', $nombre);
    $stmt_check->execute();
    $stmt_check->store_result();

    if ($stmt_check->num_rows > 0) {
        echo json_encode(['success' => false, 'error' => 'Ya existe una notificación con ese nombre.']);
        $stmt_check->close();
        exit;
    }
    $stmt_check->close();

    // Preparar insert según si id_plan es NULL o no
    if ($id_plan_int === null) {
        $stmt = $conexion->prepare("INSERT INTO tbl_tipo_notificacion (nombre_tipoNotificacion, periodicidad, id_plan, id_configuracion, creado_en) VALUES (?, ?, NULL, ?, NOW())");
        $stmt->bind_param('sii', $nombre, $periodicidad_int, $id_configuracion_int);
    } else {
        $stmt = $conexion->prepare("INSERT INTO tbl_tipo_notificacion (nombre_tipoNotificacion, periodicidad, id_plan, id_configuracion, creado_en) VALUES (?, ?, ?, ?, NOW())");
        $stmt->bind_param('siii', $nombre, $periodicidad_int, $id_plan_int, $id_configuracion_int);
    }

    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Error al registrar: ' . $stmt->error]);
    }
    $stmt->close();
    exit;
}




?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Gestión de Clientes</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css"/>
<style>
    :root {
        --orange-primary: #f0ad4e;
        --orange-dark: #ec971f;
        --orange-light: #f8c986;
        --text-dark: #333;
        --text-light: #fff;
        --background-light: #f9f9f9;
        --border-color: #ddd;
        --status-active: #28a745;
        --status-inactive: #dc3545;
    }
    body{font-family:'Segoe UI', sans-serif;background:var(--background-light);margin:0;padding:20px;color:var(--text-dark);}
    .page-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;}
    .page-header h1{color:var(--text-dark);font-size:2em;display:flex;align-items:center;gap:10px;}
    .container{background:var(--text-light);padding:25px;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,.08);margin-bottom:20px;border-top:4px solid var(--orange-primary);}
    .page-layout{display:flex;gap:20px;}
    .left-column{flex:1;min-width:320px;}
    .right-column{flex:2;}
    table{width:100%;border-collapse:collapse;margin-top:12px;}
    th,td{padding:12px 15px;border-bottom:1px solid var(--border-color);text-align:left;vertical-align:middle;}
    th{background:var(--orange-dark);color:var(--text-light);text-transform:uppercase;font-size:0.85em;letter-spacing:0.5px;}
    input, textarea, select, button{font-family:inherit;font-size:1rem;}
    input, textarea, select{padding:10px;border-radius:5px;border:1px solid #ccc;transition:all .3s ease;box-sizing:border-box;width:100%;}
    input:focus, textarea:focus, select:focus{border-color:var(--orange-primary);box-shadow:0 0 0 3px rgba(240,173,78,.25);}
    .button{background:var(--orange-primary);color:var(--text-light);border:none;cursor:pointer;font-weight:600;padding:10px 15px;border-radius:5px;}
    .button:hover{background:var(--orange-dark);transform:translateY(-1px);}
    .icon-button{background:none;border:none;cursor:pointer;font-size:1.2em;margin:0 4px;padding:5px;border-radius:50%;transition:background .3s;}
    .icon-button:hover{background:rgba(0,0,0,0.1);}
    .icon-button.green{color:var(--status-active);}
    .icon-button.red{color:var(--status-inactive);}
    .icon-button.orange{color:var(--orange-dark);}
    .back-button{background:transparent;color:var(--text-dark);border:none;text-decoration:none;display:inline-flex;align-items:center;gap:8px;margin-bottom:15px;font-weight:600;}
    .back-button:hover{color:var(--orange-dark);}
    .form-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:15px;}
    .form-group{display:flex;flex-direction:column;gap:5px;}
    .form-group label{font-weight:600;}
    .full-width{grid-column:1 / -1;}
    .mensaje{padding:15px;border-radius:5px;margin: 20px 0;font-weight:bold;}
    .exito{background-color:#d4edda;color:#155724;border:1px solid #c3e6cb;}
    .error{background-color:#f8d7da;color:#721c24;border:1px solid #f5c6cb;}
    .status-badge{padding:4px 10px;border-radius:15px;font-weight:bold;color:var(--text-light);font-size:0.85em;text-transform:uppercase;}
    .status-activo{background-color:var(--status-active);}
    .status-inactivo{background-color:var(--status-inactive);}
    .form-error { color: #dc3545; font-size: 0.85em; margin-top: 5px; }
    @media (max-width: 992px) { .page-layout { flex-direction: column; } }
        /* ... estilos originales ... */
    .modal-overlay {
        position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(0,0,0,0.5); display: none; justify-content: center; align-items: center; z-index: 1000;
    }
    .modal {
        background: #fff; padding: 25px; border-radius: 8px; width: 90%; max-width: 600px;
        box-shadow: 0 4px 12px rgba(0,0,0,.2); position: relative;
    }
    .modal h3 { margin-top: 0; }
    .modal-buttons { display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px; }
</style>
</head>
<body>

<div class="page-header">
    <h1><i class="fas fa-edit"></i> Configuración de notificaciones</h1>
    <a class="back-button" href="menuprincipal.php"><i class="fas fa-arrow-left"></i> Volver al Menú</a>
</div>

<div class="right-column">
    <div class="container">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <h2><i class="fas fa-bell"></i> Tipos de notificaciones</h2>
            <button class="button" onclick="abrirModal('tipo_notificacion')"><i class="fas fa-plus-circle"></i> Agregar</button>
        </div>

       <?php if($mensaje) { echo $mensaje; } ?>



            <div style="overflow-x:auto;">
                <table id="tablaClientes">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>NOTIFICACION</th>
                            <th>PERIOSIDAD</th>
                            <th>ID PLAN</th>
                            <th>ID CONFIG</th>
                            <th>CREADA</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php while ($row = $result_clientes->fetch_assoc()): ?>
                        <tr id="cliente-<?php echo $row['id_tipoNotificacion']; ?>">
                            <td><?php echo $row['id_tipoNotificacion']; ?></td>
                            <td><?php echo htmlspecialchars($row['nombre_tipoNotificacion']); ?></td>
                            <td><?php echo htmlspecialchars($row['periodicidad']); ?></td>
                            <td><?php echo htmlspecialchars($row['id_plan'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($row['id_configuracion']); ?></td>
                            <td><?php echo date("d/m/Y", strtotime($row['creado_en'])); ?></td>
                        </tr>
                    <?php endwhile; ?>
                    <?php if ($result_clientes->num_rows === 0): ?>
                        <tr>
                            <td colspan="7" style="text-align:center;">No hay configuraciones registradas.</td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
    </div>
</div>

<!-- segunda tabla -->
 <div class="right-column">
     <div class="container">
         <div style="display: flex; justify-content: space-between; align-items: center;">
             <h2><i class="fas fa-cog"></i> Configuracion de notificaciones</h2>
             <button class="button" onclick="abrirModal('configuracion')"><i class="fas fa-plus-circle"></i> Agregar</button>
         </div>

       <?php if($mensaje) { echo $mensaje; } ?>
            <div style="overflow-x:auto;">
                <table id="tablaClientes">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>CONFIGURACION</th>
                            <th>PERIMETROS</th>
                            <th>CREADA</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php while ($row = $result_confign ->fetch_assoc()): ?>
                        <tr id="cliente-<?php echo $row['id_configuracion']; ?>">
                            <td><?php echo $row['id_configuracion']; ?></td>
                            <td><?php echo htmlspecialchars($row['nombre_configuracion']); ?></td>
                            <td><?php echo htmlspecialchars($row['parametros']); ?></td>
                            <td><?php echo date("d/m/Y", strtotime($row['creado_en'])); ?></td>
                        </tr>
                    <?php endwhile; ?>
                    <?php if ($result_clientes->num_rows === 0): ?>
                        <tr>
                            <td colspan="7" style="text-align:center;">No hay configuraciones registradas.</td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
              </table>
    </div>
</div>


<!-- 🌟 Modal de inserción -->
<div class="modal-overlay" id="modalOverlay">
    <div class="modal">
        <h3 id="modalTitle"><i class="fas fa-plus-circle"></i> Nueva Notificación</h3>
        <form id="formNotificacion">
            
            <!-- Campos para tipo_notificacion -->
            <div class="form-grid" id="campos_tipo">
                <div class="form-group">
                    <label>Nombre</label>
                    <input type="text" name="nombre_tipoNotificacion" required>
                </div>
                <div class="form-group">
                    <label>Periodicidad</label>
                    <input type="number" name="periodicidad" min="0" required>
                </div>
                <div class="form-group">
                    <label>ID Plan</label>
                    <input type="number" name="id_plan">
                </div>
                <div class="form-group">
                    <label>ID Configuración</label>
                    <input type="number" name="id_configuracion" required>
                </div>
            </div>

            <!-- Campos para configuracion (segunda tabla) -->
            <div class="form-grid" id="campos_configuracion" style="display:none;">
                <div class="form-group full-width">
                    <label>Nombre configuración</label>
                    <input type="text" name="nombre_configuracion">
                </div>
                <div class="form-group full-width">
                    <label>Parámetros</label>
                    <textarea name="parametros" rows="5" placeholder='JSON u otro formato según tu necesidad'></textarea>
                </div>
            </div>

            <div class="modal-buttons">
                <button type="button" class="button" onclick="cerrarModal()">Cancelar</button>
                <button type="submit" class="button"><i class="fas fa-save"></i> Guardar</button>
            </div>
            <input type="hidden" name="accion" value="insertar">
            <input type="hidden" name="tabla" id="inputTabla" value="tipo_notificacion">
        </form>
    </div>
</div>


<script>
function abrirModal(tabla = 'tipo_notificacion') {
    // Setear destino
    document.getElementById('inputTabla').value = tabla;
    // Título
    const title = document.getElementById('modalTitle');
    // Mostrar/ocultar campos según tabla
    const camposTipo = document.getElementById('campos_tipo');
    const camposConfig = document.getElementById('campos_configuracion');

    // Referencias a campos para activar/desactivar validación
    const nombreTipo = document.querySelector('input[name="nombre_tipoNotificacion"]');
    const periodicidad = document.querySelector('input[name="periodicidad"]');
    const idPlan = document.querySelector('input[name="id_plan"]');
    const idConfig = document.querySelector('input[name="id_configuracion"]');
    const nombreConf = document.querySelector('input[name="nombre_configuracion"]');
    const parametros = document.querySelector('textarea[name="parametros"]');

    if (tabla === 'configuracion') {
        title.innerHTML = '<i class="fas fa-cog"></i> Nueva Configuración';
        camposTipo.style.display = 'none';
        camposConfig.style.display = 'block';
        // Desactivar/limpiar campos de tipo_notificacion (evita validación bloqueante)
        if (nombreTipo) { nombreTipo.required = false; nombreTipo.disabled = true; nombreTipo.value = ''; }
        if (periodicidad) { periodicidad.required = false; periodicidad.disabled = true; periodicidad.value = ''; }
        if (idPlan) { idPlan.disabled = true; idPlan.value = ''; }
        if (idConfig) { idConfig.required = false; idConfig.disabled = true; idConfig.value = ''; }
        // Activar campos de configuración
        if (nombreConf) { nombreConf.required = true; nombreConf.disabled = false; nombreConf.value = ''; }
        if (parametros) { parametros.disabled = false; parametros.value = ''; }
    } else {
        title.innerHTML = '<i class="fas fa-plus-circle"></i> Nueva Notificación';
        camposTipo.style.display = 'grid';
        camposConfig.style.display = 'none';
        // Activar campos de tipo_notificacion
        if (nombreTipo) { nombreTipo.required = true; nombreTipo.disabled = false; }
        if (periodicidad) { periodicidad.required = true; periodicidad.disabled = false; }
        if (idPlan) { idPlan.disabled = false; }
        if (idConfig) { idConfig.required = true; idConfig.disabled = false; }
        // Desactivar campos de configuración
        if (nombreConf) { nombreConf.required = false; nombreConf.disabled = true; nombreConf.value = ''; }
        if (parametros) { parametros.disabled = true; parametros.value = ''; }
    }
    document.getElementById('modalOverlay').style.display = 'flex';
}
function cerrarModal() {
    document.getElementById('modalOverlay').style.display = 'none';
}

document.getElementById('formNotificacion').addEventListener('submit', function(e) {
    e.preventDefault();
    const datos = new FormData(this);

    fetch('configuracion_notificaciones.php', {
        method: 'POST',
        body: datos
    })
    .then(res => {
        if (!res.ok) throw new Error('Error en la respuesta del servidor');
        return res.json();
    })
    .then(data => {
        if (data.success) {
            alert('✅ Notificación agregada exitosamente');
            cerrarModal();
            location.reload();
        } else {
            alert('❌ Error al guardar: ' + (data.error || 'Respuesta inválida'));
        }
    })
    .catch(error => {
        alert('⚠️ Error de conexión: ' + error.message);
    });
});

</script>


</body>
</html>