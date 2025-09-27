<?php
// Define o tipo de conteúdo da resposta como JSON
header('Content-Type: application/json');

// Função para enviar respostas JSON padronizadas
function json_response($status_code, $data) {
    http_response_code($status_code);
    echo json_encode($data);
    exit;
}

// 1. Verifica se a requisição é do tipo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(405, ['status' => 'error', 'message' => 'Método não permitido. Apenas POST é aceito.']);
}

// 2. Lê o corpo da requisição (raw POST data)
$json_data = file_get_contents('php://input');

// Verifica se algum dado foi recebido
if (empty($json_data)) {
    json_response(400, ['status' => 'error', 'message' => 'Nenhum dado recebido no corpo da requisição.']);
}

// 3. Decodifica o payload JSON para um array associativo
$event_data = json_decode($json_data, true);

// Verifica se o JSON é válido
if (json_last_error() !== JSON_ERROR_NONE) {
    json_response(400, ['status' => 'error', 'message' => 'JSON inválido.']);
}

// 4. Lógica para atualizar os dados da entrega
$deliveries_file = 'data/deliveries.json';
$json_data_db = file_get_contents($deliveries_file);
$deliveries = json_decode($json_data_db, true);

$delivery_found = false;
foreach ($deliveries as $key => $delivery) {
    if ($delivery['delivery_id'] == $event_data['delivery_id']) {
        // Atualiza o status e a localização
        $deliveries[$key]['status'] = $event_data['status'];
        $deliveries[$key]['current_location'] = $event_data['location'];

        // Adiciona um novo registro ao histórico
        $history_entry = [
            'timestamp' => date('Y-m-d\TH:i:s\Z'),
            'status' => $event_data['status'],
            'location' => 'Atualizado via Webhook' 
        ];
        $deliveries[$key]['update_history'][] = $history_entry;
        
        $delivery_found = true;
        break;
    }
}

if (!$delivery_found) {
    json_response(404, ['status' => 'error', 'message' => 'Entrega não encontrada.']);
}

// Salva os dados atualizados de volta no arquivo JSON
file_put_contents($deliveries_file, json_encode($deliveries, JSON_PRETTY_PRINT));


// 5. Responde com sucesso
json_response(200, ['status' => 'success', 'message' => 'Evento recebido e dados da entrega atualizados.']);

?>
