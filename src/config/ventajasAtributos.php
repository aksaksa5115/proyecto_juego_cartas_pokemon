<?php
// determinamos si hay ventaja de atributo
            // array de ventajas
$ventajas = [
    1 => [3, 6, 7], // Fuego le gana a Tierra, Piedra y Planta
    2 => [1],       // Agua le gana a Fuego
    3 => [6],       // Tierra le gana a Piedra
    5 => [4, 7],    // Volador le gana a Normal y Planta
    6 => [2],       // Piedra le gana a Agua
    7 => [3, 6, 2], // Planta le gana a Tierra, Piedra y Agua
];

return $ventajas;