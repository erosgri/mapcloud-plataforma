document.addEventListener('DOMContentLoaded', () => {
  // KPIs elements
  const kpiTotal = document.getElementById('kpi-total');
  const kpiInTransit = document.getElementById('kpi-in-transit');
  const kpiDelivered = document.getElementById('kpi-delivered');

  // Timeline/feed element
  const timelineContainer = document.getElementById('timeline-container');

  // Leaflet Map setup
  const map = L.map('map').setView([-14.235, -51.925], 4);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution:
      '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
  }).addTo(map);

  const truckIcon = L.icon({
    iconUrl: 'https://cdn-icons-png.flaticon.com/64/610/610115.png',
    iconSize: [32, 32],
    iconAnchor: [16, 32],
    popupAnchor: [0, -28],
  });

  let deliveryMarkers = [];

  function clearMarkers() {
    deliveryMarkers.forEach((m) => map.removeLayer(m));
    deliveryMarkers = [];
  }

  function populateKPIs(metrics) {
    try {
      kpiTotal.textContent = metrics.total_deliveries ?? 0;
      kpiInTransit.textContent = metrics.status_count?.in_transit ?? 0;
      // tenta cobrir possíveis nomes: delivered / entregue
      kpiDelivered.textContent = metrics.status_count?.delivered ?? metrics.status_count?.entregue ?? 0;
    } catch (e) {
      console.error('Erro ao popular KPIs', e);
    }
  }

  function populateMap(deliveries) {
    clearMarkers();
    const bounds = [];

    deliveries.forEach((d) => {
      if (d.current_lat && d.current_lng) {
        const marker = L.marker([d.current_lat, d.current_lng], { icon: truckIcon })
          .addTo(map)
          .bindPopup(
            `<b>Entrega:</b> ${d.delivery_id || ''}<br/>` +
              `<b>Status:</b> ${d.status || ''}<br/>` +
              `<b>Destino:</b> ${d.destination_name || ''}`
          );
        deliveryMarkers.push(marker);
        bounds.push([d.current_lat, d.current_lng]);
      }
    });

    if (bounds.length > 0) {
      map.fitBounds(bounds, { padding: [40, 40] });
    }
  }

  function populateTimeline(deliveries) {
    // Flatten events across deliveries with delivery_id attached
    const events = [];
    deliveries.forEach((d) => {
      if (Array.isArray(d.update_history)) {
        d.update_history.forEach((ev) =>
          events.push({
            delivery_id: d.delivery_id,
            status: ev.status,
            location: ev.location_description || ev.location || '',
            timestamp: ev.timestamp,
          })
        );
      }
    });

    // Sort by timestamp desc
    events.sort((a, b) => new Date(b.timestamp) - new Date(a.timestamp));

    timelineContainer.innerHTML = '';
    if (events.length === 0) {
      timelineContainer.innerHTML = '<p>Nenhum evento encontrado.</p>';
      return;
    }

    events.slice(0, 50).forEach((ev) => {
      const date = new Date(ev.timestamp);
      const formatted = `${date.toLocaleDateString('pt-BR')} às ${date.toLocaleTimeString('pt-BR')}`;
      const html = `
        <div class="timeline-item">
          <div class="timeline-item-content">
            <p class="status">${ev.status}</p>
            <p class="delivery-id">Entrega: ${ev.delivery_id || ''}</p>
            <p>${ev.location || ''}</p>
            <p class="timestamp">${formatted}</p>
          </div>
        </div>`;
      timelineContainer.insertAdjacentHTML('beforeend', html);
    });
  }

  async function loadDashboard() {
    try {
      // KPIs
      const metricsRes = await fetch('/mapcloud-plataforma/api/metrics');
      if (!metricsRes.ok) throw new Error('Falha ao carregar métricas');
      const metrics = await metricsRes.json();
      populateKPIs(metrics);

      // Deliveries list
      const deliveriesRes = await fetch('/mapcloud-plataforma/api/deliveries');
      if (!deliveriesRes.ok) throw new Error('Falha ao carregar entregas');
      const deliveries = await deliveriesRes.json();

      populateMap(deliveries);
      populateTimeline(deliveries);
    } catch (err) {
      console.error('Erro ao carregar dashboard', err);
    }
  }

  loadDashboard();

  // Opcional: auto-refresh a cada 30s
  setInterval(loadDashboard, 30000);
});
