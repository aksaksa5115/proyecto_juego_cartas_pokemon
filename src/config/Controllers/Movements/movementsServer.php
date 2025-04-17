<?php



return function($pdo, $partidaId){

    // primero obtenemos las cartas del servidor que no fueron usadas, escogiendo una al azar
    $stmt = $pdo->prepare("
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

    // actualizar estado de la carta en mazo_carta
    $stmt = $pdo->prepare("UPDATE mazo_carta SET estado = 'descartado' WHERE mazo_id = 1 AND carta_id = ?");
    $stmt->execute([$cartaId]);

    // retorno la carta al cliente
    return (int) $cartaId;
};