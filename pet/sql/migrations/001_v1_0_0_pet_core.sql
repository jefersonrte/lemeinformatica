CREATE TABLE IF NOT EXISTS pet_schema_migrations (
    versao VARCHAR(30) PRIMARY KEY,
    descricao VARCHAR(255) NOT NULL,
    aplicado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pet_tutores (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(160) NOT NULL,
    cpf CHAR(11) NULL UNIQUE,
    rg VARCHAR(30) NULL,
    data_nascimento DATE NULL,
    genero VARCHAR(30) NULL,
    estado_civil VARCHAR(30) NULL,
    profissao VARCHAR(100) NULL,
    email VARCHAR(160) NULL,
    telefone VARCHAR(20) NOT NULL,
    whatsapp VARCHAR(20) NULL,
    cep CHAR(8) NULL,
    logradouro VARCHAR(180) NULL,
    numero VARCHAR(20) NULL,
    complemento VARCHAR(100) NULL,
    bairro VARCHAR(100) NULL,
    cidade VARCHAR(100) NULL,
    uf CHAR(2) NULL,
    contato_emergencia_nome VARCHAR(160) NULL,
    contato_emergencia_telefone VARCHAR(20) NULL,
    observacoes TEXT NULL,
    foto_caminho VARCHAR(255) NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_por INT NULL,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_pet_tutores_nome (nome),
    INDEX idx_pet_tutores_telefone (telefone),
    INDEX idx_pet_tutores_ativo (ativo),
    CONSTRAINT fk_pet_tutores_criado_por
        FOREIGN KEY (criado_por) REFERENCES usuarios_admin(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pet_animais (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tutor_id BIGINT UNSIGNED NOT NULL,
    nome VARCHAR(120) NOT NULL,
    especie VARCHAR(60) NOT NULL,
    raca VARCHAR(120) NULL,
    sexo ENUM('macho', 'femea', 'indefinido') NOT NULL DEFAULT 'indefinido',
    data_nascimento DATE NULL,
    cor VARCHAR(100) NULL,
    peso_kg DECIMAL(7,2) NULL,
    porte ENUM('mini', 'pequeno', 'medio', 'grande', 'gigante', 'nao_aplicavel') NOT NULL DEFAULT 'nao_aplicavel',
    microchip VARCHAR(80) NULL UNIQUE,
    castrado TINYINT(1) NOT NULL DEFAULT 0,
    tipo_sanguineo VARCHAR(30) NULL,
    alergias TEXT NULL,
    condicoes_preexistentes TEXT NULL,
    observacoes TEXT NULL,
    foto_caminho VARCHAR(255) NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    obito_em DATETIME NULL,
    criado_por INT NULL,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_pet_animais_tutor (tutor_id, ativo),
    INDEX idx_pet_animais_nome (nome),
    INDEX idx_pet_animais_especie (especie),
    CONSTRAINT fk_pet_animais_tutor
        FOREIGN KEY (tutor_id) REFERENCES pet_tutores(id),
    CONSTRAINT fk_pet_animais_criado_por
        FOREIGN KEY (criado_por) REFERENCES usuarios_admin(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pet_veterinarios (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL UNIQUE,
    crmv VARCHAR(30) NOT NULL,
    uf_crmv CHAR(2) NOT NULL,
    especialidade VARCHAR(120) NULL,
    telefone_profissional VARCHAR(20) NULL,
    biografia VARCHAR(500) NULL,
    foto_caminho VARCHAR(255) NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_pet_veterinario_crmv (crmv, uf_crmv),
    INDEX idx_pet_veterinarios_ativo (ativo),
    CONSTRAINT fk_pet_veterinarios_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios_admin(id)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pet_atendimentos (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    animal_id BIGINT UNSIGNED NOT NULL,
    veterinario_id BIGINT UNSIGNED NULL,
    tipo ENUM('consulta', 'retorno', 'emergencia', 'vacina', 'exame', 'procedimento') NOT NULL DEFAULT 'consulta',
    status ENUM('agendado', 'em_atendimento', 'concluido', 'cancelado') NOT NULL DEFAULT 'agendado',
    inicio_em DATETIME NOT NULL,
    fim_em DATETIME NULL,
    motivo VARCHAR(500) NOT NULL,
    anamnese TEXT NULL,
    exame_clinico TEXT NULL,
    peso_kg DECIMAL(7,2) NULL,
    temperatura_c DECIMAL(5,2) NULL,
    frequencia_cardiaca SMALLINT UNSIGNED NULL,
    frequencia_respiratoria SMALLINT UNSIGNED NULL,
    mucosas VARCHAR(100) NULL,
    hidratacao VARCHAR(100) NULL,
    diagnostico TEXT NULL,
    conduta TEXT NULL,
    prescricao TEXT NULL,
    exames_solicitados TEXT NULL,
    retorno_em DATE NULL,
    criado_por INT NULL,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_pet_atendimentos_animal_data (animal_id, inicio_em),
    INDEX idx_pet_atendimentos_status_data (status, inicio_em),
    INDEX idx_pet_atendimentos_veterinario (veterinario_id, inicio_em),
    CONSTRAINT fk_pet_atendimentos_animal
        FOREIGN KEY (animal_id) REFERENCES pet_animais(id),
    CONSTRAINT fk_pet_atendimentos_veterinario
        FOREIGN KEY (veterinario_id) REFERENCES pet_veterinarios(id)
        ON DELETE SET NULL,
    CONSTRAINT fk_pet_atendimentos_criado_por
        FOREIGN KEY (criado_por) REFERENCES usuarios_admin(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pet_internacoes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    animal_id BIGINT UNSIGNED NOT NULL,
    veterinario_responsavel_id BIGINT UNSIGNED NULL,
    atendimento_origem_id BIGINT UNSIGNED NULL,
    status ENUM('ativa', 'alta', 'transferencia', 'obito', 'cancelada') NOT NULL DEFAULT 'ativa',
    entrada_em DATETIME NOT NULL,
    previsao_alta_em DATETIME NULL,
    saida_em DATETIME NULL,
    setor VARCHAR(80) NULL,
    leito VARCHAR(40) NULL,
    classificacao_risco ENUM('baixo', 'moderado', 'alto', 'critico') NOT NULL DEFAULT 'moderado',
    motivo VARCHAR(500) NOT NULL,
    diagnostico_inicial TEXT NULL,
    plano_cuidados TEXT NULL,
    resumo_alta TEXT NULL,
    criado_por INT NULL,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_pet_internacoes_status (status, entrada_em),
    INDEX idx_pet_internacoes_animal (animal_id, entrada_em),
    CONSTRAINT fk_pet_internacoes_animal
        FOREIGN KEY (animal_id) REFERENCES pet_animais(id),
    CONSTRAINT fk_pet_internacoes_veterinario
        FOREIGN KEY (veterinario_responsavel_id) REFERENCES pet_veterinarios(id)
        ON DELETE SET NULL,
    CONSTRAINT fk_pet_internacoes_atendimento
        FOREIGN KEY (atendimento_origem_id) REFERENCES pet_atendimentos(id)
        ON DELETE SET NULL,
    CONSTRAINT fk_pet_internacoes_criado_por
        FOREIGN KEY (criado_por) REFERENCES usuarios_admin(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pet_internacao_evolucoes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    internacao_id BIGINT UNSIGNED NOT NULL,
    veterinario_id BIGINT UNSIGNED NULL,
    registrado_em DATETIME NOT NULL,
    peso_kg DECIMAL(7,2) NULL,
    temperatura_c DECIMAL(5,2) NULL,
    frequencia_cardiaca SMALLINT UNSIGNED NULL,
    frequencia_respiratoria SMALLINT UNSIGNED NULL,
    glicemia_mg_dl DECIMAL(7,2) NULL,
    pressao_arterial VARCHAR(40) NULL,
    alimentacao VARCHAR(255) NULL,
    eliminacoes VARCHAR(255) NULL,
    medicacoes TEXT NULL,
    procedimentos TEXT NULL,
    observacoes TEXT NOT NULL,
    criado_por INT NULL,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_pet_evolucoes_internacao_data (internacao_id, registrado_em),
    CONSTRAINT fk_pet_evolucoes_internacao
        FOREIGN KEY (internacao_id) REFERENCES pet_internacoes(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_pet_evolucoes_veterinario
        FOREIGN KEY (veterinario_id) REFERENCES pet_veterinarios(id)
        ON DELETE SET NULL,
    CONSTRAINT fk_pet_evolucoes_criado_por
        FOREIGN KEY (criado_por) REFERENCES usuarios_admin(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pet_audit_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NULL,
    acao VARCHAR(80) NOT NULL,
    entidade VARCHAR(80) NOT NULL,
    entidade_id BIGINT NULL,
    detalhes_json JSON NULL,
    ip VARCHAR(45) NOT NULL DEFAULT '',
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_pet_audit_usuario_data (usuario_id, criado_em),
    INDEX idx_pet_audit_entidade (entidade, entidade_id),
    CONSTRAINT fk_pet_audit_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios_admin(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO pet_schema_migrations (versao, descricao)
VALUES ('1.0.0', 'Cadastros, prontuario, internacao e auditoria do modulo Pet')
ON DUPLICATE KEY UPDATE descricao = VALUES(descricao);
