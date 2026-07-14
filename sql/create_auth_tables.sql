CREATE TABLE IF NOT EXISTS usuarios_admin (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    senha_hash VARCHAR(255) NOT NULL,
    perfil ENUM('admin', 'operador', 'visualizador') NOT NULL DEFAULT 'visualizador',
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    ultimo_login_em TIMESTAMP NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS auth_login_attempts (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    identificador CHAR(64) NOT NULL,
    email VARCHAR(150) NOT NULL,
    ip VARCHAR(45) NOT NULL DEFAULT '',
    user_agent VARCHAR(500) NOT NULL DEFAULT '',
    sucesso TINYINT(1) NOT NULL DEFAULT 0,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_login_identifier_time (identificador, criado_em),
    INDEX idx_login_created (criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS auth_audit_log (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NULL,
    acao VARCHAR(80) NOT NULL,
    detalhes VARCHAR(500) NOT NULL DEFAULT '',
    ip VARCHAR(45) NOT NULL DEFAULT '',
    user_agent VARCHAR(500) NOT NULL DEFAULT '',
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_audit_user_time (usuario_id, criado_em),
    CONSTRAINT fk_audit_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios_admin(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Usuario inicial para primeiro acesso.
-- E-mail: admin@lemesolucoesemti.com.br
-- Senha: Admin@2026!
-- Troque a senha imediatamente depois da instalacao.
INSERT INTO usuarios_admin (nome, email, senha_hash, perfil, ativo)
VALUES (
    'Administrador',
    'admin@lemesolucoesemti.com.br',
    '$2y$12$ycUoniSLVHpdWFIbaIfjy.5hIkDQdiA0o4.JSPobt5LEcM07dyry.',
    'admin',
    1
)
ON DUPLICATE KEY UPDATE email = email;
