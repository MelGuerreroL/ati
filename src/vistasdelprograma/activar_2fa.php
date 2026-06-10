<?php
session_start();
require_once __DIR__ . '/con_db.php';
require_once __DIR__ . '/totp.php';

// Verifica que haya sesión activa (usuario logueado correctamente)
if (!isset($_SESSION['usuario_id'])) {
    header("Location: index.php?error=Debe iniciar sesión primero");
    exit;
}

$user_id = $_SESSION['usuario_id'];

// Obtener datos del usuario
$sql = "SELECT nombre_usuario, correo_electronico, totp_secret, is_2fa_enabled FROM tbl_ms_usuario WHERE id_usuario = ?";
$stmt = $conexion->prepare($sql);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$res = $stmt->get_result();
$user = $res->fetch_assoc();
$stmt->close();

if (!$user) {
    header("Location: index.php?error=Usuario no encontrado");
    exit;
}

// Si ya está activado, redirige a verificar
if ($user['is_2fa_enabled'] == 1) {
    header("Location: verificar_2fa.php");
    exit;
}

// Generar secreto nuevo
$secret = generate_totp_secret();
$_SESSION['pending_totp_secret'] = $secret;

// Crear enlace para Google Authenticator
$issuer = urlencode('SistemaSuscripciones');
$account = urlencode($user['correo_electronico']);
$otpauth = "otpauth://totp/{$issuer}:{$account}?secret={$secret}&issuer={$issuer}&digits=6";

// Generar el código QR usando la API de Google Charts (método original)
$qrUrl = "https://chart.googleapis.com/chart?chs=200x200&cht=qr&chl=" . urlencode($otpauth);
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Activar doble autenticación</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-md-6">
      <div class="card shadow-sm border-0">
        <div class="card-body">
          <h4 class="mb-3 text-center">Activar Doble Autenticación</h4>
          <p>Escanea este código QR con tu aplicación <strong>Google Authenticator</strong> o <strong>Authy</strong>.</p>
          <div class="text-center">
            <img src="<?=$qrUrl?>" alt="Código QR" class="border rounded mb-3">
          </div>
          <p class="text-center">O ingresa manualmente este código: <strong><?=$secret?></strong></p>

          <form action="confirmar_2fa.php" method="POST">
            <div class="mb-3">
              <label for="codigo" class="form-label">Código de 6 dígitos</label>
              <input type="text" id="codigo" name="codigo" class="form-control" pattern="\d{6}" maxlength="6" required>
            </div>
            <button type="submit" class="btn btn-success w-100">Confirmar y Activar</button>
          </form>

          <div class="mt-3 text-center">
            <a href="perfil.php" class="text-decoration-none">Cancelar y volver al perfil</a>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
</body>
</html>
