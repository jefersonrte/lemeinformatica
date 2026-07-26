SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS animais (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    raca VARCHAR(100) NOT NULL,
    porte VARCHAR(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS alimentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    categoria VARCHAR(80) NOT NULL,
    unidade VARCHAR(30) NOT NULL,
    preco DECIMAL(10,2) NOT NULL,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_alimentos_categoria (categoria),
    INDEX idx_alimentos_nome (nome)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cargas_dados_demo (
    codigo VARCHAR(100) PRIMARY KEY,
    registros INT NOT NULL,
    executado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

START TRANSACTION;

INSERT IGNORE INTO cargas_dados_demo (codigo, registros)
VALUES ('animais_alimentos_1000_v1', 1000);

SET @executar_carga_demo = ROW_COUNT();

INSERT INTO animais (nome, raca, porte)
SELECT
    CONCAT(
        ELT(1 + MOD(seq.n * 17, 20),
            'Rex', 'Luna', 'Thor', 'Mel', 'Bob', 'Nina', 'Max', 'Lola', 'Zeus', 'Maya',
            'Fred', 'Amora', 'Toby', 'Belinha', 'Simba', 'Pipoca', 'Billy', 'Jade', 'Bruce', 'Cacau'
        ),
        ' ', LPAD(seq.n, 3, '0')
    ) AS nome,
    ELT(1 + MOD(seq.n * 13, 15),
        'Vira-lata', 'Labrador', 'Poodle', 'Bulldog', 'Beagle',
        'Pastor Alemao', 'Golden Retriever', 'Shih-tzu', 'Pinscher', 'Rottweiler',
        'Yorkshire', 'Border Collie', 'Boxer', 'Dachshund', 'Husky Siberiano'
    ) AS raca,
    ELT(1 + MOD(seq.n * 7, 3), 'Pequeno', 'Medio', 'Grande') AS porte
FROM (
    SELECT unidade.n + dezena.n * 10 + centena.n * 100 + 1 AS n
    FROM
        (SELECT 0 AS n UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4
         UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) AS unidade
    CROSS JOIN
        (SELECT 0 AS n UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4
         UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) AS dezena
    CROSS JOIN
        (SELECT 0 AS n UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4) AS centena
) AS seq
WHERE @executar_carga_demo = 1;

INSERT INTO alimentos (nome, categoria, unidade, preco)
SELECT
    CONCAT(
        ELT(1 + MOD(seq.n * 19, 25),
            'Arroz integral', 'Feijao carioca', 'Macarrao', 'Farinha de trigo', 'Acucar',
            'Cafe', 'Leite', 'Queijo', 'Iogurte', 'Manteiga',
            'Pao integral', 'Aveia', 'Banana', 'Maca', 'Laranja',
            'Tomate', 'Batata', 'Cenoura', 'Alface', 'Frango',
            'Carne bovina', 'Peixe', 'Ovos', 'Biscoito', 'Suco natural'
        ),
        ' ', LPAD(seq.n, 3, '0')
    ) AS nome,
    ELT(1 + MOD(seq.n * 11, 8),
        'Graos', 'Massas e farinhas', 'Bebidas', 'Laticinios',
        'Frutas', 'Hortifruti', 'Carnes e proteinas', 'Mercearia'
    ) AS categoria,
    ELT(1 + MOD(seq.n * 7, 5), 'kg', 'unidade', 'litro', 'pacote', 'caixa') AS unidade,
    ROUND(2.50 + MOD(seq.n * 137, 12500) / 100, 2) AS preco
FROM (
    SELECT unidade.n + dezena.n * 10 + centena.n * 100 + 1 AS n
    FROM
        (SELECT 0 AS n UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4
         UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) AS unidade
    CROSS JOIN
        (SELECT 0 AS n UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4
         UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) AS dezena
    CROSS JOIN
        (SELECT 0 AS n UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4) AS centena
) AS seq
WHERE @executar_carga_demo = 1;

COMMIT;

SELECT
    @executar_carga_demo AS carga_executada,
    (SELECT COUNT(*) FROM animais) AS total_animais,
    (SELECT COUNT(*) FROM alimentos) AS total_alimentos;
