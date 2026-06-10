<?php
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = strtolower(trim($_POST["email"]));
    $regex = "/^[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$/";

    if (preg_match($regex, $email)) {
        $mensaje = "✅ Correo válido y guardado simbólicamente: " . htmlspecialchars($email);
    } else {
        $mensaje = "❌ Correo inválido. Usa solo minúsculas y símbolos permitidos.";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Formulario de Correo</title>
  <style>
    .error { color: red; }
    .valid { color: green; }
  </style>
  <script>
    function validarCorreo(input) {
      const mensaje = document.getElementById("mensaje");
      const valor = input.value.toLowerCase();
      input.value = valor;

      const regex = /^[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$/;

      if (valor.length < 4) {
        mensaje.textContent = "";
        mensaje.className = "";
      } else if (regex.test(valor)) {
        mensaje.textContent = "✅ Correo válido.";
        mensaje.className = "valid";
      } else {
        mensaje.textContent = "❌ Correo inválido. Solo minúsculas y símbolos permitidos.";
        mensaje.className = "error";
      }
    }
  </script>
</head>
<body>
  <form method="POST">
    <label for="email">Correo electrónico:</label>
    <input type="text" name="email" id="email" required
           oninput="validarCorreo(this)">
    <button type="submit" name="guardar">Guardar</button>
  </form>

  <p id="mensaje"><?php echo isset($mensaje) ? $mensaje : ''; ?></p>
</body>
</html>
