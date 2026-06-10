<?php
session_start();

// Si no hay un usuario pendiente de verificar, no se puede estar aquí.
if (!isset($_SESSION['pending_2fa_user_id'])) {
    header("Location: ../../public/index.php?error=Acceso no autorizado");
    exit;
}

// Obtener el mensaje de error de la URL, si existe.
$error = '';
if (isset($_GET['error'])) {
    $error = htmlspecialchars($_GET['error']);
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Verificación de Dos Pasos</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background-color: #f8f9fa; }
    .card { border-radius: 0.75rem; }
    .form-control { letter-spacing: 0.5em; text-align: center; font-size: 1.25rem; }
  </style>
</head>
<body>
<div class="container vh-100 d-flex justify-content-center align-items-center">
  <div class="col-md-5 col-lg-4">
    <div class="card shadow-sm">
      <div class="card-body p-4 p-md-5">
        <h3 class="text-center mb-3">Verificación de Dos Pasos</h3>
        <p class="text-center text-muted mb-4">Abre tu aplicación de autenticación e ingresa el código de 6 dígitos.</p>
        
        <?php if ($error): ?>
          <div class="alert alert-danger text-center" role="alert">
            <?= $error ?>
          </div>
        <?php endif; ?>

        <form action="confirmar_2fa.php" method="POST">
          <div class="mb-3">
            <label for="codigo" class="form-label visually-hidden">Código de 6 dígitos</label>
            <input type="text" id="codigo" name="codigo" class="form-control" 
                   pattern="\d{6}" inputmode="numeric" maxlength="6" required autofocus 
                   autocomplete="one-time-code">
          </div>
          <div class="d-grid">
            <button type="submit" class="btn btn-primary btn-lg">Verificar Código</button>
          </div>
        </form>

        <div class="mt-4 text-center">
          <a href="cerrar_sesion.php" class="text-decoration-none">Cancelar e ir al inicio</a>
        </div>
      </div>
    </div>
  </div>
</div>
</body>
</html>