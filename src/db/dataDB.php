<?php
use App\config\errorlogs;
use App\Config\responseHTTP;
use App\db\connectionDB;
/* cargamos nuestras variables de entorno de nuestra conexion a BD*/

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__),2);
$dotenv->load();

$data = array(
    "user" => $_ENV['USER'],
    "password" => $_ENV['PASSWORD'],
    "DB" => $_ENV['DB'],
    "IP" => $_ENV['IP'],
    "port" => $_ENV['PORT']
);

/* conectamos a la base de datos llamando al metodo de la clase que retorna PDO*/
$host = 'mysql:host='.$data['IP'].';'.'port='.$data['port'].';'.'dbname='.$data['DB'];
connectionDB::inicializar($host, $data['user'], $data['password']);