<?php
use App\config\Security;
//echo "Hemos llegado al recurso AUTH";
/*
echo json_encode(Security::secretKey());
echo json_encode(Security::createPassword("hola"));


//validando contraseñas
$pass = Security::createPassword("hola");
if (Security::validatePassword("hola", $pass)) {
    echo json_encode("Contraseña correcta");
} else {
    echo json_encode("Contraseña incorrecta");
}

*/
//probando el jwt
echo Security::createTokenJwt(Security::secretKey(),["hola"]);
//echo (json_encode(Security::createTokenJwt(Security::secretKey(),["hola"])));


//probando la conexion a la BD
/*use App\db\connectionDB;
connectionDB::getConnection();*/