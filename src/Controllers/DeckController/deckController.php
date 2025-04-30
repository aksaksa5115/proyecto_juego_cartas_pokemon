<?php
use Psr\Http\Message\ResponseInterface as Response; // Importar la interfaz de respuesta de PSR
use Psr\Http\Message\ServerRequestInterface as Request; // Importar la interfaz de solicitud de PSR


    # EN ESTOS METODOS ES NECESARIO HACER LO SIGUIENTE:
    # en POSTMAN en Headers en la parte de Key poner:
    # Authorization y en Value poner Bearer seguido del token que se genera al loguearse.

return function ($app, $JWT) {

    # en POSTMAN escribir esto en formato JSON de la siguiente manera:
    # {
    #  "nombre": "nombre del mazo",
    #  "cartas": [1, 2, 3, 4, 5] el numero de las cartas puede ser un random entre 1 y 25 (hay 25 cartas en la base de datos)
    # }

    $app->post("/mazo", function(Request $request, Response $response) {
        # Recupero el payload del JWT, que contiene el id del usuario
        $user = $request->getAttribute("jwt");
        $body = $request->getParsedBody();

        if (!$body || !is_array($body) ) {
            $response->getBody()->write(json_encode(["error" => "Cuerpo de la solicitud inválido."]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }


        $userId = $user->sub;
        $nombre = $body['nombre'] ?? "";
        $cartas = $body['cartas'] ?? null;  
        $nombre = trim($nombre);
    
        if (!$userId) {
            $response->getBody()->write(json_encode(["error" => "Token inválido o sin ID."]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
        }
    
        # Verifico que el nombre no esté vacío y que el array de cartas no esté vacío
        if ($nombre === "" || !is_array($cartas) || count(array_unique($cartas)) !== 5) {
            $response->getBody()->write(json_encode(["error" => "El nombre no puede estar vacío y el mazo debe tener 5 cartas."]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400); // Bad Request
        }
    
        try {
            $pdo = Database::getConnection();
    
            # Verifico que el usuario no tenga más de 3 mazos
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM mazo WHERE usuario_id = ?");
            $stmt->execute([$userId]);
            $cantMazos = $stmt->fetchColumn();
    
            if ($cantMazos >= 3) {
                $response->getBody()->write(json_encode(["error" => "No puedes tener más de 3 mazos."]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400); // Bad Request
            }
    
            # Verifico que las 5 cartas existan
            $cartasExistentes = [];
            # por cada carta recorro el array de cartas y verifico si existe en la base de datos
            foreach ($cartas as $id) {
                $stmt = $pdo->prepare("SELECT id FROM carta WHERE id = ?");
                $stmt->execute([$id]);
                $carta = $stmt->fetchColumn();
                # si la carta no existe, el fetchColumn devuelve false, asique no la agrego
                # si la carta existe, la agrego al array de cartas existentes
                if ($carta !== false){
                    $cartasExistentes[] = $carta;
                }
            }
    
            # si el array de cartas existentes no tiene 5 elementos, significa que hay cartas inexistentes
            $cartasNoExistentes = array_diff($cartas, $cartasExistentes);
            if (count($cartasNoExistentes) > 0) {
                $response->getBody()->write(json_encode([
                    "error" => "Hay cartas inexistentes.",
                    "cartasNoExistentes" => array_values($cartasNoExistentes)
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400); // Bad Request
            }
    
            # preparo consulta para guardar el mazo
            $stmt = $pdo->prepare("INSERT INTO mazo (usuario_id, nombre) VALUES (?, ?)");
            $stmt->execute([$userId, $nombre]);
            # me quedo con el ID del mazo para guardar las cartas
            $mazoID = $pdo->lastInsertId();
    
            # preparo consulta para guardar las cartas del mazo
            $stmt = $pdo->prepare("INSERT INTO mazo_carta (mazo_id, carta_id) VALUES (?, ?)");
            # por cada carta, guardo el mazo y la carta en la tabla mazo_carta
            foreach ($cartas as $carta) {
                $stmt->execute([$mazoID, $carta]);
            }
    
            # devuelvo mensaje que fue creado todo con exito
            $response->getBody()->write(json_encode(['message' => "Mazo '$nombre' con ID $mazoID cargado con éxito."]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200); // OK
    
        } catch (Exception $e) {
            $response->getBody()->write(json_encode(['error' => 'Error al crear el mazo.',
            'detalle' => $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500); // Internal Server Error
        }
    })->add($JWT);
    

    # en POSTMAN escribir esto en la URL de la siguiente manera:
    # "ruta al proyecto"/mazo/"id del mazo a eliminar"

    $app->delete('/mazo/{mazo}', function(Request $request, Response $response, Array $args){
        $user = $request->getAttribute('jwt');
        $mazoId = $args['mazo'] ?? ""; // id del mazo a eliminar, lo extraigo desde la url
    
        // verifico que el ID del mazo no esté vacío
        if ($mazoId == ""){
            $response->getBody()->write(json_encode(['error'=> 'El ID del mazo no puede estar vacío.']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400); // Bad Request
        }
    
        $mazoId = trim($mazoId);
    
        // verifico que el ID del mazo sea un número
        if (!is_numeric($mazoId)){
            $response->getBody()->write(json_encode(['error'=> 'El ID del mazo debe ser un número.']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400); // Bad Request
        }
    
        try {
            $pdo = Database::getConnection();
    
            //verifico que el usuario tenga el mazo
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM mazo WHERE id = ? AND usuario_id = ?");
            $stmt->execute([$mazoId, $user->sub]);
            $existe = $stmt->fetchColumn();
    
            if($existe == 0){
                $response->getBody()->write(json_encode(['error' => 'El mazo no existe o no pertenece al usuario.']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404); // Not Found
            }
    
            // primero se eliminan las cartas asociadas al mazo
            $stmt = $pdo->prepare("DELETE FROM mazo_carta WHERE mazo_id = ?");
            $stmt->execute([$mazoId]);
    
            // luego se elimina el mazo
            $stmt = $pdo->prepare("DELETE FROM mazo WHERE id = ?");
            $stmt->execute([$mazoId]);
    
            // devuelvo mensaje que fue eliminado con exito
            $response->getBody()->write(json_encode(['message' => "Mazo con ID '$mazoId' eliminado con éxito."]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200); // OK
    
        } catch (Exception $e) {
            $response->getBody()->write(json_encode(['error' => 'Error al eliminar el mazo.',
            'detalle' => $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500); // Internal Server Error
        }
    })->add($JWT);
    




    $app->get('/mazos', function(Request $request, Response $response) {
        $user = $request->getAttribute('jwt');
        $userID = $user->sub;
    
        try {
            $pdo = Database::getConnection();
    
            # preparo consulta para devolver los mazos del usuario logueado
            $stmt = $pdo->prepare('SELECT nombre, id FROM mazo WHERE usuario_id = ?');
            $stmt->execute([$userID]);
            $mazos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
            # si no hay mazos, devuelvo mensaje de error
            if (empty($mazos)){
                $response->getBody()->write(json_encode(['error' => 'No hay mazos para mostrar.']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404); // Not Found
            }
    
            # si hay mazos, los retorno
            $response->getBody()->write(json_encode(['mazos' => $mazos]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200); // OK
    
        } catch (Exception $e) {
            $response->getBody()->write(json_encode(['error' => 'Error al obtener los mazos.',
            'detalle' => $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500); // Internal Server Error
        }
    })->add($JWT);
    

    # en POSTMAN escribir esto en la URL de la siguiente manera:
    # "ruta al proyecto"/mazo/"id del mazo a modificar"
    # y en formato JSON en el body escribir esto:
    # {
    #  "nombre": "nuevo nombre del mazo"
    # }
    
    $app->put('/mazo/{mazo}', function(Request $request, Response $response, Array $args) {
        $user = $request->getAttribute('jwt');
        $userID = $user->sub;
        $mazoId = $args['mazo'] ?? ""; // id del mazo a modificar, lo extraigo desde la url
        $body = $request->getParsedBody();
        
        if (!$body || !is_array($body) ) {
            $response->getBody()->write(json_encode(["error" => "Cuerpo de la solicitud inválido."]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $nombre = $body['nombre'] ?? ""; // nombre del mazo a modificar, lo extraigo desde el body
        $nombre = trim($nombre);
    
        try {
            $pdo = Database::getConnection();
    
            if ($nombre === ""){
                $response->getBody()->write(json_encode(['error'=> 'El nombre no puede estar vacío.']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400); // Bad Request
            }
    
            # verifico que el mazo exista y pertenezca al usuario
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM mazo WHERE id = ? AND usuario_id = ?");
            $stmt->execute([$mazoId, $userID]);
            $existe = $stmt->fetchColumn();
    
            if (!$existe) {
                $response->getBody()->write(json_encode(['error' => 'El mazo no existe o no pertenece al usuario.']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404); // Not Found
            }
    
            # actualizo nombre del mazo
            $stmt = $pdo->prepare('UPDATE mazo SET nombre = ? WHERE id = ? AND usuario_id = ?');
            $stmt->execute([$nombre, $mazoId, $userID]);
    
            $response->getBody()->write(json_encode(['message'=> "mazo '$mazoId' actualizado con éxito al nombre '$nombre'."]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200); // OK
    
        } catch (Exception $e) {
            $response->getBody()->write(json_encode(['error' => 'Error al actualizar el mazo.',
            'detalle' => $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500); // Internal Server Error
        }
    })->add($JWT);
    


    # para agregar los filtros a la consulta de cartas, se hace de la siguiente manera:
    # en POSTMAN escribir esto en la URL de la siguiente manera:
    # "ruta al proyecto"/cartas?atributo="nombre del atributo"&nombre="nombre de la carta"
    # el atributo y el nombre son opcionales, si no se pasan, se devuelven todas las cartas
    # pueden poner un solo filtro si quieren, el & es solo para agregar otro filtro


    $app->get('/cartas', function(Request $request, Response $response) {
        try {
            $pdo = Database::getConnection();
    
            $param = $request->getQueryParams();
            # extraigo los datos de la url
            $atributo = $param['atributo'] ?? null;
            $nombre = $param['nombre'] ?? null;
    
            # preparo la consulta de forma dinamica, por eso el WHERE 1=1, sirve de base para los demas filtros
            $sql = "SELECT c.nombre, c.ataque, c.ataque_nombre, a.nombre as atributo FROM carta c
                    INNER JOIN atributo a ON c.atributo_id = a.id WHERE 1=1";
            # preparo el array de parametros para la consulta
            $params = [];
    
            if ($atributo){
                $sql .= " AND a.nombre = ?";
                $params[] = $atributo;
            }
            if ($nombre){
                $sql .= " AND c.nombre LIKE ?";
                $params[] = "%$nombre%"; // busco por nombre, no por id
            }
    
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $cartas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
            $response->getBody()->write(json_encode(['cartas' => $cartas]));
            return $response->withHeader('Content-type', 'application/json')->withStatus(200);
    
        } catch (Exception $e) {
            $response->getBody()->write(json_encode(['error' => 'Error al recuperar las cartas.',
            'detalle' => $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500); // Internal Server Error
        }
    });
    
};