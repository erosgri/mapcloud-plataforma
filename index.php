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
                <div class="col-4">
                    <h2>Consulta de Entrega</h2>
                    <form id="search-form">
                        <label for="nfe_key">Chave da NF-e:</label>
                        <input type="text" id="nfe_key" name="nfe_key" placeholder="Digite a chave da NF-e" required>
                        <button type="submit" class="p-button--positive u-align--right">Buscar</button>
                    </form>
                    <div id="delivery-details" class="u-hide">
                        <hr>
                        <h4>Detalhes da Entrega</h4>
                        <p><strong>Status:</strong> <span id="delivery-status"></span></p>
                        <p><strong>Origem:</strong> <span id="delivery-origin"></span></p>
                        <p><strong>Destino:</strong> <span id="delivery-destination"></span></p>
                    </div>
                </div>
                <div class="col-8">
                    <h2>Histórico (Timeline)</h2>
                    <div id="timeline-container">
                        <p>Busque uma entrega para ver seu histórico de rastreamento.</p>
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
