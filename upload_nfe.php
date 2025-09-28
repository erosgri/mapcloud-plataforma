<?php
require_once 'pdo/conexao.php';

function get_coords_by_cep($cep) {
    $cep = preg_replace('/[^0-9]/', '', (string)$cep);
    if (strlen($cep) !== 8) return [null, null];

    $via_cep_url = "https://viacep.com.br/ws/{$cep}/json/";
    $addr = json_decode(@file_get_contents($via_cep_url), true);
    if (!$addr || isset($addr['erro'])) return [null, null];

    $query = http_build_query([
        'street' => $addr['logradouro'] ?? '', 'city' => $addr['localidade'] ?? '', 'state' => $addr['uf'] ?? '',
        'format' => 'json', 'limit' => 1
    ]);
    $nominatim_url = "https://nominatim.openstreetmap.org/search?{$query}";
    $context = stream_context_create(['http' => ['header' => 'User-Agent: MapCloudPlataforma/1.0']]);
    $geo = json_decode(@file_get_contents($nominatim_url, false, $context), true);

    if (!empty($geo)) {
        return [ (float)$geo[0]['lat'], (float)$geo[0]['lon'] ];
    }
    return [null, null];
}

function sanitize_name($name) {
    // Remover caracteres especiais e normalizar
    $name = trim($name);
    $name = str_replace(['"', "'", '`'], '', $name);
    $name = preg_replace('/[^\p{L}\p{N}\s\-\.]/u', '', $name);
    $name = preg_replace('/\s+/', ' ', $name);
    return $name;
}

