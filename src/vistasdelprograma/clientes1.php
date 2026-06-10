<?php
session_start();
require 'con_db.php';

// Verificar sesión
if (empty($_SESSION['usuario']) || empty($_SESSION['usuario_id'])) {
    header('Location: ../../public/index.php?error=Debes iniciar sesión');
    exit;
}

$mensaje = '';
$modo_edicion = false;
$cliente_a_editar = null;

// --- ACCIÓN: CARGAR DATOS PARA EDICIÓN ---
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['accion']) && $_GET['accion'] === 'editar' && isset($_GET['id'])) {
    $id_cliente_edicion = intval($_GET['id']);
    $stmt = $conexion->prepare("SELECT * FROM tbl_cliente WHERE id_cliente = ?");
    $stmt->bind_param('i', $id_cliente_edicion);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $modo_edicion = true;
        $cliente_a_editar = $result->fetch_assoc();
    } else {
        $mensaje = '<div class="mensaje error">Error: Cliente no encontrado.</div>';
    }
    $stmt->close();
}


// Manejar acciones POST (AJAX y formularios)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['accion'])) {
        $accion = $_POST['accion'];

        // --- ACCIÓN: REGISTRAR NUEVO CLIENTE ---
        if ($accion === 'registrar_cliente') {
            $nombre = trim($_POST['nombre_cliente'] ?? '');
            $correo = trim($_POST['correo_electronico'] ?? '');
            $telefono = trim($_POST['telefono_cliente'] ?? null);
            $fecha_nacimiento = !empty($_POST['fecha_nacimiento']) ? trim($_POST['fecha_nacimiento']) : null;
            $observaciones = trim($_POST['observaciones'] ?? null);

            if (empty($nombre) || empty($correo)) {
                $mensaje = '<div class="mensaje error">El nombre y el correo son obligatorios.</div>';
            } else {
                $stmt_check = $conexion->prepare("SELECT id_cliente FROM tbl_cliente WHERE correo_electronico = ?");
                $stmt_check->bind_param('s', $correo);
                $stmt_check->execute();
                $stmt_check->store_result();

                if ($stmt_check->num_rows > 0) {
                    $mensaje = '<div class="mensaje error">Error: El correo electrónico ya está registrado.</div>';
                } else {
                    $stmt = $conexion->prepare("INSERT INTO tbl_cliente (nombre_cliente, correo_electronico, telefono_cliente, fecha_nacimiento, observaciones, id_estadoCliente) VALUES (?, ?, ?, ?, ?, 1)");
                    $stmt->bind_param('sssss', $nombre, $correo, $telefono, $fecha_nacimiento, $observaciones);
                    if ($stmt->execute()) {
                        $mensaje = '<div class="mensaje exito">Cliente registrado correctamente.</div>';
                    } else {
                        $mensaje = '<div class="mensaje error">Error al registrar el cliente: ' . $stmt->error . '</div>';
                    }
                    $stmt->close();
                }
                $stmt_check->close();
            }
        }
        
        // --- ACCIÓN: ACTUALIZAR CLIENTE ---
        if ($accion === 'actualizar_cliente') {
            $id_cliente = intval($_POST['id_cliente'] ?? 0);
            $nombre = trim($_POST['nombre_cliente'] ?? '');
            $correo = trim($_POST['correo_electronico'] ?? '');
            $telefono = trim($_POST['telefono_cliente'] ?? null);
            $fecha_nacimiento = !empty($_POST['fecha_nacimiento']) ? trim($_POST['fecha_nacimiento']) : null;
            $observaciones = trim($_POST['observaciones'] ?? null);

            if ($id_cliente > 0 && !empty($nombre) && !empty($correo)) {
                $stmt_check = $conexion->prepare("SELECT id_cliente FROM tbl_cliente WHERE correo_electronico = ? AND id_cliente != ?");
                $stmt_check->bind_param('si', $correo, $id_cliente);
                $stmt_check->execute();
                $stmt_check->store_result();

                if ($stmt_check->num_rows > 0) {
                    $mensaje = '<div class="mensaje error">Error: El correo electrónico ya está en uso por otro cliente.</div>';
                    $modo_edicion = true;
                    $cliente_a_editar = $_POST;
                    $cliente_a_editar['id_cliente'] = $id_cliente;
                } else {
                    $stmt = $conexion->prepare("UPDATE tbl_cliente SET nombre_cliente = ?, correo_electronico = ?, telefono_cliente = ?, fecha_nacimiento = ?, observaciones = ? WHERE id_cliente = ?");
                    $stmt->bind_param('sssssi', $nombre, $correo, $telefono, $fecha_nacimiento, $observaciones, $id_cliente);

                    if ($stmt->execute()) {
                        $mensaje = '<div class="mensaje exito">Cliente actualizado correctamente.</div>';
                    } else {
                        $mensaje = '<div class="mensaje error">Error al actualizar el cliente: ' . $stmt->error . '</div>';
                    }
                    $stmt->close();
                }
                $stmt_check->close();
            } else {
                $mensaje = '<div class="mensaje error">Faltan datos para actualizar o el ID es inválido.</div>';
            }
        }


        // --- ACCIÓN: CAMBIAR ESTADO (AJAX) ---
        if ($accion === 'cambiar_estado') {
            header('Content-Type: application/json');
            $cliente_id = intval($_POST['id_cliente'] ?? 0);
            if ($cliente_id <= 0) {
                echo json_encode(['success' => false, 'error' => 'ID de cliente inválido.']);
                exit;
            }

            $stmt_get = $conexion->prepare("SELECT id_estadoCliente FROM tbl_cliente WHERE id_cliente = ?");
            $stmt_get->bind_param('i', $cliente_id);
            $stmt_get->execute();
            $result_get = $stmt_get->get_result();
            $current_status = $result_get->fetch_assoc();
            $stmt_get->close();

            if ($current_status) {
                $nuevo_estado_id = ($current_status['id_estadoCliente'] == 1) ? 2 : 1;
                $stmt_update = $conexion->prepare("UPDATE tbl_cliente SET id_estadoCliente = ? WHERE id_cliente = ?");
                $stmt_update->bind_param('ii', $nuevo_estado_id, $cliente_id);
                if ($stmt_update->execute()) {
                    $nombre_nuevo_estado = ($nuevo_estado_id == 1) ? 'ACTIVO' : 'INACTIVO';
                    echo json_encode(['success' => true, 'nuevo_estado_nombre' => $nombre_nuevo_estado]);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Error al actualizar la base de datos.']);
                }
                $stmt_update->close();
            } else {
                echo json_encode(['success' => false, 'error' => 'Cliente no encontrado.']);
            }
            exit;
        }

    }
}

