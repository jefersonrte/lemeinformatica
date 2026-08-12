-- =============================================================
-- GestaoObras - Schema MySQL Completo
-- Executar como: mysql -u root -p < database.sql
-- =============================================================

CREATE DATABASE IF NOT EXISTS gestao_obras
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE gestao_obras;

-- -------------------------------------------------------------
-- Usuários (admins + clientes)
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS usuarios (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome          VARCHAR(120) NOT NULL,
    email         VARCHAR(180) NOT NULL UNIQUE,
    senha         VARCHAR(255) NOT NULL,
    role          ENUM('admin','cliente') NOT NULL DEFAULT 'cliente',
    ativo         TINYINT(1) NOT NULL DEFAULT 1,
    token_email   VARCHAR(64) NULL,
    email_verificado TINYINT(1) NOT NULL DEFAULT 0,
    ultimo_login  DATETIME NULL,
    criado_em     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_role  (role)
) ENGINE=InnoDB;

-- -------------------------------------------------------------
-- Clientes (dados da empresa cliente)
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS clientes (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id    INT UNSIGNED NOT NULL,
    razao_social  VARCHAR(180) NOT NULL,
    nome_fantasia VARCHAR(180),
    cnpj_cpf      VARCHAR(20),
    telefone      VARCHAR(20),
    whatsapp      VARCHAR(20),
    email         VARCHAR(180),
    endereco      VARCHAR(255),
    cidade        VARCHAR(100),
    estado        CHAR(2),
    cep           VARCHAR(10),
    obs           TEXT,
    criado_em     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_usuario (usuario_id)
) ENGINE=InnoDB;

-- -------------------------------------------------------------
-- Obras / Projetos
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS obras (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cliente_id    INT UNSIGNED NOT NULL,
    nome          VARCHAR(200) NOT NULL,
    descricao     TEXT,
    endereco      VARCHAR(255),
    cidade        VARCHAR(100),
    estado        CHAR(2),
    status        ENUM('planejamento','em_andamento','pausada','concluida','cancelada') NOT NULL DEFAULT 'planejamento',
    data_inicio   DATE NULL,
    data_prev_fim DATE NULL,
    data_fim      DATE NULL,
    valor_total   DECIMAL(15,2) DEFAULT 0.00,
    progresso     TINYINT UNSIGNED DEFAULT 0 COMMENT '0-100%',
    criado_em     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE,
    INDEX idx_cliente (cliente_id),
    INDEX idx_status  (status)
) ENGINE=InnoDB;

-- -------------------------------------------------------------
-- Etapas da obra (para dashboard de andamento)
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS obra_etapas (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    obra_id     INT UNSIGNED NOT NULL,
    nome        VARCHAR(150) NOT NULL,
    descricao   TEXT,
    ordem       TINYINT UNSIGNED DEFAULT 0,
    status      ENUM('pendente','em_andamento','concluida') NOT NULL DEFAULT 'pendente',
    progresso   TINYINT UNSIGNED DEFAULT 0,
    data_inicio DATE NULL,
    data_fim    DATE NULL,
    criado_em   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (obra_id) REFERENCES obras(id) ON DELETE CASCADE,
    INDEX idx_obra (obra_id)
) ENGINE=InnoDB;