function json_response($status_code, $data) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code($status_code);
    echo json_encode($data);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $motorista_id = !empty($_POST['motorista_id']) ? (int)$_POST['motorista_id'] : null;

    try {
        // Debug: verificar $_FILES
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            file_put_contents('nfe_debug.log', "--- DEBUG \$_FILES ---\n" . print_r($_FILES, true) . "\n", FILE_APPEND);
        }
        
        if (!isset($_FILES['nfe_file']) || $_FILES['nfe_file']['error'] !== UPLOAD_ERR_OK) {
            $error_msg = 'Erro no upload do arquivo. ';
            if (isset($_FILES['nfe_file'])) {
                $error_msg .= 'Código de erro: ' . $_FILES['nfe_file']['error'];
            } else {
                $error_msg .= 'Arquivo não foi enviado.';
            }
            throw new Exception($error_msg);
        }

        $file = $_FILES['nfe_file'];
        if ($file['size'] > (5 * 1024 * 1024)) { // 5MB
            throw new Exception('Tamanho do arquivo excede o limite de 5MB.');
        }
        if (strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'xml') {
            throw new Exception('O arquivo enviado não é um XML.');
        }

        $xml_string = file_get_contents($file['tmp_name']);
        
        // Debug: verificar se o arquivo foi lido
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            file_put_contents('nfe_debug.log', "--- DEBUG XML STRING ---\nTamanho: " . strlen($xml_string) . "\nConteúdo: " . substr($xml_string, 0, 200) . "...\n", FILE_APPEND);
        }
        
        // --- Log para depuração (apenas em desenvolvimento) ---
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            file_put_contents('nfe_debug.log', "--- " . date('Y-m-d H:i:s') . " ---\n" . $xml_string . "\n\n", FILE_APPEND);
        }

        $xml = @simplexml_load_string($xml_string);
        if ($xml === false) {
            throw new Exception('Não foi possível ler o arquivo XML. Verifique se o formato está correto.');
        }
        
        // Extrair o número da NF-e (chave)
        $chave_nfe = '';
        if (isset($xml->ide->nNF)) {
            $chave_nfe = (string)$xml->ide->nNF;
        } elseif (isset($xml->NFe->infNFe->ide->nNF)) {
            $chave_nfe = (string)$xml->NFe->infNFe->ide->nNF;
        } else {
            $chave_nfe = 'NF' . time(); // Fallback
        }
        
        // Tentar extrair emitente
        $emitente = 'Emitente não identificado';
        if (isset($xml->emit->xNome)) {
            $emitente = sanitize_name((string)$xml->emit->xNome);
        } elseif (isset($xml->NFe->infNFe->emit->xNome)) {
            $emitente = sanitize_name((string)$xml->NFe->infNFe->emit->xNome);
        }

        // Tentar extrair destinatário
        $destinatario = 'Destinatário não identificado';
        if (isset($xml->dest->xNome)) {
            $destinatario = sanitize_name((string)$xml->dest->xNome);
        } elseif (isset($xml->NFe->infNFe->dest->xNome)) {
            $destinatario = sanitize_name((string)$xml->NFe->infNFe->dest->xNome);
        }
        
        // --- LÓGICA DE EXTRAÇÃO DE CEP ATUALIZADA ---
        $cep_emit = '';
        $cep_dest = '';

        // Tenta extrair CEP do novo formato (string única)
        if (isset($xml->emit->enderEmit)) {
            if (preg_match('/CEP:\s*(\d{8}|\d{5}-?\d{3})/', (string)$xml->emit->enderEmit, $matches)) {
                $cep_emit = $matches[1];
            }
        }
        if (isset($xml->dest->enderDest)) {
            if (preg_match('/CEP:\s*(\d{8}|\d{5}-?\d{3})/', (string)$xml->dest->enderDest, $matches)) {
                $cep_dest = $matches[1];
            }
        }

        // Fallback para o formato antigo (tags separadas)
        if (empty($cep_emit)) {
            if (isset($xml->NFe->infNFe->emit->enderEmit->CEP)) {
                $cep_emit = (string)$xml->NFe->infNFe->emit->enderEmit->CEP;
            } elseif (isset($xml->emit->enderEmit->CEP)) {
                $cep_emit = (string)$xml->emit->enderEmit->CEP;
            }
        }
        if (empty($cep_dest)) {
            if (isset($xml->NFe->infNFe->dest->enderDest->CEP)) {
                $cep_dest = (string)$xml->NFe->infNFe->dest->enderDest->CEP;
            } elseif (isset($xml->dest->enderDest->CEP)) {
                $cep_dest = (string)$xml->dest->enderDest->CEP;
            }
        }
        
        if (empty($cep_emit) || empty($cep_dest)) {
            throw new Exception('Não foi possível encontrar o CEP de origem ou destino no XML.');
        }

        // Debug: Log dos dados extraídos (apenas em desenvolvimento)
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            file_put_contents('nfe_debug.log', "Dados extraídos:\n", FILE_APPEND);
            file_put_contents('nfe_debug.log', "Número: $chave_nfe\n", FILE_APPEND);
            file_put_contents('nfe_debug.log', "Emitente: $emitente\n", FILE_APPEND);
            file_put_contents('nfe_debug.log', "Destinatário: $destinatario\n", FILE_APPEND);
        }
        
        // Extrair CEP das tags específicas
        // $cep_emit = '';
        // if (isset($xml->NFe->infNFe->emit->enderEmit->CEP)) {
        //     $cep_emit = (string)$xml->NFe->infNFe->emit->enderEmit->CEP;
        // } elseif (isset($xml->emit->enderEmit->CEP)) {
        //     $cep_emit = (string)$xml->emit->enderEmit->CEP;
        // } elseif (isset($xml->remetente->endereco->cep)) {
        //     $cep_emit = (string)$xml->remetente->endereco->cep;
        // } elseif (isset($xml->emit->enderEmit)) {
        //     // Fallback: tentar extrair de string com regex
        //     preg_match('/CEP:(\d{8}|\d{5}-\d{3})/', (string)$xml->emit->enderEmit, $matches);
        //     if (!empty($matches[1])) $cep_emit = $matches[1];
        // }

        // $cep_dest = '';
        // if (isset($xml->NFe->infNFe->dest->enderDest->CEP)) {
        //     $cep_dest = (string)$xml->NFe->infNFe->dest->enderDest->CEP;
        // } elseif (isset($xml->dest->enderDest->CEP)) {
        //     $cep_dest = (string)$xml->dest->enderDest->CEP;
        // } elseif (isset($xml->destinatario->endereco->cep)) {
        //     $cep_dest = (string)$xml->destinatario->endereco->cep;
        // } elseif (isset($xml->dest->enderDest)) {
        //     // Fallback: tentar extrair de string com regex
        //     preg_match('/CEP:(\d{8}|\d{5}-\d{3})/', (string)$xml->dest->enderDest, $matches);
        //     if (!empty($matches[1])) $cep_dest = $matches[1];
        // }
        
        // Debug: Log dos CEPs (apenas em desenvolvimento)
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            file_put_contents('nfe_debug.log', "CEP Emitente: $cep_emit\n", FILE_APPEND);
            file_put_contents('nfe_debug.log', "CEP Destinatário: $cep_dest\n", FILE_APPEND);
        }

        // Para este formato, a chave não existe, usamos o número da nota
        $chave = $chave_nfe; 
        if (empty($chave)) {
            // Se não encontrou número, usar timestamp como fallback
            $chave = 'NF' . time();
            $numero = $chave;
            if (defined('DEBUG_MODE') && DEBUG_MODE) {
                file_put_contents('nfe_debug.log', "Número não encontrado, usando fallback: $chave\n", FILE_APPEND);
            }
        }
        // --- Fim da Leitura Adaptada ---

        [$origin_lat, $origin_lng] = get_coords_by_cep($cep_emit);
        [$dest_lat, $dest_lng] = get_coords_by_cep($cep_dest);
        
        // Debug: Log das coordenadas (apenas em desenvolvimento)
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            file_put_contents('nfe_debug.log', "Coordenadas Origem: $origin_lat, $origin_lng\n", FILE_APPEND);
            file_put_contents('nfe_debug.log', "Coordenadas Destino: $dest_lat, $dest_lng\n", FILE_APPEND);
        }

        $db = novaConexao();
        
        // --- Verificação de Duplicidade ---
        $delivery_id = $chave ? substr($chave, -5) : ($numero ?: uniqid());
        
        // Debug: Log do delivery_id (apenas em desenvolvimento)
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            file_put_contents('nfe_debug.log', "Delivery ID: $delivery_id\n", FILE_APPEND);
            file_put_contents('nfe_debug.log', "Motorista ID: $motorista_id\n", FILE_APPEND);
        }
        $stmt_check = $db->prepare("SELECT id FROM entregas WHERE delivery_id = ?");
        $stmt_check->execute([$delivery_id]);
        if ($stmt_check->fetch()) {
            throw new Exception("Esta NF-e (ID: {$delivery_id}) já foi cadastrada anteriormente.");
        }
        // --- Fim da Verificação ---

        $db->beginTransaction();
        
        $status_inicial = 'pending_pickup';

        $stmt = $db->prepare("INSERT INTO entregas (
            delivery_id, nfe_key, status,
            origin_name, origin_lat, origin_lng,
            destination_name, destination_lat, destination_lng,
            current_lat, current_lng, motorista_id
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->execute([
            $delivery_id, $chave, $status_inicial,
            $emitente, $origin_lat, $origin_lng,
            $destinatario, $dest_lat, $dest_lng,
            $origin_lat, $origin_lng, $motorista_id
        ]);
        $entrega_id = (int)$db->lastInsertId();

        // Insere evento inicial
        $stmtEv = $db->prepare("INSERT INTO eventos_entrega (entrega_id, timestamp, status, location_description) VALUES (?, ?, ?, ?)");
        $stmtEv->execute([$entrega_id, date('Y-m-d H:i:s'), $status_inicial, 'NF-e cadastrada via upload']);

        $db->commit();
        
        json_response(200, ['message' => 'NF-e processada e salva com sucesso!']);

    } catch (Exception $e) {
        if (isset($db) && $db->inTransaction()) {
            $db->rollBack();
        }
        json_response(400, ['error' => $e->getMessage()]);
    }
} else {
    json_response(405, ['error' => 'Método não permitido']);
}
