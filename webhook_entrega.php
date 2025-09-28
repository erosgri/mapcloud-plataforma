<?php
require_once 'pdo/conexao.php';
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

// Conecta ao banco de dados usando a nova conexão global
require_once 'pdo/conexao.php';
global $pdo;
$conexao = $pdo;

// Obter o ID da entrega a partir do delivery_id (que é a chave da NF-e)
$stmt = $conexao->prepare("SELECT id FROM entregas WHERE delivery_id = ?");
$stmt->execute([$event_data['delivery_id']]);
$entrega = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$entrega) {
    throw new Exception('Entrega não encontrada.');
}
$entrega_id = $entrega['id'];

// Inicia uma transação para garantir a consistência dos dados
$conexao->beginTransaction();

try {
    // Atualiza a tabela de entregas
    $stmt_update = $conexao->prepare(
        "UPDATE entregas SET status = ?, current_lat = ?, current_lng = ? WHERE id = ?"
    );
    $stmt_update->execute([
        $event_data['status'],
        $event_data['location']['lat'],
        $event_data['location']['lng'],
        $entrega_id
    ]);

    // Insere o novo evento no histórico
    $stmt_insert = $conexao->prepare(
        "INSERT INTO eventos_entrega (entrega_id, timestamp, status, location_description) VALUES (?, ?, ?, ?)"
    );
    $stmt_insert->execute([
        $entrega_id,
        date('Y-m-d H:i:s'), // Timestamp atual
        $event_data['status'],
        'Atualizado via Webhook'
    ]);
    
    // Se tudo deu certo, confirma as alterações
    $conexao->commit();

} catch (Exception $e) {
    // Se algo deu errado, desfaz as alterações
    $conexao->rollBack();
    // Você pode querer logar o $e->getMessage() em um arquivo de erro
    json_response(404, ['status' => 'error', 'message' => $e->getMessage()]);
}


// 5. Responde com sucesso
json_response(200, ['status' => 'success', 'message' => 'Evento recebido e dados da entrega atualizados.']);

?>
