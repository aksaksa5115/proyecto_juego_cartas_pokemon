<?php
use Psr\Http\Message\ResponseInterface as Response; // Importar la interfaz de respuesta de PSR
use Psr\Http\Message\ServerRequestInterface as Request; // Importar la interfaz de solicitud de PSR

require_once __DIR__ ."/movementsServer.php"; // Importar el archivo de servidor de movimientos
require_once __DIR__ ."/../../config/Database.php"; // Importar la clase de conexión a la base de datos
require_once __DIR__ ."/../../helpers/validation.php"; // Importar la clase de validación
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
            $cartaServidorid = $movementsServer->jugadaServidor($partidaId);
    
            // Traigo el ataque, atributo y nombre del atributo de ambas cartas
            $stmt = $pdo->prepare("SELECT c.ataque, c.atributo_id, a.nombre FROM carta c
            INNER JOIN atributo a ON c.atributo_id = a.id WHERE c.id IN (?, ?)");
            $stmt->execute([$cartaUserId, $cartaServidorid]);
            $carta = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Verificamos si tenemos dos resultados
            if (count($carta) < 2) {
                // Si no hay suficientes resultados, manejar el error apropiadamente
                $response->getBody()->write(json_encode(['error' => 'No se encontraron las cartas o datos inválidos.']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
                }
            
            $fuerzaUser = $carta[0]['ataque'];
            $atributoUser = $carta[0]['atributo_id'];
            $atributoNombreUser = $carta[0]['nombre'];
            
            $fuerzaServidor = $carta[1]['ataque'];
            $atributoServer = $carta[1]['atributo_id'];
            $atributoNombreServer = $carta[1]['nombre'];


            // establezco el array de ventajas de atributos
            $ventajas = require __DIR__ ."/../../config/ventajasAtributos.php"; // Importar el archivo de ventajas de atributos


            if (validation::tieneVentaja($atributoUser, $atributoServer, $ventajas)) {
                $fuerzaUser *=1.30;
            }
            if (validation::tieneVentaja($atributoServer, $atributoUser, $ventajas)) {
                $fuerzaServidor *=1.30;
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
                'detalle' => $e->getMessage() // 👈 Mostrar detalle del error
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    
    })->add($jwt);


    $app->get('/usuario/partida/{partida}/cartas', function (Request $request, Response $response, array $args) {        
        $user = $request->getAttribute('jwt');
        $userId = $user->sub;
        $partidaId = $args['partida'] ?? "";

        if ($partidaId == "") {
            $response->getBody()->write(json_encode(["error" => "faltan datos para realizar la muestra."]));
            return $response->withHeader("Content-Type", "application/json")->withStatus(400); // Bad Request
        }

        $partidaId = trim($partidaId);

        try {
            $pdo = Database::getConnection();

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

            // Traigo las cartas del mazo
            $stmt = $pdo->prepare("SELECT carta_id FROM mazo_carta WHERE mazo_id = ? AND estado = 'en_mano'");
            $stmt->execute([$mazoId]);
            $cartas = $stmt->fetchAll(PDO::FETCH_COLUMN);

            if (!$cartas) {
                $response->getBody()->write(json_encode(["error"=> "no hay cartas en el mazo"]));
                return $response->withHeader("Content-Type", "application/json")->withStatus(400);
            }

            // Armo la respuesta
            $data = [
                "cartas" => array_map('intval', $cartas)
            ];

            $response->getBody()->write(json_encode($data));
            return $response->withHeader("Content-Type", "application/json")->withStatus(200);

        } catch (PDOException $e) {
            $response->getBody()->write(json_encode([
                'error' => 'Error de conexión o consulta a la base de datos.',
                'detalle' => $e->getMessage() // 👈 Mostrar detalle del error
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    })->add($jwt);
    
};