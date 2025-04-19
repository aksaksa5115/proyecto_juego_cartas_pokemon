<?php
use Psr\Http\Message\ResponseInterface as Response; // Importar la interfaz de respuesta de PSR
use Psr\Http\Message\ServerRequestInterface as Request; // Importar la interfaz de solicitud de PSR

require_once __DIR__ ."/movementsServer.php"; // Importar el archivo de servidor de movimientos
return function ($app, $pdo, $jwt){

    $movementsServer = new movementsServer($pdo); // Importar el archivo de servidor de movimientos
    
    $app->post('/jugada', function (Request $request, Response $response) use ($pdo, $movementsServer){
        $body = $request->getParsedBody();
        $user = $request->getAttribute('jwt');
        $userId = $user->sub;

        $partidaId = trim($body['partida_id']);
        $cartaUserId = trim($body['carta']);

        if($partidaId == "" || $cartaUserId == ""){
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
        $cartaServidorid = $movementsServer->cartaServer($partidaId);
        
        //------arranca la logica para determinar el ganador------
        // primero nos traemos ambas cartas del servidor y del usuario
        $stmt = $pdo->prepare("SELECT ataque FROM carta WHERE id IN (?, ?)");
        $stmt->execute([$cartaUserId, $cartaServidorid]);
        $carta = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $fuerzaUser = $carta[0]['ataque'];
        $fuerzaServidor = $carta[1]['ataque'];

        // determinamos el ganador
        $estadoJugada = '';
        if ($fuerzaUser > $fuerzaServidor){
            $estadoJugada = 'gano';
        } elseif ($fuerzaUser < $fuerzaServidor){
            $estadoJugada = 'perdio';
        } else {
            $estadoJugada = 'empato';
        }
        // actualizo el estado de la carta del usuario a 'descartado'
        $stmt = $pdo->prepare("UPDATE mazo_carta SET estado = 'descartado' WHERE mazo_id = ? AND carta_id = ?");
        $stmt->execute([$mazoId, $cartaUserId]);

        // creo el registro jugada
        $stmt = $pdo->prepare("INSERT INTO jugada (partida_id, carta_id_a, carta_id_b, el_usuario)
            VALUES (?, ?, ?, ?)");
        $stmt->execute([$partidaId, $cartaUserId, $cartaServidorid, $estadoJugada]);

        // me fijo cuantas jugadas hay en la partida y cuantas gano, empato y perdio el usuario
        $stmt = $pdo->prepare("SELECT 
        COUNT(*) AS total_jugadas,
        SUM(CASE WHEN el_usuario = 'gano' THEN 1 ELSE 0 END) AS total_ganadas,
        SUM(CASE WHEN el_usuario = 'perdio' THEN 1 ELSE 0 END) AS total_perdidas
        FROM jugada WHERE partida_id = ?");

        $stmt->execute([$partidaId]);
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

        // extraigo los datos de $resultado
        $totalJugadas = (int) $resultado['total_jugadas'];
        $totalGanadas = (int) $resultado['total_ganadas'];
        $totalPerdidas = (int) $resultado['total_perdidas'];
        if ($totalJugadas == 5){
            // determino el ganador de la partida
            $resultado = $totalGanadas - $totalPerdidas;
            if ($resultado > 0){
                $estado = 'gano';

            } elseif ($resultado < 0){
                $estado = 'perdio';
            } else {
                $estado = 'empato';
            }
            // actualizo el estado de la partida a 'finalizada' y seteo al ganador
            $stmt = $pdo->prepare("UPDATE partida SET estado = 'finalizada', el_usuario = ? WHERE id = ?");
            $stmt->execute([$estado, $partidaId]);

            // retorno el resultado de la partida
            $dataPartida = [
                "el usuario" => $estado,
                "total ganadas" => $totalGanadas,
                "total perdidas" => $totalPerdidas
            ];
        }

        // retorno el resultado de la jugada
        $dataRonda = [
            "carta servidor" => (int) $cartaServidorid,
            "carta usuario" => (int) $cartaUserId,
            "resultado" => $estadoJugada
        ];
        $data = [
            "ronda" => $dataRonda,
            "partida" => isset($dataPartida) ? $dataPartida : "aun no tenemos un ganador",
        ];
        $response->getBody()->write(json_encode($data));
        return $response->withHeader("Content-Type", "application/json")->withStatus(200); // OK
    })->add($jwt); // Agregar el middleware JWT a la ruta de jugada
};