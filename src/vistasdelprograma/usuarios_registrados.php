<?php
session_start();
require_once __DIR__ . '/bitacora_helpers.php';
require_once __DIR__ . '/../config/errorlogs.php';// Ajusta la ruta según tu estructura
require_once __DIR__ . '/../config/roles.php';

use App\config\errorlogs;
errorlogs::activa_error_logs();

if (empty($_SESSION['usuario']) || empty($_SESSION['usuario_id'])) {
    header('Location: ../../public/index.php?error=Debes iniciar sesión');
    exit;
}

$moduleKey = 'usuarios';
require_permission($moduleKey, PERM_READ);

$usuarioId = intval($_SESSION['usuario_id'] ?? 0);
$rol_id = intval($_SESSION['rol_id'] ?? 0);
$canRead = user_has_permission($moduleKey, PERM_READ);
$canCreate = user_has_permission($moduleKey, PERM_CREATE);
$canUpdate = user_has_permission($moduleKey, PERM_UPDATE);
$canDelete = user_has_permission($moduleKey, PERM_DELETE);

if ($usuarioId === SUPPORT_USER_ID) {
    $canCreate = $canUpdate = $canDelete = true;
}

$admin_id = $usuarioId;


function get_db_connection() {
  $host = "localhost";
$user = "root";
$pass = "";
$db   = "gestion_suscripciones";
    $conexion = mysqli_connect($host, $user, $pass, $db, 3306);
    if ($conexion) {
        mysqli_set_charset($conexion, 'utf8');
    }
    return $conexion;
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    header('Content-Type: application/json');

    $accion = $_POST['accion'];

    $conexion = get_db_connection();
    if (!$conexion) {
        echo json_encode(['success' => false, 'error' => 'Error de conexión: ' . mysqli_connect_error()]);
        exit;
    }

    ensure_fecha_creacion_column($conexion);

    if ($accion === 'actualizar') {
        if (!$canUpdate) {
            echo json_encode(['success' => false, 'error' => 'No tiene permisos para actualizar usuarios']);
            exit;
        }

        $id = intval($_POST['id'] ?? 0);
        $nombre = trim($_POST['nombre'] ?? '');
        $correo = trim($_POST['correo'] ?? '');
        $usuario = trim($_POST['usuario'] ?? '');
        $rol = intval($_POST['rol'] ?? 0);
        $fecha_vencimiento = trim($_POST['fecha_vencimiento'] ?? '');
        $contrasena = trim($_POST['contrasena'] ?? '');

        if ($id <= 0 || $nombre === '' || $correo === '' || $usuario === '' || $rol <= 0 || $fecha_vencimiento === '') {
            echo json_encode(['success' => false, 'error' => 'Faltan campos obligatorios']);
            exit;
        }

        if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'error' => 'Correo inválido']);
            exit;
        }

        $fechaValida = DateTime::createFromFormat('Y-m-d', $fecha_vencimiento);
        if (!$fechaValida || $fechaValida->format('Y-m-d') !== $fecha_vencimiento) {
            echo json_encode(['success' => false, 'error' => 'Fecha de vencimiento inválida']);
            exit;
        }

        $rolExiste = $conexion->prepare('SELECT 1 FROM tbl_ms_roles WHERE id_rol = ? LIMIT 1');
        if (!$rolExiste) {
            echo json_encode(['success' => false, 'error' => 'Error al preparar validación de rol']);
            exit;
        }
        $rolExiste->bind_param('i', $rol);
        $rolExiste->execute();
        $rolExiste->store_result();
        if ($rolExiste->num_rows === 0) {
            $rolExiste->close();
            echo json_encode(['success' => false, 'error' => 'El rol seleccionado no existe']);
            exit;
        }
        $rolExiste->close();

        $updateSql = 'UPDATE tbl_ms_usuario SET nombre_usuario = ?, correo_electronico = ?, usuario = ?, tbl_ms_roles_id_rol = ?, fecha_vencimiento = ?';
        $bindTypes = 'sssis';
        $bindValues = [$nombre, $correo, $usuario, $rol, $fecha_vencimiento];
        $hash = null;

        if ($contrasena !== '') {
            if (strlen($contrasena) < 8) {
                echo json_encode(['success' => false, 'error' => 'La contraseña debe tener al menos 8 caracteres']);
                exit;
            }
            $hash = password_hash($contrasena, PASSWORD_DEFAULT);
            if ($hash === false) {
                echo json_encode(['success' => false, 'error' => 'No se pudo generar el hash de la contraseña']);
                exit;
            }
            $updateSql .= ', contrasena = ?';
            $bindTypes .= 's';
            $bindValues[] = $hash;
        }

        $updateSql .= ' WHERE id_usuario = ?';
        $bindTypes .= 'i';
        $bindValues[] = $id;

        $stmt = $conexion->prepare($updateSql);
        if (!$stmt) {
            echo json_encode(['success' => false, 'error' => 'Error al preparar la actualización: ' . $conexion->error]);
            exit;
        }

        $bindParams = [$bindTypes];
        foreach ($bindValues as $key => $value) {
            $bindParams[] = &$bindValues[$key];
        }
        call_user_func_array([$stmt, 'bind_param'], $bindParams);

        if ($stmt->execute()) {
            $fechaCreacionDisponible = false;
            $verificarFecha = $conexion->query("SHOW COLUMNS FROM tbl_ms_usuario LIKE 'fecha_creacion'");
            if ($verificarFecha) {
                $fechaCreacionDisponible = $verificarFecha->num_rows > 0;
                $verificarFecha->free();
            }

            $campoFechaCreacion = $fechaCreacionDisponible ? 'u.fecha_creacion AS fecha_creacion' : 'NULL AS fecha_creacion';
            $selectActualizado = 'SELECT u.id_usuario, u.nombre_usuario, u.correo_electronico, u.usuario, u.tbl_ms_roles_id_rol, u.fecha_vencimiento, ' . $campoFechaCreacion . ', r.rol AS nombre_rol FROM tbl_ms_usuario u LEFT JOIN tbl_ms_roles r ON r.id_rol = u.tbl_ms_roles_id_rol WHERE u.id_usuario = ? LIMIT 1';
            $stmtDatos = $conexion->prepare($selectActualizado);
            $datosActualizados = null;
            if ($stmtDatos) {
                $stmtDatos->bind_param('i', $id);
                $stmtDatos->execute();
                $resultadoDatos = $stmtDatos->get_result();
                if ($resultadoDatos) {
                    $datosActualizados = $resultadoDatos->fetch_assoc();
                }
                $stmtDatos->close();
            }

            $descripcion_log = "El usuario ID: {$admin_id} actualizó al usuario ID: {$id}.";
            if ($hash !== null) {
                $descripcion_log .= ' Se cambió la contraseña.';
            }
            registrar_bitacora($conexion, $admin_id, 1, 'UPDATE', $descripcion_log);

            echo json_encode(['success' => true, 'updated' => $datosActualizados]);
        } else {
            echo json_encode(['success' => false, 'error' => $stmt->error]);
        }

        $stmt->close();
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Acción desconocida']);
    exit;
}

