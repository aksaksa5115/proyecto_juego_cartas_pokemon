<?php


require_once __DIR__ ."/../../config/Database.php"; // Importar la clase de conexión a la base de datos
class movementsServer {
    private $pdo;
    private const SERVER_MAZO_ID = 1;

    public function __construct() {
        $this->pdo = Database::getConnection(); // <- ESTA LÍNEA ES FUNDAMENTAL
    }
    public function jugadaServidor($partidaId){
        // primero obtenemos las cartas del servidor que no fueron usadas, escogiendo una al azar
        $stmt = $this->pdo->prepare("
        SELECT mc.carta_id FROM mazo_carta mc
        WHERE mc.mazo_id = 1
        AND mc.estado != 'descartado'
        AND mc.carta_id NOT IN (
        SELECT j.carta_id_B FROM jugada j
        WHERE j.partida_id = ? 
        )
        ORDER BY RAND()
        LIMIT 1");

        $stmt->execute([$partidaId]);
        $cartaId = $stmt->fetchColumn();

        if (!$cartaId){
            throw new Exception("no hay cartas disponibles en el servidor");
        }
        // retorno la carta al cliente
        return (int) $cartaId;
    }
}