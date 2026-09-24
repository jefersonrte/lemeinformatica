CREATE TABLE IF NOT EXISTS schema_migrations (
    versao VARCHAR(30) PRIMARY KEY,
    aplicado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS obra_plantas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    obra_id INT UNSIGNED NOT NULL,
    titulo VARCHAR(180) NOT NULL,
    descricao VARCHAR(500) NULL,
    arquivo VARCHAR(255) NOT NULL,
    nome_original VARCHAR(255) NOT NULL,
    mime_type VARCHAR(80) NOT NULL,
    tamanho BIGINT UNSIGNED NOT NULL,
    versao INT UNSIGNED NOT NULL DEFAULT 1,
    usuario_id INT UNSIGNED NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (obra_id) REFERENCES obras(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    UNIQUE KEY uk_obra_planta_versao (obra_id, titulo, versao),
    INDEX idx_obra_plantas_obra (obra_id),
    INDEX idx_obra_plantas_criado (criado_em)
) ENGINE=InnoDB;
