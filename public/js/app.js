document.addEventListener('DOMContentLoaded', function () {
    console.log("Sistema de consulta de entregas iniciado!");

    // --- Elementos do DOM ---
    const searchForm = document.getElementById('search-form');
    const nfeKeyInput = document.getElementById('nfe_key');
    const deliveryDetailsContainer = document.getElementById('delivery-details');
    const timelineContainer = document.getElementById('timeline-container');
    const deliveryStatusEl = document.getElementById('delivery-status');
    const deliveryOriginEl = document.getElementById('delivery-origin');
    const deliveryDestinationEl = document.getElementById('delivery-destination');
    
    // --- Mapa (Leaflet) ---
    const map = L.map('map').setView([-14.235, -51.925], 4); // Visão geral do Brasil
    let routePolyline = null;
    let originMarker = null;
    let destinationMarker = null;
    let truckMarker = null;

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(map);

    const truckIcon = L.icon({
        iconUrl: 'https://cdn-icons-png.flaticon.com/64/610/610115.png',
        iconSize: [38, 38],
        iconAnchor: [19, 38],
        popupAnchor: [0, -38]
    });

    // --- Funções ---

    function updateMap(delivery) {
        // Limpa camadas anteriores
        if(routePolyline) map.removeLayer(routePolyline);
        if(originMarker) map.removeLayer(originMarker);
        if(destinationMarker) map.removeLayer(destinationMarker);
        if(truckMarker) map.removeLayer(truckMarker);

        const originLatLng = [delivery.origin.lat, delivery.origin.lng];
        const destLatLng = [delivery.destination.lat, delivery.destination.lng];
        const truckLatLng = [delivery.current_location.lat, delivery.current_location.lng];

        // Desenha a nova rota e marcadores
        routePolyline = L.polyline([originLatLng, destLatLng], { color: 'blue' }).addTo(map);
        originMarker = L.marker(originLatLng).addTo(map).bindPopup(`<b>Origem:</b><br>${delivery.origin.name}`);
        destinationMarker = L.marker(destLatLng).addTo(map).bindPopup(`<b>Destino:</b><br>${delivery.destination.name}`);
        truckMarker = L.marker(truckLatLng, { icon: truckIcon }).addTo(map).bindPopup(`<b>Localização Atual</b>`);

        // Ajusta o mapa para mostrar a rota completa
        map.fitBounds(routePolyline.getBounds(), { padding: [50, 50] });
    }

    function updateTimeline(history) {
        timelineContainer.innerHTML = ''; // Limpa o conteúdo
        history.reverse().forEach(item => { // Mais recente primeiro
            const date = new Date(item.timestamp);
            const formattedDate = `${date.toLocaleDateString('pt-BR')} às ${date.toLocaleTimeString('pt-BR')}`;

            const timelineItem = `
                <div class="timeline-item">
                    <div class="timeline-item-content">
                        <p class="status">${item.status}</p>
                        <p>${item.location}</p>
                        <p class="timestamp">${formattedDate}</p>
                    </div>
                </div>
            `;
            timelineContainer.innerHTML += timelineItem;
        });
    }

    function updateDetails(delivery) {
        deliveryStatusEl.textContent = delivery.status;
        deliveryOriginEl.textContent = delivery.origin.name;
        deliveryDestinationEl.textContent = delivery.destination.name;
        deliveryDetailsContainer.classList.remove('u-hide');
    }


    // --- Event Listener ---
    searchForm.addEventListener('submit', function(event) {
        event.preventDefault();
        const nfeKey = nfeKeyInput.value.trim();

        if (!nfeKey) {
            alert('Por favor, digite uma chave de NF-e.');
            return;
        }

        fetch('/mapcloud-plataforma/api/deliveries')
            .then(response => {
                if (!response.ok) throw new Error('Não foi possível buscar as entregas.');
                return response.json();
            })
            .then(deliveries => {
                const foundDelivery = deliveries.find(d => d.nfe_key === nfeKey);
                
                if (foundDelivery) {
                    updateMap(foundDelivery);
                    updateTimeline(foundDelivery.update_history);
                    updateDetails(foundDelivery);
                } else {
                    alert('Nenhuma entrega encontrada para esta chave de NF-e.');
                    // Opcional: limpar a tela se nada for encontrado
                }
            })
            .catch(error => {
                console.error('Erro na busca:', error);
                alert('Ocorreu um erro ao buscar os dados da entrega.');
            });
    });

});
