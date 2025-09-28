<?php
// Roteador da nossa API REST
require_once '../pdo/conexao.php';
header('Content-Type: application/json; charset=utf-8');

// --- Tratamento de Erros Global ---
// Captura erros e exceções para sempre retornar uma resposta JSON válida.
set_exception_handler(function ($exception) {
    json_response(500, ['error' => 'Erro interno no servidor: ' . $exception->getMessage()]);
});
set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});


// --- Funções Auxiliares ---

function json_response($status_code, $data) {
    http_response_code($status_code);
    echo json_encode($data, JSON_NUMERIC_CHECK); // Garante que números sejam números, não strings
    exit;
}

function sanitize_ascii($value) {
    if ($value === null) return null;
    $s = (string)$value;
    // corrigir mojibake comum (UTF-8 visto como Latin-1)
    $moji = [
        'Ã¡'=>'a','Ã¢'=>'a','Ã£'=>'a','Ã¤'=>'a','Ã '=>'a','ÃÁ'=>'A','ÃÂ'=>'A','Ãƒ'=>'A','Ã„'=>'A','Ã€'=>'A',
        'Ã©'=>'e','Ãª'=>'e','Ã«'=>'e','Ã¨'=>'e','Ã‰'=>'E','ÃŠ'=>'E','Ã‹'=>'E','Ãˆ'=>'E',
        'Ã­'=>'i','Ã®'=>'i','Ã¯'=>'i','Ã¬'=>'i','ÃÌ'=>'I','ÃÍ'=>'I','ÃÎ'=>'I','ÃÏ'=>'I',
        'Ã³'=>'o','Ã´'=>'o','Ãµ'=>'o','Ã¶'=>'o','Ã²'=>'o','Ã“'=>'O','Ã”'=>'O','Ã•'=>'O','Ã–'=>'O','Ã’'=>'O',
        'Ãº'=>'u','Ã»'=>'u','Ã¼'=>'u','Ã¹'=>'u','Ãš'=>'U','Ã›'=>'U','Ãœ'=>'U','Ã™'=>'U',
        'Ã§'=>'c','Ã‡'=>'C','Ã±'=>'n','Ã‘'=>'N','Â'=>'',
    ];
    $s = strtr($s, $moji);
    // remoção de demais acentos (Português)
    $map = [
        'á'=>'a','à'=>'a','â'=>'a','ã'=>'a','ä'=>'a','Á'=>'A','À'=>'A','Â'=>'A','Ã'=>'A','Ä'=>'A',
        'é'=>'e','è'=>'e','ê'=>'e','ë'=>'e','É'=>'E','È'=>'E','Ê'=>'E','Ë'=>'E',
        'í'=>'i','ì'=>'i','î'=>'i','ï'=>'i','Í'=>'I','Ì'=>'I','Î'=>'I','Ï'=>'I',
        'ó'=>'o','ò'=>'o','ô'=>'o','õ'=>'o','ö'=>'o','Ó'=>'O','Ò'=>'O','Ô'=>'O','Õ'=>'O','Ö'=>'O',
        'ú'=>'u','ù'=>'u','û'=>'u','ü'=>'u','Ú'=>'U','Ù'=>'U','Û'=>'U','Ü'=>'U',
        'ç'=>'c','Ç'=>'C','ñ'=>'n','Ñ'=>'N'
    ];
    $s = strtr($s, $map);
    // manter apenas letras, números, espaço e hífen
    $s = preg_replace('/[^A-Za-z0-9 \-]/u', '', $s);
    // normalizar espaços
    $s = preg_replace('/\s+/u', ' ', $s);
    return trim($s);
}

