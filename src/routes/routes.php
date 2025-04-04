<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;


#Esta es una funcion de prueba, retorna todos los usuarios de la base de datos.
return function (App $app, PDO $pdo) {
    $app->get('/users', function (Request $request, Response $response) use ($pdo) {
        $stmt = $pdo->query("SELECT * FROM usuario"); 
        $usuarios = $stmt->fetchAll();

        $response->getBody()->write(json_encode($usuarios));
        return $response;
    });

    // Podés seguir agregando más rutas: POST, PUT, DELETE...
    //Ruta del login para autenticar usuarios
    (require __DIR__ . '/../login/LoginController.php')($app, $pdo);
};
