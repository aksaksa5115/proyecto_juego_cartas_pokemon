<?php
use Psr\Http\Message\ResponseInterface as Response; // Importar la interfaz de respuesta de PSR
use Psr\Http\Message\ServerRequestInterface as Request; // Importar la interfaz de solicitud de PSR

require_once __DIR__ ."/movementsServer.php"; // Importar el archivo de servidor de movimientos
require_once __DIR__ ."/../../config/Database.php"; // Importar la clase de conexión a la base de datos
return function ($app, $jwt){

    $movementsServer = new movementsServer(); // Importar el archivo de servidor de movimientos
    
    $app->post('/jugada', function (Request $request, Response $response) use ($movementsServer){
        $body = $request->getParsedBody();
        
        if (!$body || !is_array($body) ) {
            $response->getBody()->write(json_encode(["error" => "Cuerpo de la solicitud inválido."]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
        $user = $request->getAttribute('jwt');
        $userId = $user->sub;
    
        $partidaId = $body['partida_id'] ?? "";
        $cartaUserId = $body['carta'] ?? "";

        $partidaId = trim($partidaId);
        $cartaUserId = trim($cartaUserId);
    
        if ($partidaId == "" || $cartaUserId == "") {
            $response->getBody()->write(json_encode(["error" => "faltan datos para realizar la jugada."]));
            return $response->withHeader("Content-Type", "application/json")->withStatus(400); // Bad Request
        }
    
        try {
            $pdo = Database::getConnection();
    
            // Verifico que la partida exista y sea del usuario
            $stmt = $pdo->prepare("SELECT mazo_id FROM partida WHERE id = ? AND usuario_id = ?");
            $stmt->execute([$partidaId, $userId]);
            $mazoId = $stmt->fetchColumn();
    
            if (!$mazoId) {
                $response->getBody()->write(json_encode(["error"=> "la partida no existe o no es de tu propiedad."]));
                return $response->withHeader("Content-Type", "application/json")->withStatus(400); // Bad Request
            }
    
            // Verifico que la carta esté en el mazo y en mano
            $stmt = $pdo->prepare("SELECT estado FROM mazo_carta WHERE mazo_id = ? AND carta_id = ? AND estado = 'en_mano'");
            $stmt->execute([$mazoId, $cartaUserId]);
            $carta = $stmt->fetchColumn();
    
            if (!$carta) {
                $response->getBody()->write(json_encode(["error"=> "la carta es invalida"]));
                return $response->withHeader("Content-Type", "application/json")->withStatus(400);
            }
    
            // Traigo la carta del servidor
            $cartaServidorid = $movementsServer->cartaServer($partidaId);
    
            // Traigo el ataque de ambas cartas
            $stmt = $pdo->prepare("SELECT ataque FROM carta WHERE id IN (?, ?)");
            $stmt->execute([$cartaUserId, $cartaServidorid]);
            $carta = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
            $fuerzaUser = $carta[0]['ataque'];
            $fuerzaServidor = $carta[1]['ataque'];
    
            // Determino el resultado de la jugada
            $estadoJugada = '';
            if ($fuerzaUser > $fuerzaServidor) {
                $estadoJugada = 'gano';
            } elseif ($fuerzaUser < $fuerzaServidor) {
                $estadoJugada = 'perdio';
            } else {
                $estadoJugada = 'empato';
            }
    
            // Actualizo el estado de la carta del usuario
            $stmt = $pdo->prepare("UPDATE mazo_carta SET estado = 'descartado' WHERE mazo_id = ? AND carta_id = ?");
            $stmt->execute([$mazoId, $cartaUserId]);
    
            // Registro la jugada
            $stmt = $pdo->prepare("INSERT INTO jugada (partida_id, carta_id_a, carta_id_b, el_usuario) VALUES (?, ?, ?, ?)");
            $stmt->execute([$partidaId, $cartaUserId, $cartaServidorid, $estadoJugada]);
    
            // Consulto estadísticas de la partida
            $stmt = $pdo->prepare("SELECT 
                COUNT(*) AS total_jugadas,
                SUM(CASE WHEN el_usuario = 'gano' THEN 1 ELSE 0 END) AS total_ganadas,
                SUM(CASE WHEN el_usuario = 'perdio' THEN 1 ELSE 0 END) AS total_perdidas
                FROM jugada WHERE partida_id = ?");
            $stmt->execute([$partidaId]);
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
    
            $totalJugadas = (int) $resultado['total_jugadas'];
            $totalGanadas = (int) $resultado['total_ganadas'];
            $totalPerdidas = (int) $resultado['total_perdidas'];
    
            // Si es la quinta jugada, finalizo la partida
            if ($totalJugadas == 5) {
                $resultado = $totalGanadas - $totalPerdidas;
                $estado = 'empato';
                if ($resultado > 0) $estado = 'gano';
                elseif ($resultado < 0) $estado = 'perdio';
    
                // Actualizo estado de la partida
                $stmt = $pdo->prepare("UPDATE partida SET estado = 'finalizada', el_usuario = ? WHERE id = ?");
                $stmt->execute([$estado, $partidaId]);
    
                // Vuelvo las cartas al mazo
                $stmt = $pdo->prepare("UPDATE mazo_carta SET estado = 'en_mazo' WHERE mazo_id = ? AND carta_id IN (?, ?)");
                $stmt->execute([$mazoId, $cartaUserId, $cartaServidorid]);
    
                // Info para respuesta
                $dataPartida = [
                    "el usuario" => $estado,
                    "total ganadas" => $totalGanadas,
                    "total perdidas" => $totalPerdidas
                ];
            }
    
            // Armo la respuesta
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
            return $response->withHeader("Content-Type", "application/json")->withStatus(200);
    
        } catch (PDOException $e) {
            $response->getBody()->write(json_encode(["error" => "Error de conexión o consulta a la base de datos."]));
            return $response->withHeader("Content-Type", "application/json")->withStatus(500);
        }
    
    })->add($jwt);
    
};