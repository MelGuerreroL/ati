<?php
session_start();   


// Incluir PHPMailer manualmente
require '../../vendor/phpmailer/phpmailer/src/Exception.php';
require '../../vendor/phpmailer/phpmailer/src/PHPMailer.php';
require '../../vendor/phpmailer/phpmailer/src/SMTP.php';
require_once __DIR__ . '/bitacora_helpers.php';
require 'con_db.php';


use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;


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
                            case 148: $plan_label = "Anual"; break;
                            case 149: $plan_label = "Diario"; break;
                            case 123: $plan_label = "Mensual"; break;
                            case 131: $plan_label = "Quincenal"; break;
                            case 147: $plan_label = "Semanal"; break;
                            case 150: $plan_label = "Semestral"; break;
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
                        //echo '<script>alert("✅ Correo enviado exitosamente");</script>';

                        // Actualizar estado a 2 (enviado)
                        $sqlUpdate = "UPDATE tbl_calendario SET id_estadoCalendario = 2 WHERE id_calendario = ?";
                        $stmt = $conexion->prepare($sqlUpdate);
                        $stmt->bind_param("i", $id_calendario);
                        $stmt->execute();
                        $stmt->close();

                    } catch (Exception $e) {
                        error_log("Error PHPMailer: " . $e->getMessage());
                        //echo '<script>alert("❌ Error: ' . addslashes($e->getMessage()) . '");</script>';

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

    

?>