$conexion = get_db_connection();
if (!$conexion) {
    die('Error fatal: No se pudo conectar a la base de datos. ' . mysqli_connect_error());
}

ensure_fecha_creacion_column($conexion);
$rolesDisponibles = [];
$rolesResult = $conexion->query('SELECT id_rol, rol FROM tbl_ms_roles ORDER BY id_rol ASC');
if ($rolesResult) {
    while ($rol = $rolesResult->fetch_assoc()) {
        $rolesDisponibles[] = $rol;
    }
    $rolesResult->free();
}

$fechaCreacionExiste = false;
$columnaCheck = $conexion->query("SHOW COLUMNS FROM tbl_ms_usuario LIKE 'fecha_creacion'");
if ($columnaCheck) {
    $fechaCreacionExiste = $columnaCheck->num_rows > 0;
    $columnaCheck->free();
}

$selectCampos = "SELECT u.id_usuario, u.nombre_usuario, u.correo_electronico, u.usuario, u.tbl_ms_roles_id_rol, u.fecha_vencimiento, "
    . ($fechaCreacionExiste ? "u.fecha_creacion" : "NULL") . " AS fecha_creacion, r.rol AS nombre_rol "
    . "FROM tbl_ms_usuario u LEFT JOIN tbl_ms_roles r ON r.id_rol = u.tbl_ms_roles_id_rol ORDER BY u.id_usuario DESC";

