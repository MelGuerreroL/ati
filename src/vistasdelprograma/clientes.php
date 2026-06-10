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
            if (!isset($stmt) || $stmt === null) {
                // Statement already closed in else block above
            } else {
                $stmt->close();
            }
        }
    }
    return $id_objeto ?: 0;
}

$mensaje = '';
$mensajeClase = '';
$oldData = [
    'nombre_cliente' => '',
    'correo_electronico' => '',
    'telefono_cliente' => '',
    'fecha_nacimiento' => '',
    'observaciones' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? 'registrar_cliente';
    if ($accion === 'registrar_cliente') {
        $nombre = trim($_POST['nombre_cliente'] ?? '');
        $nombre = preg_replace('/\s+/', ' ', $nombre);
        $nombre = function_exists('mb_strtoupper') ? mb_strtoupper($nombre, 'UTF-8') : strtoupper($nombre);
        $correo = trim($_POST['correo_electronico'] ?? '');
        $telefono = trim($_POST['telefono_cliente'] ?? '');
        $fechaNacimiento = trim($_POST['fecha_nacimiento'] ?? '');
        $observaciones = trim($_POST['observaciones'] ?? '');

        $oldData = [
            'nombre_cliente' => $nombre,
            'correo_electronico' => $correo,
            'telefono_cliente' => $telefono,
            'fecha_nacimiento' => $fechaNacimiento,
            'observaciones' => $observaciones
        ];

        $errores = [];
        if ($nombre === '' || mb_strlen($nombre) < 3) {
            $errores[] = 'El nombre debe incluir al menos 3 caracteres.';
        }
        if ($correo === '') {
            $errores[] = 'El correo electrónico es obligatorio.';
        } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            $errores[] = 'Ingresa un correo electrónico válido.';
        }
        if ($telefono !== '') {
            $telefonoNumerico = preg_replace('/\D+/', '', $telefono);
            if (!preg_match('/^(?:504)?([2389]\d{3})(\d{4})$/', $telefonoNumerico, $coincidencias)) {
                $errores[] = 'El teléfono debe ser un número válido de Honduras (ej: 9999-9999 o +504 9999-9999).';
            } else {
                $telefono = $coincidencias[1] . '-' . $coincidencias[2];
                $oldData['telefono_cliente'] = $telefono;
            }
        }
        $hoy = new DateTime('today');
        if ($fechaNacimiento === '') {
            $errores[] = 'La fecha de nacimiento es obligatoria.';
        } else {
            $fechaValida = DateTime::createFromFormat('Y-m-d', $fechaNacimiento);
            if (!$fechaValida || $fechaValida->format('Y-m-d') !== $fechaNacimiento) {
                $errores[] = 'La fecha de nacimiento no tiene un formato válido.';
            } else {
                $fechaValida->setTime(0, 0, 0);
                $oldData['fecha_nacimiento'] = $fechaValida->format('Y-m-d');
                if ($fechaValida > $hoy) {
                    $errores[] = 'La fecha de nacimiento no puede ser en el futuro.';
                } else {
                    $edad = $fechaValida->diff($hoy);
                    if ($edad->y < 18) {
                        $errores[] = 'El cliente debe de ser mayor de edad.';
                    } elseif ($edad->y > 120 || ($edad->y === 120 && ($edad->m > 0 || $edad->d > 0))) {
                        $errores[] = 'La fecha de nacimiento no es coherente (edad máxima permitida 120 años).';
                    }
                }
            }
        }

        if (empty($errores)) {
            $stmtCheck = $conexion->prepare('SELECT id_cliente FROM tbl_cliente WHERE correo_electronico = ? LIMIT 1');
            if ($stmtCheck) {
                $stmtCheck->bind_param('s', $correo);
                $stmtCheck->execute();
                $stmtCheck->store_result();
                if ($stmtCheck->num_rows > 0) {
                    $errores[] = 'El correo electrónico ya está registrado.';
                }
                $stmtCheck->close();
            } else {
                $errores[] = 'No se pudo validar el correo electrónico.';
            }
        }

        if (empty($errores)) {
            $stmtInsert = $conexion->prepare("INSERT INTO tbl_cliente (nombre_cliente, correo_electronico, telefono_cliente, fecha_nacimiento, observaciones, id_estadoCliente) VALUES (?, ?, NULLIF(?, ''), NULLIF(?, ''), NULLIF(?, ''), 1)");
            if ($stmtInsert) {
                $stmtInsert->bind_param('sssss', $nombre, $correo, $telefono, $fechaNacimiento, $observaciones);
                if ($stmtInsert->execute()) {
                    $cliente_id = $stmtInsert->insert_id;
                    
                    // Registrar en bitácora
                    $id_usuario = $_SESSION['usuario_id'];
                    $id_objeto = obtener_objeto_clientes_id($conexion);
                    $descripcion = "Registró nuevo cliente ID: $cliente_id (Nombre: $nombre, Correo: $correo)";
                    registrar_bitacora($conexion, $id_usuario, $id_objeto, 'INSERT', $descripcion);
                    
                    $_SESSION['clientes_alert'] = [
                        'tipo' => 'success',
                        'mensaje' => 'Cliente registrado correctamente.'
                    ];
                    header('Location: clientes_registrados.php');
                    exit;
                } else {
                    $errores[] = 'Error al registrar el cliente: ' . $stmtInsert->error;
                }
                $stmtInsert->close();
            } else {
                $errores[] = 'No se pudo preparar el registro del cliente.';
            }
        }

        if (!empty($errores)) {
            $mensaje = implode('<br>', $errores);
            $mensajeClase = 'error';
        }
    }
}

