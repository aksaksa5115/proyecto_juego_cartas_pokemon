<?php

use Firebase\JWT\ExpiredException;
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
            // Decodificar el token JWT usando la clave secreta
            // y el algoritmo HS256, chequeando la fecha de expiración y si el token es válido
            $decoded = JWT::decode($token, new Key($this->secret, 'HS256'));
            $request = $request->withAttribute('jwt', $decoded);
            return $handler->handle($request);
            // si el token expiro, lanza una excepción ExpiredException
        } catch (ExpiredException $e) {
            $response = new Response();
            $response->getBody()->write(json_encode(['error' => 'El token ha expirado.']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
            // si el token es invalido, lanza una excepción de tipo Exception
        } catch (\Exception $e) {
            $response = new Response();
            $response->getBody()->write(json_encode(['error' => 'Token inválido.']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
        }
        
    }
}
