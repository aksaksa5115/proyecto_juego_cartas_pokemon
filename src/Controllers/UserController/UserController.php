<?php
use Firebase\JWT\JWT; // Importar la librería JWT de Firebase
use Psr\Http\Message\ResponseInterface as Response; // Importar la interfaz de respuesta de PSR
use Psr\Http\Message\ServerRequestInterface as Request; // Importar la interfaz de solicitud de PSR


require_once __DIR__ ."/../../helpers/validation.php"; // Importar la clase de validación
require_once __DIR__ ."/../../config/Database.php"; // Importar la clase de conexión a la base de datos 

    #----------------------METODOS DE USUARIO----------------------
    #--------------------------------------------------------------
return function ($app, $JWT ) {
    # en POSTMAN poner en formato JSON el body de la peticion
    #{ 
    #  "usuario": "introducir su usuario",
    #  "password":"introducir su contraseña"
    #}
    $app->post('/login', function(Request $request, Response $response) {
        $data = json_decode($request->getBody(), true);
        #chequeo si data es un array y si tiene los indices usuario y password

        if (!$data || !isset($data['usuario']) || !isset($data['password'])) {
            $response->getBody()->write(json_encode(["error" => "faltan datos"]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400); //bad request
        }
        $usuario = $data['usuario'] ?? ""; // Nombre de usuario (si se proporciona)
        $password = $data['password'] ?? ""; // Contraseña (si se proporciona)
        
        // chequeo si el usuario intenta entrar a server
        if ($data['usuario'] === 'server') {
            $response->getBody()->write(json_encode(["error" => "Acceso denegado al usuario del sistema."]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }
        // chequeo si se ingreso un usuario valido, y si ingreso bien la contraseña
        try {

            $pdo = Database::getConnection(); // Obtengo la conexión a la base de datos


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

            $pdo = null; // Cerrar la conexión a la base de datos

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

    
    # en POSTMAN en formato JSON poner en el body los siguientes datos:
    # { "nombre": "introducir nombre", 
    #   "usuario": "nuevoUsuario", 
    #   "password": "nuevaPassword"
    # }
    $app->post('/registro', function(Request $request, Response $response) {
        $data = json_decode($request->getBody(), true);

        // chequeo si data es un array y si tiene los indices nombre, usuario y password
        if (!$data || !isset($data['usuario']) || !isset($data['password']) || !isset($data['nombre'])) {
            $response->getBody()->write(json_encode(["error" => "faltan campos por rellenar"]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400); //bad request
        }

        // elimino los espacios en blanco por si los usuarios los ingresan
        $nombre = trim($data['nombre']);
        $usuario = trim($data['usuario']);
        $password = trim($data['password']);
        
        // chequeo si el nombre es correcto
        if (!validation::validarNombre($nombre)) {
            $response->getBody()->write(json_encode(["error" => "El nombre debe tener entre 3 y 20 caracteres y solo puede contener letras."]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400); //bad request
        }

        // chequeo si el usuario es correcto
        if (!validation::validarUsername($usuario)) {
            $response->getBody()->write(json_encode(["error" => "El nombre de usuario debe tener entre 1 y 20 caracteres y solo puede contener letras."]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400); //bad request
        }

        // chequeo si la contraseña es correcta
        if (!validation::validarPassword($password)) {
            $response->getBody()->write(json_encode(['error' => 'La clave debe tener al menos 8 caracteres, incluyendo mayúsculas, minúsculas, números y caracteres especiales.']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        try {
            $pdo = Database::getConnection();
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
            // preparo la consulta para insertar el nuevo usuario en la base de datos
            $stmt = $pdo->prepare("INSERT INTO usuario (nombre, usuario, password) VALUES (?, ?, ?)");
            $stmt->execute([$nombre, $usuario, $hashedPassword]);
            $response->getBody()->write(json_encode(['message' => 'Usuario registrado y guardado con exito.']));
            
            $pdo = null; // Cerrar la conexión a la base de datos
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200); // OK
        }  catch (PDOException $e) {
            $response->getBody()->write(json_encode(["error" => "Error en la base de datos"]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500); // Internal Server Error
        }
    });

    #----------------------PEDIR INFO DEL PERFIL---------------------------
    #----------------------ACTUALIZAR PERFIL-----------------------
    
    # EN ESTOS METODOS ES NECESARIO HACER LO SIGUIENTE:
    # en POSTMAN en Headers en la parte de Key poner:
    # Authorization y en Value poner Bearer seguido del token que se genera al loguearse.

    # en POSTMAN en formato JSON poner en el body los siguientes datos:
    # { 
    #   "password": "introducir password",
    #   "usuario": "nuevoUsuario",
    # }
    $app->put('/perfil', function (Request $request, Response $response) {
        $user = $request->getAttribute('jwt'); // Usuario autenticado
        $body = $request->getParsedBody();
        // chequeo si el body de la solicitud viene sin valores
        if (!$body || !is_array($body)) {
            $response->getBody()->write(json_encode(["error" => "Cuerpo de la solicitud inválido."]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
        $userId = $user->sub; // ID del usuario autenticado
        $usuarioActual = $user->username; // Nombre de usuario actual
        $expira = $user->exp; // Fecha de expiración del token
        $nuevaPassword = $body['password'] ?? ""; // Nueva contraseña (si se proporciona)
        $nuevoUsername = $body['usuario'] ?? ""; // Nuevo nombre de usuario (si se proporciona)
        
        //esta variable solo se usa para la consulta de update, no es necesario que la valide.
        $usuarioFinal = $usuarioActual;
        if (trim($nuevoUsername) === "" && trim($nuevaPassword) === "") {
            $response->getBody()->write(json_encode(["error" => "No se han enviado datos para actualizar."]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400); // Bad Request
        }
        // estos array se usaran para armar la consulta de update.
        $campos = [];
        $valores = [];
        $jwt = null;
        // obtengo conexion a la base de datos
        try {
            $pdo = Database::getConnection(); // siempre se obtiene conexión si hay algo que actualizar
        } catch (PDOException $e) {
            $response->getBody()->write(json_encode(["error" => "Error al conectar a la base de datos."]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }


        // si el usuario quiere cambiar su nombre entra aca
        if (trim($nuevoUsername) !== "") {
            // Validar el nuevo nombre de usuario
            if (!validation::validarUsername($nuevoUsername)) {
                $response->getBody()->write(json_encode(["error" => "El nombre de usuario debe tener entre 1 y 20 caracteres y solo puede contener letras."]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400); //bad request
            }
            // Verificar si el nuevo nombre de usuario ya está en uso
            try {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM usuario WHERE usuario = ?");
                $stmt->execute([$nuevoUsername]);
            } catch (PDOException $e) {
                $response->getBody()->write(json_encode(["error"=> $e->getMessage()]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(500); // Internal Server Error
            }
            if ($stmt->fetchColumn() > 0) {
                $response->getBody()->write(json_encode(['error' => 'El nombre de usuario ya esta en uso']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400); // Bad Request
            }
            // Guardar el nuevo nombre de usuario en el array
            $campos[] = "usuario = ?";
            $valores[] = $nuevoUsername;
            //si el nombre fue cambiado, cambio la variable $usuarioFinal para que contenga el nuevo nombre de usuario.
            $usuarioFinal = $nuevoUsername;
        }

        // si el usuario quiere cambiar su contraseña entra aca
        if (trim($nuevaPassword) !== "") {
            // Validar la nueva contraseña
            if (!validation::validarPassword($nuevaPassword)) {
                $response->getBody()->write(json_encode(['error' => 'La clave debe tener al menos 8 caracteres,
                 incluyendo mayúsculas, minúsculas, números y caracteres especiales.']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }
            // Hashear la nueva contraseña y guardarla en el array
            $campos[] = "password = ?";
            $valores[] = password_hash($nuevaPassword, PASSWORD_BCRYPT);
            // Actualizar token pero conservando la misma fecha de expiracion 
            date_default_timezone_set('America/Argentina/Buenos_Aires'); // defino zona horaria
            $key = "secret_password_no_copy";
            $payload = [
                'sub' => $userId, // ID del usuario
                'username' => $usuarioFinal, // nombre de usuario
                'exp' => $expira // fecha de expiración
            ];
            $jwt = JWT::encode($payload, $key, 'HS256');

             //actualizo el token y la fecha de expiracion en la base de datos
             $stmt = $pdo->prepare("UPDATE usuario SET token = ?, vencimiento_token = ? WHERE id = ?");
             $stmt->execute([$jwt, date('Y-m-d H:i:s', $expira), $userId]);
             $response->getBody()->write(json_encode(['mensaje' => 'Datos actualizados'
                . 'Nuevo token generado', 'token' => $jwt]));
        }

        //realizo la consulta de update
        $valores[] = $userId; // Agregar el ID del usuario al final de la consulta
        $query = "UPDATE usuario SET " . implode(", ", $campos) . " WHERE id = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute($valores); // Ejecutar la consulta con los valores

        $pdo = null; // Cerrar la conexión a la base de datos

        return $response->withHeader('Content-Type', 'application/json')->withStatus(200); // OK

    }) ->add($JWT); // Agregar el middleware JWT a la ruta de actualización del perfil

    $app->get('/perfil', function (Request $request, Response $response)  {
        $user = $request->getAttribute('jwt');
        $response->getBody()->write(json_encode([
            'mensaje' => 'Bienvenido ' . $user->username . ' con ID ' . $user->sub,
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    })->add($JWT);



};