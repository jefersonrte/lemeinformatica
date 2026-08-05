CREATE TABLE IF NOT EXISTS pet_servicos (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(50) NOT NULL UNIQUE,
    nome VARCHAR(140) NOT NULL,
    categoria ENUM('banho', 'tosa', 'spa', 'higiene', 'outro') NOT NULL DEFAULT 'banho',
    duracao_minutos SMALLINT UNSIGNED NOT NULL DEFAULT 60,
    preco DECIMAL(12,2) NOT NULL DEFAULT 0,
    descricao VARCHAR(500) NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_por INT NULL,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_pet_servicos_categoria_ativo (categoria, ativo),
    INDEX idx_pet_servicos_nome (nome),
    CONSTRAINT fk_pet_servicos_criado_por
        FOREIGN KEY (criado_por) REFERENCES usuarios_admin(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pet_banho_tosa_agendamentos (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    animal_id BIGINT UNSIGNED NOT NULL,
    status ENUM('agendado', 'confirmado', 'em_atendimento', 'concluido', 'cancelado', 'nao_compareceu') NOT NULL DEFAULT 'agendado',
    inicio_em DATETIME NOT NULL,
    fim_previsto_em DATETIME NULL,
    fim_em DATETIME NULL,
    profissional_nome VARCHAR(140) NULL,
    observacoes_entrada TEXT NULL,
    observacoes_saida TEXT NULL,
    valor_total DECIMAL(12,2) NOT NULL DEFAULT 0,
    criado_por INT NULL,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_pet_banho_tosa_data_status (inicio_em, status),
    INDEX idx_pet_banho_tosa_animal (animal_id, inicio_em),
    CONSTRAINT fk_pet_banho_tosa_animal
        FOREIGN KEY (animal_id) REFERENCES pet_animais(id),
    CONSTRAINT fk_pet_banho_tosa_criado_por
        FOREIGN KEY (criado_por) REFERENCES usuarios_admin(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pet_banho_tosa_itens (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    agendamento_id BIGINT UNSIGNED NOT NULL,
    servico_id BIGINT UNSIGNED NOT NULL,
    servico_nome VARCHAR(140) NOT NULL,
    quantidade DECIMAL(10,2) NOT NULL DEFAULT 1,
    preco_unitario DECIMAL(12,2) NOT NULL DEFAULT 0,
    subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
    observacoes VARCHAR(500) NULL,
    INDEX idx_pet_banho_tosa_itens_agendamento (agendamento_id),
    INDEX idx_pet_banho_tosa_itens_servico (servico_id),
    CONSTRAINT fk_pet_banho_tosa_itens_agendamento
        FOREIGN KEY (agendamento_id) REFERENCES pet_banho_tosa_agendamentos(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_pet_banho_tosa_itens_servico
        FOREIGN KEY (servico_id) REFERENCES pet_servicos(id)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pet_produtos (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sku VARCHAR(60) NOT NULL UNIQUE,
    nome VARCHAR(180) NOT NULL,
    categoria ENUM('racao', 'petisco', 'higiene', 'acessorio', 'medicamento', 'outro') NOT NULL DEFAULT 'outro',
    unidade VARCHAR(20) NOT NULL DEFAULT 'un',
    marca VARCHAR(100) NULL,
    codigo_barras VARCHAR(80) NULL UNIQUE,
    preco_custo DECIMAL(12,2) NULL,
    preco_venda DECIMAL(12,2) NOT NULL DEFAULT 0,
    estoque_atual DECIMAL(12,3) NOT NULL DEFAULT 0,
    estoque_minimo DECIMAL(12,3) NOT NULL DEFAULT 0,
    controla_estoque TINYINT(1) NOT NULL DEFAULT 1,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_por INT NULL,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_pet_produtos_nome (nome),
    INDEX idx_pet_produtos_categoria_ativo (categoria, ativo),
    INDEX idx_pet_produtos_estoque (controla_estoque, estoque_atual, estoque_minimo),
    CONSTRAINT fk_pet_produtos_criado_por
        FOREIGN KEY (criado_por) REFERENCES usuarios_admin(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pet_vendas (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    numero VARCHAR(32) NULL UNIQUE,
    tutor_id BIGINT UNSIGNED NULL,
    status ENUM('concluida', 'cancelada') NOT NULL DEFAULT 'concluida',
    subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
    desconto DECIMAL(12,2) NOT NULL DEFAULT 0,
    total DECIMAL(12,2) NOT NULL DEFAULT 0,
    forma_pagamento ENUM('dinheiro', 'pix', 'debito', 'credito', 'outro') NOT NULL DEFAULT 'pix',
    observacoes VARCHAR(1000) NULL,
    concluida_em DATETIME NOT NULL,
    cancelada_em DATETIME NULL,
    cancelada_por INT NULL,
    criado_por INT NULL,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_pet_vendas_data_status (concluida_em, status),
    INDEX idx_pet_vendas_tutor (tutor_id, concluida_em),
    CONSTRAINT fk_pet_vendas_tutor
        FOREIGN KEY (tutor_id) REFERENCES pet_tutores(id)
        ON DELETE SET NULL,
    CONSTRAINT fk_pet_vendas_criado_por
        FOREIGN KEY (criado_por) REFERENCES usuarios_admin(id)
        ON DELETE SET NULL,
    CONSTRAINT fk_pet_vendas_cancelada_por
        FOREIGN KEY (cancelada_por) REFERENCES usuarios_admin(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pet_venda_itens (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    venda_id BIGINT UNSIGNED NOT NULL,
    produto_id BIGINT UNSIGNED NOT NULL,
    produto_nome VARCHAR(180) NOT NULL,
    sku VARCHAR(60) NOT NULL,
    quantidade DECIMAL(12,3) NOT NULL,
    preco_unitario DECIMAL(12,2) NOT NULL,
    subtotal DECIMAL(12,2) NOT NULL,
    INDEX idx_pet_venda_itens_venda (venda_id),
    INDEX idx_pet_venda_itens_produto (produto_id),
    CONSTRAINT fk_pet_venda_itens_venda
        FOREIGN KEY (venda_id) REFERENCES pet_vendas(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_pet_venda_itens_produto
        FOREIGN KEY (produto_id) REFERENCES pet_produtos(id)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pet_estoque_movimentos (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    produto_id BIGINT UNSIGNED NOT NULL,
    tipo ENUM('entrada', 'saida', 'ajuste_positivo', 'ajuste_negativo', 'venda', 'estorno') NOT NULL,
    quantidade DECIMAL(12,3) NOT NULL,
    estoque_anterior DECIMAL(12,3) NOT NULL,
    estoque_novo DECIMAL(12,3) NOT NULL,
    custo_unitario DECIMAL(12,2) NULL,
    referencia_tipo VARCHAR(40) NULL,
    referencia_id BIGINT UNSIGNED NULL,
    motivo VARCHAR(500) NOT NULL,
    criado_por INT NULL,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_pet_estoque_produto_data (produto_id, criado_em),
    INDEX idx_pet_estoque_referencia (referencia_tipo, referencia_id),
    CONSTRAINT fk_pet_estoque_produto
        FOREIGN KEY (produto_id) REFERENCES pet_produtos(id)
        ON DELETE RESTRICT,
    CONSTRAINT fk_pet_estoque_criado_por
        FOREIGN KEY (criado_por) REFERENCES usuarios_admin(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO pet_servicos (codigo, nome, categoria, duracao_minutos, preco, descricao)
VALUES
    ('BANHO-P', 'Banho porte pequeno', 'banho', 60, 55.00, 'Banho completo para animais de pequeno porte.'),
    ('BANHO-MG', 'Banho porte medio ou grande', 'banho', 90, 85.00, 'Banho completo para animais de medio ou grande porte.'),
    ('TOSA-HIG', 'Tosa higienica', 'higiene', 35, 40.00, 'Tosa de higiene e acabamento.'),
    ('TOSA-COMP', 'Tosa completa', 'tosa', 90, 95.00, 'Tosa completa conforme raca e orientacao do tutor.'),
    ('SPA-HIDRA', 'Hidratacao de pelagem', 'spa', 30, 35.00, 'Tratamento complementar de hidratacao da pelagem.')
ON DUPLICATE KEY UPDATE
    nome = VALUES(nome),
    categoria = VALUES(categoria),
    duracao_minutos = VALUES(duracao_minutos),
    descricao = VALUES(descricao);

INSERT INTO pet_schema_migrations (versao, descricao)
VALUES ('1.1.0', 'Banho e tosa, catalogo, estoque e vendas do modulo Pet')
ON DUPLICATE KEY UPDATE descricao = VALUES(descricao);
