<?php
use Psr\Http\Message\ResponseInterface as Response; // Importar la interfaz de respuesta de PSR
use Psr\Http\Message\ServerRequestInterface as Request; // Importar la interfaz de solicitud de PSR

require_once __DIR__ ."/movementsServer.php"; // Importar el archivo de servidor de movimientos
require_once __DIR__ ."/../../config/Database.php"; // Importar la clase de conexión a la base de datos
require_once __DIR__ ."/../../helpers/validation.php"; // Importar la clase de validación
return function ($app, $jwt){

    $movementsServer = new movementsServer(); // Importar el archivo de servidor de movimientos
    
    # en POSTMAN escribir el body en formato JSON de la siguiente manera:
    # {
    #     "partida_id": "id de la partida",
    #     "carta": "id de la carta"
    # }
    $app->post('/jugadas', function (Request $request, Response $response) use ($movementsServer){
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
            $stmt = $pdo->prepare("SELECT mazo_id FROM partida WHERE id = ? AND estado = 'en_curso' AND usuario_id = ?");
            $stmt->execute([$partidaId, $userId]);
            $mazoId = $stmt->fetchColumn();
    
            if (!$mazoId) {
                $response->getBody()->write(json_encode(["error"=> "la partida termino, o no existe o no es de tu propiedad."]));
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
            $cartaServidorid = $movementsServer->jugadaServidor($partidaId);
    
            // Traigo los datos de la carta del jugador
            $stmt = $pdo->prepare("SELECT c.ataque, c.atributo_id, a.nombre FROM carta c
            INNER JOIN atributo a ON c.atributo_id = a.id WHERE c.id = ?");
            $stmt->execute([$cartaUserId]);
            $cartaUser = $stmt->fetch(PDO::FETCH_ASSOC);

            $fuerzaUser = $cartaUser['ataque'];
            $atributoNombreUser = $cartaUser['nombre'];

            // Traigo los datos de la carta del servidor
            $stmt->execute([$cartaServidorid]);
            $cartaServidor = $stmt->fetch(PDO::FETCH_ASSOC);

            $fuerzaServidor = $cartaServidor['ataque'];
            $atributoNombreServer = $cartaServidor['nombre'];   


            // establezco el array de ventajas de atributos
            $ventajas = require __DIR__ ."/../../config/ventajaAtributos.php"; // Importar el archivo de ventajas de atributos

            $atributoBuff = "";
            if (validation::tieneVentaja($atributoNombreUser, $atributoNombreServer, $ventajas)) {
                $fuerzaUser *=1.30;
                $atributoBuff = "ventaja para el usuario";
            }
            if (validation::tieneVentaja($atributoNombreServer, $atributoNombreUser, $ventajas)) {
                $fuerzaServidor *=1.30;
                $atributoBuff = "ventaja para el servidor";
            }
    
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

            // actualizo el estado de la carta del servidor
            $stmt = $pdo->prepare("UPDATE mazo_carta SET estado = 'descartado' WHERE mazo_id = 1 AND carta_id = ?");
            $stmt->execute([$cartaServidorid]);
    
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
                $stmt = $pdo->prepare("UPDATE mazo_carta SET estado = 'en_mazo' WHERE mazo_id IN (?, 1)");
                $stmt->execute([$mazoId]);
    
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
                "atributo servidor" => $atributoNombreServer,
                "fuerza servidor" => (int) $fuerzaServidor,
                "--------------------" => "vs",
                "carta usuario" => (int) $cartaUserId,
                "atributo usuario" => $atributoNombreUser,
                "fuerza usuario" => (int) $fuerzaUser,
                "--------" => "resultado",
                "buffo " => $atributoBuff,
                "resultado" => $estadoJugada
            ];
    
            $data = [
                "ronda" => $dataRonda,
                "partida" => isset($dataPartida) ? $dataPartida : "aun no tenemos un ganador",
            ];
    
            $response->getBody()->write(json_encode($data));
            return $response->withHeader("Content-Type", "application/json")->withStatus(200);
    
        } catch (PDOException $e) {
            $response->getBody()->write(json_encode([
                'error' => 'Error de conexión o consulta a la base de datos.',
                'detalle' => $e->getMessage() // Mostrar detalle del error
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    
    })->add($jwt);


    # en POSTMAN en la url escribir el id de la partida a consultar en el lugar de {partida}

    $app->get('/usuarios/{usuario}/partidas/{partida}/cartas', function (Request $request, Response $response, array $args) {        
        $partidaId = $args['partida'] ?? "";
        $userId = $args['usuario'] ?? "";

        if ($partidaId == "") {
            $response->getBody()->write(json_encode(["error" => "faltan datos para realizar la muestra."]));
            return $response->withHeader("Content-Type", "application/json")->withStatus(400); // Bad Request
        }
        
        if ($userId == "") {
            $response->getBody()->write(json_encode(["error" => "faltan datos para realizar la muestra."]));
            return $response->withHeader("Content-Type", "application/json")->withStatus(400); // Bad Request
        }

        $userId = trim($userId);
        $partidaId = trim($partidaId);
        try {
            $pdo = Database::getConnection();

            // si el id del usuario es 1, entonces es el servidor, solo extraigo los atributos de las cartas que tenga en mano
            if ($userId == 1) {
                $stmt = $pdo->prepare("
                SELECT a.nombre AS atributo
                FROM mazo_carta mc
                INNER JOIN carta c ON mc.carta_id = c.id
                INNER JOIN atributo a ON c.atributo_id = a.id
                WHERE mc.mazo_id = 1 AND mc.estado = 'en_mano'"
                );
                $stmt->execute();
                $atributos = $stmt->fetchAll(PDO::FETCH_COLUMN);

                $data = [
                    "atributos" => $atributos
                ];
    
                    $response->getBody()->write(json_encode($data));
                    return $response->withHeader("Content-Type", "application/json")->withStatus(200);
            }

            // Verifico que la partida exista y sea del usuario
            $stmt = $pdo->prepare("SELECT mazo_id FROM partida WHERE id = ? AND usuario_id = ?");
            $stmt->execute([$partidaId, $userId]);
            $mazoId = $stmt->fetchColumn();

            // verifico que la partida no haya finalizado
            $stmt = $pdo->prepare("SELECT estado FROM partida WHERE id = ? AND estado = 'en_curso' AND usuario_id = ?");
            $stmt->execute([$partidaId, $userId]);
            $partidaEstado = $stmt->fetchColumn();

            if (!$partidaEstado) {
                $response->getBody()->write(json_encode(["error"=> "la partida no existe o ya ha finalizado."]));
                return $response->withHeader("Content-Type", "application/json")->withStatus(400); // Bad Request
            }

            if (!$mazoId) {
                $response->getBody()->write(json_encode(["error"=> "la partida no existe o no es de tu propiedad."]));
                return $response->withHeader("Content-Type", "application/json")->withStatus(400); // Bad Request
            }

            $stmt = $pdo->prepare("
            SELECT a.nombre AS atributo
            FROM partida p
            INNER JOIN mazo_carta mc ON mc.mazo_id = p.mazo_id
            INNER JOIN carta c ON mc.carta_id = c.id
            INNER JOIN atributo a ON c.atributo_id = a.id
            WHERE p.id = ? AND mc.estado = 'en_mano'
            ");
            $stmt->execute([$partidaId]);
            $atributos = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
            if (!$atributos) {
                $response->getBody()->write(json_encode(["error" => "no hay cartas en mano"]));
                return $response->withHeader("Content-Type", "application/json")->withStatus(400);
            }
        
            // Armar respuesta
            $data = [
                "atributos" => $atributos
            ];

                $response->getBody()->write(json_encode($data));
                return $response->withHeader("Content-Type", "application/json")->withStatus(200);

        } catch (PDOException $e) {
            $response->getBody()->write(json_encode([
                'error' => 'Error de conexión o consulta a la base de datos.',
                'detalle' => $e->getMessage() // Mostrar detalle del error
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    })->add($jwt);
    
};