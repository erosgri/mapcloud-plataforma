<?php session_start(); header('Content-Type: text/html; charset=utf-8'); ?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Cadastrar Motorista - MapCloud</title>
	<link rel="stylesheet" href="https://assets.ubuntu.com/v1/vanilla-framework-version-4.34.1.min.css" />
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
					<div class="logo-icon"><i class="fas fa-truck"></i></div>
					<div class="logo-text">
						<h1>MapCloud</h1>
						<span>Cadastro de Motorista</span>
					</div>
				</div>
				<div class="header-actions">
					<a href="index.php" class="btn-primary">
						<i class="fas fa-arrow-left"></i>
						Voltar para Lista
					</a>
				</div>
			</div>
		</div>
	</header>

	<!-- Main Content -->
	<main class="main-content">
		<div class="container">
			<div class="content-card">
				<div class="card-header">
					<h3><i class="fas fa-user-plus"></i> Preencha os dados do novo motorista</h3>
				</div>
				<div class="card-body">
					<form id="driver-form" class="p-form">
						<div class="p-form__group">
							<label for="nome" class="p-form__label">Nome Completo: *</label>
							<div class="p-form__control">
								<input type="text" id="nome" name="nome" required placeholder="Ex: João da Silva">
							</div>
						</div>
						<div class="row">
							<div class="col-6">
								<div class="p-form__group">
									<label for="placa" class="p-form__label">Placa do Veículo:</label>
									<div class="p-form__control">
										<input type="text" id="placa" name="placa" placeholder="ABC-1234" maxlength="8">
									</div>
								</div>
							</div>
							<div class="col-6">
								<div class="p-form__group">
									<label for="documento" class="p-form__label">Documento:</label>
									<div class="p-form__control">
										<input type="text" id="documento" name="documento" placeholder="CPF ou CNH">
									</div>
								</div>
							</div>
						</div>
						<div class="p-form__group">
							<label for="telefone" class="p-form__label">Telefone:</label>
							<div class="p-form__control">
								<input type="tel" id="telefone" name="telefone" placeholder="(11) 99999-9999">
							</div>
						</div>
						<div class="p-form__group form-actions">
							<button type="submit" class="btn-primary">
								<i class="fas fa-check"></i> Cadastrar Motorista
							</button>
							<button type="button" id="clear-form" class="btn-secondary">
								<i class="fas fa-times"></i> Limpar
							</button>
						</div>
					</form>
					<div id="form-message" class="u-hide" style="margin-top: 1rem;"></div>
				</div>
			</div>
		</div>
	</main>
	<script>
	// Manipular formulário de cadastro
	document.getElementById('driver-form').addEventListener('submit', async function(e) {
		e.preventDefault();
		
		const formData = new FormData(this);
		const data = {
			nome: formData.get('nome'),
			placa: formData.get('placa'),
			documento: formData.get('documento'),
			telefone: formData.get('telefone')
		};

		// Desabilitar botão durante envio
		const submitBtn = this.querySelector('button[type="submit"]');
		const originalText = submitBtn.textContent;
		submitBtn.disabled = true;
		submitBtn.textContent = 'Cadastrando...';

		try {
			const response = await fetch('/mapcloud-plataforma/api/index.php/drivers', {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
				},
				body: JSON.stringify(data)
			});

			const result = await response.json();
			const messageEl = document.getElementById('form-message');
			
			if (response.ok) {
				messageEl.className = 'p-notification--positive';
				messageEl.innerHTML = `
					<p class="p-notification__response">
						<strong>Sucesso!</strong><br>
						${result.message}<br>
						<small>ID do motorista: ${result.id}</small>
					</p>
				`;
				messageEl.classList.remove('u-hide');
				this.reset();
				
				// Redirecionar após 2 segundos
				setTimeout(() => {
					window.location.href = 'index.php';
				}, 2000);
			} else {
				messageEl.className = 'p-notification--negative';
				messageEl.innerHTML = `<p class="p-notification__response"><strong>Erro:</strong> ${result.error}</p>`;
				messageEl.classList.remove('u-hide');
			}
		} catch (error) {
			const messageEl = document.getElementById('form-message');
			messageEl.className = 'p-notification--negative';
			messageEl.innerHTML = '<p class="p-notification__response"><strong>Erro:</strong> Falha na comunicação com o servidor.</p>';
			messageEl.classList.remove('u-hide');
		} finally {
			// Reabilitar botão
			submitBtn.disabled = false;
			submitBtn.textContent = originalText;
		}
	});

	// Botão limpar formulário
	document.getElementById('clear-form').addEventListener('click', function() {
		document.getElementById('driver-form').reset();
		document.getElementById('form-message').classList.add('u-hide');
	});

	// Máscara para telefone
	document.getElementById('telefone').addEventListener('input', function(e) {
		let value = e.target.value.replace(/\D/g, '');
		if (value.length >= 11) {
			value = value.replace(/(\d{2})(\d{5})(\d{4})/, '($1) $2-$3');
		} else if (value.length >= 7) {
			value = value.replace(/(\d{2})(\d{4})(\d{0,4})/, '($1) $2-$3');
		} else if (value.length >= 3) {
			value = value.replace(/(\d{2})(\d{0,5})/, '($1) $2');
		}
		e.target.value = value;
	});

	// Máscara para placa
	document.getElementById('placa').addEventListener('input', function(e) {
		let value = e.target.value.replace(/[^A-Za-z0-9]/g, '').toUpperCase();
		if (value.length > 3) {
			value = value.substring(0, 3) + '-' + value.substring(3, 7);
		}
		e.target.value = value;
	});
	</script>
</body>
</html>