$fechaMaxPermitida = date('Y-m-d', strtotime('-18 years'));
$fechaMinPermitida = date('Y-m-d', strtotime('-120 years'));
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Registrar Cliente</title>
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
    body {
        font-family:'Segoe UI',sans-serif;
        background:var(--background-light);
        margin:0;
        padding:20px;
        color:var(--text-dark);
    }    
    .page-header {
        display:flex;
        justify-content:space-between;
        align-items:center;
        margin-bottom:20px;
    }
    .page-header h1 {
        color:var(--text-dark);
        font-size:2em;
        display:flex;
        align-items:center;
        gap:10px;
    }
    .back-button {
        background:transparent;
        color:var(--text-dark);
        border:none;
        text-decoration:none;
        display:inline-flex;
        align-items:center;
        gap:8px;
        font-weight:600;
    }
    .back-button:hover {
        color:var(--blue-dark);
    }    
    .container {
        background:var(--text-light);
        padding:25px;
        border-radius:8px;
        box-shadow:0 4px 12px rgba(0,0,0,.08);
        border-top:4px solid var(--blue-primary);
        max-width:720px;
        margin:0 auto;
    }
    h2 {
        font-size:1.6em;
        margin:0 0 15px 0;
        display:flex;
        align-items:center;
        gap:10px;
    }
    .form-grid {
        display:grid;
        grid-template-columns:repeat(auto-fit,minmax(240px,1fr));
        gap:15px;
    }
    .form-group {
        display:flex;
        flex-direction:column;
        gap:6px;
    }
    label {
        font-weight:600;
        color:#444;
    }
    input, textarea {
        padding:10px;
        border-radius:6px;
        border:1px solid #ccc;
        font-size:1rem;
        transition:border-color .2s ease, box-shadow .2s ease;
        box-sizing:border-box;
        width:100%;
    }
    input:focus, textarea:focus {
        border-color:var(--blue-primary);
        box-shadow:0 0 0 3px rgba(30,136,229,.25);
        outline:none;
    }
    textarea {
        min-height:110px;
        resize:vertical;
    }
    .button {
        background:var(--blue-primary);
        color:var(--text-light);
        border:none;
        cursor:pointer;
        font-weight:600;
        padding:12px 20px;
        border-radius:6px;
        font-size:1rem;
        display:inline-flex;
        align-items:center;
        gap:8px;
    }
    .button:hover {
        background:var(--blue-dark);
    }
    .alert-message {
        padding:15px;
        border-radius:6px;
        margin-bottom:20px;
        font-weight:600;
        display:flex;
        align-items:center;
        gap:10px;
    }
    .alert-message.error {
        background:#f8d7da;
        border:1px solid #f5c6cb;
        color:#721c24;
    }
    .alert-message.exito {
        background:#d4edda;
        border:1px solid #c3e6cb;
        color:#155724;
    }
    .form-error {
        color:#dc3545;
        font-size:0.85em;
        margin-top:4px;
    }
    @media (max-width:600px){
        .page-header{
            flex-direction:column;
            align-items:flex-start;
            gap:10px;
        }
    }
