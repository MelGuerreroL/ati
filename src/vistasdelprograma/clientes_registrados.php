<?php
session_start();
require 'con_db.php';
require_once __DIR__ . '/bitacora_helpers.php';
require_once __DIR__ . '/../config/errorlogs.php';// Ajusta la ruta según tu estructura
require_once __DIR__ . '/../config/roles.php';

use App\config\errorlogs;
errorlogs::activa_error_logs();


if (empty($_SESSION['usuario']) || empty($_SESSION['usuario_id'])) {
    header('Location: ../../public/index.php?error=Debes iniciar sesión');
    exit;
}

require_permission('clientes', PERM_READ);

function responder_json(array $payload) {
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit;
}

function obtener_objeto_clientes_id($conexion) {
    static $id_objeto = null;
    if ($id_objeto === null) {
        $stmt = $conexion->prepare('SELECT id_objetos FROM tbl_objetos WHERE objeto = ? LIMIT 1');
        if ($stmt) {
            $objeto = 'CLIENTES';
            $stmt->bind_param('s', $objeto);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result && ($row = $result->fetch_assoc())) {
                $id_objeto = intval($row['id_objetos']);
            } else {
                // Crear el objeto si no existe
                $stmt->close();
                $stmt_insert = $conexion->prepare('INSERT INTO tbl_objetos (objeto, descripcion, tipo_objeto) VALUES (?, ?, ?)');
                if ($stmt_insert) {
                    $desc = 'Gestión de clientes del sistema';
                    $tipo = 'Módulo';
                    $stmt_insert->bind_param('sss', $objeto, $desc, $tipo);
                    if ($stmt_insert->execute()) {
                        $id_objeto = $stmt_insert->insert_id;
                    }
                    $stmt_insert->close();
                }
            }
            if (isset($stmt)) $stmt->close();
        }
    }
    return $id_objeto ?: 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    $accion = $_POST['accion'];

    if (in_array($accion, ['actualizar', 'cambiar_estado'], true) && !user_has_permission('clientes', PERM_UPDATE)) {
        responder_json(['success' => false, 'error' => 'No tiene permisos para modificar clientes.']);
    }

    if ($accion === 'actualizar') {
        $id = intval($_POST['id'] ?? 0);
        $nombre = trim($_POST['nombre'] ?? '');
        $correo = trim($_POST['correo'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        $fechaNacimiento = trim($_POST['fecha_nacimiento'] ?? '');
        $observaciones = trim($_POST['observaciones'] ?? '');

        if ($id <= 0 || $nombre === '' || $correo === '') {
            responder_json(['success' => false, 'error' => 'Faltan datos obligatorios.']);
        }
        if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            responder_json(['success' => false, 'error' => 'Correo electrónico inválido.']);
        }
        if ($telefono !== '' && !preg_match('/^[0-9 \-\(\)]+$/', $telefono)) {
            responder_json(['success' => false, 'error' => 'El teléfono solo puede contener números y los caracteres ()-.']);
        }
        if ($fechaNacimiento !== '') {
            $fechaValida = DateTime::createFromFormat('Y-m-d', $fechaNacimiento);
            $hoy = new DateTime('today');
            if (!$fechaValida || $fechaValida->format('Y-m-d') !== $fechaNacimiento) {
                responder_json(['success' => false, 'error' => 'La fecha de nacimiento no tiene un formato válido.']);
            }
            if ($fechaValida > $hoy) {
                responder_json(['success' => false, 'error' => 'La fecha de nacimiento no puede ser en el futuro.']);
            }
        }

        $stmtCheck = $conexion->prepare('SELECT id_cliente FROM tbl_cliente WHERE correo_electronico = ? AND id_cliente <> ? LIMIT 1');
        if (!$stmtCheck) {
            responder_json(['success' => false, 'error' => 'No se pudo validar el correo electrónico.']);
        }
        $stmtCheck->bind_param('si', $correo, $id);
        $stmtCheck->execute();
        $stmtCheck->store_result();
        if ($stmtCheck->num_rows > 0) {
            $stmtCheck->close();
            responder_json(['success' => false, 'error' => 'El correo electrónico ya está asignado a otro cliente.']);
        }
        $stmtCheck->close();

        $stmtUpdate = $conexion->prepare("UPDATE tbl_cliente SET nombre_cliente = ?, correo_electronico = ?, telefono_cliente = NULLIF(?, ''), fecha_nacimiento = NULLIF(?, ''), observaciones = NULLIF(?, '') WHERE id_cliente = ?");
        if (!$stmtUpdate) {
            responder_json(['success' => false, 'error' => 'No se pudo preparar la actualización.']);
        }
        $stmtUpdate->bind_param('sssssi', $nombre, $correo, $telefono, $fechaNacimiento, $observaciones, $id);

        if (!$stmtUpdate->execute()) {
            $error = $stmtUpdate->error;
            $stmtUpdate->close();
            responder_json(['success' => false, 'error' => 'No se pudo actualizar el cliente: ' . $error]);
        }
        $stmtUpdate->close();

        // Registrar en bitácora
        $id_usuario = $_SESSION['usuario_id'];
        $id_objeto = obtener_objeto_clientes_id($conexion);
        $descripcion = "Actualizó datos del cliente ID: $id (Nombre: $nombre, Correo: $correo)";
        registrar_bitacora($conexion, $id_usuario, $id_objeto, 'UPDATE', $descripcion);

        $stmtDatos = $conexion->prepare("SELECT c.id_cliente, c.nombre_cliente, c.correo_electronico, c.telefono_cliente, c.fecha_nacimiento, c.observaciones, c.fecha_registro, c.id_estadoCliente, ec.nombre_estado FROM tbl_cliente c LEFT JOIN tbl_estado_cliente ec ON ec.id_estadoCliente = c.id_estadoCliente WHERE c.id_cliente = ? LIMIT 1");
        $datosActualizados = null;
        if ($stmtDatos) {
            $stmtDatos->bind_param('i', $id);
            $stmtDatos->execute();
            $resultado = $stmtDatos->get_result();
            if ($resultado) {
                $datosActualizados = $resultado->fetch_assoc();
            }
            $stmtDatos->close();
        }

        responder_json(['success' => true, 'updated' => $datosActualizados]);
    }

    if ($accion === 'cambiar_estado') {
        $id = intval($_POST['id'] ?? 0);
        if ($id <= 0) {
            responder_json(['success' => false, 'error' => 'ID de cliente inválido.']);
        }

        $stmtEstado = $conexion->prepare('SELECT id_estadoCliente FROM tbl_cliente WHERE id_cliente = ? LIMIT 1');
        if (!$stmtEstado) {
            responder_json(['success' => false, 'error' => 'No se pudo obtener el estado actual.']);
        }
        $stmtEstado->bind_param('i', $id);
        $stmtEstado->execute();
        $resultEstado = $stmtEstado->get_result();
        $rowEstado = $resultEstado ? $resultEstado->fetch_assoc() : null;
        $stmtEstado->close();

        if (!$rowEstado) {
            responder_json(['success' => false, 'error' => 'Cliente no encontrado.']);
        }

        $estadoActual = intval($rowEstado['id_estadoCliente']);
        $nuevoEstado = $estadoActual === 1 ? 2 : 1;

        $stmtUpdate = $conexion->prepare('UPDATE tbl_cliente SET id_estadoCliente = ? WHERE id_cliente = ?');
        if (!$stmtUpdate) {
            responder_json(['success' => false, 'error' => 'No se pudo preparar el cambio de estado.']);
        }
        $stmtUpdate->bind_param('ii', $nuevoEstado, $id);
        if (!$stmtUpdate->execute()) {
            $error = $stmtUpdate->error;
            $stmtUpdate->close();
            responder_json(['success' => false, 'error' => 'No se pudo actualizar el estado: ' . $error]);
        }
        $stmtUpdate->close();

        // Registrar en bitácora
        $id_usuario = $_SESSION['usuario_id'];
        $id_objeto = obtener_objeto_clientes_id($conexion);
        $estado_texto = $nuevoEstado === 1 ? 'ACTIVO' : 'INACTIVO';
        $descripcion = "Cambió estado del cliente ID: $id al estado $estado_texto";
        registrar_bitacora($conexion, $id_usuario, $id_objeto, 'UPDATE', $descripcion);

        $stmtNombreEstado = $conexion->prepare('SELECT nombre_estado FROM tbl_estado_cliente WHERE id_estadoCliente = ? LIMIT 1');
        $nombreEstado = $nuevoEstado === 1 ? 'ACTIVO' : 'INACTIVO';
        if ($stmtNombreEstado) {
            $stmtNombreEstado->bind_param('i', $nuevoEstado);
            $stmtNombreEstado->execute();
            $resEstado = $stmtNombreEstado->get_result();
            if ($resEstado && ($estadoRow = $resEstado->fetch_assoc())) {
                $nombreEstado = $estadoRow['nombre_estado'] ?? $nombreEstado;
            }
            $stmtNombreEstado->close();
        }

        responder_json(['success' => true, 'nuevo_estado_id' => $nuevoEstado, 'nuevo_estado_nombre' => $nombreEstado]);
    }

    if ($accion === 'eliminar') {
        if (!user_has_permission('clientes', PERM_DELETE)) {
            responder_json(['success' => false, 'error' => 'No tiene permisos para eliminar clientes.']);
        }

        $id = intval($_POST['id'] ?? 0);
        if ($id <= 0) {
            responder_json(['success' => false, 'error' => 'ID de cliente inválido.']);
        }

        // Registrar en bitácora ANTES de eliminar
        $id_usuario = $_SESSION['usuario_id'];
        $id_objeto = obtener_objeto_clientes_id($conexion);
        $nombreCliente = "(No se pudo obtener el nombre)";
        $stmtNombre = $conexion->prepare("SELECT nombre_cliente FROM tbl_cliente WHERE id_cliente = ?");
        if($stmtNombre) {
            $stmtNombre->bind_param('i', $id);
            $stmtNombre->execute();
            $res = $stmtNombre->get_result();
            if($row = $res->fetch_assoc()) {
                $nombreCliente = $row['nombre_cliente'];
            }
            $stmtNombre->close();
        }
        $descripcion = "Eliminó el cliente ID: $id (Nombre: $nombreCliente)";
        
        $stmtDelete = $conexion->prepare('DELETE FROM tbl_cliente WHERE id_cliente = ?');
        if (!$stmtDelete) {
            responder_json(['success' => false, 'error' => 'No se pudo preparar la eliminación.']);
        }
        $stmtDelete->bind_param('i', $id);
        if (!$stmtDelete->execute()) {
            $error = $stmtDelete->error;
            $stmtDelete->close();
            responder_json(['success' => false, 'error' => 'No se pudo eliminar el cliente: ' . $error]);
        }

        // Si la eliminación fue exitosa, registrar en la bitácora
        if ($stmtDelete->affected_rows > 0) {
            registrar_bitacora($conexion, $id_usuario, $id_objeto, 'DELETE', $descripcion);
        }
        $stmtDelete->close();

        responder_json(['success' => true]);
    }

    responder_json(['success' => false, 'error' => 'Acción desconocida.']);
}

