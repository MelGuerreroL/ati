<?php
session_start();
$host = 'localhost';
$dbname = 'gestion_suscripciones';
$user = 'root';
$pass = '';

// Incluir PHPMailer manualmente
require '../../vendor/phpmailer/phpmailer/src/Exception.php';
require '../../vendor/phpmailer/phpmailer/src/PHPMailer.php';
require '../../vendor/phpmailer/phpmailer/src/SMTP.php';
require_once __DIR__ . '/bitacora_helpers.php';
require 'con_db.php';
require 'AutocalendarioN.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Verificar si ya existe el objeto "NOTIFICACIONES"
    $consulta = $pdo->prepare("SELECT COUNT(*) FROM tbl_objetos WHERE objeto = :objeto");
    $consulta->execute(['objeto' => 'NOTIFICACIONES']);
    $existe = $consulta->fetchColumn();

    if ($existe == 0) {
        $insertar = $pdo->prepare("INSERT INTO tbl_objetos (objeto, descripcion, tipo_objeto) VALUES (:objeto, :descripcion, :tipo_objeto)");
        $insertar->execute([
            'objeto' => 'NOTIFICACIONES',
            'descripcion' => 'Envia notificaciones a los clientes previamente procesados',
            'tipo_objeto' => 'Módulo'
        ]);
    } 

    // Verificar si ya existe el objeto "CALENDARIO"
    $consulta->execute(['objeto' => 'CALENDARIO']);
    $existe = $consulta->fetchColumn();

    if ($existe == 0) {
        $insertar = $pdo->prepare("INSERT INTO tbl_objetos (objeto, descripcion, tipo_objeto) VALUES (:objeto, :descripcion, :tipo_objeto)");
        $insertar->execute([
            'objeto' => 'CALENDARIO',
            'descripcion' => 'Inserta en la tabla calendario apartir de suscripciones',
            'tipo_objeto' => 'Módulo'
        ]);
    } 

    // Paso 1: Buscar todas las suscripciones con estado 2 o 3
    $sql = "SELECT id_suscripcion FROM tbl_suscripcion WHERE id_estadoSuscripcion IN (2, 3)";
    $stmt = $pdo->query($sql);

    while ($fila = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $id_suscripcion = $fila['id_suscripcion'];
        
        // Paso 2: Extraer todos los datos de esa suscripción
        $sqlDetalle = "SELECT id_suscripcion, id_cliente, id_plan, fecha_inicio, fecha_fin, precio, id_estadoSuscripcion
                       FROM tbl_suscripcion 
                       WHERE id_suscripcion = :id_suscripcion";
        $stmtDetalle = $pdo->prepare($sqlDetalle);
        $stmtDetalle->bindParam(':id_suscripcion', $id_suscripcion);
        $stmtDetalle->execute();

        if ($row = $stmtDetalle->fetch(PDO::FETCH_ASSOC)) {
            $id_suscripcion       = $row['id_suscripcion'];
            $id_cliente           = $row['id_cliente'];
            $id_plan              = $row['id_plan'];
            $fecha_inicio         = $row['fecha_inicio'];
            $fecha_fin            = $row['fecha_fin'];
            $precio               = $row['precio'];
            $id_estadoSuscripcion = $row['id_estadoSuscripcion'];

            // Inserción en tbl_calendario
                // Paso 1: Verificar si existe algún registro con el mismo id_suscripcion y meta = 1
                $sqlBlock = "SELECT COUNT(*) 
                            FROM tbl_calendario 
                            WHERE id_suscripcion = :id_suscripcion
                            AND meta = 1";
                $stmtBlock = $pdo->prepare($sqlBlock);
                $stmtBlock->bindValue(':id_suscripcion', $id_suscripcion, PDO::PARAM_INT);
                $stmtBlock->execute();
                $hayBloqueo = (int)$stmtBlock->fetchColumn() > 0;

                // Paso 2: Condición
                if (!$hayBloqueo){
                $fecha_actual = date('Y-m-d');
                $hora_actual = date('H:i:s');

                $sqlInsert = "INSERT INTO tbl_calendario (
                    id_suscripcion, id_cliente, fecha_notificacion, hora_notificacion, id_tipoNotificacion, contenido, meta
                ) VALUES (
                    :id_suscripcion, :id_cliente, :fecha_notificacion, :hora_notificacion, :id_tipoNotificacion, :contenido, :meta
                )";

                $stmtInsert = $pdo->prepare($sqlInsert);
                $stmtInsert->bindParam(':id_suscripcion', $id_suscripcion);
                $stmtInsert->bindParam(':id_cliente', $id_cliente);
                $stmtInsert->bindParam(':fecha_notificacion', $fecha_actual);
                $stmtInsert->bindParam(':hora_notificacion', $hora_actual);
                $stmtInsert->bindValue(':id_tipoNotificacion', 1);
                $stmtInsert->bindValue(':contenido', "VENCIMIENTO");
                $stmtInsert->bindValue(':meta', '1');

                if ($stmtInsert->execute()) {
                    // Éxito silencioso
                } else {
                    // Error silencioso
                }
            }
        }
    }

} catch (PDOException $e) {
    error_log("Error PDO: " . $e->getMessage());
}

