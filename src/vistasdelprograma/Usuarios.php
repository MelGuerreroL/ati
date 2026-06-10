<?php
session_start();
// No se incluye con_db.php directamente para un manejo de errores de conexión más robusto en AJAX.
require_once __DIR__ . '/bitacora_helpers.php'; // Incluir el helper de la bitácora
require_once __DIR__ . '/../config/errorlogs.php';// Ajusta la ruta según tu estructura
require_once __DIR__ . '/../config/roles.php';

use App\config\errorlogs;
errorlogs::activa_error_logs();

// Verificar sesión
if (empty($_SESSION['usuario']) || empty($_SESSION['usuario_id'])) {
    header('Location: ../../public/index.php?error=Debes iniciar sesión');
    exit;
}

require_permission('usuarios', PERM_READ);

// Determinar permisos por acción
$usuarioId = intval($_SESSION['usuario_id'] ?? 0);
$rol_id = intval($_SESSION['rol_id'] ?? 0);

$canCreate = user_has_permission('usuarios', PERM_CREATE);
$canUpdate = user_has_permission('usuarios', PERM_UPDATE);
$canDelete = user_has_permission('usuarios', PERM_DELETE);

if ($usuarioId === SUPPORT_USER_ID) {
    $canCreate = $canUpdate = $canDelete = true;
}

$actionUserId = $usuarioId;

// --- Lógica de Conexión Centralizada ---
function get_db_connection() {
    $host = "localhost";
    $user = "root";
    $pass = "";
    $db   = "gestion_suscripciones";
    $conexion = mysqli_connect($host, $user, $pass, $db);
    if ($conexion) {
        mysqli_set_charset($conexion, "utf8");
    }
    return $conexion;
}
// --- Fin Lógica de Conexión ---

// Acciones AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    header('Content-Type: application/json');
    
    $conexion = get_db_connection();
    if (!$conexion) {
        echo json_encode(['success' => false, 'error' => 'Error de conexión: ' . mysqli_connect_error()]);
        exit;
    }

    ensure_fecha_creacion_column($conexion);

    $accion = $_POST['accion'];

    if ($accion === 'actualizar') {
        if (!$canUpdate) { echo json_encode(['success'=>false,'error'=>'No tiene permisos para actualizar usuarios']); exit; }
        $id = intval($_POST['id'] ?? 0);
        $nombre = trim($_POST['nombre'] ?? '');
        $correo = trim($_POST['correo'] ?? '');
        $usuario = trim($_POST['usuario'] ?? '');
        $contrasena = trim($_POST['contrasena'] ?? '');
        if ($id <= 0 || $nombre === '' || $correo === '' || $usuario === '') { echo json_encode(['success'=>false,'error'=>'Faltan campos']); exit; }
        if ($contrasena !== '') {
            $hash = hash('sha512', $contrasena);
            $stmt = $conexion->prepare("UPDATE tbl_ms_usuario SET nombre_usuario=?, correo_electronico=?, usuario=?, contraseña=? WHERE id_usuario=?");
            $stmt->bind_param('ssssi', $nombre, $correo, $usuario, $hash, $id);
            $stmt_hist = $conexion->prepare("INSERT INTO tbl_ms_hist_contraseña (contraseña, tbl_ms_usuario_id_usuario) VALUES (?, ?)");
            $stmt_hist->bind_param('si', $hash, $id);
            $stmt_hist->execute();
            $stmt_hist->close();
        } else {
            $stmt = $conexion->prepare("UPDATE tbl_ms_usuario SET nombre_usuario=?, correo_electronico=?, usuario=? WHERE id_usuario=?");
            $stmt->bind_param('sssi', $nombre, $correo, $usuario, $id);
        }
        if ($stmt->execute()) {
            $descripcion_log = "El usuario ID: {$actionUserId} actualizó al usuario ID: {$id}.";
            if ($contrasena !== '') { $descripcion_log .= " Se cambió la contraseña."; }
            registrar_bitacora($conexion, $actionUserId, 1, 'UPDATE', $descripcion_log);
            echo json_encode(['success'=>true]); } else { echo json_encode(['success'=>false,'error'=>$stmt->error]); }
        exit;
    }

    if ($accion === 'eliminar') {
        if (!$canDelete) { echo json_encode(['success'=>false,'error'=>'No tiene permisos para eliminar usuarios']); exit; }
        $id = intval($_POST['id'] ?? 0);
        if ($id <= 0) { echo json_encode(['success'=>false,'error'=>'ID inválido']); exit; }
        
        // Eliminar registros dependientes para evitar errores de clave externa
        $delSeg = $conexion->prepare("DELETE FROM tbl_ms_seguridad_login WHERE tbl_ms_usuario_id_usuario = ?");
        $delSeg->bind_param('i', $id); $delSeg->execute(); $delSeg->close();
        
        $delHist = $conexion->prepare("DELETE FROM tbl_ms_hist_contraseña WHERE tbl_ms_usuario_id_usuario = ?");
        $delHist->bind_param('i', $id); $delHist->execute(); $delHist->close();

        $delBitacora = $conexion->prepare("DELETE FROM tbl_ms_bitacora WHERE id_usuario = ?");
        $delBitacora->bind_param('i', $id); $delBitacora->execute(); $delBitacora->close();

        $delParams = $conexion->prepare("DELETE FROM tbl_ms_parametros WHERE id_usuario = ?");
        $delParams->bind_param('i', $id); $delParams->execute(); $delParams->close();
        
        // Finalmente, eliminar el usuario principal
        $stmt = $conexion->prepare("DELETE FROM tbl_ms_usuario WHERE id_usuario = ?");
        $stmt->bind_param('i', $id);
        
        if ($stmt->execute()) {
            // Registrar la eliminación en la bitácora (realizada por el usuario con permiso)
            registrar_bitacora($conexion, $actionUserId, 1, 'DELETE', "El usuario ID: {$actionUserId} eliminó al usuario ID: {$id}.");
            echo json_encode(['success'=>true]);
        } else { 
            // Si falla, es probable que aún haya una restricción de clave externa no considerada.
            echo json_encode(['success'=>false,'error'=>'No se pudo eliminar al usuario. Es posible que tenga registros asociados en otras partes del sistema. Error: ' . $stmt->error]); 
        }
        exit;
    }

    echo json_encode(['success'=>false,'error'=>'Acción desconocida']);
    exit;
}

