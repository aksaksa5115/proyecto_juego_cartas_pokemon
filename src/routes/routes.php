<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;


#Esta es una funcion de prueba, retorna todos los usuarios de la base de datos.
return function (App $app, PDO $pdo, $JWT) {
    $app->get('/users', function (Request $request, Response $response) use ($pdo) {
        $stmt = $pdo->query("SELECT * FROM usuario"); 
        $usuarios = $stmt->fetchAll();

        $response->getBody()->write(json_encode($usuarios));
        return $response;
    });

    //---------A PARTIR DE ACA SE AGREGAN LAS RUTAS DE LOS CONTROLADORES------------------
    //A su derecha ira el nombre del controlador que se va a encargar de la logica de la ruta.

    //Ruta del login para autenticar usuarios
    (require __DIR__ . '/../Controllers/login/LoginController.php')($app, $pdo); //POST /login
    //Ruta para registrar usuarios
    (require __DIR__ . '/../Controllers/login/RegisterController.php')($app, $pdo); //POST /registro
    //Ruta para actualizar contraseñas de usuarios
    (require __DIR__ . '/../Controllers/login/UpdatePassword.php')($app, $pdo); //PUT /actContras
    //ruta para solicitar datos del usuario logueado con middleware JWT
    (require __DIR__ . '/../Controllers/JWTController.php')($app, $pdo, $JWT); //GET /perfil
};
