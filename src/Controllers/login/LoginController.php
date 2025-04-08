<?php
use Firebase\JWT\JWT; // Importar la librería JWT de Firebase
use Psr\Http\Message\ResponseInterface as Response; // Importar la interfaz de respuesta de PSR
use Psr\Http\Message\ServerRequestInterface as Request; // Importar la interfaz de solicitud de PSR
use Slim\App; // Importar la clase Slim\App

return function (App $app, PDO $pdo) {
    # en POSTMAN poner en formato JSON el body de la peticion
    #{ "usuario": "introducir su usuario",
    # "password":"introducir su contraseña"
    #}
    $app->post('/login', function(Request $request, Response $response) use ($pdo) {
        $data = json_decode($request->getBody(), true);
        #chequeo si data es un array y si tiene los indices usuario y password
        if (!$data || !isset($data['usuario']) || !isset($data['password'])) {
            $response->getBody()->write(json_encode(["error" => "faltan datos"]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400); //bad request
        }
        // chequeo si el usuario intenta entrar a server
        if ($data['usuario'] === 'server') {
            $response->getBody()->write(json_encode(["error" => "Acceso denegado al usuario del sistema."]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }
        //chequeo si se ingreso un usuario valido, y si ingreso bien la contraseña
        try {
            $stmt = $pdo->prepare("SELECT * FROM usuario WHERE usuario = ?");
            $stmt->execute([$data['usuario']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user || !password_verify($data['password'], $user['password'])) {
                $response->getBody()->write(json_encode(["error" => "Usuario o contraseña incorrectos"]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
            }

            // Generar el token JWT
            date_default_timezone_set('America/Argentina/Buenos_Aires'); // defino zona horaria 
            $expira = time() + 3600; // 1 hora de expiración
            $key = "secret_password_no_copy"; // Cambia esto por una clave secreta más segura
            // el payload es un array asociativo que contiene la información que se va a codificar en el token
            $payload = [
                'sub' => $user['id'],                // el id del usuario
                'username' => $user['usuario'],      // el nombre de usuario
                'exp' => $expira                     // fecha de expiración
            ];
            // Codificar el token JWT
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