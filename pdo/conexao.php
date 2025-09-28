<?php
// Carrega as configurações do banco de dados
if (file_exists(__DIR__ . '/../config.php')) {
    require_once __DIR__ . '/../config.php';
} else {
    die("<h2>Erro de Configuração</h2><p>O arquivo <code>config.php</code> não foi encontrado. Por favor, copie <code>config.php.example</code> para <code>config.php</code> e preencha com as credenciais do seu banco de dados.</p>");
}

// Configurações de conexão
$host = DB_HOST;
$dbname = DB_NAME;
$user = DB_USER;
$pass = DB_PASS;
$charset = 'utf8mb4';

// DSN (Data Source Name)
$dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";

// Opções do PDO
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    // Se a conexão falhar, pode ser porque o banco não existe.
    if ($e->getCode() === 1049) { // Código de erro para "Unknown database"
        try {
            // Tenta se conectar sem especificar o banco para criá-lo
            $temp_pdo = new PDO("mysql:host=$host", $user, $pass, $options);
            $temp_pdo->exec("CREATE DATABASE `$dbname` CHARACTER SET $charset COLLATE utf8mb4_unicode_ci;");
            
            // Tenta reconectar ao banco recém-criado
            $pdo = new PDO($dsn, $user, $pass, $options);
        } catch (\PDOException $ex) {
            throw new \PDOException("Não foi possível criar o banco de dados '$dbname'. Verifique as permissões do usuário. Erro: " . $ex->getMessage(), (int)$ex->getCode());
        }
    } else {
        throw new \PDOException($e->getMessage(), (int)$e->getCode());
    }
}

// --- Criação automática das tabelas ---
try {
    // Tabela de Motoristas
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `motoristas` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `nome` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
          `placa_veiculo` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
          `documento` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
          `telefone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
          `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // Tabela de Entregas
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `entregas` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `delivery_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
          `motorista_id` int(11) NOT NULL,
          `origin_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
          `destination_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
          `status` enum('pending_pickup','in_transit','delivered','failed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending_pickup',
          `origin_lat` decimal(10,8) DEFAULT NULL,
          `origin_lng` decimal(11,8) DEFAULT NULL,
          `destination_lat` decimal(10,8) DEFAULT NULL,
          `destination_lng` decimal(11,8) DEFAULT NULL,
          `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
          PRIMARY KEY (`id`),
          UNIQUE KEY `delivery_id` (`delivery_id`),
          KEY `motorista_id` (`motorista_id`),
          CONSTRAINT `entregas_ibfk_1` FOREIGN KEY (`motorista_id`) REFERENCES `motoristas` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // Tabela de Eventos
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `eventos_entrega` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `entrega_id` int(11) NOT NULL,
          `status_anterior` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
          `status_novo` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
          `motivo_falha` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
          `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
          PRIMARY KEY (`id`),
          KEY `entrega_id` (`entrega_id`),
          CONSTRAINT `eventos_entrega_ibfk_1` FOREIGN KEY (`entrega_id`) REFERENCES `entregas` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

} catch (PDOException $e) {
    die("Erro ao criar as tabelas: " . $e->getMessage());
}