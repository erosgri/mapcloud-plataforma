<?php session_start(); ?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rastreamento de Entregas</title>
    
    <!-- Vanilla Framework CSS -->
    <link rel="stylesheet" href="https://assets.ubuntu.com/v1/vanilla-framework-version-4.34.1.min.css" />
    
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="public/css/style.css">
</head>
<body>
    <header class="p-strip--light">
        <div class="row">
            <div class="col-12">
                <h1 class="p-heading--3">Rastreamento de Entregas MapCloud</h1>
            </div>
        </div>
    </header>

    <main>
        <div class="p-strip">
            <div class="row">
                <div class="col-6">
                    <h2>Upload de NF-e (XML)</h2>
                    <form action="upload_nfe.php" method="post" enctype="multipart/form-data">
                        <label for="nfe_xml">Selecione o arquivo XML da NF-e:</label>
                        <input type="file" id="nfe_xml" name="nfe_xml" accept=".xml" required>
                        <button type="submit" class="p-button--positive">Enviar NF-e</button>
                    </form>
                </div>
                <div class="col-6">
                    <h2>Dados da NF-e</h2>
                    <div id="nfe-data" class="p-card">
                        <?php if (isset($_SESSION['nfe_data'])): ?>
                            <?php $nfe = $_SESSION['nfe_data']; ?>
                            <p><strong>Chave de Acesso:</strong><br><?= htmlspecialchars($nfe['chave']) ?></p>
                            <p><strong>Número:</strong> <?= htmlspecialchars($nfe['numero']) ?></p>
                            <p><strong>Emitente:</strong> <?= htmlspecialchars($nfe['emitente']) ?></p>
                            <p><strong>Destinatário:</strong> <?= htmlspecialchars($nfe['destinatario']) ?></p>
                            <?php 
                                // Limpa os dados da sessão depois de exibi-los
                                unset($_SESSION['nfe_data']); 
                            ?>
                        <?php else: ?>
                            <p>Aguardando o upload de um arquivo NF-e...</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div id="map"></div>
    </main>
    
    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    
    <!-- Custom JS -->
    <script src="public/js/app.js"></script>
</body>
</html>