// Função para ler os dados do banco de dados
function get_all_deliveries($motorista_id = null) {
    global $pdo; // Usa a conexão PDO global
    $conexao = $pdo;
    
    // 1. Busca as entregas e o nome do motorista
    $sql_deliveries = "SELECT e.*, m.nome as driver_name 
                       FROM entregas e 
                       LEFT JOIN motoristas m ON e.motorista_id = m.id";
    $params = [];
    if ($motorista_id) {
        $sql_deliveries .= " WHERE e.motorista_id = ?";
        $params[] = $motorista_id;
    }
    
    $stmt_deliveries = $conexao->prepare($sql_deliveries);
    $stmt_deliveries->execute($params);
    $deliveries = $stmt_deliveries->fetchAll(PDO::FETCH_ASSOC);

    if (empty($deliveries)) {
        return []; // Se não há entregas, retorna array vazio
    }

    // 2. Busca todos os eventos para as entregas encontradas (mais eficiente)
    $delivery_ids = array_map(fn($d) => $d['id'], $deliveries);
    $placeholders = rtrim(str_repeat('?,', count($delivery_ids)), ',');
    
    $sql_events = "SELECT * FROM eventos_entrega WHERE entrega_id IN ($placeholders) ORDER BY timestamp DESC";
    $stmt_events = $conexao->prepare($sql_events);
    $stmt_events->execute($delivery_ids);
    $all_events = $stmt_events->fetchAll(PDO::FETCH_ASSOC);

    // 3. Mapeia os eventos para suas respectivas entregas
    $events_by_delivery = [];
    foreach ($all_events as $event) {
        $events_by_delivery[$event['entrega_id']][] = $event;
    }

    // 4. Combina os dados e sanitiza os nomes
    foreach ($deliveries as &$delivery) {
        $delivery['update_history'] = $events_by_delivery[$delivery['id']] ?? [];
        $delivery['origin_name'] = sanitize_ascii($delivery['origin_name'] ?? null);
        $delivery['destination_name'] = sanitize_ascii($delivery['destination_name'] ?? null);
        $delivery['driver_name'] = sanitize_ascii($delivery['driver_name'] ?? null);
        
        // Mapear campos para compatibilidade com o frontend
        $delivery['current_lat'] = $delivery['origin_lat']; // Posição atual = origem (inicialmente)
        $delivery['current_lng'] = $delivery['origin_lng'];
    }

    return $deliveries;
}

