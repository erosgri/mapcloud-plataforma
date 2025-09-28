document.addEventListener('DOMContentLoaded', () => {
  const params = new URLSearchParams(window.location.search);
  const motoristaId = params.get('motorista_id');

  // KPIs elements
  const kpiTotal = document.getElementById('kpi-total');
  const kpiInTransit = document.getElementById('kpi-in-transit');
  const kpiDelivered = document.getElementById('kpi-delivered');
  const kpiFailed = document.getElementById('kpi-failed');

  // Timeline/feed element
  const timelineContainer = document.getElementById('timeline-container');
  const deliveriesListEl = document.getElementById('deliveries-list');

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
  const deliveryIdToMarker = new Map();

  function clearMarkers() {
    deliveryMarkers.forEach((m) => map.removeLayer(m));
    deliveryMarkers = [];
    deliveryIdToMarker.clear();
  }

  function populateKPIs(metrics) {
    try {
      kpiTotal && (kpiTotal.textContent = metrics.total_entregas ?? metrics.total_deliveries ?? 0);
      kpiInTransit && (kpiInTransit.textContent = metrics.em_transito ?? metrics.status_count?.in_transit ?? 0);
      kpiDelivered && (kpiDelivered.textContent = metrics.entregues ?? metrics.status_count?.delivered ?? metrics.status_count?.entregue ?? 0);
      kpiFailed && (kpiFailed.textContent = metrics.nao_entregues ?? metrics.status_count?.failed ?? 0);
    } catch (e) {
      console.error('Erro ao popular KPIs', e);
    }
  }

  function populateMap(deliveries) {
    clearMarkers();
    const bounds = [];
    
        // Determinar a fase atual baseada no status das entregas
        const hasPendingPickup = deliveries.some(d => d.status === 'pending_pickup');
        const hasInTransit = deliveries.some(d => d.status === 'in_transit');
        const hasActiveDeliveries = hasPendingPickup || hasInTransit;
        
        let currentPhase = 'completion'; // Padrão: conclusão
        if (hasPendingPickup) {
          currentPhase = 'collection';
        } else if (hasInTransit) {
          currentPhase = 'delivery';
        }
        
        console.log('Fase atual:', currentPhase.toUpperCase());
        console.log('Entregas para processar:', deliveries.length);
    
        // Atualizar título da seção do mapa
        const mapTitle = document.querySelector('.map-container h3');
        if (mapTitle) {
          if (currentPhase === 'collection') {
            mapTitle.innerHTML = '<i class="fas fa-box-open"></i> Pontos de Coleta';
          } else if (currentPhase === 'delivery') {
            mapTitle.innerHTML = '<i class="fas fa-truck"></i> Pontos de Entrega';
          } else {
            mapTitle.innerHTML = '<i class="fas fa-flag-checkered"></i> Conclusão de Rota';
          }
        }
    
    // Adicionar indicador de fase no dashboard
    let phaseIndicator = document.getElementById('phase-indicator');
    if (!phaseIndicator) {
      phaseIndicator = document.createElement('div');
      phaseIndicator.id = 'phase-indicator';
      phaseIndicator.className = 'phase-indicator';
      document.querySelector('.kpi-grid').appendChild(phaseIndicator);
    }
    
        if (currentPhase === 'collection') {
          phaseIndicator.innerHTML = `
            <div class="phase-card collection-phase">
              <i class="fas fa-box-open"></i>
              <div class="phase-info">
                <h4>Fase de Coleta</h4>
                <p>Coletando mercadorias nos pontos de origem</p>
              </div>
            </div>
          `;
        } else if (currentPhase === 'delivery') {
          phaseIndicator.innerHTML = `
            <div class="phase-card delivery-phase">
              <i class="fas fa-truck"></i>
              <div class="phase-info">
                <h4>Fase de Entrega</h4>
                <p>Entregando mercadorias nos pontos de destino</p>
              </div>
            </div>
          `;
        } else {
          phaseIndicator.innerHTML = `
            <div class="phase-card completion-phase">
              <i class="fas fa-flag-checkered"></i>
              <div class="phase-info">
                <h4>Conclusão de Rota</h4>
                <p>Todas as entregas foram finalizadas</p>
              </div>
            </div>
          `;
        }

    deliveries.forEach((d) => {
      let marker, popupContent, coordinates;
      
      if (currentPhase === 'collection') {
        // FASE DE COLETA: Mostrar pontos de origem (coleta)
        if (d.status === 'pending_pickup' && d.origin_lat && d.origin_lng) {
          coordinates = [d.origin_lat, d.origin_lng];
          marker = L.marker(coordinates, { 
            icon: L.divIcon({
              className: 'collection-marker',
              html: '<div class="marker-icon collection-icon"><i class="fas fa-box-open"></i></div>',
              iconSize: [30, 30],
              iconAnchor: [15, 15]
            })
          });
          
          popupContent = `
            <div class="popup-content">
              <h4><i class="fas fa-box-open"></i> Ponto de Coleta</h4>
              <p><strong>Entrega:</strong> #${d.delivery_id || ''}</p>
              <p><strong>Status:</strong> <span class="status-pending">Aguardando Coleta</span></p>
              <p><strong>Origem:</strong> ${d.origin_name || ''}</p>
              <p><strong>Destino:</strong> ${d.destination_name || ''}</p>
            </div>
          `;
        }
      } else if (currentPhase === 'delivery') {
        // FASE DE ENTREGA: Mostrar pontos de destino (entrega)
        if (d.status === 'in_transit' && d.destination_lat && d.destination_lng) {
          coordinates = [d.destination_lat, d.destination_lng];
          marker = L.marker(coordinates, { 
            icon: L.divIcon({
              className: 'delivery-marker',
              html: '<div class="marker-icon delivery-icon"><i class="fas fa-truck"></i></div>',
              iconSize: [30, 30],
              iconAnchor: [15, 15]
            })
          });
          
          popupContent = `
            <div class="popup-content">
              <h4><i class="fas fa-truck"></i> Ponto de Entrega</h4>
              <p><strong>Entrega:</strong> #${d.delivery_id || ''}</p>
              <p><strong>Status:</strong> <span class="status-transit">Em Trânsito</span></p>
              <p><strong>Origem:</strong> ${d.origin_name || ''}</p>
              <p><strong>Destino:</strong> ${d.destination_name || ''}</p>
            </div>
          `;
        }
      } else {
        // FASE DE CONCLUSÃO: Mostrar todas as entregas finalizadas
        if ((d.status === 'delivered' || d.status === 'failed') && d.destination_lat && d.destination_lng) {
          coordinates = [d.destination_lat, d.destination_lng];
          const isDelivered = d.status === 'delivered';
          marker = L.marker(coordinates, { 
            icon: L.divIcon({
              className: isDelivered ? 'completion-marker' : 'failed-marker',
              html: `<div class="marker-icon ${isDelivered ? 'completion-icon' : 'failed-icon'}">
                       <i class="fas ${isDelivered ? 'fa-check-circle' : 'fa-times-circle'}"></i>
                     </div>`,
              iconSize: [30, 30],
              iconAnchor: [15, 15]
            })
          });
          
          popupContent = `
            <div class="popup-content">
              <h4><i class="fas ${isDelivered ? 'fa-check-circle' : 'fa-times-circle'}"></i> 
                  ${isDelivered ? 'Entrega Concluída' : 'Entrega Falhada'}</h4>
              <p><strong>Entrega:</strong> #${d.delivery_id || ''}</p>
              <p><strong>Status:</strong> <span class="${isDelivered ? 'status-delivered' : 'status-failed'}">
                  ${isDelivered ? 'Entregue' : 'Não Entregue'}</span></p>
              <p><strong>Origem:</strong> ${d.origin_name || ''}</p>
              <p><strong>Destino:</strong> ${d.destination_name || ''}</p>
            </div>
          `;
        }
      }
      
      if (marker && coordinates) {
        marker.addTo(map)
          .bindPopup(popupContent);
        deliveryMarkers.push(marker);
        deliveryIdToMarker.set(d.delivery_id, marker);
        bounds.push(coordinates);
      }
    });

    if (bounds.length > 0) {
      map.fitBounds(bounds, { padding: [40, 40] });
      console.log('Mapa atualizado com', bounds.length, 'marcadores');
    } else {
      console.log('Nenhum marcador adicionado ao mapa');
    }
  }

  function populateTimeline(deliveries) {
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

    events.sort((a, b) => new Date(b.timestamp) - new Date(a.timestamp));

    if (timelineContainer) {
      timelineContainer.innerHTML = '';
      if (events.length === 0) {
        timelineContainer.innerHTML = '<p>Nenhum evento encontrado.</p>';
      } else {
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
    }
  }

  function populateDeliveriesList(deliveries) {
    if (!deliveriesListEl) return;
    if (!Array.isArray(deliveries) || deliveries.length === 0) {
      deliveriesListEl.innerHTML = '<p>Nenhuma entrega encontrada.</p>';
      document.getElementById('delivery-controls').style.display = 'none';
      return;
    }

    // Mostrar controles se há entregas
    document.getElementById('delivery-controls').style.display = 'flex';

    let html = '';
    deliveries.forEach((d) => {
      const statusClass = getStatusClass(d.status);
      const canSelect = d.status === 'pending_pickup' || d.status === 'in_transit';
      
      // Determinar quais ações estão disponíveis baseado no status
      const canCollect = d.status === 'pending_pickup';
      const canDeliver = d.status === 'in_transit' || d.status === 'pending_pickup';
      const canFail = d.status === 'in_transit' || d.status === 'pending_pickup';
      
      html += `
        <div class="delivery-item clickable-delivery" data-delivery-id="${d.delivery_id}">
          <div class="delivery-info">
            <div class="delivery-main-info">
              <div class="delivery-id">#${d.delivery_id || ''}</div>
              <div class="delivery-status ${statusClass}">${getStatusText(d.status)}</div>
              <div class="delivery-route">
                <div class="delivery-origin">${d.origin_name || ''}</div>
                <div class="delivery-arrow">→</div>
                <div class="delivery-destination">${d.destination_name || ''}</div>
              </div>
            </div>
            <div class="delivery-toggle">
              <i class="fas fa-chevron-down"></i>
            </div>
          </div>
          <div class="delivery-details" id="details-${d.delivery_id}" style="display: none;">
            <div class="delivery-actions">
              <div class="action-item ${!canCollect ? 'action-disabled' : ''}">
                <div class="action-header">
                  <input type="checkbox" id="collect-${d.delivery_id}" 
                         class="action-checkbox" 
                         ${!canCollect ? 'disabled' : ''}>
                  <label for="collect-${d.delivery_id}" class="action-label ${!canCollect ? 'label-disabled' : ''}">
                    <i class="fas fa-box-open"></i> Coletar
                    ${!canCollect ? '<span class="status-badge">Já Coletado</span>' : ''}
                  </label>
                </div>
                <div class="action-address">
                  <strong>Origem:</strong> ${d.origin_name || ''}
                  <br><small>Coordenadas: ${d.origin_lat?.toFixed(4)}, ${d.origin_lng?.toFixed(4)}</small>
                </div>
              </div>
              <div class="action-item ${!canDeliver ? 'action-disabled' : ''}">
                <div class="action-header">
                  <input type="checkbox" id="deliver-${d.delivery_id}" 
                         class="action-checkbox" 
                         ${!canDeliver ? 'disabled' : ''}>
                  <label for="deliver-${d.delivery_id}" class="action-label ${!canDeliver ? 'label-disabled' : ''}">
                    <i class="fas fa-truck"></i> Entregar
                    ${!canDeliver ? '<span class="status-badge">Não Disponível</span>' : ''}
                  </label>
                </div>
                <div class="action-address">
                  <strong>Destino:</strong> ${d.destination_name || ''}
                  <br><small>Coordenadas: ${d.destination_lat?.toFixed(4)}, ${d.destination_lng?.toFixed(4)}</small>
                </div>
              </div>
              <div class="action-item ${!canFail ? 'action-disabled' : ''}">
                <div class="action-header">
                  <input type="checkbox" id="failed-${d.delivery_id}" 
                         class="action-checkbox" 
                         ${!canFail ? 'disabled' : ''}>
                  <label for="failed-${d.delivery_id}" class="action-label ${!canFail ? 'label-disabled' : ''}">
                    <i class="fas fa-times-circle"></i> Não Entregue
                    ${!canFail ? '<span class="status-badge">Não Disponível</span>' : ''}
                  </label>
                </div>
                <div class="action-address">
                  <strong>Motivo:</strong>
                  <div class="failure-reasons" id="reasons-${d.delivery_id}" style="display: none;">
                    <label class="reason-option">
                      <input type="radio" name="reason-${d.delivery_id}" value="cliente_nao_encontrado" class="reason-radio">
                      <span>Cliente não encontrado</span>
                    </label>
                    <label class="reason-option">
                      <input type="radio" name="reason-${d.delivery_id}" value="cliente_recusou" class="reason-radio">
                      <span>Cliente recusou</span>
                    </label>
                    <label class="reason-option">
                      <input type="radio" name="reason-${d.delivery_id}" value="mercadoria_avariada" class="reason-radio">
                      <span>Mercadoria avariada</span>
                    </label>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      `;
    });
    
    deliveriesListEl.innerHTML = html;
    
    // Debug: verificar se os elementos foram criados
    console.log('HTML gerado:', deliveriesListEl.innerHTML.substring(0, 500) + '...');
    console.log('Elementos de ação encontrados após inserção:', deliveriesListEl.querySelectorAll('.action-checkbox').length);
    console.log('Entregas clicáveis encontradas:', deliveriesListEl.querySelectorAll('.clickable-delivery').length);
    
    // Verificar se as entregas clicáveis têm o data-delivery-id correto
    deliveriesListEl.querySelectorAll('.clickable-delivery').forEach((item, index) => {
      console.log(`Entrega ${index}:`, item.getAttribute('data-delivery-id'));
    });

    // Removido: não há mais checkbox principal

    // Adicionar event listeners para checkboxes de ações
    const actionCheckboxes = deliveriesListEl.querySelectorAll('.action-checkbox');
    console.log('Encontrados', actionCheckboxes.length, 'checkboxes de ação');
    
    actionCheckboxes.forEach((checkbox) => {
      console.log('Adicionando event listener para:', checkbox.id);
      checkbox.addEventListener('change', function(e) {
        console.log('Checkbox alterado:', e.target.id, 'checked:', e.target.checked);
        
        // Mostrar/ocultar opções de motivo para "Não Entregue"
        if (e.target.id.startsWith('failed-')) {
          const deliveryId = e.target.id.replace('failed-', '');
          const reasonsDiv = document.getElementById(`reasons-${deliveryId}`);
          if (reasonsDiv) {
            reasonsDiv.style.display = e.target.checked ? 'block' : 'none';
          }
        }
        
        updateActionControls();
      });
    });

    // Adicionar event listeners alternativos para toggle (caso onclick não funcione)
    deliveriesListEl.querySelectorAll('.delivery-info').forEach((info) => {
      info.addEventListener('click', function(e) {
        // Só fazer toggle se clicou no ícone de toggle
        if (e.target.closest('.delivery-toggle')) {
          e.preventDefault();
          const deliveryId = this.closest('.delivery-item').getAttribute('data-delivery-id');
          console.log('Event listener alternativo ativado para toggle:', deliveryId);
          toggleDeliveryDetails(deliveryId);
        }
      });
    });

    // Adicionar event listener diretamente para cada entrega
    deliveriesListEl.querySelectorAll('.clickable-delivery').forEach((deliveryItem) => {
      deliveryItem.addEventListener('click', function(e) {
        e.stopPropagation();
        const deliveryId = this.getAttribute('data-delivery-id');
        console.log('Clique direto detectado na entrega:', deliveryId);
        focusOnDelivery(deliveryId);
      });
    });

    // Adicionar event listener para o botão de controle (caso não tenha sido adicionado antes)
    const markDeliveredBtn = document.getElementById('mark-delivered-btn');
    if (markDeliveredBtn && !markDeliveredBtn.hasAttribute('data-listener-added')) {
      console.log('Adicionando event listener para botão de controle');
      markDeliveredBtn.addEventListener('click', function(e) {
        console.log('Botão clicado via event listener direto!', e.target.textContent);
        markSelectedAsDelivered();
      });
      markDeliveredBtn.setAttribute('data-listener-added', 'true');
    }
  }

  function getStatusClass(status) {
    switch (status) {
      case 'pending_pickup': return 'status-pending';
      case 'in_transit': return 'status-transit';
      case 'delivered': return 'status-delivered';
      case 'failed': return 'status-failed';
      default: return 'status-default';
    }
  }

  function getStatusText(status) {
    switch (status) {
      case 'pending_pickup': return 'Aguardando Coleta';
      case 'in_transit': return 'Em Trânsito';
      case 'delivered': return 'Entregue';
      case 'failed': return 'Não Entregue';
      default: return status;
    }
  }

  function focusOnDelivery(deliveryId) {
    console.log('Focando na entrega:', deliveryId);
    
    // Encontrar os dados da entrega (comparação flexível)
    let delivery = window.currentDeliveries?.find(d => {
      // Comparar ID exato
      if (d.delivery_id === deliveryId) return true;
      
      // Comparar normalizado (com zeros à esquerda)
      const normalizedId = d.delivery_id?.toString().padStart(5, '0');
      const normalizedSearchId = deliveryId.toString().padStart(5, '0');
      if (normalizedId === normalizedSearchId) return true;
      
      // Comparar sem zeros à esquerda
      const dId = d.delivery_id?.toString().replace(/^0+/, '');
      const searchId = deliveryId.toString().replace(/^0+/, '');
      if (dId === searchId) return true;
      
      return false;
    });
    
    if (!delivery) {
      console.log('Entrega não encontrada:', deliveryId);
      return;
    }

    // Determinar qual coordenada mostrar baseado na fase
    let coordinates, address, phase;
    
    if (delivery.status === 'pending_pickup') {
      // Fase de coleta - mostrar origem
      coordinates = [delivery.origin_lat, delivery.origin_lng];
      address = delivery.origin_name;
      phase = 'Coleta';
    } else if (delivery.status === 'in_transit') {
      // Fase de entrega - mostrar destino
      coordinates = [delivery.destination_lat, delivery.destination_lng];
      address = delivery.destination_name;
      phase = 'Entrega';
    } else if (delivery.status === 'delivered') {
      // Entregue - mostrar destino
      coordinates = [delivery.destination_lat, delivery.destination_lng];
      address = delivery.destination_name;
      phase = 'Entregue';
    } else if (delivery.status === 'failed') {
      // Falhou - mostrar destino
      coordinates = [delivery.destination_lat, delivery.destination_lng];
      address = delivery.destination_name;
      phase = 'Não Entregue';
    } else {
      // Fallback - usar coordenadas atuais
      coordinates = [delivery.current_lat, delivery.current_lng];
      address = delivery.origin_name || delivery.destination_name;
      phase = 'Localização Atual';
    }

    // Verificar se as coordenadas são válidas
    if (coordinates[0] && coordinates[1] && !isNaN(coordinates[0]) && !isNaN(coordinates[1])) {
      // Centralizar o mapa na coordenada
      map.setView(coordinates, 16);
      
      // Criar popup com informações da fase
      const popupContent = `
        <div class="popup-content">
          <h4>#${deliveryId} - ${phase}</h4>
          <p><strong>Endereço:</strong> ${address}</p>
          <p><strong>Status:</strong> ${getStatusText(delivery.status)}</p>
          <p><strong>Coordenadas:</strong> ${coordinates[0].toFixed(6)}, ${coordinates[1].toFixed(6)}</p>
        </div>
      `;
      
      // Criar marcador temporário se não existir
      let marker = deliveryIdToMarker.get(deliveryId);
      if (!marker) {
        const icon = L.divIcon({
          className: 'custom-marker',
          html: `<div class="marker-icon ${delivery.status === 'pending_pickup' ? 'collection-icon' : 'delivery-icon'}">
                   <i class="fas ${delivery.status === 'pending_pickup' ? 'fa-box-open' : 'fa-truck'}"></i>
                 </div>`,
          iconSize: [30, 30],
          iconAnchor: [15, 15]
        });
        
        marker = L.marker(coordinates, { icon }).addTo(map);
        deliveryIdToMarker.set(deliveryId, marker);
      }
      
      // Abrir popup
      marker.bindPopup(popupContent).openPopup();
      
      console.log(`Focando na entrega ${deliveryId} - ${phase}: ${address}`);
    } else {
      // Usar coordenadas padrão de São Paulo
      const defaultCoordinates = [-23.5505, -46.6333];
      const defaultAddress = 'São Paulo, SP (Coordenadas não disponíveis)';
      
      // Centralizar o mapa nas coordenadas padrão
      map.setView(defaultCoordinates, 12);
      
      // Criar popup com informações da fase
      const popupContent = `
        <div class="popup-content">
          <h4>#${deliveryId} - ${phase}</h4>
          <p><strong>Endereço:</strong> ${address}</p>
          <p><strong>Status:</strong> ${getStatusText(delivery.status)}</p>
          <p><strong>Coordenadas:</strong> ${defaultAddress}</p>
          <p><em>⚠️ Coordenadas não disponíveis - mostrando localização padrão</em></p>
        </div>
      `;
      
      // Criar marcador temporário
      let marker = deliveryIdToMarker.get(deliveryId);
      if (!marker) {
        const icon = L.divIcon({
          className: 'custom-marker',
          html: `<div class="marker-icon warning-icon">
                   <i class="fas fa-exclamation-triangle"></i>
                 </div>`,
          iconSize: [30, 30],
          iconAnchor: [15, 15]
        });
        
        marker = L.marker(defaultCoordinates, { icon }).addTo(map);
        deliveryIdToMarker.set(deliveryId, marker);
      }
      
      // Abrir popup
      marker.bindPopup(popupContent).openPopup();
      
      console.log(`Focando na entrega ${deliveryId} com coordenadas padrão`);
    }
  }

  // Removido: função não é mais necessária sem checkbox principal


  function updateActionControls() {
    console.log('updateActionControls chamada');
    const checkedActions = deliveriesListEl.querySelectorAll('.action-checkbox:checked');
    const markDeliveredBtn = document.getElementById('mark-delivered-btn');
    
    console.log('Ações selecionadas:', checkedActions.length);
    
    // Atualizar texto do botão baseado nas ações selecionadas
    const collectActions = deliveriesListEl.querySelectorAll('.action-checkbox[id^="collect-"]:checked');
    const deliverActions = deliveriesListEl.querySelectorAll('.action-checkbox[id^="deliver-"]:checked');
    const failedActions = deliveriesListEl.querySelectorAll('.action-checkbox[id^="failed-"]:checked');
    
    console.log('Coletas selecionadas:', collectActions.length);
    console.log('Entregas selecionadas:', deliverActions.length);
    console.log('Falhas selecionadas:', failedActions.length);
    
    if (checkedActions.length > 0) {
      markDeliveredBtn.disabled = false;
      let actionText = '';
      if (collectActions.length > 0) actionText += `Coletar (${collectActions.length}) `;
      if (deliverActions.length > 0) actionText += `Entregar (${deliverActions.length}) `;
      if (failedActions.length > 0) actionText += `Não Entregue (${failedActions.length})`;
      markDeliveredBtn.textContent = actionText.trim();
      console.log('Botão atualizado para:', actionText.trim());
    } else {
      markDeliveredBtn.disabled = true;
      markDeliveredBtn.textContent = 'Marcar como Entregue';
      console.log('Nenhuma ação selecionada, botão desabilitado');
    }
  }

  // Tornar loadDashboard global para uso no upload
  window.loadDashboard = async function() {
    try {
      // Mostrar indicador de carregamento
      const dashboardSubtitle = document.getElementById('dashboard-subtitle');
      if (dashboardSubtitle) {
        const originalText = dashboardSubtitle.textContent;
        dashboardSubtitle.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Atualizando dados...';
        
        // Restaurar texto original após carregamento
        setTimeout(() => {
          dashboardSubtitle.textContent = originalText;
        }, 1000);
      }
      
      // Usando URLs relativas para robustez
      const baseUrl = window.location.origin + '/mapcloud-plataforma/api/index.php';
      
      const metricsUrl = new URL(`${baseUrl}/metrics`);
      if (motoristaId) metricsUrl.searchParams.set('motorista_id', motoristaId);
      const metricsRes = await fetch(metricsUrl);
      if (!metricsRes.ok) throw new Error('Falha ao carregar métricas');
      const metrics = await metricsRes.json();
      populateKPIs(metrics);

      const deliveriesUrl = new URL(`${baseUrl}/deliveries`);
      if (motoristaId) deliveriesUrl.searchParams.set('motorista_id', motoristaId);
      const deliveriesRes = await fetch(deliveriesUrl);
      if (!deliveriesRes.ok) throw new Error('Falha ao carregar entregas');
      const deliveries = await deliveriesRes.json();

      // Armazenar entregas globalmente para uso em focusOnDelivery
      window.currentDeliveries = deliveries;

      populateMap(deliveries);
      populateTimeline(deliveries);
      populateDeliveriesList(deliveries);
    } catch (err) {
      console.error('Erro ao carregar dashboard', err);
    }
  };

  // Event listener para o botão "Marcar como Entregue" - usando delegação de eventos
  document.addEventListener('click', function(e) {
    if (e.target && e.target.id === 'mark-delivered-btn') {
      console.log('Botão clicado via delegação!', e.target.textContent);
      markSelectedAsDelivered();
    }
    
    // Event listener global para entregas clicáveis (removido - usando event listeners diretos)
  });

  async function markSelectedAsDelivered() {
    console.log('=== INICIANDO PROCESSAMENTO DE AÇÕES ===');
    
    if (!deliveriesListEl) {
      console.error('Elemento deliveriesListEl não encontrado!');
      return;
    }
    
    const checkedActions = deliveriesListEl.querySelectorAll('.action-checkbox:checked');
    console.log('Ações selecionadas encontradas:', checkedActions.length);
    
    if (checkedActions.length === 0) {
      console.log('Nenhuma ação selecionada, saindo...');
      return;
    }

    // Separar ações de coleta, entrega e falha
    const collectActions = Array.from(checkedActions).filter(cb => cb.id.startsWith('collect-'));
    const deliverActions = Array.from(checkedActions).filter(cb => cb.id.startsWith('deliver-'));
    const failedActions = Array.from(checkedActions).filter(cb => cb.id.startsWith('failed-'));
    
    console.log('Coletas para processar:', collectActions.length);
    console.log('Entregas para processar:', deliverActions.length);
    console.log('Falhas para processar:', failedActions.length);
    
    const markDeliveredBtn = document.getElementById('mark-delivered-btn');
    if (!markDeliveredBtn) {
      console.error('Botão de controle não encontrado!');
      return;
    }
    
    try {
      markDeliveredBtn.disabled = true;
      markDeliveredBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processando...';
      console.log('Botão desabilitado e texto alterado');

      // Processar coletas
      if (collectActions.length > 0) {
        const collectIds = collectActions.map(cb => {
          const id = cb.id.replace('collect-', '');
          // Garantir que o ID tenha 5 dígitos com zero à esquerda se necessário
          return id.padStart(5, '0');
        });
        console.log('Processando coletas para IDs:', collectIds);
        
        const response = await fetch('/mapcloud-plataforma/api/index.php/delivery/status', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            delivery_ids: collectIds,
            status: 'in_transit',
            action: 'collect'
          })
        });
        
        console.log('Resposta da API para coletas:', response.status);
        if (!response.ok) {
          throw new Error(`Erro na API: ${response.status}`);
        }
      }

      // Processar entregas
      if (deliverActions.length > 0) {
        const deliverIds = deliverActions.map(cb => {
          const id = cb.id.replace('deliver-', '');
          // Garantir que o ID tenha 5 dígitos com zero à esquerda se necessário
          return id.padStart(5, '0');
        });
        console.log('Processando entregas para IDs:', deliverIds);
        
        const response = await fetch('/mapcloud-plataforma/api/index.php/delivery/status', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            delivery_ids: deliverIds,
            status: 'delivered',
            action: 'deliver'
          })
        });
        
        console.log('Resposta da API para entregas:', response.status);
        if (!response.ok) {
          throw new Error(`Erro na API: ${response.status}`);
        }
      }

      // Processar falhas de entrega
      if (failedActions.length > 0) {
        const failedIds = failedActions.map(cb => {
          const id = cb.id.replace('failed-', '');
          return id.padStart(5, '0');
        });
        console.log('Processando falhas para IDs:', failedIds);
        
        // Coletar motivos das falhas
        const failureReasons = [];
        failedActions.forEach(cb => {
          const deliveryId = cb.id.replace('failed-', '');
          const reasonRadio = document.querySelector(`input[name="reason-${deliveryId}"]:checked`);
          const reason = reasonRadio ? reasonRadio.value : 'motivo_nao_informado';
          failureReasons.push({ delivery_id: deliveryId.padStart(5, '0'), reason });
        });
        
        const response = await fetch('/mapcloud-plataforma/api/index.php/delivery/status', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            delivery_ids: failedIds,
            status: 'failed',
            action: 'failed',
            reasons: failureReasons
          })
        });
        
        console.log('Resposta da API para falhas:', response.status);
        if (!response.ok) {
          throw new Error(`Erro na API: ${response.status}`);
        }
      }

      console.log('Recarregando dashboard...');
      // Recarregar dashboard
      await loadDashboard();
      
      // Feedback visual
      markDeliveredBtn.innerHTML = '<i class="fas fa-check"></i> Ações Processadas!';
      console.log('Ações processadas com sucesso!');
      setTimeout(() => {
        markDeliveredBtn.innerHTML = '<i class="fas fa-check"></i> Marcar como Entregue';
        markDeliveredBtn.disabled = false;
      }, 2000);

    } catch (error) {
      console.error('Erro ao processar ações:', error);
      markDeliveredBtn.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Erro!';
      setTimeout(() => {
        markDeliveredBtn.innerHTML = '<i class="fas fa-check"></i> Marcar como Entregue';
        markDeliveredBtn.disabled = false;
      }, 2000);
    }
  }

  window.loadDashboard();
  setInterval(window.loadDashboard, 30000);
});

