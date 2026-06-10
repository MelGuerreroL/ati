<?php
require_once __DIR__ . '/../config/errorlogs.php';// Ajusta la ruta según tu estructura

use App\config\errorlogs;
errorlogs::activa_error_logs();
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../../public/index.php?error=Debes iniciar sesión");
    exit;
}

$conexion = new mysqli("localhost", "root", "", "gestion_suscripciones");
if ($conexion->connect_error) {
    die("Error de conexión: " . $conexion->connect_error);
}

require '../../vendor/phpmailer/phpmailer/src/Exception.php';
require '../../vendor/phpmailer/phpmailer/src/PHPMailer.php';
require '../../vendor/phpmailer/phpmailer/src/SMTP.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre_completo']);
    $correo = strtolower(trim($_POST['correo']));
    $usuario = trim($_POST['usuario']);
    $contrasena = trim($_POST['contraseña']);
    $confirmacion = trim($_POST['confirmar_contraseña'] ?? '');
    $rolSeleccionado = isset($_POST['rol']) ? intval($_POST['rol']) : 0;
    $fechaCreacion = trim($_POST['fecha_creacion'] ?? '');
    $fechaVencimiento = trim($_POST['fecha_vencimiento'] ?? '');

    // ============================
    // 🧩 Validaciones
    // ============================
    if (empty($nombre) || empty($correo) || empty($usuario) || empty($contrasena) || empty($confirmacion) || empty($fechaVencimiento)) {
        echo "<script>alert('❌ Todos los campos son obligatorios, incluido el rol, las fechas y la confirmación de contraseña.'); window.history.back();</script>";
        exit;
    }

    if ($rolSeleccionado <= 0) {
        echo "<script>alert('❌ Seleccione un rol válido.'); window.history.back();</script>";
        exit;
    }

    // 🔠 Normalizar nombre (colapsar espacios y convertir a MAYÚSCULAS)
    $nombre = preg_replace('/\s+/', ' ', $nombre);
    $nombre = strtoupper($nombre);

    // ✅ Validar que solo contenga letras mayúsculas A–Z y un solo espacio entre palabras
    if (!preg_match('/^[A-Z]+(?: [A-Z]+)*$/', $nombre)) {
        echo "<script>alert('❌ El nombre solo puede contener letras mayúsculas A-Z y un único espacio entre palabras (ej: JUAN PEREZ)'); window.history.back();</script>";
        exit;
    }

    // Validar formato del correo
    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        echo "<script>alert('❌ Correo electrónico inválido'); window.history.back();</script>";
        exit;
    }

    // Validar formato del usuario
    if (!preg_match('/^[A-Za-z0-9._-]{3,20}$/', $usuario)) {
        echo "<script>alert('❌ El nombre de usuario debe tener entre 3 y 20 caracteres y solo puede contener letras, números o puntos'); window.history.back();</script>";
        exit;
    }

    // Validar contraseña
    $patron = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_])[^^\s]{8,15}$/';
    if (!preg_match($patron, $contrasena)) {
        echo "<script>alert('❌ La contraseña debe tener entre 8 y 15 caracteres, incluir mayúscula, minúscula, número y carácter especial'); window.history.back();</script>";
        exit;
    }

    if (!hash_equals($contrasena, $confirmacion)) {
        echo "<script>alert('❌ Las contraseñas no coinciden.'); window.history.back();</script>";
        exit;
    }

    // Validar fechas
    if ($fechaCreacion === '') {
        $fechaCreacion = date('Y-m-d');
    }

    $fechaCreacionDate = DateTime::createFromFormat('Y-m-d', $fechaCreacion);
    if (!$fechaCreacionDate || $fechaCreacionDate->format('Y-m-d') !== $fechaCreacion) {
        echo "<script>alert('❌ La fecha de creación no es válida.'); window.history.back();</script>";
        exit;
    }
    $fechaCreacionDate->setTime(0, 0, 0);

    $fechaVencDate = DateTime::createFromFormat('Y-m-d', $fechaVencimiento);
    if (!$fechaVencDate || $fechaVencDate->format('Y-m-d') !== $fechaVencimiento) {
        echo "<script>alert('❌ La fecha de vencimiento no es válida.'); window.history.back();</script>";
        exit;
    }
    $fechaVencDate->setTime(0, 0, 0);

    if ($fechaVencDate < $fechaCreacionDate) {
        echo "<script>alert('❌ La fecha de vencimiento no puede ser anterior a la fecha de creación.'); window.history.back();</script>";
        exit;
    }

    $fechaCreacionTimestamp = $fechaCreacionDate->format('Y-m-d H:i:s');
    $fechaCreacion = $fechaCreacionDate->format('Y-m-d');
    $fechaVencimiento = $fechaVencDate->format('Y-m-d');

    // Validar que el rol exista
    $rolStmt = $conexion->prepare("SELECT COUNT(*) FROM tbl_ms_roles WHERE id_rol = ?");
    if (!$rolStmt) {
        echo "<script>alert('❌ No se pudo validar el rol seleccionado.'); window.history.back();</script>";
        exit;
    }
    $rolStmt->bind_param('i', $rolSeleccionado);
    $rolStmt->execute();
    $rolStmt->bind_result($rolExiste);
    $rolStmt->fetch();
    $rolStmt->close();
    if (empty($rolExiste)) {
        echo "<script>alert('❌ El rol seleccionado no es válido.'); window.history.back();</script>";
        exit;
    }

    // Verificar duplicado
    $check = $conexion->prepare("SELECT id_usuario FROM tbl_ms_usuario WHERE correo_electronico = ? OR usuario = ?");
    $check->bind_param('ss', $correo, $usuario);
    $check->execute();
    $result = $check->get_result();
    if ($result && $result->num_rows > 0) {
        echo "<script>alert('⚠️ Ya existe un usuario con ese correo o nombre de usuario'); window.history.back();</script>";
        exit;
    }
    if ($result) {
        $result->free();
    }
    $check->close();

    // Encriptar contraseña
    $hash = hash('sha512', $contrasena);

    // Generar código de verificación
    $codigoVerificacion = sprintf('%06d', random_int(0, 999999));
    $expiracionCodigo = date('Y-m-d H:i:s', strtotime('+10 minutes'));

    // Crear tabla de códigos si no existe
    $sqlTablaCodigos = "CREATE TABLE IF NOT EXISTS tbl_verificacion_codigos (
        id INT PRIMARY KEY AUTO_INCREMENT,
        email VARCHAR(100) NOT NULL,
        codigo VARCHAR(6) NOT NULL,
        expiracion DATETIME NOT NULL,
        usado TINYINT DEFAULT 0,
        intentos TINYINT DEFAULT 0,
        fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_email (email),
        INDEX idx_codigo (codigo),
        INDEX idx_expiracion (expiracion)
    )";

    if (!$conexion->query($sqlTablaCodigos)) {
        echo "<script>alert('❌ No se pudo preparar el almacenamiento del código de verificación.'); window.history.back();</script>";
        exit;
    }

    $stmtCodigo = $conexion->prepare("INSERT INTO tbl_verificacion_codigos (email, codigo, expiracion) VALUES (?, ?, ?)");
    if (!$stmtCodigo) {
        echo "<script>alert('❌ No se pudo generar el código de verificación.'); window.history.back();</script>";
        exit;
    }
    $stmtCodigo->bind_param('sss', $correo, $codigoVerificacion, $expiracionCodigo);
    if (!$stmtCodigo->execute()) {
        $stmtCodigo->close();
        echo "<script>alert('❌ Error al guardar el código de verificación.'); window.history.back();</script>";
        exit;
    }
    $stmtCodigo->close();

    // Enviar el código por correo electrónico
    try {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'castilloemer2002@gmail.com';
        $mail->Password = 'qxhd jyuy dcbl wicm';
        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
            ),
        );

        $mail->setFrom('no-reply@gestion-suscripciones.com', 'Sistema de Suscripciones');
        $mail->addAddress($correo, $nombre);
        $mail->addReplyTo('soporte@gestion-suscripciones.com', 'Soporte');
        $mail->isHTML(true);
        $mail->Subject = 'Código de verificación de usuario';
        $mail->CharSet = 'UTF-8';
        $mail->Body = "<html><head><style>body{font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;background:#FFE5B4;margin:0;padding:20px;}.container{background:white;border-radius:15px;padding:30px;box-shadow:0 10px 30px rgba(0,0,0,0.1);max-width:500px;margin:0 auto;}h2{color:#2E2E2E;margin-bottom:20px;}p{color:#555;line-height:1.6;margin-bottom:15px;}.code-box{background:linear-gradient(135deg,#FFA500,#FF8C00);color:white;padding:20px;font-size:28px;font-weight:bold;text-align:center;letter-spacing:8px;border-radius:12px;margin:20px 0;box-shadow:0 5px 15px rgba(255,165,0,0.3);}.warning{background:rgba(255,193,7,0.1);border:1px solid rgba(255,193,7,0.3);padding:15px;border-radius:8px;color:#856404;font-weight:600;margin:15px 0;}.footer{text-align:center;margin-top:25px;padding-top:15px;border-top:1px solid #eee;color:#888;font-size:14px;}</style></head><body><div class='container'><h2>🏢 Registro de Usuario - Admin</h2><p>Hola <strong>{$nombre}</strong>,</p><p>Un administrador ha registrado una cuenta para ti en el <strong>Sistema de Gestión de Suscripciones</strong>.</p><p>Para activar tu cuenta, ingresa el siguiente código de verificación:</p><div class='code-box'>{$codigoVerificacion}</div><div class='warning'>⚠️ <strong>Importante:</strong> Este código expirará en 10 minutos por motivos de seguridad.</div><p>Una vez verificado, podrás acceder al sistema con tus credenciales.</p><p>Si tienes preguntas, contacta al administrador o soporte técnico.</p><div class='footer'>Sistema de Gestión de Suscripciones<br>Mensaje automático - No responder</div></div></body></html>";
        $mail->AltBody = "Código de verificación: {$codigoVerificacion}. Este código expira en 10 minutos.";
        $mail->send();
    } catch (\PHPMailer\PHPMailer\Exception $e) {
        echo "<script>alert('❌ No se pudo enviar el código de verificación: " . addslashes($e->getMessage()) . "'); window.history.back();</script>";
        exit;
    }

    // Guardar datos en la sesión para completar el registro tras verificar el código
    if (isset($_SESSION['registro_pendiente'])) {
        unset($_SESSION['registro_pendiente']);
    }

    $_SESSION['registro_pendiente'] = [
        'usuario' => $usuario,
        'nombre_completo' => $nombre,
        'correo' => $correo,
        'contraseña_hash' => $hash,
        'rol_id' => $rolSeleccionado,
        'estado_usuario' => 'ACTIVO',
        'fecha_vencimiento' => $fechaVencimiento,
        'fecha_ultima_conexion' => $fechaCreacionTimestamp,
        'fecha_creacion' => $fechaCreacion,
        'primer_ingreso' => 1,
        'admin_id' => intval($_SESSION['usuario_id']),
        'redirect_success' => 'usuarios.php?success=' . urlencode('Usuario verificado correctamente.'),
        'redirect_error' => 'usuarios.php?error=' . urlencode('No se pudo completar la verificación del usuario.'),
        'origen' => 'admin'
    ];

    $info = urlencode('Se envió un código de verificación al correo ' . $correo . '. Ingresa el código para completar el registro.');
    header("Location: verificacion_codigo.php?origen=admin&info={$info}");
    exit;
}

$conexion->close();
?>
