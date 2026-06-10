<?php
session_start();
$host = 'localhost';
$dbname = 'gestion_suscripciones';
$user = 'root';
$pass = '';


try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    try {
        // Prepare and execute the stored procedure
        $sql = "CALL ejecutar_todo()"; // Assuming the procedure is named 'autocalendario_procedure'
        $stmt = $pdo->prepare($sql);
        $stmt->execute();

        //echo "✅ Cron job executed successfully.";
    } catch (PDOException $e) {
        //echo "⚠️ Error executing cron job: " . $e->getMessage();
    }

} catch (PDOException $e) {
    error_log("Error PDO: " . $e->getMessage());
}

?>