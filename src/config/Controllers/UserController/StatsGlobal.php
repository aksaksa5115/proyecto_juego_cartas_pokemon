<?php
use Psr\Http\Message\ResponseInterface as Response; // Importar la interfaz de respuesta de PSR
use Psr\Http\Message\ServerRequestInterface as Request; // Importar la interfaz de solicitud de PSR

return function ($app, PDO $pdo){

    $app->get('/stats', function (Request $request, Response $response) use ($pdo){
        
        # preparo consulta para traer todo
        $sql = "
        SELECT 
            u.id AS usuario_id,
            u.usuario,
            SUM(CASE WHEN p.el_usuario = 'gano' THEN 1 ELSE 0 END) AS ganadas,
            SUM(CASE WHEN p.el_usuario = 'perdio' THEN 1 ELSE 0 END) AS perdidas,
            SUM(CASE WHEN p.el_usuario = 'empato' THEN 1 ELSE 0 END) AS empatadas
        FROM usuario u
        LEFT JOIN partida p ON u.id = p.usuario_id AND p.estado = 'finalizada'
        GROUP BY u.id, u.usuario
        ";
        $stmt = $pdo->query($sql);
        $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $response->getBody()->write(json_encode(['message' => $stats]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200); // OK
    });
};