// Obtener la lista de clientes para mostrar en la tabla
$query_clientes = "
    SELECT c.id_cliente, c.nombre_cliente, c.correo_electronico, c.telefono_cliente, c.fecha_registro, ec.nombre_estado, c.id_estadoCliente
    FROM tbl_cliente c
    LEFT JOIN tbl_estado_cliente ec ON c.id_estadoCliente = ec.id_estadoCliente
    ORDER BY c.id_cliente DESC";
$result_clientes = $conexion->query($query_clientes);

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
</style>
</head>
<body>

<div class="page-header">
    <h1><i class="fas fa-user-friends"></i> Gestión de Clientes</h1>
    <a class="back-button" href="menuprincipal.php"><i class="fas fa-arrow-left"></i> Volver al Menú</a>
</div>

<?php if($mensaje) { echo $mensaje; } ?>

<div class="page-layout">
    <div class="left-column">
        <div class="container">
            <h2><i class="fas fa-user-plus"></i> <?php echo $modo_edicion ? 'Editar Cliente' : 'Registrar Nuevo Cliente'; ?></h2>
            <form id="formCliente" action="clientes.php" method="POST" onsubmit="return validarFormulario()">
                <?php if ($modo_edicion): ?>
                    <input type="hidden" name="accion" value="actualizar_cliente">
                    <input type="hidden" name="id_cliente" value="<?php echo htmlspecialchars($cliente_a_editar['id_cliente']); ?>">
                <?php else: ?>
                    <input type="hidden" name="accion" value="registrar_cliente">
                <?php endif; ?>

                <div class="form-grid">
                    <div class="form-group">
                        <label for="nombre_cliente">Nombre Completo</label>
                        <input type="text" id="nombre_cliente" name="nombre_cliente" placeholder="Nombre del cliente" value="<?php echo htmlspecialchars($cliente_a_editar['nombre_cliente'] ?? ''); ?>" required>
                        <div id="error_nombre" class="form-error"></div>
                    </div>
                    <div class="form-group">
                        <label for="correo_electronico">Correo Electrónico</label>
                        <input type="email" id="correo_electronico" name="correo_electronico" placeholder="ejemplo@correo.com" value="<?php echo htmlspecialchars($cliente_a_editar['correo_electronico'] ?? ''); ?>" required>
                        <div id="error_correo" class="form-error"></div>
                    </div>
                    <div class="form-group">
                        <label for="telefono_cliente">Teléfono</label>
                        <input type="tel" id="telefono_cliente" name="telefono_cliente" placeholder="(Opcional)" value="<?php echo htmlspecialchars($cliente_a_editar['telefono_cliente'] ?? ''); ?>">
                         <div id="error_telefono" class="form-error"></div>
                    </div>
                    <div class="form-group">
                        <label for="fecha_nacimiento">Fecha de Nacimiento</label>
                        <input type="date" id="fecha_nacimiento" name="fecha_nacimiento" value="<?php echo htmlspecialchars($cliente_a_editar['fecha_nacimiento'] ?? ''); ?>">
                        <div id="error_fecha" class="form-error"></div>
                    </div>
                    <div class="form-group full-width">
                        <label for="observaciones">Observaciones</label>
                        <textarea id="observaciones" name="observaciones" rows="3" placeholder="(Opcional)"><?php echo htmlspecialchars($cliente_a_editar['observaciones'] ?? ''); ?></textarea>
                    </div>
                </div>
                <br>
                <button type="submit" class="button">
                    <i class="fas fa-save"></i> <?php echo $modo_edicion ? 'Actualizar Cliente' : 'Registrar Cliente'; ?>
                </button>
                <?php if ($modo_edicion): ?>
                    <a href="clientes.php" class="button" style="background-color: #6c757d; text-decoration: none;">Cancelar Edición</a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <div class="right-column">
        <div class="container">
            <h2><i class="fas fa-users"></i> Lista de Clientes</h2>
            <div style="overflow-x:auto;">
                <table id="tablaClientes">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nombre</th>
                            <th>Correo</th>
                            <th>Teléfono</th>
                            <th>Registro</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php while ($row = $result_clientes->fetch_assoc()): ?>
                        <tr id="cliente-<?php echo $row['id_cliente']; ?>">
                            <td><?php echo $row['id_cliente']; ?></td>
                            <td><?php echo htmlspecialchars($row['nombre_cliente']); ?></td>
                            <td><?php echo htmlspecialchars($row['correo_electronico']); ?></td>
                            <td><?php echo htmlspecialchars($row['telefono_cliente'] ?? 'N/A'); ?></td>
                            <td><?php echo date("d/m/Y", strtotime($row['fecha_registro'])); ?></td>
                            <td class="status-cell">
                                <span class="status-badge <?php echo ($row['nombre_estado'] === 'ACTIVO') ? 'status-activo' : 'status-inactivo'; ?>">
                                    <?php echo $row['nombre_estado']; ?>
                                </span>
                            </td>
                            <td class="action-cell">
                                <button class="icon-button <?php echo ($row['id_estadoCliente'] == 1) ? 'red' : 'green'; ?>" onclick="cambiarEstado(<?php echo $row['id_cliente']; ?>)" title="Cambiar Estado">
                                    <i class="fas <?php echo ($row['id_estadoCliente'] == 1) ? 'fa-toggle-on' : 'fa-toggle-off'; ?>"></i>
                                </button>
                                <a href="clientes.php?accion=editar&id=<?php echo $row['id_cliente']; ?>#formCliente" class="icon-button orange" title="Editar">
                                    <i class="fas fa-edit"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    <?php if ($result_clientes->num_rows === 0): ?>
                        <tr>
                            <td colspan="7" style="text-align:center;">No hay clientes registrados.</td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function validarFormulario() {
    let valido = true;
    document.querySelectorAll('.form-error').forEach(el => el.textContent = '');

    const nombre = document.getElementById('nombre_cliente').value.trim();
    if (nombre.length < 3) {
        document.getElementById('error_nombre').textContent = 'El nombre debe tener al menos 3 caracteres.';
        valido = false;
    }

    const correo = document.getElementById('correo_electronico').value.trim();
    const regexCorreo = /^[^\s@]+@[^\s@]+\.[^\s@]+$/; // Corregido de la respuesta anterior
    if (!regexCorreo.test(correo)) {
        document.getElementById('error_correo').textContent = 'Por favor, introduce un correo electrónico válido.';
        valido = false;
    }

    const telefono = document.getElementById('telefono_cliente').value.trim();
    const regexTelefono = /^[0-9 \-\(\)]+$/;
    if (telefono && !regexTelefono.test(telefono)) {
        document.getElementById('error_telefono').textContent = 'El teléfono solo puede contener números y los caracteres ()-.';
        valido = false;
    }
    
    const fechaNacimiento = document.getElementById('fecha_nacimiento').value;
    if (fechaNacimiento) {
        const hoy = new Date().toISOString().split('T')[0];
        if (fechaNacimiento > hoy) {
            document.getElementById('error_fecha').textContent = 'La fecha de nacimiento no puede ser en el futuro.';
            valido = false;
        }
    }

    return valido;
}

