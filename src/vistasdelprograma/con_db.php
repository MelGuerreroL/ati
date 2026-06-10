<?php
// con_db.php - en src/vistasdelprograma/
$host = "localhost";
$user = "root";
$pass = "";
$db   = "gestion_suscripciones";

$conexion = mysqli_connect($host, $user, $pass, $db);
if (!$conexion) {
    die("Error de conexión: " . mysqli_connect_error());
}
mysqli_set_charset($conexion, "utf8");
?>
