CREATE TABLE IF NOT EXISTS pet_sso_tokens (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    codigo_hash CHAR(64) NOT NULL UNIQUE,
    token_hash CHAR(64) NULL UNIQUE,
    codigo_expira_em DATETIME NOT NULL,
    token_expira_em DATETIME NULL,
    trocado_em DATETIME NULL,
    ultimo_uso_em DATETIME NULL,
    revogado_em DATETIME NULL,
    ip_criacao VARCHAR(45) NULL,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_pet_sso_usuario (usuario_id, token_expira_em),
    INDEX idx_pet_sso_validade (token_expira_em, revogado_em),
    CONSTRAINT fk_pet_sso_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios_admin(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO pet_schema_migrations (versao, descricao)
VALUES ('1.1.1', 'Autenticacao central para o dashboard Pet entre dominios')
ON DUPLICATE KEY UPDATE descricao = VALUES(descricao);
