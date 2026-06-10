<?php
// Desactiva salida HTML
ob_start();
error_reporting(0);
ini_set('display_errors', 0);

session_start();
require 'con_db.php';
require_once __DIR__ . '/../config/errorlogs.php';
require_once __DIR__ . '/../config/roles.php';

use App\config\errorlogs;
errorlogs::activa_error_logs();

// Verificar sesión
if (empty($_SESSION['usuario']) || empty($_SESSION['usuario_id'])) {
    echo "<script>
        alert('Debes iniciar sesión');
        window.location='../../public/index.php';
    </script>";
    exit;
}

require_permission('config', PERM_UPDATE);

// Datos BD
$usuario = "root";
$password = "";
$nombreBD = "gestion_suscripciones";

// Datos bitácora
$usuario_id = $_SESSION['usuario_id'];
$descripcion = "Realizó un Backup";
$accion = "BackupBD";

// GUARDA SALIDA SQL & BITÁCORA
try {
    if (isset($conexion) && $conexion) {

        // Registro en tbl_objetos
        @$conexion->query("
            CREATE TABLE IF NOT EXISTS tbl_objetos (
                id_objetos INT AUTO_INCREMENT PRIMARY KEY,
                objeto VARCHAR(100) NOT NULL,
                descripcion VARCHAR(255),
                tipo_objeto VARCHAR(100) NOT NULL,
                nombre_objeto VARCHAR(100)
            )
        ");

        $idObjeto = 0;
        if ($stmt = $conexion->prepare("SELECT id_objetos FROM tbl_objetos WHERE objeto='Backup' LIMIT 1")) {
            $stmt->execute();
            $stmt->bind_result($obj);
            if ($stmt->fetch()) $idObjeto = $obj;
            $stmt->close();
        }

        if ($idObjeto == 0) {
            $stmt = $conexion->prepare("INSERT INTO tbl_objetos (objeto, descripcion, tipo_objeto) VALUES ('Backup','Respaldo BD','Seguridad')");
            $stmt->execute();
            $idObjeto = $stmt->insert_id;
            $stmt->close();
        }

        // Tabla bitácora
        @$conexion->query("
            CREATE TABLE IF NOT EXISTS tbl_ms_bitacora (
                id_bitacora INT AUTO_INCREMENT PRIMARY KEY,
                fecha DATE NOT NULL,
                id_usuario INT NOT NULL,
                id_objetos INT NOT NULL,
                accion VARCHAR(100) NOT NULL,
                descripcion VARCHAR(255)
            )
        ");

        // Insertar en bitácora
        $stmt = $conexion->prepare("INSERT INTO tbl_ms_bitacora (fecha, id_usuario, id_objetos, accion, descripcion)
                                    VALUES (CURDATE(), ?, ?, ?, ?)");
        $stmt->bind_param("iiss", $usuario_id, $idObjeto, $accion, $descripcion);
        $stmt->execute();
        $stmt->close();

        $conexion->close();
    }
} catch (Throwable $e) {}

ob_end_clean();

// ============================
// GENERAR BACKUP EN CARPETA
// ============================

$fecha = date("Y-m-d_H-i-s");
$nombreArchivo = "Backup_{$nombreBD}_{$fecha}.sql";

// Carpeta de destino
$carpetaBackup = __DIR__ . "/../../BD/backups/";

if (!is_dir($carpetaBackup)) {
    mkdir($carpetaBackup, 0777, true);
}

$rutaBackup = $carpetaBackup . $nombreArchivo;

// mysqldump
$ruta_mysqldump = "C:\\xampp\\mysql\\bin\\mysqldump.exe";

$comando = "\"$ruta_mysqldump\" --user=$usuario --password=$password --skip-comments $nombreBD > \"$rutaBackup\"";

// Ejecutar
exec($comando, $salida, $resultado);

// ============================
// SWEET ALERT COMO LA IMAGEN
// ============================

echo "
<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
</head>
<body>

<script>
Swal.fire({
    title: 'Backup realizado correctamente',
    text: 'El archivo se guardó en la carpeta de backups.',
    icon: 'success',
    confirmButtonText: 'Aceptar',
    background: '#1a1a1a',
    color: 'white',
    confirmButtonColor: '#5cb85c'
}).then(() => {
    // ⬅️ REGRESAR AUTOMÁTICAMENTE AL MENÚ PRINCIPAL
    window.location.href = 'menuprincipal.php';
});
</script>

</body>
</html>
";
exit;
?>
