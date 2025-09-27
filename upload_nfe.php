<?php

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['nfe_xml']) && $_FILES['nfe_xml']['error'] === UPLOAD_ERR_OK) {
        
        $xml_file_path = $_FILES['nfe_xml']['tmp_name'];
        
        // Carrega o XML
        $xml = simplexml_load_file($xml_file_path);

        if ($xml === false) {
            echo "Erro ao ler o arquivo XML.";
            exit;
        }

        // Namespace da NF-e (geralmente é este)
        $ns = 'http://www.portalfiscal.inf.br/nfe';

        // Extrai os dados usando o namespace
        $chave = (string) $xml->protNFe->infProt->chNFe;
        $numero = (string) $xml->NFe->infNFe->ide->nNF;
        $emitente = (string) $xml->NFe->infNFe->emit->xNome;
        $destinatario = (string) $xml->NFe->infNFe->dest->xNome;

        // Monta um array com os dados extraídos
        $nfe_data = [
            'chave' => $chave,
            'numero' => $numero,
            'emitente' => $emitente,
            'destinatario' => $destinatario,
        ];

        // Inicia a sessão para passar os dados para a página principal
        session_start();
        $_SESSION['nfe_data'] = $nfe_data;

        // Redireciona de volta para o index.php
        header('Location: index.php');
        exit;
        
    } else {
        echo "Erro no upload do arquivo.";
    }
} else {
    // Redireciona para a página inicial se o acesso não for via POST
    header('Location: index.php');
    exit;
}
