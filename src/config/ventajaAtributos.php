<?php
return [
// Este archivo fue generado automáticamente.
// Se actualiza cada vez que se crea una partida.

    'Fuego' => ['Tierra', 'Piedra', 'Planta'], // Fuego le gana a Tierra, Piedra, Planta
    'Agua' => ['Fuego'], // Agua le gana a Fuego
    'Tierra' => ['Piedra'], // Tierra le gana a Piedra
    'Volador' => ['Normal', 'Planta'], // Volador le gana a Normal, Planta
    'Piedra' => ['Agua'], // Piedra le gana a Agua
    'Planta' => ['Tierra', 'Piedra', 'Agua'], // Planta le gana a Tierra, Piedra, Agua
];