// Botón Enviar
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'actualizar_calendario') {
    require 'con_db.php';

    mysqli_set_charset($conexion, "utf8");

    $fechaEnvio = date('Y-m-d H:i:s');

    // Leer registros con estado 1 o 3
    $sqlSelect = "SELECT id_calendario, intento_count FROM tbl_calendario WHERE id_estadoCalendario IN (1, 3)";
    $resultado = mysqli_query($conexion, $sqlSelect);

    if ($resultado && mysqli_num_rows($resultado) > 0) {
        while ($fila = mysqli_fetch_assoc($resultado)) {
            $id = $fila['id_calendario'];
            $intentos = $fila['intento_count'] + 1;

            $sqlUpdate = "UPDATE tbl_calendario 
                        SET fecha_envio = '$fechaEnvio', 
                            intento_count = $intentos 
                        WHERE id_calendario = $id";

            if (mysqli_query($conexion, $sqlUpdate)) {
                // Actualizado
            } else {
                error_log("Error al actualizar: " . mysqli_error($conexion));
            }
        }
    }

    // Envío de notificación
    $sqlCalendario = "SELECT id_calendario, id_suscripcion FROM tbl_calendario WHERE id_estadoCalendario IN (1, 3)";
    $resultCalendario = mysqli_query($conexion, $sqlCalendario);

    if ($resultCalendario && mysqli_num_rows($resultCalendario) > 0) {
        while ($rowCalendario = mysqli_fetch_assoc($resultCalendario)) {
            $id_calendario = $rowCalendario['id_calendario'];
            $id_suscripcion = $rowCalendario['id_suscripcion'];

            // Buscar id_cliente en tbl_suscripcion
            $sqlSuscripcion = "SELECT id_cliente, id_plan, id_estadoSuscripcion  FROM tbl_suscripcion WHERE id_suscripcion = ?";
            $stmtSuscripcion = $conexion->prepare($sqlSuscripcion);
            $stmtSuscripcion->bind_param("i", $id_suscripcion);
            $stmtSuscripcion->execute();
            $resultSuscripcion = $stmtSuscripcion->get_result();

            if ($resultSuscripcion->num_rows > 0) {
                $rowSuscripcion = $resultSuscripcion->fetch_assoc();
                $id_cliente = $rowSuscripcion['id_cliente'];
                $id_plan = $rowSuscripcion['id_plan'];
                $id_estadoSuscripcion = $rowSuscripcion['id_estadoSuscripcion'];

                // Buscar correo_electronico en tbl_cliente
                $sqlCorreo = "SELECT correo_electronico, nombre_cliente FROM tbl_cliente WHERE id_cliente = ?";
                $stmtCorreo = $conexion->prepare($sqlCorreo);
                $stmtCorreo->bind_param("i", $id_cliente);
                $stmtCorreo->execute();
                $resultCorreo = $stmtCorreo->get_result();

                if ($resultCorreo->num_rows > 0) {
                    $rowCorreo = $resultCorreo->fetch_assoc();
                    $correo = $rowCorreo['correo_electronico'];
                    $nombre_cliente = $rowCorreo['nombre_cliente'];

                    try {
                        // Mapear id_plan a texto
                        switch ($id_plan) {
                            case 1 : $plan_label = "vencido"; break;
                            case 6: $plan_label = "Anual"; break;
                            case 7: $plan_label = "Diario"; break;
                            case 4: $plan_label = "Mensual"; break;
                            case 3: $plan_label = "Quincenal"; break;
                            case 2: $plan_label = "Semanal"; break;
                            case 5: $plan_label = "Semestral"; break;
                            default: $plan_label = "Adquirido";
                        }

                        // Configurar PHPMailer
                        $mail = new PHPMailer(true);
                        $mail->isSMTP();
                        $mail->Host = 'smtp.gmail.com';
                        $mail->SMTPAuth = true;
                        $mail->Username = 'castilloemer2002@gmail.com';
                        $mail->Password = 'qxhdjyuydcblwicm'; // SIN espacios
                        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                        $mail->Port = 587;
                        $mail->SMTPOptions = array(
                            'ssl' => array(
                                'verify_peer' => false,
                                'verify_peer_name' => false,
                                'allow_self_signed' => true
                            )
                        );

                        $mail->setFrom('castilloemer2002@gmail.com', 'Sistema de Suscripciones');
                        $mail->addAddress($correo, $nombre_cliente); // CORREGIDO
                        $mail->addReplyTo('castilloemer2002@gmail.com', 'Soporte');

                        $mail->isHTML(true);
                        $mail->Subject = 'Aviso de vencimiento de suscripción - GYMS Club';
                        $mail->CharSet = 'UTF-8';

                        if($id_estadoSuscripcion  == 2){
                            //Mensaje para suscripción vencida
                            
                        $mail->Body = "
                        <html>
                        <head>
                            <style>
                                body { 
                                    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
                                    line-height: 1.6; 
                                    color: #2E2E2E; 
                                    margin: 0; 
                                    padding: 20px; 
                                    background: #FFE5B4; 
                                }
                                .container { 
                                    max-width: 600px; 
                                    margin: 0 auto; 
                                    background: white; 
                                    border-radius: 15px; 
                                    overflow: hidden; 
                                    box-shadow: 0 10px 30px rgba(0,0,0,0.1); 
                                }
                                .header { 
                                    background: linear-gradient(135deg, #FFA500, #FF8C00); 
                                    color: white; 
                                    padding: 35px; 
                                    text-align: center; 
                                }
                                .header h1 { 
                                    margin: 0; 
                                    font-size: 28px; 
                                    font-weight: bold; 
                                }
                                .content { 
                                    padding: 35px; 
                                }
                                .alert-box { 
                                    background: rgba(255,193,7,0.1); 
                                    border: 1px solid rgba(255,193,7,0.5); 
                                    padding: 25px; 
                                    border-radius: 12px; 
                                    margin: 25px 0; 
                                    text-align: center; 
                                    font-weight: bold; 
                                    color: #856404; 
                                }
                                .footer { 
                                    padding: 25px; 
                                    background: #f8f9fa; 
                                    text-align: center; 
                                    font-size: 14px; 
                                    color: #888; 
                                    border-top: 1px solid #eee; 
                                }
                            </style>
                        </head>
                        <body>
                            <div class='container'>
                                <div class='header'>
                                    <h1>⚠️ Aviso de Vencimiento</h1>
                                </div>
                                <div class='content'>
                                    <h2>¡Hola $nombre_cliente!</h2>
                                    <p>Te escribimos desde <strong>GYM CLUB</strong> para informarte sobre el estado de tu suscripción.</p>

                                    <div class='alert-box'>
                                        🕒 <strong>Tu suscripción en GYM CLUB a vencido </strong>
                                    </div>
                                    
                                    <p>Para seguir disfrutando de nuestros servicios, te invitamos a renovar tu suscripción y continuar tu camino hacia una vida más activa y saludable.</p>
                                    
                                    <p><strong>¿Qué puedes hacer?</strong></p>
                                    <ul style='color: #555; padding-left: 20px;'>
                                        <li>Visita nuestras instalaciones para realizar el pago</li>
                                        <li>Contacta a nuestro equipo de atención al cliente</li>
                                        <li>Consulta nuestros nuevos planes y promociones</li>
                                    </ul>
                                    
                                    <p>¡Gracias por ser parte de la familia GYM Club!</p>
                                </div>
                                <div class='footer'>
                                    <p><strong>GYM Club - Sistema de Gestión</strong></p>
                                    <p>Este es un mensaje automático - No responder</p>
                                </div>
                            </div>
                        </body>
                        </html>
                        ";

                        }else{
                            //Mensaje para suscripción próxima a vencer
                             $mail->Body = "
                        <html>
                        <head>
                            <style>
                                body { 
                                    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
                                    line-height: 1.6; 
                                    color: #2E2E2E; 
                                    margin: 0; 
                                    padding: 20px; 
                                    background: #FFE5B4; 
                                }
                                .container { 
                                    max-width: 600px; 
                                    margin: 0 auto; 
                                    background: white; 
                                    border-radius: 15px; 
                                    overflow: hidden; 
                                    box-shadow: 0 10px 30px rgba(0,0,0,0.1); 
                                }
                                .header { 
                                    background: linear-gradient(135deg, #FFA500, #FF8C00); 
                                    color: white; 
                                    padding: 35px; 
                                    text-align: center; 
                                }
                                .header h1 { 
                                    margin: 0; 
                                    font-size: 28px; 
                                    font-weight: bold; 
                                }
                                .content { 
                                    padding: 35px; 
                                }
                                .alert-box { 
                                    background: rgba(255,193,7,0.1); 
                                    border: 1px solid rgba(255,193,7,0.5); 
                                    padding: 25px; 
                                    border-radius: 12px; 
                                    margin: 25px 0; 
                                    text-align: center; 
                                    font-weight: bold; 
                                    color: #856404; 
                                }
                                .footer { 
                                    padding: 25px; 
                                    background: #f8f9fa; 
                                    text-align: center; 
                                    font-size: 14px; 
                                    color: #888; 
                                    border-top: 1px solid #eee; 
                                }
                            </style>
                        </head>
                        <body>
                            <div class='container'>
                                <div class='header'>
                                    <h1>⚠️ Aviso de Vencimiento</h1>
                                </div>
                                <div class='content'>
                                    <h2>¡Hola $nombre_cliente!</h2>
                                    <p>Te escribimos desde <strong>GYM CLUB</strong> para informarte sobre el estado de tu suscripción.</p>

                                    <div class='alert-box'>
                                        🕒 <strong>Tu suscripción $plan_label en GYM CLUB está a pocos días de vencer</strong>
                                    </div>
                                    
                                    <p>Para seguir disfrutando de nuestros servicios, te invitamos a renovar tu suscripción y continuar tu camino hacia una vida más activa y saludable.</p>
                                    
                                    <p><strong>¿Qué puedes hacer?</strong></p>
                                    <ul style='color: #555; padding-left: 20px;'>
                                        <li>Visita nuestras instalaciones para realizar el pago</li>
                                        <li>Contacta a nuestro equipo de atención al cliente</li>
                                        <li>Consulta nuestros nuevos planes y promociones</li>
                                    </ul>
                                    
                                    <p>¡Gracias por ser parte de la familia GYM Club!</p>
                                </div>
                                <div class='footer'>
                                    <p><strong>GYM Club - Sistema de Gestión</strong></p>
                                    <p>Este es un mensaje automático - No responder</p>
                                </div>
                            </div>
                        </body>
                        </html>
                        ";
                        }
                       

                        $mail->send();
                        echo '<script>alert("✅ Correo enviado exitosamente");</script>';

                        // Actualizar estado a 2 (enviado)
                        $sqlUpdate = "UPDATE tbl_calendario SET id_estadoCalendario = 2 WHERE id_calendario = ?";
                        $stmt = $conexion->prepare($sqlUpdate);
                        $stmt->bind_param("i", $id_calendario);
                        $stmt->execute();
                        $stmt->close();

                    } catch (Exception $e) {
                        error_log("Error PHPMailer: " . $e->getMessage());
                        echo '<script>alert("❌ Error: ' . addslashes($e->getMessage()) . '");</script>';

                        // Actualizar estado a 3 (fallido)
                        $sqlUpdate = "UPDATE tbl_calendario SET id_estadoCalendario = 3 WHERE id_calendario = ?";
                        $stmt = $conexion->prepare($sqlUpdate);
                        $stmt->bind_param("i", $id_calendario);
                        $stmt->execute();
                        $stmt->close();
                    }

                    $stmtCorreo->close();
                }
                $stmtSuscripcion->close();
            }
        }
    }

    mysqli_close($conexion);
    exit;
}

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1"/>
    <title>Notificaciones</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css"/>
    <style>
        :root {
            --orange-primary: #f0ad4e;
            --orange-dark: #ec971f;
            --text-dark: #333;
            --text-light: #fff;
            --background-light: #f9f9f9;
            --border-color: #ddd;
        }
        body {
            font-family: 'Segoe UI', sans-serif;
            background: var(--background-light);
            margin: 0;
            padding: 20px;
            color: var(--text-dark);
        }
        .page-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;}
        .page-header h1{color:var(--text-dark);font-size:1.6em;display:flex;align-items:center;gap:10px;}
        .container {
            background: var(--text-light);
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,.08);
            margin-bottom: 20px;
            border-top: 4px solid var(--orange-primary);
        }
        .controls { display:flex; gap:12px; align-items:center; margin-bottom:15px; flex-wrap:wrap; }
        input[type="text"]{padding:10px;border-radius:5px;border:1px solid #ccc;width:320px;box-sizing:border-box;}
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 12px;
        }
        th, td {
            padding: 12px 15px;
            border-bottom: 1px solid var(--border-color);
            text-align: left;
        }
        th {
            background: var(--orange-dark);
            color: var(--text-light);
            text-transform: uppercase;
            font-size: 0.85em;
        }
        input, textarea, select, button{font-family:inherit;font-size:1rem;}
        input, textarea, select{padding:10px;border-radius:5px;border:1px solid #ccc;box-sizing:border-box;}
        .button{background:var(--orange-primary);color:var(--text-light);border:none;cursor:pointer;font-weight:600;padding:10px 15px;border-radius:5px;}
        .button:hover{background:var(--orange-dark);}
        .button.reset{background:#6c757d;}
        .back-button{background:transparent;color:var(--text-dark);border:none;text-decoration:none;display:inline-flex;align-items:center;gap:8px;font-weight:600;cursor:pointer;}
        .back-button:hover{color:var(--orange-dark);}
    </style>
</head>
<body>
    <div class="page-header">
        <h1><i class="fas fa-bell"></i> Notificaciones</h1>
        <a class="back-button" href="menuprincipal.php"><i class="fas fa-arrow-left"></i> Volver al Menú</a>
    </div>

    <div class="container">
        <div class="controls">
<form action="reporte_calendario_pdf.php" method="get" target="_blank">
    <input id="filtro_nombre" name="nombre" type="text" placeholder="Buscar por nombre..." autocomplete="off">
    
    <select id="filtro_estado" name="estado" style="padding:10px;border-radius:5px;border:1px solid #ccc;">
        <option value="">Todos los estados</option>
        <option value="PENDIENTE">Pendiente</option>
        <option value="ENVIADO">Enviado</option>
        <option value="FALLIDO">Fallido</option>
    </select>

    <select id="filtro_contenido" name="contenido" style="padding:10px;border-radius:5px;border:1px solid #ccc;">
        <option value="">Todos los contenidos</option>
        <option value="VENCIMIENTO">Vencimiento</option>
        <option value="AVISO SEMANAL">Aviso Semanal</option>
        <option value="AVISO QUINCENAL">Aviso Quincenal</option>
        <option value="AVISO MENSUAL">Aviso Mensual</option>
        <option value="AVISO SEMESTRAL">Aviso Semestral</option>
        <option value="AVISO ANUAL">Aviso Anual</option>
    </select>
<button type="submit" 
        style="background:#dc3545; color:#fff; border:none; padding:10px 15px; border-radius:5px; cursor:pointer;">
    <i class="fas fa-file-pdf"></i> Generar reporte
</button>
    
</form>     
            <button id="btnReset" class="button reset" type="button">Restablecer</button>
            <button id="btnEnviar" class="button" type="button">Enviar</button>
            <button id="btnConfigurar" class="button" type="button" onclick="window.location.href='configuracion_notificaciones.php';">Configurar</button>
            <button id="btnCalendario" class="button" type="button" onclick="window.location.href='Autocalendario.php';">Calendario</button>
        </div>

        <div style="overflow-x:auto;">
            <table id="tablaClientes">
                <thead>
                    <tr>
                        <th>N. Suscripción</th>
                        <th>Nombre Cliente</th>
                        <th>Correo</th>
                        <th>Estado</th>
                        <th>Fecha Notificación</th>
                        <th>Hora</th>
                        <th>Fecha Envío</th>
                        <th>Intentos</th>
                        <th>Contenido</th>
                        <th>N. Calendario</th>
                        <th>Est. Suscripción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    require 'con_db.php';

                    $sql_calendario = "SELECT id_calendario, id_suscripcion, id_estadoCalendario, fecha_notificacion, hora_notificacion, fecha_envio, intento_count, contenido FROM tbl_calendario";
                    $resultado_calendario = $conexion->query($sql_calendario);

                    if ($resultado_calendario && $resultado_calendario->num_rows > 0) {
                        while ($fila_calendario = $resultado_calendario->fetch_assoc()) {
                            $id_calendario = htmlspecialchars($fila_calendario['id_calendario']);
                            $id_suscripcion = $fila_calendario['id_suscripcion'];
                            $id_estadoCalendario = $fila_calendario['id_estadoCalendario'];
                            $estado_texto = ($id_estadoCalendario == 3) ? 'FALLIDO' : (($id_estadoCalendario == 2) ? 'ENVIADO' : 'PENDIENTE');
                            $fecha_notificacion = htmlspecialchars($fila_calendario['fecha_notificacion'] ?? '');
                            $hora_notificacion = htmlspecialchars($fila_calendario['hora_notificacion'] ?? '');
                            $fecha_envio = htmlspecialchars($fila_calendario['fecha_envio'] ?? '');
                            $intento_count = htmlspecialchars($fila_calendario['intento_count']);
                            $contenido = htmlspecialchars($fila_calendario['contenido']);

                            $sql_suscripcion = "SELECT id_cliente, id_estadoSuscripcion FROM tbl_suscripcion WHERE id_suscripcion = ?";
                            $stmt_sus = $conexion->prepare($sql_suscripcion);
                            $stmt_sus->bind_param("i", $id_suscripcion);
                            $stmt_sus->execute();
                            $resultado_suscripcion = $stmt_sus->get_result();

                            if ($resultado_suscripcion->num_rows > 0) {
                                $fila_suscripcion = $resultado_suscripcion->fetch_assoc();
                                $id_cliente = $fila_suscripcion['id_cliente'];
                                $id_estadoSuscripcion = htmlspecialchars($fila_suscripcion['id_estadoSuscripcion']);

                                $sql_cliente = "SELECT nombre_cliente, correo_electronico FROM tbl_cliente WHERE id_cliente = ?";
                                $stmt_cli = $conexion->prepare($sql_cliente);
                                $stmt_cli->bind_param("i", $id_cliente);
                                $stmt_cli->execute();
                                $resultado_cliente = $stmt_cli->get_result();

                                if ($resultado_cliente->num_rows > 0) {
                                    $fila_cliente = $resultado_cliente->fetch_assoc();
                                    $nombre_cliente = htmlspecialchars($fila_cliente['nombre_cliente']);
                                    $correo_electronico = htmlspecialchars($fila_cliente['correo_electronico']);

                                    switch ($estado_texto) {
                                        case 'FALLIDO':
                                            $color = 'red';
                                            $icono = '❌';
                                            break;
                                        case 'ENVIADO':
                                            $color = 'green';
                                            $icono = '✅';
                                            break;
                                        case 'PENDIENTE':
                                            $color = 'orange';
                                            $icono = '⏳';
                                            break;
                                        default:
                                            $color = 'black';
                                            $icono = 'ℹ️';
                                    }

                                    echo "<tr>
                                            <td>$id_suscripcion</td>
                                            <td class='nombre-cliente'>$nombre_cliente</td>
                                            <td>$correo_electronico</td>
                                            <td style='color:$color;font-weight:bold;' class='estado-celda'>$icono $estado_texto</td>
                                            <td>$fecha_notificacion</td>
                                            <td>$hora_notificacion</td>
                                            <td>$fecha_envio</td>
                                            <td>$intento_count</td>
                                            <td class='contenido-celda'>$contenido</td>
                                            <td>$id_calendario</td>
                                            <td>$id_estadoSuscripcion</td>
                                          </tr>";
                                }
                                $stmt_cli->close();
                            }
                            $stmt_sus->close();
                        }
                    } else {
                        echo "<tr><td colspan='12' style='text-align:center;'>No hay registros en tbl_calendario.</td></tr>";
                    }
                    $conexion->close();
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
    (function(){
        const inputNombre = document.getElementById('filtro_nombre');
        const selectEstado = document.getElementById('filtro_estado');
        const selectContenido = document.getElementById('filtro_contenido');
        const tabla = document.getElementById('tablaClientes');
        const btnReset = document.getElementById('btnReset');
        if (!inputNombre || !tabla) return;

        function normalizeText(s) {
            return String(s || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
        }

        function filtrarTabla() {
            const qNombre = normalizeText(inputNombre.value.trim());
            const qEstado = selectEstado.value.toUpperCase();
            const qContenido = selectContenido.value.toUpperCase();
            
            const rows = tabla.querySelectorAll('tbody tr');
            rows.forEach(row => {
                const nameCell = row.querySelector('.nombre-cliente');
                const name = nameCell ? normalizeText(nameCell.textContent) : '';
                
                const estadoCell = row.querySelector('.estado-celda');
                const estado = estadoCell ? estadoCell.textContent.trim() : '';
                
                const contenidoCell = row.querySelector('.contenido-celda');
                const contenido = contenidoCell ? contenidoCell.textContent.trim().toUpperCase() : '';
                
                // Validar filtro de nombre
                const matchNombre = qNombre === '' || name.indexOf(qNombre) !== -1;
                
                // Validar filtro de estado
                const matchEstado = qEstado === '' || estado.includes(qEstado);
                
                // Validar filtro de contenido
                const matchContenido = qContenido === '' || contenido.includes(qContenido);
                
                // Mostrar fila solo si cumple todos los filtros
                row.style.display = (matchNombre && matchEstado && matchContenido) ? '' : 'none';
            });
        }

        inputNombre.addEventListener('input', filtrarTabla);
        selectEstado.addEventListener('change', filtrarTabla);
        selectContenido.addEventListener('change', filtrarTabla);

        btnReset.addEventListener('click', function(){
            inputNombre.value = '';
            selectEstado.value = '';
            selectContenido.value = '';
            filtrarTabla();
        });
    })();

    document.getElementById('btnEnviar').addEventListener('click', function() {
        if (confirm('¿Deseas enviar las notificaciones pendientes?')) {
            fetch('calendario.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'accion=actualizar_calendario'
            })
            .then(response => response.text())
            .then(data => {
                setTimeout(() => window.location.reload(), 1500);
            })
            .catch(error => alert('❌ Error: ' + error));
        }
    });
    </script>

</body>
</html>


