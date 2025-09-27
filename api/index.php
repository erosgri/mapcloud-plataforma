<?php
// Roteador da nossa API REST
header('Content-Type: application/json');

// --- Funções Auxiliares ---

// Função para enviar respostas JSON padronizadas
function json_response($status_code, $data) {
    http_response_code($status_code);
    echo json_encode($data);
    exit;
}

// Função para ler os dados do nosso "banco de dados"
function get_all_deliveries() {
    $json_data = file_get_contents('../data/deliveries.json');
    return json_decode($json_data, true);
}

// Função para buscar uma entrega específica por ID
function get_delivery_by_id($id) {
    $deliveries = get_all_deliveries();
    foreach ($deliveries as $delivery) {
        if ($delivery['delivery_id'] == $id) {
            return $delivery;
        }
    }
    return null; // Retorna nulo se não encontrar
}

// --- Roteamento ---

// Pega a URL da requisição, ignorando a parte do diretório do projeto
$request_uri = $_SERVER['REQUEST_URI'];
$base_path = '/mapcloud-plataforma/api';
$route = str_replace($base_path, '', $request_uri);
$route = trim($route, '/');
$route_parts = explode('/', $route);

// Analisa a rota e o método da requisição
$method = $_SERVER['REQUEST_METHOD'];

switch ($route_parts[0]) {
    case 'deliveries':
        // Se a rota for 'deliveries/{id}'
        if (isset($route_parts[1]) && $method === 'GET') {
            $delivery_id = $route_parts[1];
            $delivery = get_delivery_by_id($delivery_id);
            if ($delivery) {
                json_response(200, $delivery);
            } else {
                json_response(404, ['error' => 'Entrega não encontrada']);
            }
        } 
        // Se a rota for apenas 'deliveries'
        elseif ($method === 'GET') {
            $deliveries = get_all_deliveries();
            json_response(200, $deliveries);
        } else {
            json_response(405, ['error' => 'Método não permitido']);
        }
        break;

    case 'metrics':
        if ($method === 'GET') {
            $deliveries = get_all_deliveries();
            $metrics = [
                'total_deliveries' => count($deliveries),
                'status_count' => [
                    'in_transit' => 0,
                    'delivered' => 0,
                    'pending_pickup' => 0,
                    // Adicionar outros status conforme necessário
                ]
            ];
            foreach ($deliveries as $delivery) {
                if (isset($metrics['status_count'][$delivery['status']])) {
                    $metrics['status_count'][$delivery['status']]++;
                }
            }
            json_response(200, $metrics);
        } else {
            json_response(405, ['error' => 'Método não permitido']);
        }
        break;

    default:
        json_response(404, ['error' => 'Endpoint não encontrado']);
        break;
}
