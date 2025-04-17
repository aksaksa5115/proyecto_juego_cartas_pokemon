<?php
use Psr\Http\Message\ResponseInterface as Response; // Importar la interfaz de respuesta de PSR
use Psr\Http\Message\ServerRequestInterface as Request; // Importar la interfaz de solicitud de PSR
require_once __DIR__ ."/movementsServer.php"; // Importar el archivo de servidor de movimientos

return function ($app, $pdo, $jwt){


    $app->post('/jugada', function (Request $request, Response $response) use ($pdo){
        $user = $request->getAttribute('jwt');
        $userId = $user->sub;
        $body = $request->getParsedBody();

        $partidaId = trim($body['partida_id']);
        $cartaUserId = trim($body['carta']);

        if($partidaId = "" || $cartaUserId = ""){
            $response->getBody()->write(json_encode(["error" => "faltan datos para realizar la jugada."]));
            return $response->withHeader("Content-Type", "application/json")->withStatus(400); // Bad Request
        }

        // verifico que la partida exista y que el mazo sea del usuario
        $stmt = $pdo->prepare("SELECT mazo_id FROM partida WHERE id = ? AND usuario_id = ?");
        $stmt->execute([$partidaId, $userId]);
        $mazoId = $stmt->fetchColumn();

        if (!$mazoId){
            $response->getBody()->write(json_encode(["error"=> "la partida no existe o no es de tu propiedad."]));
            return $response->withHeader("Content-Type", "application/json")->withStatus(400); // Bad Request
        }

        // verifico que la carta sea del mazo
        $stmt = $pdo->prepare("SELECT estado FROM mazo_carta WHERE mazo_id = ? AND carta_id = ? AND estado = 'en_mano'");
        $stmt->execute([$mazoId, $cartaUserId]);
        $carta = $stmt->fetchColumn();

        if (!$carta){
            $response->getBody()->write(json_encode(["error"=> "la carta es invalida"]));
            return $response->withHeader("Content-Type", "application/json")->withStatus(400);
        }

        // traigo la carta por el servidor
        $cartaServidorid = movementsServer($pdo, $partidaId);
        
        //------arranca la logica para determinar el ganador------
        // primero nos traemos ambas cartas del servidor y del usuario
        $stmt = $pdo->prepare("SELECT fuerza FROM carta WHERE id IN (?, ?)");
        $stmt->execute([$cartaUserId, $cartaServidorid]);
        $carta = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $fuerzaUser = $carta[0]['fuerza'];
        $fuerzaServidor = $carta[1]['fuerza'];

        // determinamos el ganador
        $estado = '';
        if ($fuerzaUser > $fuerzaServidor){
            $estado = 'ganador';
        } elseif ($fuerzaUser < $fuerzaServidor){
            $estado = 'perdedor';
        } else {
            $estado = 'empate';
        }
        // actualizo el estado de la carta del usuario a 'descartado'
        $stmt = $pdo->prepare("UPDATE mazo_carta SET estado = 'descartado' WHERE mazo_id = ? AND carta_id = ?");
        $stmt->execute([$mazoId, $cartaUserId]);

        // creo el registro jugada


    
    });



};