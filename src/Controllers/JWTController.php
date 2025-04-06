<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

return function ($app, $pdo, $JWT) {
    $app->get('/perfil', function (Request $request, Response $response) {
        $user = $request->getAttribute('jwt');
        $response->getBody()->write(json_encode([
            'mensaje' => 'Bienvenido ' . $user->username . ' con ID ' . $user->sub,
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    })->add($JWT);
};
