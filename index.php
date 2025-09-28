<?php session_start(); header('Content-Type: text/html; charset=utf-8'); ?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>MapCloud - Sistema de Monitoramento</title>
	<link rel="stylesheet" href="https://assets.ubuntu.com/v1/vanilla-framework-version-4.34.1.min.css" />
	<link rel="stylesheet" href="public/css/style.css">
	<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="modern-template">
	<!-- Header com gradiente -->
	<header class="modern-header">
		<div class="header-background"></div>
		<div class="container">
			<div class="header-content">
				<div class="logo-section">
					<div class="logo-icon">
						<i class="fas fa-truck"></i>
					</div>
					<div class="logo-text">
						<h1>MapCloud</h1>
						<span>Sistema de Monitoramento</span>
					</div>
				</div>
				<div class="header-actions">
					<a href="cadastro_motorista.php" class="btn-primary">
						<i class="fas fa-plus"></i>
						Novo Motorista
					</a>
				</div>
			</div>
		</div>
	</header>

	<!-- Hero Section -->
	<section class="hero-section">
		<div class="container">
			<div class="hero-content">
				<h2>Selecione um Motorista</h2>
				<p>Escolha um motorista para monitorar entregas em tempo real</p>
			</div>
		</div>
	</section>

	<!-- Main Content -->
	<main class="main-content">
		<div class="container">
			<div class="content-card">
				<div class="card-header">
					<h3><i class="fas fa-users"></i> Motoristas Disponíveis</h3>
					<div class="card-actions">
						<span id="driver-count" class="badge">Carregando...</span>
					</div>
				</div>
				<div class="card-body">
					<div id="drivers-list" class="drivers-container">
						<div class="loading-state">
							<i class="fas fa-spinner fa-spin"></i>
							<p>Carregando motoristas...</p>
						</div>
					</div>
				</div>
			</div>
		</div>
	</main>
	<script>
	// Função para carregar lista de motoristas
	async function loadDrivers() {
		try {
			const res = await fetch('/mapcloud-plataforma/api/index.php/drivers');
			if(!res.ok) throw new Error('Falha ao carregar motoristas');
			const drivers = await res.json();
			const el = document.getElementById('drivers-list');
			const countEl = document.getElementById('driver-count');
			
			if(!Array.isArray(drivers) || drivers.length===0){ 
				el.innerHTML = `
					<div class="empty-state">
						<i class="fas fa-user-slash"></i>
						<h4>Nenhum motorista cadastrado</h4>
						<p>Cadastre o primeiro motorista para começar o monitoramento</p>
						<a href="cadastro_motorista.php" class="btn-primary">
							<i class="fas fa-plus"></i>
							Cadastrar Primeiro Motorista
						</a>
					</div>
				`;
				countEl.textContent = '0 motoristas';
				return; 
			}

			countEl.textContent = `${drivers.length} motorista${drivers.length > 1 ? 's' : ''}`;
			
			let html = '<div class="drivers-grid">';
			drivers.forEach(d => {
				html += `
					<div class="driver-card">
						<div class="driver-avatar">
							<i class="fas fa-user"></i>
						</div>
						<div class="driver-info">
							<h4>${d.nome || 'Nome não informado'}</h4>
							<div class="driver-details">
								${d.placa ? `<span class="detail-item"><i class="fas fa-car"></i> ${d.placa}</span>` : ''}
								${d.documento ? `<span class="detail-item"><i class="fas fa-id-card"></i> ${d.documento}</span>` : ''}
								${d.telefone ? `<span class="detail-item"><i class="fas fa-phone"></i> ${d.telefone}</span>` : ''}
							</div>
						</div>
						<div class="driver-actions">
							<a href="/mapcloud-plataforma/monitoramento.php?motorista_id=${d.id}" class="btn-monitor">
								<i class="fas fa-eye"></i>
								Monitorar
							</a>
						</div>
					</div>
				`;
			});
			html += '</div>';
			el.innerHTML = html;
		}catch(e){
			document.getElementById('drivers-list').innerHTML = `
				<div class="error-state">
					<i class="fas fa-exclamation-triangle"></i>
					<h4>Erro ao carregar motoristas</h4>
					<p>Verifique sua conexão e tente novamente</p>
					<button onclick="loadDrivers()" class="btn-secondary">
						<i class="fas fa-redo"></i>
						Tentar Novamente
					</button>
				</div>
			`;
			document.getElementById('driver-count').textContent = 'Erro';
		}
	}

	// Carregar motoristas ao inicializar
	loadDrivers();
	</script>
</body>
</html>
