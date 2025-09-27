<?php session_start(); // Mantido para futuras funcionalidades de login, etc. ?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MapCloud - Dashboard de Entregas</title>
    
    <!-- Frameworks e CSS -->
    <link rel="stylesheet" href="https://assets.ubuntu.com/v1/vanilla-framework-version-4.34.1.min.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="public/css/style.css">
</head>
<body>
    <header class="p-strip--light">
        <div class="row">
            <div class="col-12">
                <h1 class="p-heading--3">Dashboard de Monitoramento</h1>
            </div>
        </div>
    </header>

    <main>
        <!-- Seção de KPIs -->
        <div class="p-strip--light kpi-strip">
            <div class="row" id="kpi-container">
                <div class="col-4">
                    <div class="p-card--highlighted">
                        <h4 class="p-heading--6 u-no-margin--bottom">Total de Entregas</h4>
                        <p id="kpi-total" class="kpi-value u-no-margin--bottom">0</p>
                    </div>
                </div>
                <div class="col-4">
                    <div class="p-card--highlighted">
                        <h4 class="p-heading--6 u-no-margin--bottom">Em Trânsito</h4>
                        <p id="kpi-in-transit" class="kpi-value u-no-margin--bottom">0</p>
                    </div>
                </div>
                <div class="col-4">
                    <div class="p-card--highlighted">
                        <h4 class="p-heading--6 u-no-margin--bottom">Entregues</h4>
                        <p id="kpi-delivered" class="kpi-value u-no-margin--bottom">0</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Seção Principal com Mapa e Feed -->
        <div class="p-strip">
            <div class="row">
                <div class="col-8">
                    <div class="p-card">
                        <h3 class="p-heading--4">Localização das Entregas Ativas</h3>
                        <div id="map"></div>
                    </div>
                </div>
                <div class="col-4">
                    <div class="p-card">
                        <h3 class="p-heading--4">Feed de Eventos</h3>
                        <div id="timeline-container">
                            <p>Carregando eventos...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
    
    <!-- Scripts -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="public/js/app.js"></script>
</body>
</html>