$result = $conexion->query($selectCampos);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Usuarios Registrados</title>
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
    <h1><i class="fas fa-users"></i> Usuarios Registrados</h1>
    <div class="action-buttons">
        <?php if ($canCreate || $usuarioId === SUPPORT_USER_ID): ?>
            <a class="create-button" href="usuarios.php">
                <i class="fas fa-user-plus"></i> Nuevo Usuario
            </a>
        <?php endif; ?>
        <?php if ($canRead): ?>
            <a class="report-button" href="reporte_usuarios_pdf.php" id="reportButton" target="_blank" rel="noopener">
                <i class="fas fa-file-pdf"></i> Reporte PDF
            </a>
        <?php endif; ?>
        <a class="back-button" href="pre_usuarios.php"><i class="fas fa-arrow-left"></i> Volver</a>
    </div>
</div>

<div class="container">
    <div class="notice">
        <?php if ($canUpdate || $canDelete): ?>
            Puedes actualizar o eliminar usuarios según tus permisos asignados.
        <?php else: ?>
            Tu rol solo permite consultar los usuarios registrados. Contacta al administrador si necesitas más acceso.
        <?php endif; ?>
    </div>
    
    <!-- Barra de búsqueda -->
    <div class="search-container">
        <div class="search-input-wrapper">
            <i class="fas fa-search search-icon"></i>
            <input type="text" id="searchInput" placeholder="Buscar por nombre, correo, usuario o rol..." />
            <button type="button" id="clearSearch" title="Limpiar búsqueda">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="search-stats">
            <span id="searchStats">Mostrando <strong id="visibleCount">0</strong> de <strong id="totalCount">0</strong> usuarios</span>
        </div>
    </div>
    
    <div style="overflow-x:auto;">
        <table>
            <thead>
                <tr>
                    <th>Núm.</th>
                    <th>Nombre</th>
                    <th>Correo</th>
                    <th>Usuario</th>
                    <th>Rol</th>
                    <th>Fecha Creación</th>
                    <th>Fecha Vencimiento</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $numeroSecuencial = 1;
            if ($result): ?>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <?php
                        $fechaCruda = $row['fecha_creacion'];
                        $fechaFormateada = '—';
                        if (!empty($fechaCruda)) {
                            $fechaTimestamp = strtotime($fechaCruda);
                            if ($fechaTimestamp !== false) {
                                $fechaFormateada = date('d/m/Y', $fechaTimestamp);
                            }
                        }
                        $fechaVenc = $row['fecha_vencimiento'] ? date('d/m/Y', strtotime($row['fecha_vencimiento'])) : '—';
                        $rolNombre = $row['nombre_rol'] ?? '';
                        $rolNombreSafe = $rolNombre ? htmlspecialchars($rolNombre) : 'Sin rol';
                        $fechaVencRaw = $row['fecha_vencimiento'] ?? '';
                        $fechaCreacionRaw = $row['fecha_creacion'] ?? '';
                    ?>
                    <tr data-id="<?php echo $row['id_usuario']; ?>"
                        data-nombre="<?php echo htmlspecialchars($row['nombre_usuario'], ENT_QUOTES, 'UTF-8'); ?>"
                        data-correo="<?php echo htmlspecialchars($row['correo_electronico'], ENT_QUOTES, 'UTF-8'); ?>"
                        data-usuario="<?php echo htmlspecialchars($row['usuario'], ENT_QUOTES, 'UTF-8'); ?>"
                        data-rol-id="<?php echo (int)$row['tbl_ms_roles_id_rol']; ?>"
                        data-rol-nombre="<?php echo htmlspecialchars($rolNombre, ENT_QUOTES, 'UTF-8'); ?>"
                        data-fecha-creacion="<?php echo htmlspecialchars($fechaCreacionRaw, ENT_QUOTES, 'UTF-8'); ?>"
                        data-fecha-vencimiento="<?php echo htmlspecialchars($fechaVencRaw, ENT_QUOTES, 'UTF-8'); ?>">
                        <td class="col-num"><?php echo $numeroSecuencial++; ?></td>
                        <td class="col-nombre"><?php echo htmlspecialchars($row['nombre_usuario']); ?></td>
                        <td class="col-correo"><?php echo htmlspecialchars($row['correo_electronico']); ?></td>
                        <td class="col-usuario"><?php echo htmlspecialchars($row['usuario']); ?></td>
                        <td class="col-rol"><span class="tag" data-rol-badge><?php echo $rolNombreSafe; ?></span></td>
                        <td class="col-fecha-creacion"><span class="date-pill" data-fecha-creacion><?php echo $fechaFormateada; ?></span></td>
                        <td class="col-fecha-vencimiento"><span class="date-pill" data-fecha-vencimiento><?php echo $fechaVenc; ?></span></td>
                        <td>
                            <?php if ($canUpdate || $canDelete): ?>
                                <?php if ($canUpdate): ?>
                                    <button class="icon-button orange" onclick="openEditModal(this)" title="Actualizar"><i class="fas fa-sync-alt"></i></button>
                                <?php endif; ?>
                                <?php if ($canDelete): ?>
                                    <button class="icon-button red" onclick="eliminarUsuario(this)" title="Eliminar"><i class="fas fa-trash"></i></button>
                                <?php endif; ?>
                            <?php else: ?>
                                <span style="color:#888;font-size:0.9em">Sin permisos</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php endif; ?>
            <?php if (!$result || $result->num_rows === 0): ?>
                <tr>
                    <td colspan="8" style="text-align:center;color:#777;">No se encontraron usuarios registrados.</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

    <?php if ($canUpdate): ?>
        <div class="modal-backdrop" id="modalBackdrop"></div>
        <div class="modal" id="editModal" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
            <header>
                <h3 id="modalTitle"><i class="fas fa-user-edit"></i> Editar usuario</h3>
                <button type="button" id="modalClose" aria-label="Cerrar"><i class="fas fa-times"></i></button>
            </header>
            <form id="editForm">
                <input type="hidden" id="modalUserId" name="id">
                <div>
                    <label for="modalNombre">Nombre completo</label>
                    <input type="text" id="modalNombre" name="nombre" required>
                </div>
                <div>
                    <label for="modalCorreo">Correo electrónico</label>
                    <input type="email" id="modalCorreo" name="correo" required>
                </div>
                <div>
                    <label for="modalUsuario">Usuario</label>
                    <input type="text" id="modalUsuario" name="usuario" required>
                </div>
                <div>
                    <label for="modalRol">Rol</label>
                    <select id="modalRol" name="rol" required>
                        <option value="" disabled selected>Seleccione un rol</option>
                        <?php foreach ($rolesDisponibles as $rol): ?>
                            <option value="<?php echo (int)$rol['id_rol']; ?>"><?php echo htmlspecialchars($rol['rol']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="modalFechaVenc">Fecha de vencimiento</label>
                    <input type="date" id="modalFechaVenc" name="fecha_vencimiento" required>
                </div>
                <div>
                    <label for="modalPassword">Nueva contraseña (opcional)</label>
                    <div class="password-field">
                        <input type="password" id="modalPassword" name="contrasena" placeholder="Dejar vacío para mantenerla">
                        <button type="button" id="togglePassword" aria-label="Mostrar u ocultar contraseña"><i class="fas fa-eye"></i></button>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-secondary" id="modalCancel">Cancelar</button>
                    <button type="submit" class="btn-primary">Guardar cambios</button>
                </div>
            </form>
        </div>
    <?php endif; ?>

<script>
    const canUpdate = <?php echo $canUpdate ? 'true' : 'false'; ?>;
    const canDelete = <?php echo $canDelete ? 'true' : 'false'; ?>;

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

    if (canUpdate) {
        const modal = document.getElementById('editModal');
        const backdrop = document.getElementById('modalBackdrop');
        const modalCloseBtn = document.getElementById('modalClose');
        const modalCancelBtn = document.getElementById('modalCancel');
        const modalForm = document.getElementById('editForm');
        const modalUserId = document.getElementById('modalUserId');
        const modalNombre = document.getElementById('modalNombre');
        const modalCorreo = document.getElementById('modalCorreo');
        const modalUsuario = document.getElementById('modalUsuario');
        const modalRol = document.getElementById('modalRol');
        const modalFechaVenc = document.getElementById('modalFechaVenc');
        const modalPassword = document.getElementById('modalPassword');
        const togglePasswordBtn = document.getElementById('togglePassword');
        let activeRow = null;

        function showModal() {
            if (!modal || !backdrop) return;
            modal.classList.add('visible');
            backdrop.classList.add('visible');
        }

        function hideModal() {
            if (!modal || !backdrop) return;
            modal.classList.remove('visible');
            backdrop.classList.remove('visible');
            activeRow = null;
            modalForm.reset();
            if (modalRol) {
                modalRol.selectedIndex = 0;
            }
            if (togglePasswordBtn) {
                togglePasswordBtn.innerHTML = '<i class="fas fa-eye"></i>';
                modalPassword.type = 'password';
            }
        }

        function normalizarFechaInput(valor) {
            if (!valor) return '';
            const coincidencia = valor.match(/^(\d{4}-\d{2}-\d{2})/);
            return coincidencia ? coincidencia[1] : valor;
        }

        function populateModal(row) {
            modalUserId.value = row.dataset.id || '';
            modalNombre.value = row.dataset.nombre || '';
            modalCorreo.value = row.dataset.correo || '';
            modalUsuario.value = row.dataset.usuario || '';

            const rolId = row.dataset.rolId || '';
            let rolAsignado = false;
            if (modalRol) {
                for (const option of modalRol.options) {
                    option.selected = false;
                    if (option.value === rolId) {
                        option.selected = true;
                        rolAsignado = true;
                    }
                }
                if (!rolAsignado) {
                    modalRol.selectedIndex = 0;
                }
            }

            const fechaVencRaw = row.dataset.fechaVencimiento || '';
            modalFechaVenc.value = normalizarFechaInput(fechaVencRaw);
            modalPassword.value = '';
        }

        togglePasswordBtn?.addEventListener('click', () => {
            if (!modalPassword) return;
            if (modalPassword.type === 'password') {
                modalPassword.type = 'text';
                togglePasswordBtn.innerHTML = '<i class="fas fa-eye-slash"></i>';
            } else {
                modalPassword.type = 'password';
                togglePasswordBtn.innerHTML = '<i class="fas fa-eye"></i>';
            }
        });

        window.openEditModal = function (button) {
            const row = button.closest('tr');
            if (!row) return;
            activeRow = row;
            populateModal(row);
            showModal();
        };

        function closeOnBackdrop(event) {
            if (event.target === backdrop) {
                hideModal();
            }
        }

        modalCloseBtn?.addEventListener('click', hideModal);
        modalCancelBtn?.addEventListener('click', hideModal);
        backdrop?.addEventListener('click', closeOnBackdrop);
        document.addEventListener('keydown', event => {
            if (event.key === 'Escape' && modal?.classList.contains('visible')) {
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
            const usuario = modalUsuario.value.trim();
            const rol = modalRol.value;
            const fechaVenc = modalFechaVenc.value;
            const contrasena = modalPassword.value.trim();

            if (!nombre || !correo || !usuario || !rol || !fechaVenc) {
                alert('Completa todos los campos obligatorios.');
                return;
            }

            const formData = new FormData();
            formData.append('accion', 'actualizar');
            formData.append('id', modalUserId.value);
            formData.append('nombre', nombre);
            formData.append('correo', correo);
            formData.append('usuario', usuario);
            formData.append('rol', rol);
            formData.append('fecha_vencimiento', fechaVenc);
            formData.append('contrasena', contrasena);

            fetch('usuarios_registrados.php', {method: 'POST', body: formData, credentials: 'same-origin'})
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.updated) {
                        applyRowUpdates(activeRow, data.updated);
                        alert('✅ Usuario actualizado correctamente.');
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

            if (data.nombre_usuario) {
                row.dataset.nombre = data.nombre_usuario;
                const celdaNombre = row.querySelector('.col-nombre');
                if (celdaNombre) celdaNombre.textContent = data.nombre_usuario;
            }

            if (data.correo_electronico) {
                row.dataset.correo = data.correo_electronico;
                const celdaCorreo = row.querySelector('.col-correo');
                if (celdaCorreo) celdaCorreo.textContent = data.correo_electronico;
            }

            if (data.usuario) {
                row.dataset.usuario = data.usuario;
                const celdaUsuario = row.querySelector('.col-usuario');
                if (celdaUsuario) celdaUsuario.textContent = data.usuario;
            }

            if (typeof data.tbl_ms_roles_id_rol !== 'undefined') {
                row.dataset.rolId = data.tbl_ms_roles_id_rol;
            }

            if (data.nombre_rol) {
                row.dataset.rolNombre = data.nombre_rol;
                const badge = row.querySelector('[data-rol-badge]');
                if (badge) badge.textContent = data.nombre_rol;
            }

            if (data.fecha_vencimiento) {
                const normalizada = normalizarFechaInput(data.fecha_vencimiento);
                row.dataset.fechaVencimiento = normalizada;
                const fechaPill = row.querySelector('[data-fecha-vencimiento]');
                if (fechaPill) {
                    const fechaFormateada = formatearFecha(data.fecha_vencimiento);
                    fechaPill.textContent = fechaFormateada || '—';
                }
            }

            if (data.fecha_creacion) {
                row.dataset.fechaCreacion = data.fecha_creacion;
                const pillCreacion = row.querySelector('[data-fecha-creacion]');
                if (pillCreacion) {
                    const fechaFormateada = formatearFecha(data.fecha_creacion);
                    pillCreacion.textContent = fechaFormateada || '—';
                }
            }

            const searchInput = document.getElementById('searchInput');
            const hasFilter = searchInput && searchInput.value.trim() !== '';
            reindexRows(hasFilter);
            updateSearchStats();
        }
    }

    function formatearFecha(fechaIso) {
        if (!fechaIso) return '';
        const fecha = new Date(fechaIso);
        if (Number.isNaN(fecha.getTime())) {
            const coincidencia = fechaIso.match(/^(\d{4})-(\d{2})-(\d{2})/);
            if (coincidencia) {
                return `${coincidencia[3]}/${coincidencia[2]}/${coincidencia[1]}`;
            }
            return '';
        }
        const dia = String(fecha.getDate()).padStart(2, '0');
        const mes = String(fecha.getMonth() + 1).padStart(2, '0');
        const anio = fecha.getFullYear();
        return `${dia}/${mes}/${anio}`;
    }

function eliminarUsuario(button) {
    if (!canDelete) {
        alert('No tiene permisos para eliminar usuarios.');
        return;
    }
    if (!confirm('¿Eliminar este usuario? Esta acción no se puede deshacer.')) {
        return;
    }

    const row = button.closest('tr');
    if (!row) {
        alert('No se pudo identificar el usuario.');
        return;
    }
    const id = row.dataset.id;
    const formData = new FormData();
    formData.append('id', id);

    fetch('delete_usuario.php', {method: 'POST', body: formData, credentials: 'same-origin'})
        .then(async response => {
            let data;
            try {
                data = await response.json();
            } catch (error) {
                const text = await response.text();
                throw new Error('Respuesta inválida del servidor: ' + text);
            }
            return data;
        })
        .then(data => {
            if (data.success) {
                alert('✅ Usuario eliminado');
                const searchInput = document.getElementById('searchInput');
                const hasFilter = searchInput && searchInput.value.trim() !== '';
                row.remove();
                reindexRows(hasFilter);
                updateSearchStats();
            } else {
                alert('❌ Error: ' + (data.error || 'Error desconocido'));
            }
        })
        .catch(error => {
            console.error(error);
            alert('Error de red al eliminar.');
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

    updateSearchStats = function updateSearchStatsHandler() {
        const allRows = getAllDataRows();
        const visibleRows = allRows.filter(row => !row.classList.contains('hidden'));

        visibleCountSpan.textContent = visibleRows.length;
        totalCountSpan.textContent = allRows.length;
    };

    function filterTable() {
        const searchTerm = searchInput.value.toLowerCase().trim();
        const allRows = getAllDataRows();

        const noResultsMsg = tableBody.querySelector('.no-results-row');
        if (noResultsMsg) {
            noResultsMsg.remove();
        }

        if (searchTerm === '') {
            allRows.forEach(row => row.classList.remove('hidden'));
            clearButton.classList.remove('visible');
        } else {
            clearButton.classList.add('visible');
            let hasVisibleRows = false;

            allRows.forEach(row => {
                const nombre = row.querySelector('.col-nombre')?.textContent.toLowerCase() || '';
                const correo = row.querySelector('.col-correo')?.textContent.toLowerCase() || '';
                const usuario = row.querySelector('.col-usuario')?.textContent.toLowerCase() || '';
                const rol = row.querySelector('[data-rol-badge]')?.textContent.toLowerCase() || '';

                const matchesSearch = nombre.includes(searchTerm) ||
                    correo.includes(searchTerm) ||
                    usuario.includes(searchTerm) ||
                    rol.includes(searchTerm);

                if (matchesSearch) {
                    row.classList.remove('hidden');
                    hasVisibleRows = true;
                } else {
                    row.classList.add('hidden');
                }
            });

            if (!hasVisibleRows) {
                const noResultsRow = document.createElement('tr');
                noResultsRow.className = 'no-results-row';
                noResultsRow.innerHTML = `
                    <td colspan="8" class="no-results-message">
                        <i class="fas fa-search"></i> No se encontraron usuarios que coincidan con "${searchTerm}"
                    </td>
                `;
                tableBody.appendChild(noResultsRow);
            }
        }

        reindexRows(searchTerm !== '');
        updateSearchStats();
    }

    searchInput.addEventListener('input', filterTable);

    clearButton.addEventListener('click', function() {
        searchInput.value = '';
        filterTable();
        searchInput.focus();
    });

    searchInput.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            clearButton.click();
        }
    });

    reindexRows();
    updateSearchStats();

    reportButton?.addEventListener('click', event => {
        event.preventDefault();
        const term = searchInput ? searchInput.value.trim() : '';
        const baseUrl = 'reporte_usuarios_pdf.php';
        const url = term ? `${baseUrl}?search=${encodeURIComponent(term)}` : baseUrl;
        window.open(url, '_blank', 'noopener');
    });
});

</script>
</body>
</html>
