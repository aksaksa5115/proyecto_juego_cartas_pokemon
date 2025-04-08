<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;

// ESTO SOLO SE USA SI SE INGRESAN USUARIOS DE FORMA MANUAL EN LA BASE DE DATOS
return function (App $app, PDO $pdo) {
$app->put('/actContras', function (Request $request, Response $response) use ($pdo) {
    try {
        $stmt = $pdo->query("SELECT id, password FROM usuario");
        $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $actualizados = 0;

        foreach ($usuarios as $user) {
            $pass = $user['password'];

            // Si no parece estar hasheada, la actualizamos
            if (strlen($pass) !== 60 || !str_starts_with($pass, '$2y$')) {
                $hashed = password_hash($pass, PASSWORD_BCRYPT);
                $updateStmt = $pdo->prepare("UPDATE usuario SET password = ? WHERE id = ?");
                $updateStmt->execute([$hashed, $user['id']]);
                $actualizados++;
            }
        }

        $response->getBody()->write(json_encode([
            "mensaje" => "Contraseñas actualizadas",
            "usuarios_actualizados" => $actualizados
        ]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    } catch (PDOException $e) {
        $response->getBody()->write(json_encode([
            "error" => "Error al actualizar contraseñas"
        ]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    }
});
};