-- -------------------------------------------------------------
-- Categorias de produtos
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS categorias (
    id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome      VARCHAR(100) NOT NULL UNIQUE,
    descricao VARCHAR(255),
    ativo     TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

-- -------------------------------------------------------------
-- Fornecedores
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS fornecedores (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome         VARCHAR(180) NOT NULL,
    cnpj_cpf     VARCHAR(20),
    email        VARCHAR(180),
    telefone     VARCHAR(20),
    whatsapp     VARCHAR(20),
    contato      VARCHAR(120),
    endereco     VARCHAR(255),
    cidade       VARCHAR(100),
    estado       CHAR(2),
    obs          TEXT,
    ativo        TINYINT(1) NOT NULL DEFAULT 1,
    criado_em    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_nome (nome)
) ENGINE=InnoDB;

-- Categorias atendidas por cada fornecedor (N:N)
CREATE TABLE IF NOT EXISTS fornecedor_categorias (
    fornecedor_id INT UNSIGNED NOT NULL,
    categoria_id  INT UNSIGNED NOT NULL,
    PRIMARY KEY (fornecedor_id, categoria_id),
    FOREIGN KEY (fornecedor_id) REFERENCES fornecedores(id) ON DELETE CASCADE,
    FOREIGN KEY (categoria_id)  REFERENCES categorias(id)  ON DELETE CASCADE
) ENGINE=InnoDB;

-- -------------------------------------------------------------
-- Produtos / Materiais
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS produtos (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    categoria_id INT UNSIGNED NOT NULL,
    codigo       VARCHAR(50),
    nome         VARCHAR(200) NOT NULL,
    unidade      VARCHAR(20) DEFAULT 'UN',
    descricao    TEXT,
    ativo        TINYINT(1) NOT NULL DEFAULT 1,
    criado_em    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (categoria_id) REFERENCES categorias(id),
    INDEX idx_categoria (categoria_id),
    INDEX idx_codigo    (codigo)
) ENGINE=InnoDB;

-- -------------------------------------------------------------
-- Orçamentos
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS orcamentos (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    obra_id      INT UNSIGNED NOT NULL,
    cliente_id   INT UNSIGNED NOT NULL,
    titulo       VARCHAR(200) NOT NULL,
    status       ENUM('rascunho','aguardando_cotacao','cotado','aprovado','reprovado','cancelado') NOT NULL DEFAULT 'rascunho',
    total_estimado DECIMAL(15,2) DEFAULT 0.00,
    total_cotado   DECIMAL(15,2) DEFAULT 0.00,
    arquivo_origem VARCHAR(255) COMMENT 'nome do arquivo importado',
    tipo_origem    ENUM('manual','excel','xml','pdf','caixa') DEFAULT 'manual',
    obs            TEXT,
    criado_em      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (obra_id)    REFERENCES obras(id)    ON DELETE CASCADE,
    FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE,
    INDEX idx_obra    (obra_id),
    INDEX idx_cliente (cliente_id),
    INDEX idx_status  (status)
) ENGINE=InnoDB;

-- Itens do orçamento
CREATE TABLE IF NOT EXISTS orcamento_itens (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    orcamento_id    INT UNSIGNED NOT NULL,
    produto_id      INT UNSIGNED NULL,
    categoria_id    INT UNSIGNED NULL,
    descricao       VARCHAR(255) NOT NULL,
    unidade         VARCHAR(20) DEFAULT 'UN',
    quantidade      DECIMAL(12,3) NOT NULL DEFAULT 1,
    preco_unitario  DECIMAL(12,4) DEFAULT 0,
    preco_total     DECIMAL(15,2) GENERATED ALWAYS AS (quantidade * preco_unitario) STORED,
    preco_cotado    DECIMAL(12,4) DEFAULT NULL,
    total_cotado    DECIMAL(15,2) GENERATED ALWAYS AS (quantidade * COALESCE(preco_cotado, 0)) STORED,
    fornecedor_id   INT UNSIGNED NULL,
    obs             VARCHAR(255),
    FOREIGN KEY (orcamento_id) REFERENCES orcamentos(id) ON DELETE CASCADE,
    FOREIGN KEY (produto_id)   REFERENCES produtos(id)   ON DELETE SET NULL,
    FOREIGN KEY (categoria_id) REFERENCES categorias(id) ON DELETE SET NULL,
    FOREIGN KEY (fornecedor_id)REFERENCES fornecedores(id) ON DELETE SET NULL,
    INDEX idx_orcamento (orcamento_id)
) ENGINE=InnoDB;

-- -------------------------------------------------------------
-- Cotações enviadas aos fornecedores
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS cotacoes (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    orcamento_id   INT UNSIGNED NOT NULL,
    fornecedor_id  INT UNSIGNED NOT NULL,
    status         ENUM('pendente','enviada','respondida','aceita','recusada') NOT NULL DEFAULT 'pendente',
    canal_envio    ENUM('email','whatsapp','manual') DEFAULT 'email',
    mensagem       TEXT,
    resposta       TEXT,
    arquivo_resp   VARCHAR(255),
    data_envio     DATETIME NULL,
    data_resposta  DATETIME NULL,
    criado_em      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (orcamento_id)  REFERENCES orcamentos(id)   ON DELETE CASCADE,
    FOREIGN KEY (fornecedor_id) REFERENCES fornecedores(id) ON DELETE CASCADE,
    INDEX idx_orcamento  (orcamento_id),
    INDEX idx_fornecedor (fornecedor_id)
) ENGINE=InnoDB;

-- Itens da cotação respondida
CREATE TABLE IF NOT EXISTS cotacao_itens (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cotacao_id       INT UNSIGNED NOT NULL,
    orcamento_item_id INT UNSIGNED NULL,
    descricao        VARCHAR(255) NOT NULL,
    unidade          VARCHAR(20) DEFAULT 'UN',
    quantidade       DECIMAL(12,3),
    preco_unitario   DECIMAL(12,4),
    obs              VARCHAR(255),
    FOREIGN KEY (cotacao_id)        REFERENCES cotacoes(id)       ON DELETE CASCADE,
    FOREIGN KEY (orcamento_item_id) REFERENCES orcamento_itens(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- -------------------------------------------------------------
-- Compras / Pedidos gerados a partir de cotações aprovadas
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS compras (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    obra_id        INT UNSIGNED NOT NULL,
    cotacao_id     INT UNSIGNED NOT NULL,
    fornecedor_id  INT UNSIGNED NOT NULL,
    status         ENUM('solicitado','confirmado','em_producao','enviado','entregue','cancelado') NOT NULL DEFAULT 'solicitado',
    valor_total    DECIMAL(15,2) DEFAULT 0.00,
    data_pedido    DATE NULL,
    data_prev_entrega DATE NULL,
    data_entrega   DATE NULL,
    nf_numero      VARCHAR(50),
    obs            TEXT,
    criado_em      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (obra_id)       REFERENCES obras(id)       ON DELETE CASCADE,
    FOREIGN KEY (cotacao_id)    REFERENCES cotacoes(id)    ON DELETE CASCADE,
    FOREIGN KEY (fornecedor_id) REFERENCES fornecedores(id) ON DELETE CASCADE,
    INDEX idx_obra    (obra_id),
    INDEX idx_status  (status)
) ENGINE=InnoDB;

-- -------------------------------------------------------------
-- Log de atividades
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS logs (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT UNSIGNED NULL,
    acao       VARCHAR(100) NOT NULL,
    tabela     VARCHAR(60),
    registro_id INT UNSIGNED,
    detalhe    TEXT,
    ip         VARCHAR(45),
    criado_em  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_usuario   (usuario_id),
    INDEX idx_criado_em (criado_em)
) ENGINE=InnoDB;

-- -------------------------------------------------------------
-- Plantas e documentos técnicos versionados por obra
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS obra_plantas (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    obra_id       INT UNSIGNED NOT NULL,
    titulo        VARCHAR(180) NOT NULL,
    descricao     VARCHAR(500),
    arquivo       VARCHAR(255) NOT NULL,
    nome_original VARCHAR(255) NOT NULL,
    mime_type     VARCHAR(80) NOT NULL,
    tamanho       BIGINT UNSIGNED NOT NULL,
    versao        INT UNSIGNED NOT NULL DEFAULT 1,
    usuario_id    INT UNSIGNED NULL,
    criado_em     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (obra_id) REFERENCES obras(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    UNIQUE KEY uk_obra_planta_versao (obra_id, titulo, versao),
    INDEX idx_obra_plantas_obra (obra_id),
    INDEX idx_obra_plantas_criado (criado_em)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS schema_migrations (
    versao      VARCHAR(30) PRIMARY KEY,
    aplicado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- -------------------------------------------------------------
-- Dados iniciais
-- -------------------------------------------------------------
INSERT INTO usuarios (nome, email, senha, role, ativo, email_verificado)
VALUES ('Administrador', 'admin@lemeinformatica.com.br',
        '$2y$12$placeholder_troque_pela_senha_gerada_pelo_php', 'admin', 1, 1);

INSERT INTO categorias (nome) VALUES
('Alvenaria e Argamassa'),
('Revestimentos Cerâmicos'),
('Tintas e Impermeabilizantes'),
('Instalações Elétricas'),
('Instalações Hidráulicas'),
('Estrutura Metálica e Madeira'),
('Coberturas e Telhados'),
('Esquadrias e Vidros'),
('Pisos e Acabamentos'),
('Ferramentas e EPI');
