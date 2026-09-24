ALTER TABLE obras
    ADD COLUMN area_construida DECIMAL(12,2) NULL AFTER valor_total,
    ADD COLUMN tipologia VARCHAR(40) NULL AFTER area_construida,
    ADD COLUMN padrao VARCHAR(10) NULL AFTER tipologia;

CREATE TABLE IF NOT EXISTS referencias (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    codigo           VARCHAR(20) NOT NULL,
    titulo           VARCHAR(160) NOT NULL,
    tipologia        VARCHAR(40) NOT NULL,
    padrao           VARCHAR(10) NULL,
    area_construida  DECIMAL(12,2) NULL,
    data_base        DATE NULL,
    total_direto     DECIMAL(15,2) NOT NULL DEFAULT 0,
    bdi_percentual   DECIMAL(6,2) NOT NULL DEFAULT 0,
    origem           VARCHAR(20) NOT NULL DEFAULT 'acervo',
    orcamento_id     INT UNSIGNED NULL,
    fonte_hash       CHAR(40) NOT NULL,
    ativo            TINYINT(1) NOT NULL DEFAULT 1,
    criado_em        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_referencias_hash (fonte_hash),
    INDEX idx_referencias_tipologia (tipologia, ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS referencia_itens (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    referencia_id   INT UNSIGNED NOT NULL,
    etapa           VARCHAR(150) NULL,
    etapa_padrao    VARCHAR(40) NOT NULL DEFAULT 'outros',
    descricao       VARCHAR(255) NOT NULL,
    unidade         VARCHAR(20) NOT NULL DEFAULT 'UN',
    quantidade      DECIMAL(14,4) NOT NULL DEFAULT 0,
    preco_unitario  DECIMAL(14,4) NOT NULL DEFAULT 0,
    FOREIGN KEY (referencia_id) REFERENCES referencias(id) ON DELETE CASCADE,
    INDEX idx_referencia_itens_ref (referencia_id),
    INDEX idx_referencia_itens_etapa (etapa_padrao)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS precos_base (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    fonte        VARCHAR(20) NOT NULL,
    codigo       VARCHAR(30) NOT NULL,
    descricao    VARCHAR(500) NOT NULL,
    unidade      VARCHAR(20) NOT NULL,
    preco        DECIMAL(14,4) NOT NULL,
    data_base    DATE NULL,
    localidade   VARCHAR(60) NULL,
    UNIQUE KEY uk_precos_base (fonte, codigo, data_base)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