$conexion = get_db_connection();
if (!$conexion) {
    // Si no se puede conectar, se termina la ejecución mostrando un error claro.
    die("Error fatal: No se pudo conectar a la base de datos. " . mysqli_connect_error());
}

ensure_fecha_creacion_column($conexion);
$roles = [];
$rolesQuery = $conexion->query("SELECT id_rol, rol FROM tbl_ms_roles ORDER BY id_rol ASC");
if ($rolesQuery) {
    while ($rolDato = $rolesQuery->fetch_assoc()) {
        $roles[] = $rolDato;
    }
    $rolesQuery->free();
}
$fechaHoy = date('Y-m-d');
$fechaVencDefault = date('Y-m-d', strtotime('+90 days'));
$result = $conexion->query("SELECT id_usuario, nombre_usuario, correo_electronico, usuario, tbl_ms_roles_id_rol FROM tbl_ms_usuario ORDER BY id_usuario DESC");
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Gestión de Usuarios</title>
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
    input,button,select{padding:10px;border-radius:5px;border:1px solid #ccc;transition:all .3s ease;box-sizing:border-box;width:100%;}
    input:focus,select:focus{border-color:var(--orange-primary);box-shadow:0 0 0 3px rgba(240,173,78,.25);outline:none;}
    .button{background:var(--orange-primary);color:var(--text-light);border:none;cursor:pointer;font-weight:600;}
    .button:hover{background:var(--orange-dark);transform:translateY(-1px);}
    .icon-button{background:none;border:none;cursor:pointer;font-size:1.2em;margin:0 4px;padding:5px;border-radius:50%;transition:background .3s;}
    .icon-button:hover{background:rgba(0,0,0,0.1);}
    .icon-button.red{color:#dc3545;}
    .icon-button.orange{color:var(--orange-dark);}
    .back-button{background:transparent;color:var(--text-dark);border:none;text-decoration:none;display:inline-flex;align-items:center;gap:8px;margin-bottom:15px;font-weight:600;}
    .back-button:hover{color:var(--orange-dark);}
    .password-container{position:relative;}
    .password-container input{padding-right:40px;}
    .password-toggle{position:absolute;right:10px;top:50%;transform:translateY(-50%);cursor:pointer;color:#888;}
    .search-results{margin-top:15px;max-height:300px;overflow-y:auto;border:1px solid var(--border-color);border-radius:5px;}
    .notice{padding:12px;border-radius:6px;background:#fff8e1;border:1px solid var(--orange-light);color:#6d4c41;margin-bottom:15px;}
    h2{font-size:1.5em;margin:0 0 15px 0;padding:0;display:flex;align-items:center;gap:10px;}
    .date-row{display:flex;gap:12px;flex-wrap:wrap;}
    .date-row input{flex:1 1 150px;}
    .alert-message{padding:12px;border-radius:6px;margin-bottom:15px;font-weight:600;display:flex;align-items:center;gap:8px;}
    .alert-success{background:#d4edda;border:1px solid #c3e6cb;color:#155724;}
    .alert-error{background:#f8d7da;border:1px solid #f5c6cb;color:#721c24;}
    
    /* Estilos para validación */
    .form-error {
        color: #d32f2f;
        font-size: 12px;
        margin-top: 5px;
        display: block;
    }
    
    .form-success {
        color: #388e3c;
        font-size: 12px;
        margin-top: 5px;
        display: block;
    }
    
    .input-error {
        border-color: #d32f2f !important;
        box-shadow: 0 0 0 2px rgba(211, 47, 47, 0.1) !important;
    }
    
    .input-success {
        border-color: #388e3c !important;
        box-shadow: 0 0 0 2px rgba(56, 142, 60, 0.1) !important;
    }
    
    .form-group {
        margin-bottom: 15px;
        position: relative;
    }
    
    .form-group input {
        transition: border-color 0.3s, box-shadow 0.3s;
    }
    
    @media (max-width: 992px) { .page-layout { flex-direction: column; } }
</style>
</head>
<body>

<div class="page-header">
    <h1><i class="fas fa-users-cog"></i> Gestión de Usuarios</h1>
    <a class="back-button" href="pre_usuarios.php"><i class="fas fa-arrow-left"></i> Volver</a>
</div>

<div class="page-layout">
    <div class="left-column">
        <div class="container">
            <?php if (isset($_GET['success'])): ?>
                <div class="alert-message alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($_GET['success']); ?></div>
            <?php endif; ?>
            <?php if (isset($_GET['error'])): ?>
                <div class="alert-message alert-error"><i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($_GET['error']); ?></div>
            <?php endif; ?>

            <?php if ($canCreate): ?>
                <h2><i class="fas fa-user-plus"></i> Registrar Nuevo Usuario</h2>
                <form action="registro_usuario_admin.php" method="POST" id="registroForm">
                    <input type="hidden" name="origen" value="usuarios">
                    <div style="display:flex;flex-direction:column;gap:15px;">
                        <div class="form-group">
                            <input type="text" id="nombre_completo" name="nombre_completo" placeholder="Nombre completo (Ej: Juan Carlos Perez)" required>
                            <span id="error-nombre" class="form-error"></span>
                            <span id="success-nombre" class="form-success"></span>
                        </div>
                        
                        <div class="form-group">
                            <input type="email" id="correo" name="correo" placeholder="Correo electrónico" required>
                            <span id="error-correo" class="form-error"></span>
                            <span id="success-correo" class="form-success"></span>
                        </div>
                        
                        <div class="form-group">
                            <input type="text" id="usuario" name="usuario" placeholder="Usuario (3-20 caracteres)" required>
                            <span id="error-usuario" class="form-error"></span>
                            <span id="success-usuario" class="form-success"></span>
                        </div>
                        
                        <div class="form-group">
                            <select name="rol" <?php echo empty($roles) ? 'disabled' : ''; ?> required>
                                <option value="" disabled selected>Seleccione un rol</option>
                                <?php foreach ($roles as $rolDisponible): ?>
                                    <option value="<?php echo (int)$rolDisponible['id_rol']; ?>"><?php echo htmlspecialchars($rolDisponible['rol']); ?></option>
                                <?php endforeach; ?>
                                <?php if (empty($roles)): ?>
                                    <option value="">No hay roles disponibles</option>
                                <?php endif; ?>
                            </select>
                        </div>
                        
                        <div class="date-row">
                            <input type="date" name="fecha_creacion" value="<?php echo $fechaHoy; ?>" readonly>
                            <input type="date" name="fecha_vencimiento" value="<?php echo $fechaVencDefault; ?>" min="<?php echo $fechaHoy; ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <div class="password-container">
                                <input type="password" id="contraseña" name="contraseña" placeholder="Contraseña (8-15 caracteres)" required>
                                <span class="password-toggle" onclick="togglePasswordField(this)"><i class="fas fa-eye"></i></span>
                            </div>
                            <span id="error-password" class="form-error"></span>
                            <span id="success-password" class="form-success"></span>
                        </div>
                        
                        <div class="form-group">
                            <div class="password-container">
                                <input type="password" id="confirmar_contraseña" name="confirmar_contraseña" placeholder="Confirmar contraseña" required>
                                <span class="password-toggle" onclick="togglePasswordField(this)"><i class="fas fa-eye"></i></span>
                            </div>
                            <span id="error-confirm" class="form-error"></span>
                            <span id="success-confirm" class="form-success"></span>
                        </div>
                        
                        <button type="submit" class="button" id="submit-btn"><i class="fas fa-save"></i> Registrar Usuario</button>
                    </div>
                </form>
                <?php if (empty($roles)): ?>
                    <p style="margin-top:10px;color:#b94a48;font-size:0.9em;">No hay roles disponibles para asignar. Agregue roles antes de registrar nuevos usuarios.</p>
                <?php endif; ?>
            <?php else: ?>
                <div class="notice"><i class="fas fa-info-circle"></i> No tiene permisos para registrar nuevos usuarios. Puede consultar la información existente.</div>
            <?php endif; ?>
        </div>

        
    </div>

</div>

<script>
function togglePasswordField(element) {
    const p_container = element.closest('.password-container');
    const p_input = p_container.querySelector('input');
    const icon = element.querySelector('i');
    if (p_input.type === 'password') { p_input.type = 'text'; icon.className = 'fas fa-eye-slash'; } 
    else { p_input.type = 'password'; icon.className = 'fas fa-eye'; }
}

// Función para limpiar mensajes de error y éxito
function limpiarMensajes(campo) {
    document.getElementById(`error-${campo}`).textContent = '';
    document.getElementById(`success-${campo}`).textContent = '';
}

// Función para mostrar error
function mostrarError(campo, mensaje, inputElement) {
    limpiarMensajes(campo);
    document.getElementById(`error-${campo}`).textContent = mensaje;
    if (inputElement) {
        inputElement.classList.add('input-error');
        inputElement.classList.remove('input-success');
    }
}

// Función para mostrar éxito
function mostrarExito(campo, mensaje, inputElement) {
    limpiarMensajes(campo);
    document.getElementById(`success-${campo}`).textContent = mensaje;
    if (inputElement) {
        inputElement.classList.add('input-success');
        inputElement.classList.remove('input-error');
    }
}

// Validar nombre completo
function validarNombre(input) {
    const valor = input.value.trim();
    
    if (valor === '') {
        limpiarMensajes('nombre');
        input.classList.remove('input-error', 'input-success');
        return false;
    }
    
    // Verificar que no tenga espacios múltiples o al inicio/final
    if (/\s{2,}/.test(valor)) {
        mostrarError('nombre', 'No se permiten espacios múltiples consecutivos', input);
        return false;
    }
    
    // Verificar que tenga al menos un espacio (al menos dos nombres)
    if (!/\s/.test(valor)) {
        mostrarError('nombre', 'Debe ingresar al menos nombre y apellido separados por un espacio', input);
        return false;
    }
    
    // Normalizar: convertir a formato Title Case
    const valorNormalizado = valor.toLowerCase().replace(/\b\w/g, letra => letra.toUpperCase());
    
    // Validar formato: solo letras y espacios únicos
    const formatoValido = /^[A-Za-z]+(?: [A-Za-z]+)+$/.test(valorNormalizado);
    
    if (!formatoValido) {
        mostrarError('nombre', 'Solo letras y espacios únicos entre nombres (Ej: Juan Carlos Perez)', input);
        return false;
    }
    
    if (valorNormalizado.length < 3) {
        mostrarError('nombre', 'El nombre debe tener al menos 3 caracteres', input);
        return false;
    }
    
    // Actualizar el input con el valor normalizado
    input.value = valorNormalizado;
    mostrarExito('nombre', 'Formato válido', input);
    return true;
}

// Validar correo electrónico
function validarCorreo(input) {
    const valor = input.value.trim().toLowerCase();
    
    if (valor === '') {
        limpiarMensajes('correo');
        input.classList.remove('input-error', 'input-success');
        return false;
    }
    
    // Actualizar input con minúsculas
    input.value = valor;
    
    // Regex para email válido
    const emailValido = /^[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$/.test(valor);
    
    if (!emailValido) {
        mostrarError('correo', 'Ingrese un correo electrónico válido', input);
        return false;
    }
    
    mostrarExito('correo', 'Correo válido', input);
    return true;
}

// Validar usuario
function validarUsuario(input) {
    const valor = input.value.trim();
    
    if (valor === '') {
        limpiarMensajes('usuario');
        input.classList.remove('input-error', 'input-success');
        return false;
    }
    
    if (valor.length < 3 || valor.length > 20) {
        mostrarError('usuario', 'El usuario debe tener entre 3 y 20 caracteres', input);
        return false;
    }
    
    // Solo letras, números, puntos, guiones y guiones bajos
    const formatoValido = /^[A-Za-z0-9._-]+$/.test(valor);
    
    if (!formatoValido) {
        mostrarError('usuario', 'Solo se permiten letras, números, puntos, guiones y guiones bajos', input);
        return false;
    }
    
    mostrarExito('usuario', 'Usuario válido', input);
    return true;
}

// Validar contraseña
function validarContraseña(input) {
    const valor = input.value;
    
    if (valor === '') {
        limpiarMensajes('password');
        input.classList.remove('input-error', 'input-success');
        return false;
    }
    
    if (valor.length < 8 || valor.length > 15) {
        mostrarError('password', 'La contraseña debe tener entre 8 y 15 caracteres', input);
        return false;
    }
    
    // Validar que contenga: mayúscula, minúscula, número y símbolo especial
    const tieneMayuscula = /[A-Z]/.test(valor);
    const tieneMinuscula = /[a-z]/.test(valor);
    const tieneNumero = /\d/.test(valor);
    const tieneEspecial = /[\W_]/.test(valor);
    const sinEspacios = !/\s/.test(valor);
    
    if (!tieneMayuscula) {
        mostrarError('password', 'Debe contener al menos una letra mayúscula', input);
        return false;
    }
    
    if (!tieneMinuscula) {
        mostrarError('password', 'Debe contener al menos una letra minúscula', input);
        return false;
    }
    
    if (!tieneNumero) {
        mostrarError('password', 'Debe contener al menos un número', input);
        return false;
    }
    
    if (!tieneEspecial) {
        mostrarError('password', 'Debe contener al menos un carácter especial (!@#$%^&*)', input);
        return false;
    }
    
    if (!sinEspacios) {
        mostrarError('password', 'No se permiten espacios en blanco', input);
        return false;
    }
    
    mostrarExito('password', 'Contraseña segura', input);
    
    // Validar confirmación si ya tiene valor
    const confirmarInput = document.getElementById('confirmar_contraseña');
    if (confirmarInput.value !== '') {
        validarConfirmacion(confirmarInput);
    }
    
    return true;
}

// Validar confirmación de contraseña
function validarConfirmacion(input) {
    const valor = input.value;
    const passwordOriginal = document.getElementById('contraseña').value;
    
    if (valor === '') {
        limpiarMensajes('confirm');
        input.classList.remove('input-error', 'input-success');
        return false;
    }
    
    if (valor !== passwordOriginal) {
        mostrarError('confirm', 'Las contraseñas no coinciden', input);
        return false;
    }
    
    mostrarExito('confirm', 'Las contraseñas coinciden', input);
    return true;
}

// Event listeners para validación en tiempo real
document.addEventListener('DOMContentLoaded', function() {
    const nombreInput = document.getElementById('nombre_completo');
    const correoInput = document.getElementById('correo');
    const usuarioInput = document.getElementById('usuario');
    const passwordInput = document.getElementById('contraseña');
    const confirmInput = document.getElementById('confirmar_contraseña');
    const form = document.getElementById('registroForm');
    const submitBtn = document.getElementById('submit-btn');
    
    if (nombreInput) {
        nombreInput.addEventListener('input', function() {
            validarNombre(this);
        });
        
        nombreInput.addEventListener('blur', function() {
            validarNombre(this);
        });
    }
    
    if (correoInput) {
        correoInput.addEventListener('input', function() {
            validarCorreo(this);
        });
        
        correoInput.addEventListener('blur', function() {
            validarCorreo(this);
        });
    }
    
    if (usuarioInput) {
        usuarioInput.addEventListener('input', function() {
            validarUsuario(this);
        });
        
        usuarioInput.addEventListener('blur', function() {
            validarUsuario(this);
        });
    }
    
    if (passwordInput) {
        passwordInput.addEventListener('input', function() {
            validarContraseña(this);
        });
        
        passwordInput.addEventListener('blur', function() {
            validarContraseña(this);
        });
    }
    
    if (confirmInput) {
        confirmInput.addEventListener('input', function() {
            validarConfirmacion(this);
        });
        
        confirmInput.addEventListener('blur', function() {
            validarConfirmacion(this);
        });
    }
    
    // Validación al enviar el formulario
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Validar todos los campos
            const nombreValido = validarNombre(nombreInput);
            const correoValido = validarCorreo(correoInput);
            const usuarioValido = validarUsuario(usuarioInput);
            const passwordValido = validarContraseña(passwordInput);
            const confirmValido = validarConfirmacion(confirmInput);
            
            // Verificar que todos los campos estén válidos
            if (!nombreValido || !correoValido || !usuarioValido || !passwordValido || !confirmValido) {
                alert('Por favor, corrija los errores en el formulario antes de continuar.');
                return;
            }
            
            // Deshabilitar botón y mostrar loading
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Registrando...';
            
            // Enviar formulario
            this.submit();
        });
    }
});

function buscarUsuario(){
    const correo = document.getElementById('buscarCorreo').value.trim();
    if (!correo) { alert("Escribe un correo para buscar."); return; }
    const fd = new FormData();
    fd.append('accion','buscar'); fd.append('correo', correo);
    fetch('usuarios.php',{method:'POST',body:fd})
    .then(r=>r.json()).then(data=>{
        if (!data.success){ alert(data.error); return; }
        const resultado = document.getElementById('resultadoBusqueda');
        if (data.usuarios.length===0){ resultado.innerHTML="<p style='text-align:center;padding:10px;color:#666'>No se encontraron usuarios.</p>"; return; }
        let html=`<table style='width:100%;'><thead><tr style='background:var(--orange-light);'><th>ID</th><th>Nombre</th><th>Correo</th><th>Usuario</th></tr></thead><tbody>`;
        data.usuarios.forEach(u=>{ html+=`<tr><td>${u.id_usuario}</td><td>${u.nombre_usuario}</td><td>${u.correo_electronico}</td><td>${u.usuario}</td></tr>`; });
        html+="</tbody></table>"; resultado.innerHTML = html;
    }).catch(e=>{ console.error(e); alert("Error al buscar"); });
}

function actualizarUsuario(btn){
    const fila = btn.closest('tr');
    const id = fila.querySelector('.uid').textContent.trim();
    const nombre = fila.querySelector('.nombre').value.trim();
    const correo = fila.querySelector('.correo').value.trim();
    const usuario = fila.querySelector('.usuario').value.trim();
    const contr = fila.querySelector('.contrasena').value.trim();
    if (!id || !nombre || !correo || !usuario){ alert('Faltan campos'); return; }
    const fd = new FormData();
    fd.append('accion','actualizar'); fd.append('id',id); fd.append('nombre',nombre); fd.append('correo',correo);
    fd.append('usuario',usuario); fd.append('contrasena',contr);
    fetch('usuarios.php',{method:'POST',body:fd}).then(r=>r.json()).then(data=>{
        if (data.success) alert('✅ Usuario actualizado correctamente.');
        else alert('❌ Error: '+(data.error||'Error desconocido'));
    }).catch(e=>{ console.error(e); alert('Error de red'); });
}

function eliminarUsuario(btn){
    if (!confirm('¿Eliminar este usuario? Esta acción no se puede deshacer.')) return;
    const id = btn.closest('tr').querySelector('.uid').textContent.trim();
    const fd = new FormData(); fd.append('id',id);
    fetch('delete_usuario.php',{method:'POST',body:fd, credentials: 'same-origin'}).then(async r=>{
        // Intentar parsear JSON; si falla muestra texto
        let data;
        try { data = await r.json(); } catch(e) { const txt = await r.text(); throw new Error('Respuesta inválida del servidor: ' + txt); }
        return data;
    }).then(data=>{
        if (data.success){ alert('✅ Usuario eliminado'); btn.closest('tr').remove(); }
        else alert('❌ Error: '+(data.error||'Error desconocido'));
    }).catch(e=>{ console.error(e); alert('Error de red'); });
}
</script>
</body>
</html>