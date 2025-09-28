<?php 
session_start(); 
require_once 'pdo/conexao.php';

// Função para sanitizar caracteres especiais
function sanitize_ascii($value) {
    if ($value === null) return null;
    
    // Mapeamento direto de acentos para letras sem acento
    $acentos = [
        'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a',
        'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
        'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
        'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
        'ç' => 'c', 'ñ' => 'n',
        'Á' => 'A', 'À' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A',
        'É' => 'E', 'È' => 'E', 'Ê' => 'E', 'Ë' => 'E',
        'Í' => 'I', 'Ì' => 'I', 'Î' => 'I', 'Ï' => 'I',
        'Ó' => 'O', 'Ò' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O',
        'Ú' => 'U', 'Ù' => 'U', 'Û' => 'U', 'Ü' => 'U',
        'Ç' => 'C', 'Ñ' => 'N'
    ];
    
    $s = strtr($value, $acentos);
    
    // Remove qualquer caractere que não seja letra, número, espaço ou hífen
    $s = preg_replace('/[^A-Za-z0-9 \-]/', '', $s);
    
    // Normaliza espaços múltiplos
    $s = preg_replace('/\s+/', ' ', $s);
    
    return trim($s);
}

// Buscar informações do motorista
$motorista_id = $_GET['motorista_id'] ?? null;
$motorista_nome = 'Motorista';

