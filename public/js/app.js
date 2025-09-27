document.addEventListener('DOMContentLoaded', function () {
    console.log("Sistema de rastreamento iniciado!");

    // Coordenadas de exemplo (São Paulo)
    const initialCoords = [-23.5505, -46.6333];
    
    // Inicializa o mapa
    const map = L.map('map').setView(initialCoords, 13);

    // Adiciona a camada de mapa (tile layer) do OpenStreetMap
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(map);

    console.log("Mapa inicializado com sucesso!");

    // --- Início da lógica com dados da API ---

    // Busca os dados da rota da nossa API PHP
    fetch('api.php')
        .then(response => response.json())
        .then(routeLatLngs => {
            
            // Desenha a polilinha da rota no mapa
            const polyline = L.polyline(routeLatLngs, {color: 'blue'}).addTo(map);

            // Adiciona marcadores para o início e o fim da rota
            L.marker(routeLatLngs[0]).addTo(map).bindPopup("<b>Início da Rota</b>");
            L.marker(routeLatLngs[routeLatLngs.length - 1]).addTo(map).bindPopup("<b>Fim da Rota</b>");

            // Ajusta o zoom do mapa para mostrar a rota inteira
            map.fitBounds(polyline.getBounds());

            // Cria um ícone personalizado para o veículo de entrega
            const truckIcon = L.icon({
                iconUrl: 'https://cdn-icons-png.flaticon.com/64/610/610115.png',
                iconSize: [38, 38], // Tamanho do ícone
                iconAnchor: [19, 38], // Ponto do ícone que corresponderá à posição do marcador
                popupAnchor: [0, -38] // Ponto a partir do qual o popup deve abrir em relação ao iconAnchor
            });

            // Adiciona o marcador do veículo no ponto inicial da rota
            const truckMarker = L.marker(routeLatLngs[0], {icon: truckIcon}).addTo(map)
                .bindPopup("<b>Veículo de Entrega</b>");

            // Animação do veículo
            let currentIndex = 0;
            setInterval(() => {
                // Move para o próximo ponto na rota
                currentIndex = (currentIndex + 1) % routeLatLngs.length;
                
                // Atualiza a posição do marcador
                truckMarker.setLatLng(routeLatLngs[currentIndex]);

                // Centraliza o mapa na nova posição do caminhão
                map.panTo(routeLatLngs[currentIndex]);

            }, 2000); // Atualiza a cada 2 segundos
        })
        .catch(error => console.error('Erro ao buscar dados da rota:', error));
});
