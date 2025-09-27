<?php
require_once 'pdo/conexao.php';

function get_coords_by_cep($cep) {
    $cep = preg_replace('/[^0-9]/', '', (string)$cep);
    if (strlen($cep) !== 8) return [null, null];

    $via_cep_url = "https://viacep.com.br/ws/{$cep}/json/";
    $addr = json_decode(@file_get_contents($via_cep_url), true);
    if (!$addr || isset($addr['erro'])) return [null, null];

    $query = http_build_query([
        'street' => $addr['logradouro'] ?? '',
        'city' => $addr['localidade'] ?? '',
        'state' => $addr['uf'] ?? '',
        'format' => 'json',
        'limit' => 1
    ]);
    $nominatim_url = "https://nominatim.openstreetmap.org/search?{$query}";
    $context = stream_context_create(['http' => ['header' => 'User-Agent: MapCloudPlataforma/1.0']]);
    $geo = json_decode(@file_get_contents($nominatim_url, false, $context), true);

    if (!empty($geo)) {
        return [ (float)$geo[0]['lat'], (float)$geo[0]['lon'] ];
    }
    return [null, null];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['nfe_xml']) && $_FILES['nfe_xml']['error'] === UPLOAD_ERR_OK) {
        $xml_file_path = $_FILES['nfe_xml']['tmp_name'];
        $xml = simplexml_load_file($xml_file_path);
        if ($xml === false) {
            echo "Erro ao ler o arquivo XML.";
            exit;
        }

        // Extrai campos principais
        $chave = (string) ($xml->protNFe->infProt->chNFe ?? '');
        $numero = (string) ($xml->NFe->infNFe->ide->nNF ?? '');
        $emitente = (string) ($xml->NFe->infNFe->emit->xNome ?? '');
        $destinatario = (string) ($xml->NFe->infNFe->dest->xNome ?? '');

        // CEPs, quando disponíveis
        $cep_emit = (string) ($xml->NFe->infNFe->emit->enderEmit->CEP ?? '');
        $cep_dest = (string) ($xml->NFe->infNFe->dest->enderDest->CEP ?? '');

        // Coordenadas por CEP
        [$origin_lat, $origin_lng] = get_coords_by_cep($cep_emit);
        [$dest_lat, $dest_lng] = get_coords_by_cep($cep_dest);

        // Persiste no banco
        $db = novaConexao();
        try {
            $db->beginTransaction();

            // cria delivery_id simples a partir da chave/número
            $delivery_id = $chave ? substr($chave, -5) : ($numero ?: uniqid());

            $stmt = $db->prepare("INSERT INTO entregas (
                delivery_id, nfe_key, status,
                origin_name, origin_lat, origin_lng,
                destination_name, destination_lat, destination_lng,
                current_lat, current_lng
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

            $status_inicial = 'pending_pickup';
            $stmt->execute([
                $delivery_id,
                $chave,
                $status_inicial,
                $emitente,
                $origin_lat, $origin_lng,
                $destinatario,
                $dest_lat, $dest_lng,
                $origin_lat, $origin_lng
            ]);

            $entrega_id = (int)$db->lastInsertId();

            // evento inicial
            $stmtEv = $db->prepare("INSERT INTO eventos_entrega (entrega_id, timestamp, status, location_description)
                                    VALUES (?, ?, ?, ?)");
            $stmtEv->execute([
                $entrega_id,
                date('Y-m-d H:i:s'),
                $status_inicial,
                'NF-e cadastrada via upload'
            ]);

            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
            echo 'Erro ao salvar no banco: ' . $e->getMessage();
            exit;
        }

        // Redireciona de volta para o index.php
        header('Location: index.php');
        exit;

    } else {
        echo "Erro no upload do arquivo.";
    }
} else {
    header('Location: index.php');
    exit;
}
