<?php
use Psr\Http\Message\ResponseInterface as Response; // Importar la interfaz de respuesta de PSR
use Psr\Http\Message\ServerRequestInterface as Request; // Importar la interfaz de solicitud de PSR


    # EN ESTOS METODOS ES NECESARIO HACER LO SIGUIENTE:
    # en POSTMAN en Headers en la parte de Key poner:
    # Authorization y en Value poner Bearer seguido del token que se genera al loguearse.

return function ($app, PDO $pdo, $JWT) {

    # en POSTMAN escribir esto en formato JSON de la siguiente manera:
    # {
    #  "nombre": "nombre del mazo",
    #  "cartas": [1, 2, 3, 4, 5] el numero de las cartas puede ser un random entre 1 y 25 (hay 25 cartas en la base de datos)
    # }

    $app->post("/mazo", function(Request $request, Response $response) use ($pdo) {
        # Recupero el payload del JWT, que contiene el id del usuario
        $user = $request->getAttribute("jwt");
        $body = $request->getParsedBody();
        $userId = $user->sub;  
        $nombre = trim($body['nombre'] ?? '');
        $cartas = $body['cartas'] ?? [];

        //depuracion

        #$userId = is_object($user) ? $user->sub : ($user['sub'] ?? null);


        if (!$userId) {
            $response->getBody()->write(json_encode(["error" => "Token inválido o sin ID."]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
        }


        # Verifico que el nombre no esté vacío y que el array de cartas no esté vacío
        if ($nombre === "" || !is_array($cartas) || count(array_unique($cartas)) !== 5) {
            $response->getBody()->write(json_encode(["error" => "El nombre no puede estar vacío y el mazo debe tener 5 cartas."]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400); // Bad Request
        }

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
            $response->getBody()->write(json_encode(["error" => "Hay cartas inexistentes.",
            "cartasNoExistentes" => array_values($cartasNoExistentes)]));
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
    })->add($JWT);

    # en POSTMAN escribir esto en la URL de la siguiente manera:
    # "ruta al proyecto"/mazo/"id del mazo a eliminar"
    # 
    $app->delete('/mazo/{mazo}', function(Request $request, Response $response, Array $args) use ($pdo){
        $user = $request->getAttribute('jwt');
        $mazoId = $args['mazo']; // id del mazo a eliminar, lo extraigo desde la url

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
        $response->getBody()->write(json_encode(['message' => "Mazo con ID '$mazoId' deliminado con éxito."]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200); // OK
    })->add($JWT);




    $app->get('/mazos', function(Request $request, Response $response) use ($pdo) {
        $user = $request->getAttribute('jwt');
        $userID = $user->sub;

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
    })->add($JWT);

    # en POSTMAN escribir esto en la URL de la siguiente manera:
    # "ruta al proyecto"/mazo/"id del mazo a modificar"
    # y en formato JSON en el body escribir esto:
    # {
    #  "nombre": "nuevo nombre del mazo"
    # }
    
    $app->put('/mazo/{mazo}', function(Request $request, Response $response, Array $args) use ($pdo) {
        $user = $request->getAttribute('jwt');
        $userID = $user->sub;
        $mazoId = $args['mazo']; // id del mazo a modificar, lo extraigo desde la url
        $body = $request->getParsedBody();
        $nombre = trim($body['nombre'] ?? '');


        if ($nombre === ''){
            $response->getBody()->write(json_encode(['error'=> 'El nombre no puede estar vacío.']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400); // Bad Request
        }
        # actualizo nombre del mazo
        $stmt = $pdo->prepare('UPDATE mazo SET nombre = ? WHERE id = ? AND usuario_id = ?');
        $stmt->execute([$nombre, $mazoId, $userID]);

        $response->getBody()->write(json_encode(['message'=> "mazo '$mazoId' actualizado con éxito al nombre '$nombre'."]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200); // OK
    })->add($JWT);


    $app->get('/cartas', function(Request $request, Response $response) use ($pdo) {
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
    });
};