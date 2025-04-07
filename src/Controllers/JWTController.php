<?php
use Firebase\JWT\JWT;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
    # EN ESTOS METODOS ES NECESARIO HACER LO SIGUIENTE:
    # en POSTMAN en Headers en la parte de Key
    # Authorization y en Value poner Bearer seguido del token que se genera al loguearse.
return function ($app, $pdo, $JWT) {

    $app->get('/perfil', function (Request $request, Response $response)  {
        $user = $request->getAttribute('jwt');
        $response->getBody()->write(json_encode([
            'mensaje' => 'Bienvenido ' . $user->username . ' con ID ' . $user->sub,
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    })->add($JWT);

    # en POSTMAN en formato JSON poner en el body los siguientes datos:
    # { "password": "introducir password",
    #   "passwordConfirmacion": "confirmar password",
    #   "usuario": "nuevoUsuario",
    #   "usuarioConfirmacion": "confirmar nuevo usuario"
    # }
    $app->put('/perfil', function (Request $request, Response $response) use ($pdo) {
        $user = $request->getAttribute('jwt'); // Usuario autenticado
        $body = $request->getParsedBody(); // Obtener el cuerpo de la solicitud
        $userId = $user->sub; // ID del usuario autenticado
        $usuarioActual = $user->username; // Nombre de usuario actual
        $expira = $user->exp; // Fecha de expiración del token
        $nuevaPassword = $body['password']; // Nueva contraseña
        $PasswordConfirmacion = $body['passwordConfirmacion']; // Confirmación de la nueva contraseña
        $nuevoUsername = $body['usuario']; // Nuevo nombre de usuario
        $UsernameConfirmacion = $body['usuarioConfirmacion']; // Confirmación del nuevo nombre de usuario
        //esta variable solo se usa para la consulta de update, no es necesario que la valide.
        $usuarioFinal = $usuarioActual;
        // voy a asumir que se van a enviar todos los campos, simplemente voy a validar que no esten vacios y que sean iguales.
        if (trim($nuevoUsername) === "" && trim($nuevaPassword) === "") {
            $response->getBody()->write(json_encode(["error" => "No se han enviado datos para actualizar."]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400); // Bad Request
        }
        // estos array se usaran para armar la consulta de update.
        $campos = [];
        $valores = [];

        if (trim($nuevoUsername) !== "") {
            // Validar el nuevo nombre de usuario
            if (!preg_match('/^[a-zA-Z0-9]{1,20}$/', $nuevoUsername)) {
                $response->getBody()->write(json_encode(["error" => "El nombre de usuario debe tener entre 1 y 20 caracteres y solo puede contener letras."]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400); //bad request
            }
            // verifico que el nombre de usuario y la confirmacion sean iguales.
            if (trim($nuevoUsername) !== trim($UsernameConfirmacion)) {
                $response->getBody()->write(json_encode(["error" => "El nombre de usuario no coincide con la confirmación."]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400); // Bad Request
            }
            // Verificar si el nuevo nombre de usuario ya está en uso
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM usuario WHERE usuario = ?");
            $stmt->execute([$nuevoUsername]);
            if ($stmt->fetchColumn() > 0) {
                $response->getBody()->write(json_encode(['error' => 'El nombre de usuario ya está en uso']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400); // Bad Request
            }
            // Guardar el nuevo nombre de usuario en el array
            $campos[] = "usuario = ?";
            $valores[] = $nuevoUsername;
            //si el nombre fue cambiado, cambio la variable $usuarioFinal para que contenga el nuevo nombre de usuario.
            $usuarioFinal = $nuevoUsername;
        }

        if (trim($nuevaPassword) !== "") {
            // Validar la nueva contraseña
            if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/', $nuevaPassword)) {
                $response->getBody()->write(json_encode(['error' => 'La clave debe tener al menos 8 caracteres,
                 incluyendo mayúsculas, minúsculas, números y caracteres especiales.']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }
            // Verificar que la nueva contraseña y la confirmación sean iguales
            if (trim($nuevaPassword) !== trim($PasswordConfirmacion)) {
                $response->getBody()->write(json_encode(["error" => "La contraseña no coincide con la confirmación."]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400); // Bad Request
            }
            // Hashear la nueva contraseña y guardarla en el array
            $campos[] = "password = ?";
            $valores[] = password_hash($nuevaPassword, PASSWORD_BCRYPT);
            // Actualizar token pero conservando la misma fecha de expiracion (consultar como funciona el token)
            date_default_timezone_set('America/Argentina/Buenos_Aires'); // defino zona horaria
            $key = "secret_password_no_copy";
            $payload = [
                'sub' => $userId, // ID del usuario
                'username' => $usuarioFinal, // nombre de usuario
                'exp' => $expira // fecha de expiración
            ];
            $jwt = JWT::encode($payload, $key, 'HS256');

        }

        //realizo la consulta de update
        $valores[] = $userId; // Agregar el ID del usuario al final de la consulta
        $query = "UPDATE usuario SET " . implode(", ", $campos) . " WHERE id = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute($valores); // Ejecutar la consulta con los valores
        $response->getBody()->write(json_encode(['mensaje' => 'Datos actualizados.',
         'usuario' => $usuarioFinal]));

        //actualizo el token y la fecha de expiracion en la base de datos
        $stmt = $pdo->prepare("UPDATE usuario SET token = ?, vencimiento_token = ? WHERE id = ?");
            $stmt->execute([$jwt, date('Y-m-d H:i:s', $expira), $userId]);
        $response->getBody()->write(json_encode(['mensaje' => 'Datos actualizados.
         Nuevo token generado', 'token' => $jwt]));

        return $response->withHeader('Content-Type', 'application/json')->withStatus(200); // OK

    }) ->add($JWT); // Agregar el middleware JWT a la ruta de actualización del perfil

};

