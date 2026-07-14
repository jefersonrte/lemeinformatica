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
