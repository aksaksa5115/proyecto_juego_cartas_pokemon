<?php
use Psr\Http\Message\ResponseInterface as Response; // Importar la interfaz de respuesta de PSR
use Psr\Http\Message\ServerRequestInterface as Request; // Importar la interfaz de solicitud de PSR

return function ($app, PDO $pdo, $JWT) {

    $app->post('/partida', function (Request $request, Response $response) use ($pdo) {
        $body = $request->getParsedBody();
        $user = $request->getAttribute('jwt');
        $userId = $user->sub; // Obtener el ID del usuario desde el JWT
        $mazoId = $body['mazo'] ?? null;

        if ($mazoId === null) {
            $response->getBody()->write(json_encode(['error' => 'El ID del mazo es requerido.']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400); // Bad Request
        }

        # Verifico que el mazo exista y pertenezca al usuario
        $stmt = $pdo->prepare("SELECT id FROM mazo WHERE id = ? AND usuario_id = ?");
        $stmt->execute([$mazoId, $userId]);
        $mazo = $stmt->fetchColumn();
        if (!$mazo) {
            $response->getBody()->write(json_encode(['error' => 'El mazo no existe o no te pertenece.']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404); // Not Found
        }
        # Verifico que el mazo no esté en uso en otra partida
        $stmt = $pdo->prepare("SELECT id FROM partida WHERE mazo_id = ? AND estado = 'en_curso'");
        $stmt->execute([$mazoId]);
        $partidaEnCurso = $stmt->fetchColumn();

        if ($partidaEnCurso) {
            $response->getBody()->write(json_encode(['error' => 'El mazo ya está en uso en otra partida.']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400); // Bad Request
        }
        # creo la partida
        $stmt = $pdo->prepare("INSERT INTO partida (usuario_id, el_usuario, fecha, mazo_id, estado ) 
        VALUES (?, NULL, NOW(), ?, 'en_curso')");
        $stmt->execute([$userId, $mazoId]);
        $partidaId = $pdo->lastInsertId(); // Obtener el ID de la partida recién creada


        # actualizo el estado de las cartas del mazo a 'en mano'
        $stmt = $pdo->prepare("UPDATE mazo_carta SET estado = 'en_mano' WHERE mazo_id = ?");
        $stmt->execute([$mazoId]);

        # retorno la lista de cartas del mazo
        $stmt = $pdo->prepare("SELECT nombre, ataque FROM CARTA WHERE id IN (SELECT carta_id FROM mazo_carta WHERE mazo_id = ?)");
        $stmt->execute([$mazoId]);
        $cartas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $response->getBody()->write(json_encode(['message' => 'Partida creada con éxito.', 'partida_id' => $partidaId,
         'cartas' => $cartas]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(201); // Created
    }) ->add($JWT); // Agregar el middleware JWT a la ruta de partidas

    // este endpoint sirve para debuggear en caso de hacer una partida de forma incorrecta
    $app->delete('/partida/{duelo}', function (Request $request, Response $response, Array $args) use ($pdo) {
        $user = $request->getAttribute('jwt');
        $partidaIdAeliminar = $args['duelo']; // id de la partida a eliminar, lo extraigo desde la url

        #chequeo que la partida exista
        $stmt = $pdo->prepare('SELECT id FROM partida WHERE id = ? ');
        $stmt->execute([$partidaIdAeliminar]);
        $partida = $stmt->fetchColumn();

        if ($partida == 0){
            $response->getBody()->write(json_encode(['error'=> 'la partida no existe']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404); // Not Found
        }

        # antes de eliminar la partida, actualizo el estado de las cartas a 'en mazo'
        $stmt = $pdo->prepare("UPDATE mazo_carta SET estado = 'en_mazo' WHERE mazo_id = 
        (SELECT mazo_id FROM partida WHERE id = ?)");
        $stmt->execute([$partida]);

        # luego elimino la partida
        $stmt = $pdo->prepare("DELETE FROM partida WHERE id = ?");
        $stmt->execute([$partidaIdAeliminar]);

        # devuelvo mensaje que fue eliminado con exito
        $response->getBody()->write(json_encode(['message' => "Partida con ID '$partidaIdAeliminar' eliminada con éxito."]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200); // OK
    })->add($JWT);
};