<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;

return function (App $app, PDO $pdo) {
    # en POSTMAN en formato JSON poner en el body los siguientes datos:
    # { "nombre": "introducir nombre", 
    #   "usuario": "nuevoUsuario", 
    #   "password": "nuevaPassword"
    # }
    $app->post('/registro', function(Request $request, Response $response) use ($pdo) {
        $data = json_decode($request->getBody(), true);


        if (!$data || !isset($data['usuario']) || !isset($data['password']) || !isset($data['nombre'])) {
            $response->getBody()->write(json_encode(["error" => "faltan campos por rellenar"]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400); //bad request
        }
        $nombre = trim($data['nombre']);
        $usuario = trim($data['usuario']);
        $password = trim($data['password']);

        if (!preg_match('/^[a-zA-Z]{3,20}$/', $nombre)) {
            $response->getBody()->write(json_encode(["error" => "El nombre debe tener entre 3 y 20 caracteres y solo puede contener letras."]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400); //bad request
        }

        if (!preg_match('/^[a-zA-Z0-9]{1,20}$/', $usuario)) {
            $response->getBody()->write(json_encode(["error" => "El nombre de usuario debe tener entre 1 y 20 caracteres y solo puede contener letras."]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400); //bad request
        }

        if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/', $password)) {
            $response->getBody()->write(json_encode(['error' => 'La clave debe tener al menos 8 caracteres, incluyendo mayúsculas, minúsculas, números y caracteres especiales.']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        try {
            // Verificar si el usuario ya existe
            $stmt = $pdo->prepare("SELECT * FROM usuario WHERE usuario = ?");
            $stmt->execute([$usuario]);
            // Si el usuario ya existe, devolver un error
            if ($stmt->fetch()) {
                $response->getBody()->write(json_encode(["error" => "El nombre de usuario ya existe, ingrese otro."]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400); //bad request
            }
            // Hashear la contraseña antes de almacenarla en la base de datos.
            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("INSERT INTO usuario (nombre, usuario, password) VALUES (?, ?, ?)");
            $stmt->execute([$nombre, $usuario, $hashedPassword]);
            $response->getBody()->write(json_encode(['message' => 'Usuario registrado y guardado con exito.']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200); // OK
        }  catch (PDOException $e) {
            $response->getBody()->write(json_encode(["error" => "Error en la base de datos"]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500); // Internal Server Error
        }
    });
};



    