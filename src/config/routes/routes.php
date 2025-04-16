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
    //---------<A PARTIR DE ACA SE AGREGAN LAS RUTAS DE LOS CONTROLADORES>------------------
    //--------- Ruta para los controladores de usuarios -----------
    #este controlador posee las siguientes opciones:
    # POST /login <--------- loguearse en la pag
    # POST /registro <------ registrarse en la pag
    # PUT /perfil <------- actualizar datos del usuario logueado
    # GET /perfil <------- obtener datos del usuario logueado
    (require __DIR__ . '/../Controllers/UserController/UserController.php')($app, $pdo, $JWT);
    # Esta ruta solo extrae las estadisticas de los jugadores, cantidad de ganadas, perdidas y empatadas
    (require __DIR__ . '/../Controllers/UserController/StatsGlobal.php')($app, $pdo);
    //---------------------------------------------------------------------------------------
    //--------- Ruta para los controladores de mazos -----------
    (require __DIR__ . '/../Controllers/DeckController/deckController.php')($app, $pdo, $JWT);
    
};
