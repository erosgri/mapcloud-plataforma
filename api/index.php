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

function get_data_from_cep($cep) {
    // 1. Limpa e valida o CEP
    $cep = preg_replace('/[^0-9]/', '', $cep);
    if (strlen($cep) !== 8) {
        return ['error' => 'CEP inválido.'];
    }

    // 2. Consulta a API do ViaCEP
    $via_cep_url = "https://viacep.com.br/ws/{$cep}/json/";
    $address_data = json_decode(@file_get_contents($via_cep_url), true);

    if (isset($address_data['erro'])) {
        return ['error' => 'CEP não encontrado.'];
    }

    // 3. Monta a query para a API de geocodificação (Nominatim)
    $query = http_build_query([
        'street' => $address_data['logradouro'],
        'city' => $address_data['localidade'],
        'state' => $address_data['uf'],
        'format' => 'json',
        'limit' => 1
    ]);

    $nominatim_url = "https://nominatim.openstreetmap.org/search?{$query}";
    
    // É necessário um User-Agent para usar a API do Nominatim
    $context = stream_context_create(['http' => ['header' => 'User-Agent: MapCloudPlataforma/1.0']]);
    $geo_data = json_decode(@file_get_contents($nominatim_url, false, $context), true);

    // 4. Combina os resultados
    $result = $address_data;
    if (!empty($geo_data)) {
        $result['lat'] = $geo_data[0]['lat'];
        $result['lon'] = $geo_data[0]['lon'];
    } else {
        $result['lat'] = null;
        $result['lon'] = null;
        $result['warning'] = 'Coordenadas não encontradas para este endereço.';
    }

    return $result;
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

    case 'cep':
        if (isset($route_parts[1]) && $method === 'GET') {
            $cep = $route_parts[1];
            $data = get_data_from_cep($cep);
            if (isset($data['error'])) {
                json_response(404, $data);
            } else {
                json_response(200, $data);
            }
        } else {
            json_response(400, ['error' => 'CEP não fornecido.']);
        }
        break;

    default:
        json_response(404, ['error' => 'Endpoint não encontrado']);
        break;
}