$queryClientes = "SELECT c.id_cliente, c.nombre_cliente, c.correo_electronico, c.telefono_cliente, c.fecha_nacimiento, c.observaciones, c.fecha_registro, c.id_estadoCliente, ec.nombre_estado FROM tbl_cliente c LEFT JOIN tbl_estado_cliente ec ON ec.id_estadoCliente = c.id_estadoCliente ORDER BY c.id_cliente DESC";
$resultClientes = $conexion->query($queryClientes);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Clientes Registrados</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css"/>
<style>
    :root {
        --blue-primary: #1E88E5;   /* Azul elegante principal */
        --blue-dark: #1565C0;      /* Azul profundo para hover */
        --blue-light: #90CAF9;     /* Azul claro para acentos */
        --text-dark: #333;
        --text-light: #fff;
        --background-light: #f4f8fb; /* Fondo claro azulado */
        --border-color: #ccd6e0;   /* Gris azulado para bordes */
    }
    body {font-family: 'Segoe UI', sans-serif; background: var(--background-light); margin: 0; padding: 20px; color: var(--text-dark);}    
    .page-header {display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;}
    .page-header h1 {color: var(--text-dark); font-size: 2em; display: flex; align-items: center; gap: 10px;}
    .action-buttons {display: flex; align-items: center; gap: 12px; flex-wrap: wrap;}
    .report-button {background: var(--blue-dark); color: var(--text-light); border: none; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; font-weight: 600; padding: 10px 16px; border-radius: 6px; transition: background .3s;}
    .report-button:hover {background: #0D47A1;}
    .create-button {background: var(--blue-primary); color: var(--text-light); border: none; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; font-weight: 600; padding: 10px 16px; border-radius: 6px; transition: background .3s;}
    .create-button:hover {background: var(--blue-dark);}
    .container {background: var(--text-light); padding: 25px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,.08); border-top: 4px solid var(--blue-primary);}    
    table {width: 100%; border-collapse: collapse; margin-top: 12px;}
    th, td {padding: 12px 15px; border-bottom: 1px solid var(--border-color); text-align: left; vertical-align: middle;}
    th {background: var(--blue-dark); color: var(--text-light); text-transform: uppercase; font-size: 0.85em; letter-spacing: 0.5px;}
    input {padding: 10px; border-radius: 5px; border: 1px solid #ccc; transition: all .3s ease; box-sizing: border-box; width: 100%;}
    input:focus {border-color: var(--blue-primary); box-shadow: 0 0 0 3px rgba(30,136,229,.25);}    
    .icon-button {background: none; border: none; cursor: pointer; font-size: 1.2em; margin: 0 4px; padding: 5px; border-radius: 50%; transition: background .3s;}
    .icon-button:hover {background: rgba(0,0,0,0.1);}    
    .icon-button.red {color: #dc3545;}
    .icon-button.blue {color: var(--blue-dark);}    
    .notice {padding: 12px; border-radius: 6px; background: #e3f2fd; border: 1px solid var(--blue-light); color: #0d47a1; margin-bottom: 15px;}
    .back-button {background: transparent; color: var(--text-dark); border: none; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; font-weight: 600;}
    .back-button:hover {color: var(--blue-dark);}    
    .tag {display:inline-block;padding:6px 12px;border-radius:20px;background:var(--blue-light);color:var(--text-dark);font-weight:600;font-size:0.85em;}
    .date-pill {display:inline-block;padding:6px 10px;border-radius:12px;background:#eef2f7;color:#444;font-size:0.85em;}
    .modal-backdrop {position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.45);opacity:0;visibility:hidden;transition:opacity .2s ease;z-index:98;}
    .modal-backdrop.visible {opacity:1;visibility:visible;}
    .modal {position:fixed;top:50%;left:50%;transform:translate(-50%,-50%) scale(0.95);background:#fff;border-radius:12px;box-shadow:0 20px 45px rgba(0,0,0,0.25);width:100%;max-width:480px;max-height:90vh;overflow:auto;padding:0;opacity:0;visibility:hidden;transition:all .2s ease;z-index:99;}
    .modal.visible {opacity:1;visibility:visible;transform:translate(-50%,-50%) scale(1);}
    .modal header {display:flex;align-items:center;justify-content:space-between;padding:18px 24px;border-bottom:1px solid #eee;}
    .modal header h3 {margin:0;font-size:1.2em;color:var(--text-dark);display:flex;align-items:center;gap:10px;}
    .modal header button {background:none;border:none;font-size:1.2em;cursor:pointer;color:#888;}
    .modal header button:hover {color:#333;}
    .modal form {padding:20px 24px 24px 24px;display:flex;flex-direction:column;gap:16px;}
    .modal label {font-weight:600;color:#444;font-size:0.92em;}
    .modal input[type="text"],
    .modal input[type="email"],
    .modal input[type="password"],
    .modal input[type="date"],
    .modal select {width:100%;padding:10px 12px;border-radius:6px;border:1px solid #ccc;font-size:0.95em;transition:border-color .2s ease, box-shadow .2s ease;}
    .modal input:focus,
    .modal select:focus {border-color:var(--blue-primary);box-shadow:0 0 0 3px rgba(30,136,229,.25);outline:none;}
    .modal-footer {display:flex;justify-content:flex-end;gap:12px;margin-top:10px;}
    .modal-footer button {padding:10px 16px;border-radius:6px;border:none;font-weight:600;cursor:pointer;}
    .btn-secondary {background:#e1e1e1;color:#555;}
    .btn-secondary:hover {background:#d1d1d1;}
    .btn-primary {background:var(--blue-primary);color:#fff;}
    .btn-primary:hover {background:var(--blue-dark);}
    .password-field {position:relative;}
    .password-field button {position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;color:#888;cursor:pointer;}
    .password-field button:hover {color:var(--blue-dark);}

    /* Estilos para la barra de búsqueda */
    .search-container {
        margin-bottom: 20px;
        padding: 15px;
        background: #f8f9fa;
        border-radius: 8px;
        border: 1px solid #e9ecef;
    }
    .search-input-wrapper {
        position: relative;
        max-width: 500px;
        margin: 0 auto;
    }
    .search-input-wrapper input {
        width: 100%;
        padding: 12px 45px 12px 40px;
        border: 2px solid #dee2e6;
        border-radius: 25px;
        font-size: 16px;
        background: white;
        transition: all 0.3s ease;
        box-sizing: border-box;
    }
    .search-input-wrapper input:focus {
        outline: none;
        border-color: var(--blue-primary);
        box-shadow: 0 0 0 3px rgba(30,136,229,0.2);
    }
    .search-icon {
        position: absolute;
        left: 15px;
        top: 50%;
        transform: translateY(-50%);
        color: #6c757d;
        font-size: 16px;
    }
    .search-input-wrapper button {
        position: absolute;
        right: 10px;
        top: 50%;
        transform: translateY(-50%);
        background: none;
        border: none;
        color: #6c757d;
        cursor: pointer;
        padding: 5px;
        border-radius: 50%;
        display: none;
        transition: all 0.2s ease;
    }
    .search-input-wrapper button:hover {
        background: #f8f9fa;
        color: var(--blue-dark);
    }

</style>
</head>
<body>

<div class="page-header">
    <h1><i class="fas fa-user-friends"></i> Clientes Registrados</h1>
    <div class="action-buttons">
        <a class="create-button" href="clientes.php">
            <i class="fas fa-user-plus"></i> Nuevo Cliente
        </a>
        <a class="report-button" href="reporte_clientes_pdf.php" id="reportButton" target="_blank" rel="noopener">
            <i class="fas fa-file-pdf"></i> Reporte PDF
        </a>
        <a class="back-button" href="pre_clientes.php"><i class="fas fa-arrow-left"></i> Volver</a>
    </div>
</div>

<div class="container">
    <div class="notice">Desde esta vista puedes actualizar los datos de un cliente o activar / desactivar su estado.</div>
    
    <!-- Barra de búsqueda -->
    <div class="search-container">
        <div class="search-input-wrapper">
            <i class="fas fa-search search-icon"></i>
            <input type="text" id="searchInput" placeholder="Buscar por nombre, correo, teléfono o estado..." />
            <button type="button" id="clearSearch" title="Limpiar búsqueda">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="search-stats">
            <span id="searchStats">Mostrando <strong id="visibleCount">0</strong> de <strong id="totalCount">0</strong> clientes</span>
        </div>
    </div>
    
    <div style="overflow-x:auto;">
        <table>
            <thead>
                <tr>
                    <th>Núm.</th>
                    <th>Nombre</th>
                    <th>Correo</th>
                    <th>Teléfono</th>
                    <th>Fecha Registro</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $numeroSecuencial = 1;
            if ($resultClientes): ?>
                <?php while ($row = $resultClientes->fetch_assoc()): ?>
                    <?php
                        $fechaRegistroRaw = $row['fecha_registro'] ?? '';
                        $fechaRegistro = '—';
                        if (!empty($fechaRegistroRaw)) {
                            $timestamp = strtotime($fechaRegistroRaw);
                            if ($timestamp !== false) {
                                $fechaRegistro = date('d/m/Y', $timestamp);
                            }
                        }
                        $telefonoMostrar = $row['telefono_cliente'] ?: 'N/A';
                        $estadoId = (int)($row['id_estadoCliente'] ?? 0);
                        $estadoNombre = $row['nombre_estado'] ?? ($estadoId === 1 ? 'ACTIVO' : 'INACTIVO');
                        $badgeClass = $estadoId === 1 ? 'status-activo' : 'status-inactivo';
                    ?>
                    <tr
                        data-id="<?php echo $row['id_cliente']; ?>"
                        data-nombre="<?php echo htmlspecialchars($row['nombre_cliente'], ENT_QUOTES, 'UTF-8'); ?>"
                        data-correo="<?php echo htmlspecialchars($row['correo_electronico'], ENT_QUOTES, 'UTF-8'); ?>"
                        data-telefono="<?php echo htmlspecialchars($row['telefono_cliente'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                        data-fecha-nacimiento="<?php echo htmlspecialchars($row['fecha_nacimiento'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                        data-observaciones="<?php echo htmlspecialchars($row['observaciones'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                        data-estado-id="<?php echo $estadoId; ?>"
                        data-estado-nombre="<?php echo htmlspecialchars($estadoNombre, ENT_QUOTES, 'UTF-8'); ?>"
                    >
                        <td class="col-num"><?php echo $numeroSecuencial++; ?></td>
                        <td class="col-nombre"><?php echo htmlspecialchars($row['nombre_cliente']); ?></td>
                        <td class="col-correo"><?php echo htmlspecialchars($row['correo_electronico']); ?></td>
                        <td class="col-telefono"><?php echo htmlspecialchars($telefonoMostrar); ?></td>
                        <td class="col-registro"><span class="date-pill" data-fecha-registro="<?php echo htmlspecialchars($fechaRegistroRaw ?? '', ENT_QUOTES, 'UTF-8'); ?>"><?php echo $fechaRegistro; ?></span></td>
                        <td class="col-estado">
                            <span class="status-badge <?php echo $badgeClass; ?>" data-estado-badge><?php echo htmlspecialchars($estadoNombre); ?></span>
                        </td>
                        <td>
                            <button class="icon-button orange" onclick="openEditModal(this)" title="Editar"><i class="fas fa-edit"></i></button>
                            <button class="icon-button <?php echo $estadoId === 1 ? 'red' : 'green'; ?>" onclick="cambiarEstado(this)" title="Cambiar estado"><i class="fas <?php echo $estadoId === 1 ? 'fa-toggle-on' : 'fa-toggle-off'; ?>"></i></button>
                            <button class="icon-button red" onclick="eliminarCliente(this)" title="Eliminar"><i class="fas fa-trash-alt"></i></button>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php endif; ?>
            <?php if (!$resultClientes || $resultClientes->num_rows === 0): ?>
                <tr>
                    <td colspan="7" style="text-align:center;color:#777;">No se encontraron clientes registrados.</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal-backdrop" id="modalBackdrop"></div>
<div class="modal" id="editModal" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
    <header>
        <h3 id="modalTitle"><i class="fas fa-user-edit"></i> Editar cliente</h3>
        <button type="button" id="modalClose" aria-label="Cerrar"><i class="fas fa-times"></i></button>
    </header>
    <form id="editForm">
        <input type="hidden" id="modalClienteId" name="id">
        <div>
            <label for="modalNombre">Nombre completo</label>
            <input type="text" id="modalNombre" name="nombre" required>
        </div>
        <div>
            <label for="modalCorreo">Correo electrónico</label>
            <input type="email" id="modalCorreo" name="correo" required>
        </div>
        <div>
            <label for="modalTelefono">Teléfono</label>
            <input type="tel" id="modalTelefono" name="telefono" placeholder="Opcional">
        </div>
        <div>
            <label for="modalFechaNac">Fecha de nacimiento</label>
            <input type="date" id="modalFechaNac" name="fecha_nacimiento">
        </div>
        <div>
            <label for="modalObservaciones">Observaciones</label>
            <textarea id="modalObservaciones" name="observaciones" placeholder="Opcional"></textarea>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-secondary" id="modalCancel">Cancelar</button>
            <button type="submit" class="btn-primary">Guardar cambios</button>
        </div>
    </form>
</div>

<script>
const modal = document.getElementById('editModal');
const backdrop = document.getElementById('modalBackdrop');
const modalCloseBtn = document.getElementById('modalClose');
const modalCancelBtn = document.getElementById('modalCancel');
const modalForm = document.getElementById('editForm');
const modalClienteId = document.getElementById('modalClienteId');
const modalNombre = document.getElementById('modalNombre');
const modalCorreo = document.getElementById('modalCorreo');
const modalTelefono = document.getElementById('modalTelefono');
const modalFechaNac = document.getElementById('modalFechaNac');
const modalObservaciones = document.getElementById('modalObservaciones');
let activeRow = null;

let updateSearchStats = () => {};

function getAllDataRows() {
    return Array.from(document.querySelectorAll('tbody tr[data-id]'));
}

function reindexRows(onlyVisible = false) {
    let contador = 1;
    getAllDataRows().forEach(row => {
        if (onlyVisible && row.classList.contains('hidden')) {
            return;
        }
        const numCell = row.querySelector('.col-num');
        if (numCell) {
            numCell.textContent = contador++;
        }
    });
}

function showModal() {
    modal.classList.add('visible');
    backdrop.classList.add('visible');
}

function hideModal() {
    modal.classList.remove('visible');
    backdrop.classList.remove('visible');
    activeRow = null;
    modalForm.reset();
}

function normalizarFechaInput(valor) {
    if (!valor) return '';
    const match = valor.match(/^(\d{4}-\d{2}-\d{2})/);
    return match ? match[1] : valor;
}

function populateModal(row) {
    modalClienteId.value = row.dataset.id || '';
    modalNombre.value = row.dataset.nombre || '';
    modalCorreo.value = row.dataset.correo || '';
    modalTelefono.value = row.dataset.telefono || '';
    modalFechaNac.value = normalizarFechaInput(row.dataset.fechaNacimiento || '');
    modalObservaciones.value = row.dataset.observaciones || '';
}

function openEditModal(button) {
    const row = button.closest('tr');
    if (!row) return;
    activeRow = row;
    populateModal(row);
    showModal();
}

function closeOnBackdrop(event) {
    if (event.target === backdrop) {
        hideModal();
    }

    reindexRows();
}

modalCloseBtn?.addEventListener('click', hideModal);
modalCancelBtn?.addEventListener('click', hideModal);
backdrop?.addEventListener('click', closeOnBackdrop);
document.addEventListener('keydown', event => {
    if (event.key === 'Escape' && modal.classList.contains('visible')) {
        hideModal();
    }
});

modalForm?.addEventListener('submit', event => {
    event.preventDefault();
    if (!activeRow) {
        hideModal();
        return;
    }

    const nombre = modalNombre.value.trim();
    const correo = modalCorreo.value.trim();
    const telefono = modalTelefono.value.trim();
    const fechaNacimiento = modalFechaNac.value;
    const observaciones = modalObservaciones.value.trim();

    if (!nombre || !correo) {
        alert('Nombre y correo son obligatorios.');
        return;
    }

    const formData = new FormData();
    formData.append('accion', 'actualizar');
    formData.append('id', modalClienteId.value);
    formData.append('nombre', nombre);
    formData.append('correo', correo);
    formData.append('telefono', telefono);
    formData.append('fecha_nacimiento', fechaNacimiento);
    formData.append('observaciones', observaciones);

    fetch('clientes_registrados.php', {method: 'POST', body: formData, credentials: 'same-origin'})
        .then(response => response.json())
        .then(data => {
            if (data.success && data.updated) {
                applyRowUpdates(activeRow, data.updated);
                alert('✅ Cliente actualizado correctamente.');
                hideModal();
            } else {
                alert('❌ Error: ' + (data.error || 'No se pudo actualizar.'));
            }
        })
        .catch(error => {
            console.error(error);
            alert('Error de red al actualizar.');
        });
});

function applyRowUpdates(row, data) {
    if (!row || !data) return;

    if (data.nombre_cliente) {
        row.dataset.nombre = data.nombre_cliente;
        const colNombre = row.querySelector('.col-nombre');
        if (colNombre) colNombre.textContent = data.nombre_cliente;
    }
    if (data.correo_electronico) {
        row.dataset.correo = data.correo_electronico;
        const colCorreo = row.querySelector('.col-correo');
        if (colCorreo) colCorreo.textContent = data.correo_electronico;
    }
    const telefono = data.telefono_cliente || '';
    row.dataset.telefono = telefono;
    const colTelefono = row.querySelector('.col-telefono');
    if (colTelefono) colTelefono.textContent = telefono !== '' ? telefono : 'N/A';

    row.dataset.fechaNacimiento = data.fecha_nacimiento || '';
    row.dataset.observaciones = data.observaciones || '';

    if (typeof data.id_estadoCliente !== 'undefined') {
        row.dataset.estadoId = data.id_estadoCliente;
    }
    if (data.nombre_estado) {
        row.dataset.estadoNombre = data.nombre_estado;
        const badge = row.querySelector('[data-estado-badge]');
        if (badge) {
            badge.textContent = data.nombre_estado;
            badge.classList.toggle('status-activo', data.id_estadoCliente === 1);
            badge.classList.toggle('status-inactivo', data.id_estadoCliente !== 1);
        }
    }

    const searchInput = document.getElementById('searchInput');
    const hasFilter = searchInput && searchInput.value.trim() !== '';
    reindexRows(hasFilter);
}

function cambiarEstado(button) {
    if (!confirm('¿Deseas cambiar el estado de este cliente?')) {
        return;
    }

    const row = button.closest('tr');
    if (!row) return;
    const id = row.dataset.id;
    const formData = new FormData();
    formData.append('accion', 'cambiar_estado');
    formData.append('id', id);

    fetch('clientes_registrados.php', {method: 'POST', body: formData, credentials: 'same-origin'})
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                row.dataset.estadoId = data.nuevo_estado_id;
                row.dataset.estadoNombre = data.nuevo_estado_nombre;
                const badge = row.querySelector('[data-estado-badge]');
                if (badge) {
                    badge.textContent = data.nuevo_estado_nombre;
                    if (data.nuevo_estado_id === 1) {
                        badge.classList.add('status-activo');
                        badge.classList.remove('status-inactivo');
                        button.classList.add('red');
                        button.classList.remove('green');
                        button.querySelector('i').className = 'fas fa-toggle-on';
                    } else {
                        badge.classList.add('status-inactivo');
                        badge.classList.remove('status-activo');
                        button.classList.add('green');
                        button.classList.remove('red');
                        button.querySelector('i').className = 'fas fa-toggle-off';
                    }
                }
                alert('Estado del cliente actualizado correctamente.');
            } else {
                alert('❌ Error: ' + (data.error || 'No se pudo cambiar el estado.'));
            }
        })
        .catch(error => {
            console.error(error);
            alert('Error de red al cambiar el estado.');
        });
}

function eliminarCliente(button) {
    if (!confirm('¿Estás seguro de que deseas eliminar este cliente? Esta acción no se puede deshacer.')) {
        return;
    }

    const row = button.closest('tr');
    if (!row) return;
    const id = row.dataset.id;
    const formData = new FormData();
    formData.append('accion', 'eliminar');
    formData.append('id', id);

    fetch('clientes_registrados.php', {method: 'POST', body: formData, credentials: 'same-origin'})
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                row.remove();
                const searchInput = document.getElementById('searchInput');
                const hasFilter = searchInput && searchInput.value.trim() !== '';
                reindexRows(hasFilter);
                updateSearchStats();
                alert('✅ Cliente eliminado correctamente.');
            } else {
                alert('❌ Error: ' + (data.error || 'No se pudo eliminar el cliente.'));
            }
        })
        .catch(error => {
            console.error(error);
            alert('Error de red al eliminar el cliente.');
        });
}

// ===== FUNCIONALIDAD DE BÚSQUEDA =====
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchInput');
    const clearButton = document.getElementById('clearSearch');
    const reportButton = document.getElementById('reportButton');
    const visibleCountSpan = document.getElementById('visibleCount');
    const totalCountSpan = document.getElementById('totalCount');
    const tableBody = document.querySelector('tbody');

    // Actualizar estadísticas de búsqueda
    updateSearchStats = function updateSearchStatsHandler() {
        const allRows = getAllDataRows();
        const visibleRows = allRows.filter(row => !row.classList.contains('hidden'));
        
        visibleCountSpan.textContent = visibleRows.length;
        totalCountSpan.textContent = allRows.length;
    };
    
    // Función de filtrado
    function filterTable() {
        const searchTerm = searchInput.value.toLowerCase().trim();
        const allRows = getAllDataRows();
        
        // Remover mensaje de "no se encontraron resultados" si existe
        const noResultsMsg = tableBody.querySelector('.no-results-row');
        if (noResultsMsg) {
            noResultsMsg.remove();
        }
        
        if (searchTerm === '') {
            // Mostrar todas las filas si no hay término de búsqueda
            allRows.forEach(row => row.classList.remove('hidden'));
            clearButton.classList.remove('visible');
        } else {
            clearButton.classList.add('visible');
            let hasVisibleRows = false;
            
            allRows.forEach(row => {
                const nombre = row.querySelector('.col-nombre')?.textContent.toLowerCase() || '';
                const correo = row.querySelector('.col-correo')?.textContent.toLowerCase() || '';
                const telefono = row.querySelector('.col-telefono')?.textContent.toLowerCase() || '';
                const estado = row.querySelector('[data-estado-badge]')?.textContent.toLowerCase() || '';
                
                // Buscar en todos los campos relevantes
                const matchesSearch = nombre.includes(searchTerm) || 
                                    correo.includes(searchTerm) || 
                                    telefono.includes(searchTerm) || 
                                    estado.includes(searchTerm);
                
                if (matchesSearch) {
                    row.classList.remove('hidden');
                    hasVisibleRows = true;
                } else {
                    row.classList.add('hidden');
                }
            });
            
            // Mostrar mensaje si no hay resultados
            if (!hasVisibleRows) {
                const noResultsRow = document.createElement('tr');
                noResultsRow.className = 'no-results-row';
                noResultsRow.innerHTML = `
                    <td colspan="7" class="no-results-message">
                        <i class="fas fa-search"></i> No se encontraron clientes que coincidan con "${searchTerm}"
                    </td>
                `;
                tableBody.appendChild(noResultsRow);
            }
        }
        
        reindexRows(searchTerm !== '');
        updateSearchStats();
    }
    
    // Event listeners
    searchInput.addEventListener('input', filterTable);
    
    clearButton.addEventListener('click', function() {
        searchInput.value = '';
        filterTable();
        searchInput.focus();
    });
    
    // Permitir limpiar con Escape
    searchInput.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            clearButton.click();
        }
    });
    
    // Inicializar estadísticas
    reindexRows();
    updateSearchStats();

    reportButton?.addEventListener('click', event => {
        event.preventDefault();
        const term = searchInput ? searchInput.value.trim() : '';
        const baseUrl = 'reporte_clientes_pdf.php';
        const url = term ? `${baseUrl}?search=${encodeURIComponent(term)}` : baseUrl;
        window.open(url, '_blank', 'noopener');
    });
});

</script>
</body>
</html>
