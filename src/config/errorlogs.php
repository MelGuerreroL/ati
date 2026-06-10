<?php

namespace App\config;
date_default_timezone_set('America/Tegucigalpa'); //agregamos la zona horaria
class errorlogs{
    public static function activa_error_logs(){
        error_reporting(E_ALL); //activamos todos los errores de php
       // $log = fopen('logs/error.log', 'a+');

        ini_set('ignore_repeated_errors', TRUE); //ignorar los error repetidos
        ini_set('display_errors', FALSE); // no necesitamos que muestre los errores en el navegador
        ini_set('log_errors', TRUE); // habilitamos el log de errores 
        ini_set('error_log', dirname(__DIR__).'/logs/php-error.log'); //donde se guardaran los errores
    }
}