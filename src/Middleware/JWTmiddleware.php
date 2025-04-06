<?php

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Slim\Psr7\Response;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Psr\Http\Message\ServerRequestInterface as Request;

class JWTmiddleware {
    private $secret;

    public function __construct($secret = "secret_password_no_copy") {
        $this->secret = $secret;
    }

    // Método __invoke: se ejecuta automáticamente cuando el middleware es llamado
    public function __invoke(Request $request, RequestHandler $handler): Response {
        // Obtener el header Authorization de la petición HTTP
        $authHeader = $request->getHeaderLine('Authorization');
        
        // Verificar si el header Authorization está presente y tiene el formato correcto
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            $response = new Response();
            $response->getBody()->write(json_encode(['error' => 'Token requerido']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
        }

        // Extrae solo el token, quitando la palabra "Bearer "
        $token = str_replace('Bearer ', '', $authHeader);

        try {
            // Decodifica el token usando la clave secreta y el algoritmo HS256
            $decoded = JWT::decode($token, new Key($this->secret, 'HS256'));
            // Agrega el token decodificado como atributo de la request (para usarlo en el controlador)
            $request = $request->withAttribute('jwt', $decoded);
            // Si todo está bien, deja que la request continúe al controlador
            return $handler->handle($request);

        } catch (\Exception $e) {
            // Si el token es inválido o expiró, devuelve un error 401
            $response = new Response();
            $response->getBody()->write(json_encode(['error' => 'Token inválido o expirado']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
        }
    }
}
