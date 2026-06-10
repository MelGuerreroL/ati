<?php
// Habilitar reporte de errores para depuración
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once __DIR__ . '/con_db.php'; // Usar ruta absoluta para mayor robustez

// Verificar sesión
if (empty($_SESSION['usuario_id'])) {
    header('Location: ../../public/index.php?error=Debes iniciar sesión');
    exit;
}

$user_id = $_SESSION['usuario_id'];
$mensaje = '';
$tipo_mensaje = '';

// --- ACCIÓN: DESACTIVAR 2FA ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'desactivar_2fa') {
    $stmt = $conexion->prepare("UPDATE tbl_ms_usuario SET is_2fa_enabled = 0, totp_secret = NULL WHERE id_usuario = ?");
    $stmt->bind_param('i', $user_id);
    if ($stmt->execute()) {
        $mensaje = 'La doble autenticación ha sido desactivada.';
        $tipo_mensaje = 'success';
    } else {
        $mensaje = 'Error al desactivar la doble autenticación.';
        $tipo_mensaje = 'error';
    }
    $stmt->close();
}

// Obtener la información más reciente del usuario
$stmt_user = $conexion->prepare("SELECT nombre_usuario, correo_electronico, usuario, is_2fa_enabled FROM tbl_ms_usuario WHERE id_usuario = ?");
$stmt_user->bind_param('i', $user_id);
$stmt_user->execute();
$result_user = $stmt_user->get_result();
$user = $result_user->fetch_assoc();
$stmt_user->close();

if (!$user) {
    // Si el usuario no existe, destruir sesión y redirigir
    session_destroy();
    header('Location: ../../public/index.php?error=Usuario no encontrado');
    exit;
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Mi Perfil - Sistema de Suscripciones</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css"/>
<style>
    :root {
        --color-primary: #667eea;
        --color-secondary: #764ba2;
        --color-text: #333;
        --color-light: #fff;
        --color-background: #f8f9fa;
        --color-success: #28a745;
        --color-danger: #dc3545;
    }
    body { font-family: 'Segoe UI', sans-serif; background-color: var(--color-background); margin: 0; padding: 20px; }
    .container { max-width: 800px; margin: 20px auto; background: var(--color-light); padding: 30px; border-radius: 12px; box-shadow: 0 6px 20px rgba(0,0,0,0.08); }
    .page-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #eee; padding-bottom: 15px; margin-bottom: 25px; }
    .page-header h1 { color: var(--color-text); font-size: 2em; display: flex; align-items: center; gap: 12px; margin: 0; }
    .back-button { color: var(--color-primary); text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 8px; }
    .back-button:hover { color: var(--color-secondary); }
    .profile-card { border: 1px solid #e9ecef; border-radius: 8px; padding: 20px; margin-bottom: 20px; }
    .profile-card h3 { margin-top: 0; color: var(--color-secondary); }
    .profile-info { display: grid; grid-template-columns: 150px 1fr; gap: 10px; }
    .profile-info strong { color: #555; }
    .security-section { margin-top: 30px; }
    .security-status { display: flex; align-items: center; gap: 15px; padding: 15px; border-radius: 8px; }
    .status-enabled { background-color: #e9f7ef; border-left: 5px solid var(--color-success); }
    .status-disabled { background-color: #fbe9e7; border-left: 5px solid var(--color-danger); }
    .status-icon { font-size: 1.5em; }
    .status-enabled .status-icon { color: var(--color-success); }
    .status-disabled .status-icon { color: var(--color-danger); }
    .btn { padding: 10px 20px; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-block; text-align: center; }
    .btn-success { background-color: var(--color-success); color: var(--color-light); }
    .btn-danger { background-color: var(--color-danger); color: var(--color-light); }
    .btn:hover { opacity: 0.9; transform: translateY(-1px); }
    .message { padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: center; font-weight: 500; }
    .message.success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; }
    .message.error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; }
</style>
</head>
<body>

<div class="container">
    <div class="page-header">
        <h1><i class="fas fa-user-shield"></i> Mi Perfil y Seguridad</h1>
        <a class="back-button" href="menuprincipal.php"><i class="fas fa-arrow-left"></i> Volver al Menú</a>
    </div>

    <?php if ($mensaje): ?>
        <div class="message <?php echo $tipo_mensaje; ?>">
            <?php echo htmlspecialchars($mensaje); ?>
        </div>
    <?php endif; ?>

    <div class="profile-card">
        <h3><i class="fas fa-user-circle"></i> Información del Usuario</h3>
        <div class="profile-info">
            <strong>Nombre:</strong>
            <span><?php echo htmlspecialchars($user['nombre_usuario']); ?></span>
            <strong>Usuario:</strong>
            <span><?php echo htmlspecialchars($user['usuario']); ?></span>
            <strong>Correo:</strong>
            <span><?php echo htmlspecialchars($user['correo_electronico']); ?></span>
        </div>
    </div>

    <div class="security-section">
        <h3><i class="fas fa-shield-alt"></i> Doble Autenticación (2FA)</h3>
        
        <?php if ($user['is_2fa_enabled']): ?>
            <!-- Estado: 2FA Activado -->
            <div class="security-status status-enabled">
                <div class="status-icon"><i class="fas fa-check-circle"></i></div>
                <div>
                    <strong>Estado: Activado</strong>
                    <p>Tu cuenta está protegida con doble autenticación.</p>
                </div>
            </div>
            <p>Si deseas desactivar la doble autenticación, puedes hacerlo aquí. Ten en cuenta que esto reducirá la seguridad de tu cuenta.</p>
            <form action="perfil.php" method="POST" onsubmit="return confirm('¿Estás seguro de que quieres desactivar la doble autenticación?');">
                <input type="hidden" name="accion" value="desactivar_2fa">
                <button type="submit" class="btn btn-danger">
                    <i class="fas fa-shield-slash"></i> Desactivar 2FA
                </button>
            </form>

        <?php else: ?>
            <!-- Estado: 2FA Desactivado -->
            <div class="security-status status-disabled">
                <div class="status-icon"><i class="fas fa-times-circle"></i></div>
                <div>
                    <strong>Estado: Desactivado</strong>
                    <p>Tu cuenta no está protegida con doble autenticación.</p>
                </div>
            </div>
            <p>Recomendamos encarecidamente activar la doble autenticación para mejorar la seguridad de tu cuenta. Serás redirigido para escanear un código QR con tu aplicación de autenticación (como Google Authenticator).</p>
            <a href="activar_2fa.php" class="btn btn-success">
                <i class="fas fa-shield-check"></i> Activar 2FA Ahora
            </a>
        <?php endif; ?>
    </div>

</div>

</body>
</html>