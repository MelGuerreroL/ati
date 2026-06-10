<?php
require_once __DIR__ . '/../config/errorlogs.php';// Ajusta la ruta según tu estructura

use App\config\errorlogs;
errorlogs::activa_error_logs();
// Configuración de la conexión (considera usar variables de entorno para mayor seguridad)
$host = 'localhost';
$db = 'gestion_suscripciones';
$user = 'root';
$password = '';

try {
    // Crear una conexión PDO con manejo de errores y configuración de charset
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); 

    // Consulta simple para verificar la conexión
    $stmt = $pdo->query("SELECT 1");

    if ($stmt->fetchColumn() === 1) {
        echo "Conexión exitosa a la base de datos.<br>";
    } else {
        echo "Error al verificar la conexión.<br>";
    }
} catch (PDOException $e) {
    // Manejo de errores más detallado
    echo "Error de conexión: " . $e->getMessage() . "<br>";
    echo "Código de error: " . $e->getCode() . "<br>";
}