// Função para buscar uma entrega específica por ID
function get_delivery_by_nfe_key($nfe_key) {
    global $pdo; // Usa a conexão PDO global
    $conexao = $pdo;
    
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
            "name" => sanitize_ascii($delivery['origin_name'] ?? null),
            "lat" => $delivery['origin_lat'],
            "lng" => $delivery['origin_lng']
        ],
        "destination" => [
            "name" => sanitize_ascii($delivery['destination_name'] ?? null),
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

function get_all_drivers() {
    global $pdo; // Usa a conexão PDO global
    $conexao = $pdo;
    // Corrigido: Seleciona 'placa_veiculo' e a renomeia para 'placa' para compatibilidade com o front-end
    $stmt = $conexao->query("SELECT id, nome, placa_veiculo AS placa, documento, telefone FROM motoristas ORDER BY nome");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // sanitiza campos textuais
    foreach ($rows as &$r) {
        $r['nome'] = sanitize_ascii($r['nome'] ?? null);
        $r['placa'] = sanitize_ascii($r['placa'] ?? null);
        $r['documento'] = sanitize_ascii($r['documento'] ?? null);
        $r['telefone'] = sanitize_ascii($r['telefone'] ?? null);
    }
    return $rows;
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
$base_path = '/mapcloud-plataforma/api/index.php';
$route = str_replace($base_path, '', $request_uri);

// Remove parâmetros da query string para processar apenas a rota
$route = parse_url($route, PHP_URL_PATH);
$route = trim($route, '/');
$route_parts = explode('/', $route);

$method = $_SERVER['REQUEST_METHOD'];

switch ($route_parts[0]) {
    case 'deliveries':
        // A busca no front-end é por NFE, vamos adaptar
        $motoristaId = $_GET['motorista_id'] ?? null;
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
            $deliveries = get_all_deliveries($motoristaId);
            json_response(200, $deliveries);
        } else {
            json_response(405, ['error' => 'Método não permitido']);
        }
        break;

    case 'metrics':
        if ($method === 'GET') {
            global $pdo; // Usa a conexão PDO global
            $conexao = $pdo;
            $motoristaId = $_GET['motorista_id'] ?? null;
            if ($motoristaId) {
                $stmt = $conexao->prepare("SELECT status, COUNT(*) as count FROM entregas WHERE motorista_id = ? GROUP BY status");
                $stmt->execute([$motoristaId]);
                $status_counts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

                $stmt_total = $conexao->prepare("SELECT COUNT(*) FROM entregas WHERE motorista_id = ?");
                $stmt_total->execute([$motoristaId]);
                $total_deliveries = $stmt_total->fetchColumn();
            } else {
                $stmt = $conexao->query("SELECT status, COUNT(*) as count FROM entregas GROUP BY status");
                $status_counts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
                $stmt_total = $conexao->query("SELECT COUNT(*) FROM entregas");
                $total_deliveries = $stmt_total->fetchColumn();
            }
            // Preparar métricas no formato esperado pelo frontend
            $metrics = [
                'total_entregas' => (int)$total_deliveries,
                'em_transito' => (int)($status_counts['in_transit'] ?? 0),
                'entregues' => (int)($status_counts['delivered'] ?? 0),
                'nao_entregues' => (int)($status_counts['failed'] ?? 0)
            ];
            json_response(200, $metrics);
        } else {
            json_response(405, ['error' => 'Método não permitido']);
        }
        break;

    case 'drivers':
        if ($method === 'GET') {
            $drivers = get_all_drivers();
            json_response(200, $drivers);
        } elseif ($method === 'POST') {
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) {
                json_response(400, ['error' => 'Dados inválidos']);
            }
            
            // Validação básica
            if (empty($input['nome'])) {
                json_response(400, ['error' => 'Nome é obrigatório']);
            }
            
            global $pdo; // Usa a conexão PDO global
            $conexao = $pdo;
            try {
                $stmt = $conexao->prepare("INSERT INTO motoristas (nome, placa_veiculo, documento, telefone) VALUES (?, ?, ?, ?)");
                $stmt->execute([
                    sanitize_ascii($input['nome']),
                    sanitize_ascii($input['placa'] ?? null),
                    sanitize_ascii($input['documento'] ?? null),
                    sanitize_ascii($input['telefone'] ?? null)
                ]);
                
                $driver_id = $conexao->lastInsertId();
                json_response(201, ['message' => 'Motorista cadastrado com sucesso', 'id' => $driver_id]);
            } catch (Exception $e) {
                json_response(500, ['error' => 'Erro ao cadastrar motorista: ' . $e->getMessage()]);
            }
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

    case 'delivery':
        if (isset($route_parts[1]) && $route_parts[1] === 'status' && $method === 'POST') {
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input || !isset($input['delivery_ids']) || !isset($input['status'])) {
                json_response(400, ['error' => 'Dados inválidos']);
            }
            
            global $pdo; // Usa a conexão PDO global
            $conexao = $pdo;
            $conexao->beginTransaction();
            
            try {
                $action = $input['action'] ?? 'update';
                $location_description = '';
                
                // Definir descrição baseada na ação
                switch ($action) {
                    case 'collect':
                        $location_description = 'Coleta realizada via interface';
                        break;
                    case 'deliver':
                        $location_description = 'Entrega realizada via interface';
                        break;
                    case 'failed':
                        $location_description = 'Entrega falhou via interface';
                        break;
                    default:
                        $location_description = 'Status atualizado via interface';
                }
                
                foreach ($input['delivery_ids'] as $delivery_id) {
                    // Atualizar status da entrega
                    $stmt = $conexao->prepare("UPDATE entregas SET status = ? WHERE delivery_id = ?");
                    $stmt->execute([$input['status'], $delivery_id]);
                    
                    // Para falhas, adicionar motivo específico
                    $event_description = $location_description;
                    if ($action === 'failed' && isset($input['reasons'])) {
                        $reason = null;
                        foreach ($input['reasons'] as $reason_data) {
                            if ($reason_data['delivery_id'] === $delivery_id) {
                                $reason = $reason_data['reason'];
                                break;
                            }
                        }
                        
                        if ($reason) {
                            $reason_text = '';
                            switch ($reason) {
                                case 'cliente_nao_encontrado':
                                    $reason_text = 'Cliente não encontrado';
                                    break;
                                case 'cliente_recusou':
                                    $reason_text = 'Cliente recusou a entrega';
                                    break;
                                case 'mercadoria_avariada':
                                    $reason_text = 'Mercadoria avariada';
                                    break;
                                default:
                                    $reason_text = 'Motivo não informado';
                            }
                            $event_description = "Entrega falhou: {$reason_text}";
                        }
                    }
                    
                    // Adicionar evento
                    $stmt_event = $conexao->prepare("INSERT INTO eventos_entrega (entrega_id, status, location_description) 
                                                   SELECT id, ?, ? 
                                                   FROM entregas WHERE delivery_id = ?");
                    $stmt_event->execute([$input['status'], $event_description, $delivery_id]);
                }
                
                $conexao->commit();
                json_response(200, ['message' => 'Ação processada com sucesso']);
            } catch (Exception $e) {
                $conexao->rollback();
                json_response(500, ['error' => 'Erro ao processar ação: ' . $e->getMessage()]);
            }
        } else {
            json_response(404, ['error' => 'Endpoint não encontrado']);
        }
        break;

    default:
        json_response(404, ['error' => 'Endpoint não encontrado']);
        break;
}
