ALTER TABLE orcamento_itens
    ADD COLUMN etapa VARCHAR(150) NULL AFTER orcamento_id,
    ADD COLUMN ordem INT UNSIGNED NOT NULL DEFAULT 0 AFTER etapa;

ALTER TABLE orcamentos
    ADD COLUMN bdi_percentual DECIMAL(6,2) NOT NULL DEFAULT 0.00 AFTER total_cotado,
    ADD COLUMN revisao_de INT UNSIGNED NULL AFTER bdi_percentual;
