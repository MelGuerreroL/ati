<?php

namespace App\controllers;
use App\config\responseHTTP;
use App\models\userModel;
class userController{
    private $method;
    private $route;
    private $params;
    private $data;
    private $headers; 

    //atributos de usuario 
    //atributos con expresion regular:
    /*
    - toda expresion regular empieza con ^
    - toda expresion regular termina con $
    - \d: cualquier digito
    - \D: cualquier caracter que no sea un digito
    - \w: cualquier caracter que sea una letra o un digito
    - \W: cualquier caracter que no sea una letra o un digito
    - \s: cualquier caracter que sea un espacio en blanco
    - \S: cualquier caracter que no sea un espacio en blanco
    -  [a-zA-Z] se refiere a que el atributo puede tomar cualquier caracter que sea
    - [a-zA-Z0-9] se refiere a que el atributo puede tomar caracter o numero
    - El signo mas(+) indica que cualquiera de los valores puede ser tomado del rango especificado
    Considerando lo anterior 
    [1,2,3] se refiere a que el atributo puede tomar cualquiera de estos tres valores 1,2 o 3
    {1,1} se refiere a que como minimo tendra 1 y maximo tendra 1

    
    */
    private static $validar_rol = '/^[1,2,3]{1,1}$/'; //validamos el rol (1 = "", 2="", 3="")
    private static $validar_numero = '/^[0-9]+$/'; //validamos numeros (0-9)
    private static $validar_texto = '/^[a-zA-Z]+$/'; //validamos texto (a-z y A-Z)

    public function __construct($method,$route,$params,$data,$headers){        
        $this->method = $method;
        $this->route = $route;
        $this->params = $params;
        $this->data = $data;
        $this->headers = $headers;            
    }
    //esta forma de poder inicializar un objeto solo se puede en PHP8 
    //directamente definir los argumentos en el constructor e inicializarlos 
    /*public function __construct(
        private string $method,
        private string $route,
        private string $params,
        private $data,
        private $headers
    ){}*/
        //metodo que recibe un endpoint (ruta a un recurso)
    final public function post($endpoint){
        //validamos method y endpoint 
        if($this->method == 'post' && $endpoint == $this->route){            
            //validamos que los campos no vengan vacios
            if (empty($this->data['nombre']) || empty($this->data['dni']) || empty($this->data['email']) || 
              empty($this->data['rol']) || empty($this->data['clave']) || empty($this->data['confirmaClave'])) {
                echo json_encode(responseHTTP::status400('Todos los campos son requeridos, proceda a llenarlos.'));
                //validamos que los campos de texto sean de texto mediante preg_match evaluamos expresiones regulares
            } else if (!preg_match(self::$validar_texto, $this->data['nombre'])) {
                echo json_encode(responseHTTP::status400('En el campo nombre debe ingresar solo texto.'));
                //validamos que los campos numericos sean contengan solo numeros mediante preg_match evaluamos expresiones regulares
            } else if (!preg_match(self::$validar_numero,$this->data['dni'])) {
                echo json_encode(responseHTTP::status400('En el campo dni debe ingresar solo numeros.'));
                //validamos que el correo tenga el formato correcto 
                //filter_var permite crear un filtro especifico y luego validar a partir de este
            }  else if (!filter_var($this->data['email'], FILTER_VALIDATE_EMAIL)) {
                echo json_encode(responseHTTP::status400('El correo debe tener el formato correcto.'));
                //validamos el rol 
            }else if (!preg_match(self::$validar_rol,$this->data['rol'])) {
                echo json_encode(responseHTTP::status400('El rol es invalido'));
            } else {
                new userModel($this->data);
                echo json_encode(userModel::post());
            }
            exit;
        }

    }
} 