function cambiarEstado(clienteId) {
    if (!confirm('¿Estás seguro de que quieres cambiar el estado de este cliente?')) {
        return;
    }

    const formData = new FormData();
    formData.append('accion', 'cambiar_estado');
    formData.append('id_cliente', clienteId);

    fetch('clientes.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const row = document.getElementById('cliente-' + clienteId);
            const statusCell = row.querySelector('.status-cell .status-badge');
            const button = row.querySelector('.action-cell button.icon-button');
            const icon = button.querySelector('i');

            statusCell.textContent = data.nuevo_estado_nombre;
            if (data.nuevo_estado_nombre === 'ACTIVO') {
                statusCell.classList.remove('status-inactivo');
                statusCell.classList.add('status-activo');
                button.classList.remove('green');
                button.classList.add('red');
                icon.classList.remove('fa-toggle-off');
                icon.classList.add('fa-toggle-on');
            } else {
                statusCell.classList.remove('status-activo');
                statusCell.classList.add('status-inactivo');
                button.classList.remove('red');
                button.classList.add('green');
                icon.classList.remove('fa-toggle-on');
                icon.classList.add('fa-toggle-off');
            }
            alert('Estado del cliente actualizado correctamente.');
            // window.location.reload(); // Recargar para asegurar consistencia visual
        } else {
            alert('Error: ' + data.error);
        }
    })
    .catch(error => {
        console.error('Error en la solicitud:', error);
        alert('Ocurrió un error de red. Por favor, inténtalo de nuevo.');
    });
}
</script>

</body>
</html>