</style>

</head>
<body>

<div class="page-header">
    <h1><i class="fas fa-user-friends"></i> Gestión de Clientes</h1>
    <a class="back-button" href="pre_clientes.php"><i class="fas fa-arrow-left"></i> Volver</a>
</div>

<div class="container">
    <?php if ($mensaje !== ''): ?>
        <div class="alert-message <?php echo $mensajeClase === 'exito' ? 'exito' : 'error'; ?>">
            <i class="fas <?php echo $mensajeClase === 'exito' ? 'fa-check-circle' : 'fa-exclamation-triangle'; ?>"></i>
            <span><?php echo $mensaje; ?></span>
        </div>
        <?php if (strpos($mensaje, 'El cliente debe de ser mayor de edad.') !== false): ?>
            <script>
                alert('El cliente debe de ser mayor de edad.');
            </script>
        <?php endif; ?>
        <?php if (strpos($mensaje, 'La fecha de nacimiento no es coherente (edad máxima permitida 120 años).') !== false): ?>
            <script>
                alert('La fecha de nacimiento no es coherente (edad máxima permitida 120 años).');
            </script>
        <?php endif; ?>
    <?php endif; ?>

    <h2><i class="fas fa-user-plus"></i> Registrar Nuevo Cliente</h2>
    <form id="formCliente" action="clientes.php" method="POST" onsubmit="return validarFormulario()">
        <input type="hidden" name="accion" value="registrar_cliente">
        <div class="form-grid">
            <div class="form-group">
                <label for="nombre_cliente">Nombre Completo</label>
                <input type="text" id="nombre_cliente" name="nombre_cliente" placeholder="Nombre del cliente" value="<?php echo htmlspecialchars($oldData['nombre_cliente'], ENT_QUOTES, 'UTF-8'); ?>" required>
                <div id="error_nombre" class="form-error"></div>
            </div>
            <div class="form-group">
                <label for="correo_electronico">Correo Electrónico</label>
                <input type="email" id="correo_electronico" name="correo_electronico" placeholder="ejemplo@correo.com" value="<?php echo htmlspecialchars($oldData['correo_electronico'], ENT_QUOTES, 'UTF-8'); ?>" required>
                <div id="error_correo" class="form-error"></div>
            </div>
            <div class="form-group">
                <label for="telefono_cliente">Teléfono</label>
                <input type="tel" id="telefono_cliente" name="telefono_cliente" placeholder="9999-9999" pattern="(?:\+?504[- ]?)?(?:2|3|8|9)\d{3}[- ]?\d{4}" title="Ingresa un número válido de Honduras (ej: 9999-9999 o +504 9999-9999)" value="<?php echo htmlspecialchars($oldData['telefono_cliente'], ENT_QUOTES, 'UTF-8'); ?>">
                <div id="error_telefono" class="form-error"></div>
            </div>
            <div class="form-group">
                <label for="fecha_nacimiento">Fecha de Nacimiento</label>
                <input type="date" id="fecha_nacimiento" name="fecha_nacimiento" min="<?php echo $fechaMinPermitida; ?>" max="<?php echo $fechaMaxPermitida; ?>" value="<?php echo htmlspecialchars($oldData['fecha_nacimiento'], ENT_QUOTES, 'UTF-8'); ?>" required>
                <div id="error_fecha" class="form-error"></div>
            </div>
            <div class="form-group" style="grid-column:1 / -1;">
                <label for="observaciones">Observaciones</label>
                <textarea id="observaciones" name="observaciones" placeholder="(Opcional)"><?php echo htmlspecialchars($oldData['observaciones'], ENT_QUOTES, 'UTF-8'); ?></textarea>
            </div>
        </div>
        <br>
        <button type="submit" class="button"><i class="fas fa-save"></i> Registrar Cliente</button>
    </form>
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
    const regexCorreo = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!regexCorreo.test(correo)) {
        document.getElementById('error_correo').textContent = 'Por favor, introduce un correo electrónico válido.';
        valido = false;
    }

    const telefono = document.getElementById('telefono_cliente').value.trim();
    const telefonoDigitos = telefono.replace(/\D/g, '');
    if (telefono && !/^(?:504)?([2389]\d{3})(\d{4})$/.test(telefonoDigitos)) {
        document.getElementById('error_telefono').textContent = 'Ingresa un teléfono válido de Honduras (ej: 9999-9999 o +504 9999-9999).';
        valido = false;
    }

    const fechaNacimientoValor = document.getElementById('fecha_nacimiento').value;
    const fechaError = document.getElementById('error_fecha');
    if (!fechaNacimientoValor) {
        fechaError.textContent = 'La fecha de nacimiento es obligatoria.';
        valido = false;
    } else {
        const fechaNacimientoDate = new Date(`${fechaNacimientoValor}T00:00:00`);
        if (Number.isNaN(fechaNacimientoDate.getTime())) {
            fechaError.textContent = 'La fecha de nacimiento no tiene un formato válido.';
            valido = false;
        } else {
            const hoy = new Date();
            hoy.setHours(0, 0, 0, 0);
            if (fechaNacimientoDate > hoy) {
                fechaError.textContent = 'La fecha de nacimiento no puede ser en el futuro.';
                valido = false;
            } else {
                const limiteAntiguedad = new Date();
                limiteAntiguedad.setHours(0, 0, 0, 0);
                limiteAntiguedad.setFullYear(limiteAntiguedad.getFullYear() - 120);
                if (fechaNacimientoDate < limiteAntiguedad) {
                    const mensajeAntiguedad = 'La fecha de nacimiento no es coherente (edad máxima permitida 120 años).';
                    fechaError.textContent = mensajeAntiguedad;
                    alert(mensajeAntiguedad);
                    valido = false;
                } else {
                    let edad = hoy.getFullYear() - fechaNacimientoDate.getFullYear();
                    const mes = hoy.getMonth() - fechaNacimientoDate.getMonth();
                    if (mes < 0 || (mes === 0 && hoy.getDate() < fechaNacimientoDate.getDate())) {
                        edad--;
                    }
                    if (edad < 18) {
                        const mensajeEdad = 'El cliente debe de ser mayor de edad.';
                        fechaError.textContent = mensajeEdad;
                        alert(mensajeEdad);
                        valido = false;
                    }
                }
            }
        }
    }

    return valido;
}

document.addEventListener('DOMContentLoaded', () => {
    const nombreInput = document.getElementById('nombre_cliente');
    if (nombreInput) {
        nombreInput.addEventListener('input', () => {
            const start = nombreInput.selectionStart;
            const end = nombreInput.selectionEnd;
            nombreInput.value = nombreInput.value.toUpperCase();
            nombreInput.setSelectionRange(start, end);
        });
    }

    const telefonoInput = document.getElementById('telefono_cliente');
    if (telefonoInput) {
        telefonoInput.addEventListener('input', () => {
            let value = telefonoInput.value.replace(/[^0-9+\- ]/g, '');
            if (value.includes('+')) {
                value = '+' + value.replace(/\+/g, '');
            }
            telefonoInput.value = value;
        });

        telefonoInput.addEventListener('blur', () => {
            const digits = telefonoInput.value.replace(/\D/g, '');
            const match = digits.match(/^(?:504)?([2389]\d{3})(\d{4})$/);
            if (match) {
                telefonoInput.value = `${match[1]}-${match[2]}`;
            }
        });
    }
});
</script>
</body>
</html>