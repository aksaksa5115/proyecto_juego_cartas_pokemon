<?php
use Firebase\JWT\JWT;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;

return function (App $app, PDO $pdo) {
    $app->post('/login', function(Request $request, Response $response) use ($pdo) {
        $data = json_decode($request->getBody(), true);
        #chequeo si data es un array y si tiene los indices usuario y password
        if (!$data || !isset($data['usuario']) || !isset($data['password'])) {
            $response->getBody()->write(json_encode(["error" => "faltan datos"]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400); //bad request
        }

        if ($data['usuario'] === 'server') {
            $response->getBody()->write(json_encode(["error" => "Acceso denegado al usuario del sistema."]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        try {
            $stmt = $pdo->prepare("SELECT * FROM usuario WHERE usuario = ?");
            $stmt->execute([$data['usuario']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user || $data['password'] !== $user['password']) {
                $response->getBody()->write(json_encode(["error" => "Usuario o contraseña incorrectos"]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
            }

            // Generar el token JWT
            date_default_timezone_set('America/Argentina/Buenos_Aires'); // defino zona horaria 
            $expira = time() + 3600; // 1 hora de expiración
            $key = "secret_password_no_copy"; // Cambia esto por una clave secreta más segura
            $payload = ['usuario_id' => $user['id'], 'exp' => time() + 3600];
            $jwt = JWT::encode($payload, $key, 'HS256');

            // Guardar el token y la fecha de expiración en la base de datos
            $stmt = $pdo->prepare("UPDATE usuario SET token = ?, vencimiento_token = ? WHERE usuario = ?");
            $stmt->execute([$jwt, date('Y-m-d H:i:s', $expira), $data['usuario']]);

            // Devolver el token y la fecha de expiración
            $response->getBody()->write
            (json_encode
            (["token" => $jwt, "expira" => date('Y-m-d H:i:s', $expira), "usuario" => $data['usuario']]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        } catch (PDOException $e) {
            $response->getBody()->write(json_encode(["error" => "Error en la base de datos"]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    });
};