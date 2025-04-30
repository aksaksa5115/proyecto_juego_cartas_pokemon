<?php
use Psr\Http\Message\ResponseInterface as Response; // Importar la interfaz de respuesta de PSR
use Psr\Http\Message\ServerRequestInterface as Request; // Importar la interfaz de solicitud de PSR
require_once __DIR__ ."/../../config/Database.php"; // Importar la clase de conexión a la base de datos
    # EN ESTOS METODOS ES NECESARIO HACER LO SIGUIENTE:
    # en POSTMAN en Headers en la parte de Key poner:
    # Authorization y en Value poner Bearer seguido del token que se genera al loguearse.

return function ($app, $JWT) {

    # en POSTMAN escribir esto en formato JSON de la siguiente manera:
    # {
    #  "mazo": 1, // id del mazo a usar
    # }
    $app->post('/partidas', function (Request $request, Response $response) {
        $body = $request->getParsedBody();
    
        if (!$body || !is_array($body)) {
            $response->getBody()->write(json_encode(["error" => "Cuerpo de la solicitud inválido."]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
    
        $user = $request->getAttribute('jwt');
        $userId = $user->sub;
        $mazoId = $body['mazo'] ?? null;
    
        if ($mazoId === null) {
            $response->getBody()->write(json_encode(['error' => 'El ID del mazo es requerido.']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
    
        try {
            $pdo = Database::getConnection();
    
            // Verificar que el mazo exista y pertenezca al usuario
            $stmt = $pdo->prepare("SELECT id FROM mazo WHERE id = ? AND usuario_id = ?");
            $stmt->execute([$mazoId, $userId]);
            $mazo = $stmt->fetchColumn();
    
            if (!$mazo) {
                $response->getBody()->write(json_encode(['error' => 'El mazo no existe o no te pertenece.']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }
    
            // Verificar que el mazo del usuario no esté en uso
            $stmt = $pdo->prepare("SELECT id FROM partida WHERE mazo_id = ? AND estado = 'en_curso'");
            $stmt->execute([$mazoId]);
            if ($stmt->fetchColumn()) {
                $response->getBody()->write(json_encode(['error' => 'El mazo ya está en uso en otra partida.']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            // verificar que el mazo del servidor no esté en uso
            $stmt = $pdo->prepare("SELECT id FROM partida WHERE estado = 'en_curso'");
    
            // Crear la partida
            $stmt = $pdo->prepare("INSERT INTO partida (usuario_id, el_usuario, fecha, mazo_id, estado) 
                VALUES (?, NULL, NOW(), ?, 'en_curso')");
            $stmt->execute([$userId, $mazoId]);
            $partidaId = $pdo->lastInsertId();
    
            // Actualizar cartas a 'en_mano'
            $stmt = $pdo->prepare("UPDATE mazo_carta SET estado = 'en_mano' WHERE mazo_id IN (?, 1)");
            $stmt->execute([$mazoId]);
    
            // Obtener las cartas
            $stmt = $pdo->prepare("SELECT nombre, ataque FROM carta WHERE id IN (SELECT carta_id FROM mazo_carta WHERE mazo_id = ?)");
            $stmt->execute([$mazoId]);
            $cartas = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // se testearan cosas aca, creamos un archivo php desde aca que almacenara el array asociativo de los atributos
            // y sus ventajas, para luego ser usado en el juego
            // Consulta con JOIN para traer los nombres
            $stmt = $pdo->query("
            SELECT a1.nombre AS atributo, a2.nombre AS venceA
            FROM gana_a g
            JOIN atributo a1 ON g.atributo_id = a1.id
            JOIN atributo a2 ON g.atributo_id2 = a2.id
            ");
        
            $ventajas = [];
        
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $a = $row['atributo'];
              $v = $row['venceA'];
              $ventajas[$a][] = $v;
          }
        
            // Armamos el contenido manualmente con sintaxis limpia
            $contenido = "<?php\nreturn [\n";
            $contenido .= "// Este archivo fue generado automáticamente.\n";
            $contenido .= "// Se actualiza cada vez que se crea una partida.\n\n";
        
            foreach ($ventajas as $clave => $valores) {
                $lista = "['" . implode("', '", $valores) . "']";
                $comentario = "// $clave le gana a " . implode(", ", $valores);
                $contenido .= "    '$clave' => $lista, $comentario\n";
            }
        
            $contenido .= "];\n";
        
            // Guardamos
            file_put_contents(__DIR__ . '/../../config/ventajaAtributos.php', $contenido);
            
    
            $response->getBody()->write(json_encode([
                'message' => 'Partida creada con éxito.',
                'partida_id' => $partidaId,
                'cartas' => $cartas
            ]));
    
            return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
    
        } catch (Exception $e) {
            $response->getBody()->write(json_encode(['error' => 'Error al crear la partida.',
            'detalle' => $e->getMessage()])); 
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    })->add($JWT);
    

    # en POSTMAN escribir esto en la URL de la siguiente manera:
    # "ruta al proyecto"/partida/"id de la partida a eliminar"

    // este endpoint sirve para debuggear en caso de hacer una partida de forma incorrecta
    $app->delete('/partidas/{duelo}', function (Request $request, Response $response, array $args) {
        $user = $request->getAttribute('jwt');
        $partidaIdAeliminar = $args['duelo'];
    
        try {
            $pdo = Database::getConnection();
    
            // Verificar que la partida exista
            $stmt = $pdo->prepare("SELECT id FROM partida WHERE id = ?");
            $stmt->execute([$partidaIdAeliminar]);
            $partida = $stmt->fetchColumn();
    
            if (!$partida) {
                $response->getBody()->write(json_encode(['error' => 'La partida no existe.']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }
    
            // Resetear cartas a 'en_mazo'
            $stmt = $pdo->prepare("UPDATE mazo_carta SET estado = 'en_mazo' WHERE mazo_id = 
                (SELECT mazo_id FROM partida WHERE id = ?)");
            $stmt->execute([$partidaIdAeliminar]);
    
            // Eliminar la partida
            $stmt = $pdo->prepare("DELETE FROM partida WHERE id = ?");
            $stmt->execute([$partidaIdAeliminar]);
    
            $response->getBody()->write(json_encode(['message' => "Partida con ID '$partidaIdAeliminar' eliminada con éxito."]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    
        } catch (Exception $e) {
            $response->getBody()->write(json_encode(['error' => 'Error al eliminar la partida.',
            'detalle' => $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    })->add($JWT);
    
};