if ($motorista_id) {
    try {
        $conexao = novaConexao();
        $stmt = $conexao->prepare("SELECT nome FROM motoristas WHERE id = ?");
        $stmt->execute([$motorista_id]);
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($resultado) {
            $motorista_nome = sanitize_ascii($resultado['nome']);
        }
    } catch (Exception $e) {
        // Se houver erro, mantém o nome padrão
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Monitoramento - MapCloud</title>
	<link rel="stylesheet" href="https://assets.ubuntu.com/v1/vanilla-framework-version-4.34.1.min.css" />
	<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
	<link rel="stylesheet" href="public/css/style.css">
	<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="modern-template">
	<!-- Header -->
	<header class="modern-header">
		<div class="header-background"></div>
		<div class="container">
			<div class="header-content">
				<div class="logo-section">
					<div class="logo-icon"><i class="fas fa-satellite-dish"></i></div>
					<div class="logo-text">
						<h1>MapCloud</h1>
						<span id="dashboard-subtitle">Monitoramento: <?php echo htmlspecialchars($motorista_nome); ?></span>
					</div>
				</div>
				<div class="header-actions">
					<a href="index.php" class="btn-primary">
						<i class="fas fa-arrow-left"></i>
						Trocar Motorista
					</a>
				</div>
			</div>
		</div>
	</header>

	<!-- KPIs -->
	<main class="main-content">
		<div class="container">
			<div class="kpi-grid">
				<!-- KPI Total de Entregas -->
				<div class="kpi-card">
					<div class="kpi-icon" style="background: #4a90e2;">
						<i class="fas fa-box-open"></i>
					</div>
					<div class="kpi-info">
						<span class="kpi-title">Total de Entregas</span>
						<span id="kpi-total" class="kpi-value">0</span>
					</div>
				</div>
				<!-- KPI Em Trânsito -->
				<div class="kpi-card">
					<div class="kpi-icon" style="background: #f5a623;">
						<i class="fas fa-truck-moving"></i>
					</div>
					<div class="kpi-info">
						<span class="kpi-title">Em Trânsito</span>
						<span id="kpi-in-transit" class="kpi-value">0</span>
					</div>
				</div>
				<!-- KPI Entregues -->
				<div class="kpi-card">
					<div class="kpi-icon" style="background: #7ed321;">
						<i class="fas fa-check-circle"></i>
					</div>
					<div class="kpi-info">
						<span class="kpi-title">Entregues</span>
						<span id="kpi-delivered" class="kpi-value">0</span>
					</div>
				</div>
				<!-- KPI Não Entregue -->
				<div class="kpi-card">
					<div class="kpi-icon" style="background: #d32f2f;">
						<i class="fas fa-times-circle"></i>
					</div>
					<div class="kpi-info">
						<span class="kpi-title">Não Entregue</span>
						<span id="kpi-failed" class="kpi-value">0</span>
					</div>
				</div>
			</div>

			<!-- Conteúdo Principal: Mapa e Feeds -->
			<div class="dashboard-grid">
				<!-- Coluna do Mapa -->
				<div class="map-container content-card">
					<div class="card-header">
						<h3><i class="fas fa-map-marked-alt"></i> Localização das Entregas Ativas</h3>
						<button id="add-delivery-btn" class="btn-primary" style="padding: 0.5rem 1rem;">
							<i class="fas fa-plus"></i> Adicionar Entrega
						</button>
					</div>
					<div class="card-body no-padding">
						<div id="map"></div>
					</div>
				</div>
				<!-- Coluna dos Feeds -->
				<div class="feeds-container">
					<div class="content-card">
						<div class="card-header">
							<h3><i class="fas fa-list-ul"></i> Entregas</h3>
							<div class="delivery-controls" style="display: none;" id="delivery-controls">
								<button id="mark-delivered-btn" class="btn-primary" disabled>
									<i class="fas fa-check"></i> Marcar como Entregue
								</button>
							</div>
						</div>
						<div class="card-body">
							<div id="deliveries-list"><p>Carregando entregas...</p></div>
						</div>
					</div>
					<div class="content-card">
						<div class="card-header">
							<h3><i class="fas fa-stream"></i> Feed de Eventos</h3>
						</div>
						<div class="card-body">
							<div id="timeline-container"><p>Carregando eventos...</p></div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</main>
	<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
	<script src="public/js/app.js"></script>

	<!-- Modal de Upload de NF-e -->
	<div id="upload-modal" class="modal-overlay u-hide">
		<div class="modal-content content-card">
			<div class="card-header">
				<h3><i class="fas fa-upload"></i> Adicionar Nova Entrega (NF-e)</h3>
				<button id="close-modal-btn" class="modal-close-btn">&times;</button>
			</div>
			<div class="card-body">
				<form id="upload-form" action="upload_nfe.php" method="post" enctype="multipart/form-data" class="p-form">
					<?php
						$motorista_id = isset($_GET['motorista_id']) ? htmlspecialchars($_GET['motorista_id']) : '';
					?>
					<input type="hidden" name="motorista_id" value="<?php echo $motorista_id; ?>">
					
					<div class="p-form__group">
						<label for="nfe_file" class="p-form__label">Selecione o arquivo XML da NF-e:</label>
						<div class="p-form__control">
							<input type="file" id="nfe_file" name="nfe_file" accept=".xml" required>
						</div>
						<small class="p-form-help-text">Apenas arquivos .xml são permitidos.</small>
					</div>
					<div class="form-actions">
						<button type="submit" class="btn-primary">
							<i class="fas fa-check"></i> Enviar NF-e
						</button>
					</div>
				</form>
				<div id="upload-feedback" style="margin-top: 1rem;"></div>
			</div>
		</div>
	</div>

	<script>
		document.addEventListener('DOMContentLoaded', () => {
			const uploadModal = document.getElementById('upload-modal');
			const addDeliveryBtn = document.getElementById('add-delivery-btn');
			const closeModalBtn = document.getElementById('close-modal-btn');
			const uploadForm = document.getElementById('upload-form');
			const feedbackEl = document.getElementById('upload-feedback');

			// Abrir modal
			addDeliveryBtn.addEventListener('click', () => {
				uploadModal.classList.remove('u-hide');
				feedbackEl.innerHTML = ''; // Limpa feedback antigo
				uploadForm.reset(); // Limpa formulário
			});

			// Fechar modal
			const closeModal = () => uploadModal.classList.add('u-hide');
			closeModalBtn.addEventListener('click', closeModal);
			uploadModal.addEventListener('click', (e) => {
				if (e.target === uploadModal) {
					closeModal();
				}
			});

			// Lógica de upload com AJAX e feedback aprimorado
			uploadForm.addEventListener('submit', async (e) => {
				e.preventDefault();
				console.log('Upload iniciado...');

				const submitBtn = uploadForm.querySelector('button[type="submit"]');
				const originalBtnText = submitBtn.innerHTML;
				feedbackEl.innerHTML = '';

				submitBtn.disabled = true;
				submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processando...';

				try {
					const formData = new FormData(uploadForm);
					const response = await fetch(uploadForm.action, {
						method: 'POST',
						body: formData,
					});

					console.log('Resposta do servidor recebida:', response.status, response.statusText);

					const responseText = await response.text();
					console.log('Corpo da resposta (texto):', responseText);

					if (!response.ok) {
						throw new Error(`Erro do servidor: ${response.status}. Verifique o console para a resposta completa.`);
					}
					
					let result;
					try {
						result = JSON.parse(responseText);
					} catch (jsonError) {
						console.error("Falha ao analisar a resposta como JSON:", jsonError);
						throw new Error("O servidor enviou uma resposta inesperada. Verifique o console.");
					}

					console.log('Resposta JSON processada:', result);
					if (result.error) {
						throw new Error(result.error);
					}

					feedbackEl.className = 'p-notification--positive';
					feedbackEl.innerHTML = `<p class="p-notification__response">${result.message}</p>`;
					
					// Mostrar indicador de recarregamento
					setTimeout(() => {
						feedbackEl.innerHTML = `
							<p class="p-notification__response">
								<i class="fas fa-spinner fa-spin"></i> Atualizando dashboard...
							</p>
						`;
					}, 1500);
					
					setTimeout(() => {
						closeModal();
						// Recarregar dashboard automaticamente
						if (typeof window.loadDashboard === 'function') {
							console.log('Recarregando dashboard após upload...');
							window.loadDashboard().then(() => {
								console.log('Dashboard recarregado com sucesso!');
							}).catch((err) => {
								console.error('Erro ao recarregar dashboard:', err);
								// Fallback: recarregar página
								window.location.reload();
							});
						} else {
							// Fallback: recarregar página se a função não estiver disponível
							console.log('Função loadDashboard não encontrada, recarregando página...');
							window.location.reload();
						}
					}, 2000);

				} catch (error) {
					console.error('Erro capturado no bloco catch:', error);
					feedbackEl.className = 'p-notification--negative';
					feedbackEl.innerHTML = `<p class="p-notification__response">${error.message}</p>`;
				} finally {
					submitBtn.disabled = false;
					submitBtn.innerHTML = originalBtnText;
				}
			});
		});
	</script>
</body>
</html>
