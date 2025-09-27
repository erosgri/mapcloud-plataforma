<?php
// Roteador da nossa API REST
require_once '../pdo/conexao.php';
header('Content-Type: application/json');

// --- Funções Auxiliares ---

function json_response($status_code, $data) {
    http_response_code($status_code);
    echo json_encode($data, JSON_NUMERIC_CHECK); // Garante que números sejam números, não strings
    exit;
}

// Função para ler os dados do banco de dados
function get_all_deliveries() {
    $conexao = novaConexao();
    $stmt = $conexao->query("SELECT * FROM entregas");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Função para buscar uma entrega específica por ID
function get_delivery_by_nfe_key($nfe_key) {
    $conexao = novaConexao();
    
    // Busca a entrega principal
    $stmt = $conexao->prepare("SELECT * FROM entregas WHERE nfe_key = ?");
    $stmt->execute([$nfe_key]);
    $delivery = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$delivery) {
        return null;
    }

    // Busca o histórico de eventos
    $stmt = $conexao->prepare("SELECT timestamp, status, location_description FROM eventos_entrega WHERE entrega_id = ? ORDER BY timestamp ASC");
    $stmt->execute([$delivery['id']]);
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Combina os resultados
    // Mapeia a estrutura do BD para a estrutura que o front-end espera
    $delivery_details = [
        "delivery_id" => $delivery['delivery_id'],
        "nfe_key" => $delivery['nfe_key'],
        "status" => $delivery['status'],
        "origin" => [
            "name" => $delivery['origin_name'],
            "lat" => $delivery['origin_lat'],
            "lng" => $delivery['origin_lng']
        ],
        "destination" => [
            "name" => $delivery['destination_name'],
            "lat" => $delivery['destination_lat'],
            "lng" => $delivery['destination_lng']
        ],
        "current_location" => [
            "lat" => $delivery['current_lat'],
            "lng" => $delivery['current_lng']
        ],
        "update_history" => $history
    ];
    
    return $delivery_details;
}

// --- Roteamento ---

$request_uri = $_SERVER['REQUEST_URI'];
$base_path = '/mapcloud-plataforma/api';
$route = str_replace($base_path, '', $request_uri);
$route = trim($route, '/');
$route_parts = explode('/', $route);

$method = $_SERVER['REQUEST_METHOD'];

switch ($route_parts[0]) {
    case 'deliveries':
        // A busca no front-end é por NFE, vamos adaptar
        $nfeKey = $_GET['nfe_key'] ?? null;

        if ($nfeKey && $method === 'GET') {
            $delivery = get_delivery_by_nfe_key($nfeKey);
            if ($delivery) {
                json_response(200, $delivery);
            } else {
                json_response(404, ['error' => 'Entrega não encontrada']);
            }
        } 
        elseif ($method === 'GET') {
            $deliveries = get_all_deliveries();
            json_response(200, $deliveries);
        } else {
            json_response(405, ['error' => 'Método não permitido']);
        }
        break;

    case 'metrics':
        if ($method === 'GET') {
            $conexao = novaConexao();
            $stmt = $conexao->query("SELECT status, COUNT(*) as count FROM entregas GROUP BY status");
            $status_counts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            
            $stmt_total = $conexao->query("SELECT COUNT(*) FROM entregas");
            $total_deliveries = $stmt_total->fetchColumn();

            $metrics = [
                'total_deliveries' => $total_deliveries,
                'status_count' => $status_counts
            ];
            
            json_response(200, $metrics);
        } else {
            json_response(405, ['error' => 'Método não permitido']);
        }
        break;

    default:
        json_response(404, ['error' => 'Endpoint não encontrado']);
        break;
}
