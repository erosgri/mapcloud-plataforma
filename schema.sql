-- Garante que estamos usando o banco de dados correto
USE mapcloud;

-- Tabela para armazenar as informações principais das entregas
CREATE TABLE IF NOT EXISTS entregas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    delivery_id VARCHAR(50) NOT NULL UNIQUE,
    nfe_key VARCHAR(44) NOT NULL UNIQUE,
    status VARCHAR(50) NOT NULL,
    origin_name VARCHAR(255),
    origin_lat DECIMAL(10, 8),
    origin_lng DECIMAL(11, 8),
    destination_name VARCHAR(255),
    destination_lat DECIMAL(10, 8),
    destination_lng DECIMAL(11, 8),
    current_lat DECIMAL(10, 8),
    current_lng DECIMAL(11, 8),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela para armazenar o histórico de eventos de cada entrega (timeline)
CREATE TABLE IF NOT EXISTS eventos_entrega (
    id INT AUTO_INCREMENT PRIMARY KEY,
    entrega_id INT NOT NULL,
    timestamp DATETIME NOT NULL,
    status VARCHAR(50) NOT NULL,
    location_description VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (entrega_id) REFERENCES entregas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Exemplo de como adicionar um índice para otimizar buscas
CREATE INDEX idx_nfe_key ON entregas(nfe_key);