// Função de teste para debug
function testActionControls() {
  console.log('=== TESTE DE CONTROLES DE AÇÃO ===');
  console.log('Elementos de ação encontrados:', document.querySelectorAll('.action-checkbox').length);
  console.log('Botão de controle:', document.getElementById('mark-delivered-btn'));
  
  // Simular uma seleção
  const firstCheckbox = document.querySelector('.action-checkbox');
  if (firstCheckbox) {
    console.log('Simulando clique no primeiro checkbox:', firstCheckbox.id);
    firstCheckbox.checked = true;
    firstCheckbox.dispatchEvent(new Event('change'));
  }
}

// Função para testar clique no botão
function testButtonClick() {
  console.log('=== TESTE DE CLIQUE NO BOTÃO ===');
  const btn = document.getElementById('mark-delivered-btn');
  if (btn) {
    console.log('Botão encontrado, simulando clique...');
    btn.click();
  } else {
    console.error('Botão não encontrado!');
  }
}

// Função global para toggle dos detalhes
function toggleDeliveryDetails(deliveryId) {
  console.log('toggleDeliveryDetails chamada para:', deliveryId);
  const details = document.getElementById(`details-${deliveryId}`);
  const toggle = document.querySelector(`[data-delivery-id="${deliveryId}"] .delivery-toggle i`);
  
  console.log('Details element:', details);
  console.log('Toggle element:', toggle);
  
  if (!details) {
    console.error('Elemento details não encontrado:', `details-${deliveryId}`);
    return;
  }
  
  if (!toggle) {
    console.error('Elemento toggle não encontrado para delivery:', deliveryId);
    return;
  }
  
  if (details.style.display === 'none' || details.style.display === '') {
    details.style.display = 'block';
    toggle.classList.remove('fa-chevron-down');
    toggle.classList.add('fa-chevron-up');
    console.log('Expandindo detalhes');
  } else {
    details.style.display = 'none';
    toggle.classList.remove('fa-chevron-up');
    toggle.classList.add('fa-chevron-down');
    console.log('Contraindo detalhes');
  }
}
