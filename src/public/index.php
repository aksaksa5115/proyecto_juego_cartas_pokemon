<?php
/* DEPENDENCIAS IMPORTADAS DEL PSR de SLIM */
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

require __DIR__ . '/../../vendor/autoload.php'; //COLOCAR PUNTOS PARA DIRIGIRME AL DIRECTORIO VENDOR.
$pdo = require __DIR__ . '/../config/database.php'; //COLOCAR PUNTOS PARA DIRIGIRME AL DIRECTORIO DE LA BASE DE DATOS.
$app = AppFactory::create(); //Crea la app (El Core)

// Add routing and body parsing middleware
$app->addRoutingMiddleware();
$app->addBodyParsingMiddleware();
$app->addErrorMiddleware(true, true, true);

// Middleware to handle CORS and headers
$app->add(function ($request, $handler) {
    $response = $handler->handle($request);

    return $response
        ->withHeader('Access-Control-Allow-Origin', '*')
        ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
        ->withHeader('Access-Control-Allow-Methods', 'OPTIONS, GET, POST, PUT, PATCH, DELETE')
        ->withHeader('Content-Type', 'application/json');
});


$app->get('/', function (Request $request, Response $response, $args) { //El string del argumento es el LOCALHOST de la APP.
    $response->getBody()->write("API SLIM funcionando");
    return $response;
});

// importo el middleware de JWT
require_once __DIR__ . '/../Middleware/JWTmiddleware.php';

$JWT = new JWTmiddleware("secret_password_no_copy"); 

// 👉 Cargar las rutas desde el archivo routes/routes.php
(require __DIR__ . '/../routes/routes.php')($app, $pdo, $JWT); //Le paso la app y la base de datos a las rutas.


$app->run(); //Corre la APP.
/* No es necesario cerrar la etiqueta PHP para ejecutar este codigo */