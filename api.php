<?php

header('Content-Type: application/json');

$routeLatLngs = [
    [-23.5505, -46.6333], // Ponto inicial
    [-23.5550, -46.6380],
    [-23.5600, -46.6400],
    [-23.5650, -46.6450],
    [-23.5700, -46.6500]  // Ponto final
];

echo json_encode($routeLatLngs);
