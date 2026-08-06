<?php
declare(strict_types=1);

const GOV_CAMARA_API = 'https://dadosabertos.camara.leg.br/api/v2';
const GOV_IBGE_MUNICIPIOS_API = 'https://servicodados.ibge.gov.br/api/v1/localidades/municipios?orderBy=nome';
const GOV_PNCP_PROPOSTAS_API = 'https://pncp.gov.br/api/consulta/v1/contratacoes/proposta';

function govConfig(): array
{
    static $config = null;
    if ($config === null) {
        $config = require __DIR__ . '/private/config.php';
    }
    return $config;
}

function govPdo(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $db = govConfig()['db'];
    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=%s',
        $db['host'],
        $db['name'],
        $db['charset']
    );

    $pdo = new PDO($dsn, $db['user'], $db['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}

function govEnsureSchema(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS deputados_sc (
            id INT UNSIGNED NOT NULL,
            uri VARCHAR(255) NOT NULL,
            nome VARCHAR(160) NOT NULL,
            sigla_partido VARCHAR(40) NOT NULL,
            uri_partido VARCHAR(255) NULL,
            sigla_uf CHAR(2) NOT NULL DEFAULT "SC",
            id_legislatura INT UNSIGNED NULL,
            url_foto VARCHAR(500) NULL,
            email VARCHAR(190) NULL,
            ativo TINYINT(1) NOT NULL DEFAULT 1,
            atualizado_em DATETIME NOT NULL,
            importado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_deputados_sc_ativo_nome (ativo, nome),
            KEY idx_deputados_sc_partido (sigla_partido)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS proposicoes_sc (
            id BIGINT UNSIGNED NOT NULL,
            uri VARCHAR(255) NOT NULL,
            sigla_tipo VARCHAR(24) NOT NULL,
            numero INT UNSIGNED NOT NULL DEFAULT 0,
            ano SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            ementa TEXT NOT NULL,
            data_apresentacao DATETIME NULL,
            ativo TINYINT(1) NOT NULL DEFAULT 1,
            atualizado_em DATETIME NOT NULL,
            importado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_proposicoes_sc_ativo_data (ativo, data_apresentacao),
            KEY idx_proposicoes_sc_tipo (sigla_tipo)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS despesas_recentes_sc (
            chave CHAR(64) NOT NULL,
            deputado_id INT UNSIGNED NOT NULL,
            cod_documento VARCHAR(60) NULL,
            ano SMALLINT UNSIGNED NOT NULL,
            mes TINYINT UNSIGNED NOT NULL,
            tipo_despesa VARCHAR(220) NOT NULL,
            tipo_documento VARCHAR(100) NULL,
            data_documento DATETIME NULL,
            valor_documento DECIMAL(14,2) NOT NULL DEFAULT 0,
            valor_liquido DECIMAL(14,2) NOT NULL DEFAULT 0,
            valor_glosa DECIMAL(14,2) NOT NULL DEFAULT 0,
            fornecedor VARCHAR(255) NOT NULL,
            fornecedor_documento VARCHAR(24) NULL,
            url_documento VARCHAR(500) NULL,
            atualizado_em DATETIME NOT NULL,
            importado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (chave),
            KEY idx_despesas_sc_data (data_documento),
            KEY idx_despesas_sc_deputado (deputado_id, data_documento)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS municipios_br (
            id INT UNSIGNED NOT NULL,
            nome VARCHAR(160) NOT NULL,
            uf_id SMALLINT UNSIGNED NOT NULL,
            uf_sigla CHAR(2) NOT NULL,
            uf_nome VARCHAR(80) NOT NULL,
            regiao_id TINYINT UNSIGNED NOT NULL,
            regiao_sigla VARCHAR(4) NOT NULL,
            regiao_nome VARCHAR(40) NOT NULL,
            regiao_imediata_id INT UNSIGNED NULL,
            regiao_imediata_nome VARCHAR(160) NULL,
            regiao_intermediaria_id INT UNSIGNED NULL,
            regiao_intermediaria_nome VARCHAR(160) NULL,
            microrregiao_nome VARCHAR(160) NULL,
            mesorregiao_nome VARCHAR(160) NULL,
            ativo TINYINT(1) NOT NULL DEFAULT 1,
            atualizado_em DATETIME NOT NULL,
            importado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_municipios_br_nome (nome),
            KEY idx_municipios_br_uf_nome (uf_sigla, nome),
            KEY idx_municipios_br_regiao (regiao_nome, nome),
            KEY idx_municipios_br_ativo (ativo)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS importacoes_gov (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            status VARCHAR(20) NOT NULL,
            total_registros INT UNSIGNED NOT NULL DEFAULT 0,
            mensagem VARCHAR(500) NULL,
            criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_importacoes_gov_criado (criado_em)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS licitacoes_municipais (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            cidade_slug VARCHAR(30) NOT NULL,
            cidade_nome VARCHAR(100) NOT NULL,
            codigo_processo BIGINT UNSIGNED NOT NULL,
            codigo_modulo INT UNSIGNED NOT NULL DEFAULT 0,
            codigo_edital BIGINT UNSIGNED NULL,
            numero_processo VARCHAR(140) NULL,
            numero_edital VARCHAR(140) NULL,
            orgao VARCHAR(250) NULL,
            unidade VARCHAR(250) NULL,
            objeto TEXT NOT NULL,
            modalidade VARCHAR(180) NULL,
            tipo_modalidade VARCHAR(180) NULL,
            situacao VARCHAR(180) NOT NULL,
            situacao_codigo INT NULL,
            situacao_icone VARCHAR(140) NULL,
            categoria VARCHAR(20) NOT NULL DEFAULT "outros",
            em_andamento TINYINT(1) NOT NULL DEFAULT 0,
            data_inicio DATETIME NULL,
            data_fim DATETIME NULL,
            valor_estimado DECIMAL(18,2) NOT NULL DEFAULT 0,
            url_processo VARCHAR(600) NOT NULL,
            dados_json LONGTEXT NULL,
            ativo TINYINT(1) NOT NULL DEFAULT 1,
            atualizado_em DATETIME NOT NULL,
            importado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_licitacao_cidade_processo (cidade_slug, codigo_processo),
            KEY idx_licitacao_andamento_cidade (em_andamento, cidade_slug),
            KEY idx_licitacao_categoria (categoria),
            KEY idx_licitacao_data_fim (data_fim),
            FULLTEXT KEY ftx_licitacao_busca (objeto, orgao, unidade)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
}

function govApiUrl(string $path, array $query = []): string
{
    $url = GOV_CAMARA_API . '/' . ltrim($path, '/');
    return $query ? $url . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986) : $url;
}

function govFetchJson(string $url, string $context): array
{
    if (!function_exists('curl_init')) {
        throw new RuntimeException('A extensao cURL nao esta disponivel no servidor.');
    }

    $curl = curl_init($url);
    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'User-Agent: LemeGovSC/2.0 (+https://lemeinformatica.com.br/gov/)',
        ],
    ];
    if (defined('CURLOPT_PROTOCOLS') && defined('CURLPROTO_HTTPS')) {
        $options[CURLOPT_PROTOCOLS] = CURLPROTO_HTTPS;
    }
    curl_setopt_array($curl, $options);

    $body = curl_exec($curl);
    $error = curl_error($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if ($body === false || $error !== '') {
        throw new RuntimeException('Falha ao consultar ' . $context . ': ' . $error);
    }
    if ($status === 204) {
        return [];
    }
    if ($status !== 200) {
        throw new RuntimeException($context . ' respondeu com HTTP ' . $status . '.');
    }

    try {
        $payload = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $exception) {
        throw new RuntimeException('A resposta de ' . $context . ' nao e um JSON valido.', 0, $exception);
    }

    if (!is_array($payload)) {
        throw new RuntimeException('A resposta de ' . $context . ' possui formato inesperado.');
    }
    return $payload;
}

function govFetchRecords(string $url, string $context, bool $required = false): array
{
    $payload = govFetchJson($url, $context);
    $records = $payload['dados'] ?? null;
    if (!is_array($records)) {
        throw new RuntimeException($context . ' nao retornou uma lista de dados.');
    }
    if ($required && count($records) === 0) {
        throw new RuntimeException($context . ' nao retornou registros.');
    }
    return $records;
}

function govProcurementCategory(string $object): string
{
    $normalized = mb_strtolower($object, 'UTF-8');
    $serviceTerms = [
        'contratacao', 'contratação', 'prestacao', 'prestação', 'servico', 'serviço',
        'manutencao', 'manutenção', 'locacao', 'locação', 'consultoria', 'obra',
        'engenharia', 'reforma', 'limpeza', 'transporte', 'seguro', 'exame', 'curso',
    ];
    $productTerms = [
        'aquisicao', 'aquisição', 'compra', 'fornecimento', 'material', 'equipamento',
        'medicamento', 'genero alimenticio', 'gênero alimentício', 'veiculo', 'veículo',
        'mobiliario', 'mobiliário', 'ferramenta', 'insumo',
    ];

    foreach ($serviceTerms as $term) {
        if (str_contains($normalized, $term)) {
            return 'servicos';
        }
    }
    foreach ($productTerms as $term) {
        if (str_contains($normalized, $term)) {
            return 'produtos';
        }
    }
    return 'outros';
}

function govOpenProcurements(): array
{
    date_default_timezone_set('America/Sao_Paulo');
    $cities = [
        'florianopolis' => [
            'nome' => 'Florianópolis',
            'codigoIbge' => '4205407',
            'portal' => 'https://wbc.pmf.sc.gov.br/portal/Mural.aspx?nNmTela=E',
        ],
        'sao-jose' => [
            'nome' => 'São José',
            'codigoIbge' => '4216602',
            'portal' => 'https://saojose.atende.net/autoatendimento/servicos/consulta-de-licitacoes/detalhar/1',
        ],
    ];
    $today = date('Ymd');
    $now = time();
    $records = [];
    $warnings = [];

    foreach ($cities as $slug => $city) {
        $url = GOV_PNCP_PROPOSTAS_API . '?' . http_build_query([
            'dataFinal' => $today,
            'codigoMunicipioIbge' => $city['codigoIbge'],
            'pagina' => 1,
            'tamanhoPagina' => 50,
        ], '', '&', PHP_QUERY_RFC3986);

        try {
            $payload = govFetchJson($url, 'o PNCP para ' . $city['nome']);
            $items = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        } catch (Throwable $exception) {
            $warnings[] = 'A consulta do PNCP para ' . $city['nome'] . ' ficou indisponível.';
            error_log('[GOV PNCP] ' . $exception->getMessage());
            continue;
        }

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $closing = trim((string) ($item['dataEncerramentoProposta'] ?? ''));
            $closingTimestamp = $closing !== '' ? strtotime($closing) : false;
            if ($closingTimestamp !== false && $closingTimestamp < $now) {
                continue;
            }

            $organization = is_array($item['orgaoEntidade'] ?? null) ? $item['orgaoEntidade'] : [];
            $unit = is_array($item['unidadeOrgao'] ?? null) ? $item['unidadeOrgao'] : [];
            $cnpj = preg_replace('/\D+/', '', (string) ($organization['cnpj'] ?? ''));
            $year = (int) ($item['anoCompra'] ?? 0);
            $sequence = (int) ($item['sequencialCompra'] ?? 0);
            $pncpUrl = $cnpj !== '' && $year > 0 && $sequence > 0
                ? sprintf('https://pncp.gov.br/app/editais/%s/%d/%d', $cnpj, $year, $sequence)
                : 'https://pncp.gov.br/app/editais';
            $object = trim((string) ($item['objetoCompra'] ?? 'Objeto não informado.'));
            $control = trim((string) ($item['numeroControlePNCP'] ?? ''));

            $records[] = [
                'id' => $control !== '' ? $control : hash('sha256', $slug . '|' . $pncpUrl . '|' . $object),
                'cidade' => $city['nome'],
                'cidadeSlug' => $slug,
                'codigoIbge' => $city['codigoIbge'],
                'categoria' => govProcurementCategory($object),
                'numero' => trim((string) ($item['numeroCompra'] ?? '')),
                'processo' => trim((string) ($item['processo'] ?? '')),
                'modalidade' => trim((string) ($item['modalidadeNome'] ?? 'Não informada')),
                'situacao' => trim((string) ($item['situacaoCompraNome'] ?? 'Recebendo propostas')),
                'objeto' => $object,
                'orgao' => trim((string) ($organization['razaoSocial'] ?? 'Não informado')),
                'unidade' => trim((string) ($unit['nomeUnidade'] ?? '')),
                'valorEstimado' => (float) ($item['valorTotalEstimado'] ?? 0),
                'abertura' => $item['dataAberturaProposta'] ?? null,
                'encerramento' => $closing !== '' ? $closing : null,
                'publicadoEm' => $item['dataPublicacaoPncp'] ?? null,
                'urlPncp' => $pncpUrl,
                'urlOrigem' => filter_var($item['linkSistemaOrigem'] ?? null, FILTER_VALIDATE_URL) ?: null,
                'portalMunicipal' => $city['portal'],
            ];
        }
    }

    usort($records, static function (array $left, array $right): int {
        $leftDate = strtotime((string) ($left['encerramento'] ?? '')) ?: PHP_INT_MAX;
        $rightDate = strtotime((string) ($right['encerramento'] ?? '')) ?: PHP_INT_MAX;
        return $leftDate <=> $rightDate;
    });

    $byCity = ['florianopolis' => 0, 'sao-jose' => 0];
    foreach ($records as $record) {
        $byCity[$record['cidadeSlug']]++;
    }

    return [
        'dados' => $records,
        'cidades' => $cities,
        'totaisPorCidade' => $byCity,
        'avisos' => $warnings,
        'consultadoEm' => date(DATE_ATOM),
    ];
}

function govWbcSources(): array
{
    return [
        'florianopolis' => [
            'nome' => 'Florianópolis',
            'base' => 'https://wbc.pmf.sc.gov.br/portal',
            'portal' => 'https://wbc.pmf.sc.gov.br/portal/Mural.aspx',
            'resolve' => 'wbc.pmf.sc.gov.br:443:200.192.64.11',
        ],
        'sao-jose' => [
            'nome' => 'São José',
            'base' => 'https://egov.paradigmabs.com.br/saojose/portal',
            'portal' => 'https://egov.paradigmabs.com.br/saojose/portal/Mural.aspx',
        ],
    ];
}

function govFetchWbcProcesses(array $source, int $viewType, int $vision = 0): array
{
    if (!function_exists('curl_init')) {
        throw new RuntimeException('A extensao cURL nao esta disponivel no servidor.');
    }

    $dto = [
        'nAnoFinalizacao' => 0,
        'tmpTipoMuralProcesso' => $viewType,
        'nCdModulo' => 0,
        'nCdModalidade' => 0,
        'nCdModalidadeFase' => 0,
        'nCdTipoModalidade' => 0,
        'tmpTipoMuralVisao' => $vision,
        'nCdSituacao' => $vision,
        'nCdTipoProcesso' => 0,
        'nCdEmpresa' => 0,
        'sNrProcesso' => '',
        'nCdProcesso' => 0,
        'sDsObjeto' => '',
        'sDtPeriodoDe' => '',
        'sDtPeriodoAte' => '',
        'sOrdenarPor' => $viewType === 2 ? 'NCDPROCESSO' : 'TDTINICIAL',
        'sOrdenarPorDirecao' => 'DESC',
        'dtoPaginacao' => ['nPaginaDe' => 1, 'nPaginaAte' => 5000],
        'dtoIdioma' => ['nCdIdioma' => 1],
    ];
    $body = json_encode(['dtoProcesso' => $dto], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $url = rtrim($source['base'], '/') . '/WebService/Servicos.asmx/PesquisarProcessos';
    $referer = $source['portal'] . '?nNmTela=E';
    $curl = curl_init($url);
    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 120,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json, text/javascript, */*; q=0.01',
            'Content-Type: application/json; charset=utf-8',
            'X-Requested-With: XMLHttpRequest',
            'Referer: ' . $referer,
            'User-Agent: LemeGovLicitacoes/1.0 (+https://lemeinformatica.com.br/gov/)',
        ],
    ];
    if (defined('CURLOPT_PROTOCOLS') && defined('CURLPROTO_HTTPS')) {
        $options[CURLOPT_PROTOCOLS] = CURLPROTO_HTTPS;
    }
    if (!empty($source['resolve']) && defined('CURLOPT_RESOLVE')) {
        $options[CURLOPT_RESOLVE] = [(string) $source['resolve']];
    }
    curl_setopt_array($curl, $options);
    $response = curl_exec($curl);
    $error = curl_error($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if ($response === false || $error !== '') {
        throw new RuntimeException('Falha ao consultar o portal de ' . $source['nome'] . ': ' . $error);
    }
    if ($status !== 200) {
        throw new RuntimeException('O portal de ' . $source['nome'] . ' respondeu com HTTP ' . $status . '.');
    }
    $payload = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
    $records = $payload['d'] ?? null;
    if (!is_array($records)) {
        throw new RuntimeException('O portal de ' . $source['nome'] . ' retornou um formato inesperado.');
    }
    return $records;
}

function govFetchWbcProcessViews(array $source): array
{
    $views = [
        'editor' => [2, 0],
        'mural' => [0, 0],
        'ongoing' => [0, 999],
    ];
    if (!function_exists('curl_multi_init')) {
        return [
            'editor' => govFetchWbcProcesses($source, 2, 0),
            'mural' => govFetchWbcProcesses($source, 0, 0),
            'ongoing' => govFetchWbcProcesses($source, 0, 999),
        ];
    }

    $multi = curl_multi_init();
    $handles = [];
    try {
        foreach ($views as $name => [$viewType, $vision]) {
            $dto = [
                'nAnoFinalizacao' => 0,
                'tmpTipoMuralProcesso' => $viewType,
                'nCdModulo' => 0,
                'nCdModalidade' => 0,
                'nCdModalidadeFase' => 0,
                'nCdTipoModalidade' => 0,
                'tmpTipoMuralVisao' => $vision,
                'nCdSituacao' => $vision,
                'nCdTipoProcesso' => 0,
                'nCdEmpresa' => 0,
                'sNrProcesso' => '',
                'nCdProcesso' => 0,
                'sDsObjeto' => '',
                'sDtPeriodoDe' => '',
                'sDtPeriodoAte' => '',
                'sOrdenarPor' => $viewType === 2 ? 'NCDPROCESSO' : 'TDTINICIAL',
                'sOrdenarPorDirecao' => 'DESC',
                'dtoPaginacao' => ['nPaginaDe' => 1, 'nPaginaAte' => 5000],
                'dtoIdioma' => ['nCdIdioma' => 1],
            ];
            $body = json_encode(['dtoProcesso' => $dto], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            $url = rtrim($source['base'], '/') . '/WebService/Servicos.asmx/PesquisarProcessos';
            $curl = curl_init($url);
            $options = [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $body,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT => 120,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_HTTPHEADER => [
                    'Accept: application/json, text/javascript, */*; q=0.01',
                    'Content-Type: application/json; charset=utf-8',
                    'X-Requested-With: XMLHttpRequest',
                    'Referer: ' . $source['portal'] . '?nNmTela=E',
                    'User-Agent: LemeGovLicitacoes/1.0 (+https://lemeinformatica.com.br/gov/)',
                ],
            ];
            if (defined('CURLOPT_PROTOCOLS') && defined('CURLPROTO_HTTPS')) {
                $options[CURLOPT_PROTOCOLS] = CURLPROTO_HTTPS;
            }
            if (!empty($source['resolve']) && defined('CURLOPT_RESOLVE')) {
                $options[CURLOPT_RESOLVE] = [(string) $source['resolve']];
            }
            curl_setopt_array($curl, $options);
            curl_multi_add_handle($multi, $curl);
            $handles[$name] = $curl;
        }

        do {
            $multiStatus = curl_multi_exec($multi, $running);
            if ($multiStatus !== CURLM_OK) {
                throw new RuntimeException('Falha ao executar as consultas simultaneas de ' . $source['nome'] . '.');
            }
            if ($running > 0 && curl_multi_select($multi, 1.0) === -1) {
                usleep(10000);
            }
        } while ($running > 0);

        $result = [];
        foreach ($handles as $name => $curl) {
            $response = curl_multi_getcontent($curl);
            $error = curl_error($curl);
            $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
            if ($response === false || $error !== '') {
                throw new RuntimeException('Falha ao consultar o portal de ' . $source['nome'] . ': ' . $error);
            }
            if ($status !== 200) {
                throw new RuntimeException('O portal de ' . $source['nome'] . ' respondeu com HTTP ' . $status . '.');
            }
            $payload = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($payload['d'] ?? null)) {
                throw new RuntimeException('O portal de ' . $source['nome'] . ' retornou um formato inesperado.');
            }
            $result[$name] = $payload['d'];
        }
        return $result;
    } finally {
        foreach ($handles as $curl) {
            curl_multi_remove_handle($multi, $curl);
            curl_close($curl);
        }
        curl_multi_close($multi);
    }
}

function govWbcSqlDate($value): ?string
{
    if (!is_string($value) || $value === '') {
        return null;
    }
    if (preg_match('/\\/Date\\((-?\d+)(?:[+-]\d+)?\\)\\//', $value, $matches)) {
        return date('Y-m-d H:i:s', (int) floor(((int) $matches[1]) / 1000));
    }
    if (preg_match('/[\x00-\x1F]/', $value)) {
        return null;
    }
    foreach (['d/m/Y H:i:s', 'd/m/Y H:i', 'd/m/Y'] as $format) {
        $date = DateTimeImmutable::createFromFormat('!' . $format, trim($value));
        if ($date instanceof DateTimeImmutable) {
            return $date->format('Y-m-d H:i:s');
        }
    }
    return null;
}

function govImportMunicipalProcurements(PDO $pdo, ?string $onlyCity = null): array
{
    @set_time_limit(240);
    $snapshots = [];
    $sources = govWbcSources();
    if ($onlyCity !== null) {
        if (!isset($sources[$onlyCity])) {
            throw new InvalidArgumentException('Cidade invalida para a importacao de licitacoes.');
        }
        $sources = [$onlyCity => $sources[$onlyCity]];
    }
    foreach ($sources as $slug => $source) {
        $views = govFetchWbcProcessViews($source);
        $editorRecords = $views['editor'];
        $muralRecords = $views['mural'];
        $ongoingRecords = $views['ongoing'];

        $byProcess = [];
        foreach ($editorRecords as $record) {
            $processId = (int) ($record['nCdProcesso'] ?? 0);
            if ($processId > 0) {
                $byProcess[$processId] = $record;
            }
        }
        foreach ($muralRecords as $record) {
            $processId = (int) ($record['nCdProcesso'] ?? 0);
            if ($processId > 0) {
                $byProcess[$processId] = array_merge($byProcess[$processId] ?? [], $record);
            }
        }
        $ongoingIds = [];
        foreach ($ongoingRecords as $record) {
            $processId = (int) ($record['nCdProcesso'] ?? 0);
            if ($processId > 0) {
                $ongoingIds[$processId] = true;
                $byProcess[$processId] = array_merge($byProcess[$processId] ?? [], $record);
            }
        }
        if (count($byProcess) < 10) {
            throw new RuntimeException('Poucos registros foram retornados para ' . $source['nome'] . '.');
        }
        $snapshots[$slug] = [
            'source' => $source,
            'records' => $byProcess,
            'ongoing' => $ongoingIds,
        ];
    }

    $statement = $pdo->prepare(
        'INSERT INTO licitacoes_municipais (
            cidade_slug, cidade_nome, codigo_processo, codigo_modulo, codigo_edital,
            numero_processo, numero_edital, orgao, unidade, objeto, modalidade,
            tipo_modalidade, situacao, situacao_codigo, situacao_icone, categoria,
            em_andamento, data_inicio, data_fim, valor_estimado, url_processo,
            dados_json, ativo, atualizado_em
        ) VALUES (
            :cidade_slug, :cidade_nome, :codigo_processo, :codigo_modulo, :codigo_edital,
            :numero_processo, :numero_edital, :orgao, :unidade, :objeto, :modalidade,
            :tipo_modalidade, :situacao, :situacao_codigo, :situacao_icone, :categoria,
            :em_andamento, :data_inicio, :data_fim, :valor_estimado, :url_processo,
            :dados_json, 1, NOW()
        ) ON DUPLICATE KEY UPDATE
            cidade_nome = VALUES(cidade_nome), codigo_modulo = VALUES(codigo_modulo),
            codigo_edital = VALUES(codigo_edital), numero_processo = VALUES(numero_processo),
            numero_edital = VALUES(numero_edital), orgao = VALUES(orgao), unidade = VALUES(unidade),
            objeto = VALUES(objeto), modalidade = VALUES(modalidade),
            tipo_modalidade = VALUES(tipo_modalidade), situacao = VALUES(situacao),
            situacao_codigo = VALUES(situacao_codigo), situacao_icone = VALUES(situacao_icone),
            categoria = VALUES(categoria), em_andamento = VALUES(em_andamento),
            data_inicio = VALUES(data_inicio), data_fim = VALUES(data_fim),
            valor_estimado = VALUES(valor_estimado), url_processo = VALUES(url_processo),
            dados_json = VALUES(dados_json), ativo = 1, atualizado_em = NOW()'
    );

    $counts = ['total' => 0, 'emAndamento' => 0, 'florianopolis' => 0, 'sao-jose' => 0];
    try {
        $pdo->beginTransaction();
        if ($onlyCity !== null) {
            $deactivate = $pdo->prepare('UPDATE licitacoes_municipais SET ativo = 0 WHERE cidade_slug = :cidade');
            $deactivate->execute([':cidade' => $onlyCity]);
        } else {
            $pdo->exec('UPDATE licitacoes_municipais SET ativo = 0');
        }
        foreach ($snapshots as $slug => $snapshot) {
            $source = $snapshot['source'];
            foreach ($snapshot['records'] as $processId => $record) {
                $object = trim((string) ($record['sDsObjeto'] ?? ''));
                if ($object === '') {
                    $object = 'Objeto não informado pelo portal de origem.';
                }
                $status = trim((string) ($record['sDsSituacao'] ?? ''));
                if ($status === '' || preg_match('/[\x00-\x1F]/', $status)) {
                    $status = trim((string) ($record['sDsImagem'] ?? 'Situação não informada'));
                }
                $module = max(0, (int) ($record['nCdModulo'] ?? 0));
                $ongoing = isset($snapshot['ongoing'][$processId]) ? 1 : 0;
                $processUrl = $source['portal'] . '?' . http_build_query([
                    'nNmTela' => 'E',
                    'nCdProcesso' => $processId,
                    'nCdModulo' => $module,
                ], '', '&', PHP_QUERY_RFC3986);
                $statement->execute([
                    ':cidade_slug' => $slug,
                    ':cidade_nome' => $source['nome'],
                    ':codigo_processo' => $processId,
                    ':codigo_modulo' => $module,
                    ':codigo_edital' => isset($record['nCdEdital']) ? max(0, (int) $record['nCdEdital']) : null,
                    ':numero_processo' => ($record['sNrProcessoDisplay'] ?? null) ?: null,
                    ':numero_edital' => ($record['sNrEdital'] ?? null) ?: null,
                    ':orgao' => ($record['sNmEntidade'] ?? null) ?: (($record['sNmEmpresa'] ?? null) ?: $source['nome']),
                    ':unidade' => ($record['sNmApelido'] ?? null) ?: null,
                    ':objeto' => $object,
                    ':modalidade' => ($record['sNmModalidade'] ?? null) ?: null,
                    ':tipo_modalidade' => ($record['sNmModalidadeTipo'] ?? null) ?: null,
                    ':situacao' => $status,
                    ':situacao_codigo' => isset($record['nCdSituacao']) ? (int) $record['nCdSituacao'] : null,
                    ':situacao_icone' => ($record['sDsImagem'] ?? null) ?: null,
                    ':categoria' => govProcurementCategory($object),
                    ':em_andamento' => $ongoing,
                    ':data_inicio' => govWbcSqlDate($record['tDtInicial'] ?? ($record['sDtInicialFormatada'] ?? null)),
                    ':data_fim' => govWbcSqlDate($record['tDtFinal'] ?? ($record['sDtFinalFormatada'] ?? null)),
                    ':valor_estimado' => (float) ($record['dVlEstimado'] ?? 0),
                    ':url_processo' => $processUrl,
                    ':dados_json' => json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ]);
                $counts['total']++;
                $counts[$slug]++;
                $counts['emAndamento'] += $ongoing;
            }
        }
        govLogImport(
            $pdo,
            'sucesso',
            $counts['total'],
            sprintf(
                '%d licitacoes municipais importadas (%d em andamento; %d Florianopolis; %d Sao Jose).',
                $counts['total'],
                $counts['emAndamento'],
                $counts['florianopolis'],
                $counts['sao-jose']
            )
        );
        $pdo->commit();
        return $counts;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        try {
            govLogImport($pdo, 'erro', 0, $exception->getMessage());
        } catch (Throwable $ignored) {
        }
        throw $exception;
    }
}

function govFetchSource(): array
{
    return govFetchRecords(govConfig()['source_url'], 'a lista de deputados da Camara', true);
}

function govFetchPropositions(): array
{
    $end = new DateTimeImmutable('today');
    $start = $end->sub(new DateInterval('P45D'));
    return govFetchRecords(
        govApiUrl('proposicoes', [
            'siglaUfAutor' => 'SC',
            'dataInicio' => $start->format('Y-m-d'),
            'dataFim' => $end->format('Y-m-d'),
            'ordem' => 'DESC',
            'ordenarPor' => 'id',
            'itens' => 30,
        ]),
        'as proposicoes recentes de Santa Catarina'
    );
}

function govFetchRecentExpenses(array $deputies): array
{
    $expenses = [];
    $year = (int) date('Y');

    foreach ($deputies as $deputy) {
        $deputyId = (int) ($deputy['id'] ?? 0);
        if ($deputyId <= 0) {
            continue;
        }

        $records = govFetchRecords(
            govApiUrl('deputados/' . $deputyId . '/despesas', [
                'ano' => $year,
                'ordem' => 'DESC',
                'ordenarPor' => 'dataDocumento',
                'itens' => 8,
            ]),
            'as despesas recentes do deputado ' . $deputyId
        );

        foreach ($records as $record) {
            $record['_deputadoId'] = $deputyId;
            $expenses[] = $record;
        }
    }
    return $expenses;
}

function govFetchMunicipalities(): array
{
    $records = govFetchJson(GOV_IBGE_MUNICIPIOS_API, 'a API de Localidades do IBGE');
    if (!array_is_list($records) || count($records) < 1000) {
        throw new RuntimeException('A API do IBGE nao retornou a colecao nacional esperada.');
    }
    return $records;
}

function govSqlDate($value): ?string
{
    $text = trim((string) $value);
    if ($text === '') {
        return null;
    }
    try {
        return (new DateTimeImmutable($text))->format('Y-m-d H:i:s');
    } catch (Throwable $ignored) {
        return null;
    }
}

function govLogImport(PDO $pdo, string $status, int $total, string $message): void
{
    $statement = $pdo->prepare(
        'INSERT INTO importacoes_gov (status, total_registros, mensagem)
         VALUES (:status, :total, :mensagem)'
    );
    $statement->execute([
        ':status' => $status,
        ':total' => $total,
        ':mensagem' => mb_substr($message, 0, 500),
    ]);
}

function govStoreMunicipalities(PDO $pdo, array $records): int
{
    $statement = $pdo->prepare(
        'INSERT INTO municipios_br (
            id, nome, uf_id, uf_sigla, uf_nome, regiao_id, regiao_sigla, regiao_nome,
            regiao_imediata_id, regiao_imediata_nome, regiao_intermediaria_id,
            regiao_intermediaria_nome, microrregiao_nome, mesorregiao_nome, ativo, atualizado_em
        ) VALUES (
            :id, :nome, :uf_id, :uf_sigla, :uf_nome, :regiao_id, :regiao_sigla, :regiao_nome,
            :regiao_imediata_id, :regiao_imediata_nome, :regiao_intermediaria_id,
            :regiao_intermediaria_nome, :microrregiao_nome, :mesorregiao_nome, 1, NOW()
        ) ON DUPLICATE KEY UPDATE
            nome = VALUES(nome), uf_id = VALUES(uf_id), uf_sigla = VALUES(uf_sigla),
            uf_nome = VALUES(uf_nome), regiao_id = VALUES(regiao_id),
            regiao_sigla = VALUES(regiao_sigla), regiao_nome = VALUES(regiao_nome),
            regiao_imediata_id = VALUES(regiao_imediata_id),
            regiao_imediata_nome = VALUES(regiao_imediata_nome),
            regiao_intermediaria_id = VALUES(regiao_intermediaria_id),
            regiao_intermediaria_nome = VALUES(regiao_intermediaria_nome),
            microrregiao_nome = VALUES(microrregiao_nome),
            mesorregiao_nome = VALUES(mesorregiao_nome), ativo = 1, atualizado_em = NOW()'
    );

    $pdo->exec('UPDATE municipios_br SET ativo = 0');
    $imported = 0;
    foreach ($records as $record) {
        $id = (int) ($record['id'] ?? 0);
        $name = trim((string) ($record['nome'] ?? ''));
        $microregion = is_array($record['microrregiao'] ?? null) ? $record['microrregiao'] : [];
        $mesoregion = is_array($microregion['mesorregiao'] ?? null) ? $microregion['mesorregiao'] : [];
        $immediate = is_array($record['regiao-imediata'] ?? null) ? $record['regiao-imediata'] : [];
        $intermediate = is_array($immediate['regiao-intermediaria'] ?? null) ? $immediate['regiao-intermediaria'] : [];
        $state = is_array($mesoregion['UF'] ?? null)
            ? $mesoregion['UF']
            : (is_array($intermediate['UF'] ?? null) ? $intermediate['UF'] : []);
        $region = is_array($state['regiao'] ?? null) ? $state['regiao'] : [];
        $stateCode = strtoupper(trim((string) ($state['sigla'] ?? '')));

        if ($id <= 0 || $name === '' || !preg_match('/^[A-Z]{2}$/', $stateCode)) {
            continue;
        }

        $statement->execute([
            ':id' => $id,
            ':nome' => $name,
            ':uf_id' => max(0, (int) ($state['id'] ?? 0)),
            ':uf_sigla' => $stateCode,
            ':uf_nome' => trim((string) ($state['nome'] ?? $stateCode)),
            ':regiao_id' => max(0, (int) ($region['id'] ?? 0)),
            ':regiao_sigla' => trim((string) ($region['sigla'] ?? '')),
            ':regiao_nome' => trim((string) ($region['nome'] ?? 'Nao informada')),
            ':regiao_imediata_id' => isset($immediate['id']) ? (int) $immediate['id'] : null,
            ':regiao_imediata_nome' => ($immediate['nome'] ?? null) ?: null,
            ':regiao_intermediaria_id' => isset($intermediate['id']) ? (int) $intermediate['id'] : null,
            ':regiao_intermediaria_nome' => ($intermediate['nome'] ?? null) ?: null,
            ':microrregiao_nome' => ($microregion['nome'] ?? null) ?: null,
            ':mesorregiao_nome' => ($mesoregion['nome'] ?? null) ?: null,
        ]);
        $imported++;
    }

    if ($imported < 1000) {
        throw new RuntimeException('Menos de mil municipios validos foram encontrados na resposta do IBGE.');
    }
    return $imported;
}

function govImportMunicipalities(PDO $pdo): int
{
    @set_time_limit(120);
    $records = govFetchMunicipalities();
    try {
        $pdo->beginTransaction();
        $imported = govStoreMunicipalities($pdo, $records);
        govLogImport($pdo, 'sucesso', $imported, $imported . ' municipios do IBGE importados.');
        $pdo->commit();
        return $imported;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        try {
            govLogImport($pdo, 'erro', 0, $exception->getMessage());
        } catch (Throwable $ignored) {
        }
        throw $exception;
    }
}

function govImportPublicData(PDO $pdo): array
{
    @set_time_limit(120);
    $deputies = govFetchSource();
    $propositions = govFetchPropositions();
    $expenses = govFetchRecentExpenses($deputies);
    $municipalities = govFetchMunicipalities();

    $deputyStatement = $pdo->prepare(
        'INSERT INTO deputados_sc (
            id, uri, nome, sigla_partido, uri_partido, sigla_uf,
            id_legislatura, url_foto, email, ativo, atualizado_em
        ) VALUES (
            :id, :uri, :nome, :sigla_partido, :uri_partido, :sigla_uf,
            :id_legislatura, :url_foto, :email, 1, NOW()
        ) ON DUPLICATE KEY UPDATE
            uri = VALUES(uri), nome = VALUES(nome), sigla_partido = VALUES(sigla_partido),
            uri_partido = VALUES(uri_partido), sigla_uf = VALUES(sigla_uf),
            id_legislatura = VALUES(id_legislatura), url_foto = VALUES(url_foto),
            email = VALUES(email), ativo = 1, atualizado_em = NOW()'
    );
    $propositionStatement = $pdo->prepare(
        'INSERT INTO proposicoes_sc (
            id, uri, sigla_tipo, numero, ano, ementa, data_apresentacao, ativo, atualizado_em
        ) VALUES (
            :id, :uri, :sigla_tipo, :numero, :ano, :ementa, :data_apresentacao, 1, NOW()
        ) ON DUPLICATE KEY UPDATE
            uri = VALUES(uri), sigla_tipo = VALUES(sigla_tipo), numero = VALUES(numero),
            ano = VALUES(ano), ementa = VALUES(ementa), data_apresentacao = VALUES(data_apresentacao),
            ativo = 1, atualizado_em = NOW()'
    );
    $expenseStatement = $pdo->prepare(
        'INSERT INTO despesas_recentes_sc (
            chave, deputado_id, cod_documento, ano, mes, tipo_despesa, tipo_documento,
            data_documento, valor_documento, valor_liquido, valor_glosa, fornecedor,
            fornecedor_documento, url_documento, atualizado_em
        ) VALUES (
            :chave, :deputado_id, :cod_documento, :ano, :mes, :tipo_despesa, :tipo_documento,
            :data_documento, :valor_documento, :valor_liquido, :valor_glosa, :fornecedor,
            :fornecedor_documento, :url_documento, NOW()
        ) ON DUPLICATE KEY UPDATE atualizado_em = NOW()'
    );

    $counts = ['deputados' => 0, 'proposicoes' => 0, 'despesas' => 0, 'municipios' => 0];
    try {
        $pdo->beginTransaction();
        $pdo->exec('UPDATE deputados_sc SET ativo = 0');
        $pdo->exec('UPDATE proposicoes_sc SET ativo = 0');
        $pdo->exec('DELETE FROM despesas_recentes_sc');

        foreach ($deputies as $record) {
            $id = (int) ($record['id'] ?? 0);
            $name = trim((string) ($record['nome'] ?? ''));
            $state = strtoupper(trim((string) ($record['siglaUf'] ?? '')));
            if ($id <= 0 || $name === '' || $state !== 'SC') {
                continue;
            }
            $deputyStatement->execute([
                ':id' => $id,
                ':uri' => (string) ($record['uri'] ?? ''),
                ':nome' => $name,
                ':sigla_partido' => trim((string) ($record['siglaPartido'] ?? 'SEM PARTIDO')),
                ':uri_partido' => ($record['uriPartido'] ?? null) ?: null,
                ':sigla_uf' => $state,
                ':id_legislatura' => isset($record['idLegislatura']) ? (int) $record['idLegislatura'] : null,
                ':url_foto' => ($record['urlFoto'] ?? null) ?: null,
                ':email' => ($record['email'] ?? null) ?: null,
            ]);
            $counts['deputados']++;
        }

        if ($counts['deputados'] === 0) {
            throw new RuntimeException('Nenhum deputado valido foi encontrado na resposta.');
        }

        foreach ($propositions as $record) {
            $id = (int) ($record['id'] ?? 0);
            $summary = trim((string) ($record['ementa'] ?? ''));
            if ($id <= 0 || $summary === '') {
                continue;
            }
            $propositionStatement->execute([
                ':id' => $id,
                ':uri' => (string) ($record['uri'] ?? ''),
                ':sigla_tipo' => trim((string) ($record['siglaTipo'] ?? 'OUTRA')),
                ':numero' => max(0, (int) ($record['numero'] ?? 0)),
                ':ano' => max(0, (int) ($record['ano'] ?? 0)),
                ':ementa' => $summary,
                ':data_apresentacao' => govSqlDate($record['dataApresentacao'] ?? null),
            ]);
            $counts['proposicoes']++;
        }

        foreach ($expenses as $record) {
            $deputyId = (int) ($record['_deputadoId'] ?? 0);
            $documentCode = trim((string) ($record['codDocumento'] ?? ''));
            $supplier = trim((string) ($record['nomeFornecedor'] ?? 'Fornecedor nao informado'));
            $date = govSqlDate($record['dataDocumento'] ?? null);
            if ($deputyId <= 0) {
                continue;
            }
            $key = hash('sha256', implode('|', [
                $deputyId,
                $documentCode,
                (string) ($record['parcela'] ?? ''),
                (string) ($record['valorLiquido'] ?? ''),
                $supplier,
                $date ?? '',
            ]));
            $expenseStatement->execute([
                ':chave' => $key,
                ':deputado_id' => $deputyId,
                ':cod_documento' => $documentCode !== '' ? $documentCode : null,
                ':ano' => max(0, (int) ($record['ano'] ?? date('Y'))),
                ':mes' => max(0, min(12, (int) ($record['mes'] ?? 0))),
                ':tipo_despesa' => trim((string) ($record['tipoDespesa'] ?? 'Nao informado')),
                ':tipo_documento' => ($record['tipoDocumento'] ?? null) ?: null,
                ':data_documento' => $date,
                ':valor_documento' => (float) ($record['valorDocumento'] ?? 0),
                ':valor_liquido' => (float) ($record['valorLiquido'] ?? 0),
                ':valor_glosa' => (float) ($record['valorGlosa'] ?? 0),
                ':fornecedor' => $supplier,
                ':fornecedor_documento' => ($record['cnpjCpfFornecedor'] ?? null) ?: null,
                ':url_documento' => ($record['urlDocumento'] ?? null) ?: null,
            ]);
            $counts['despesas']++;
        }

        $counts['municipios'] = govStoreMunicipalities($pdo, $municipalities);

        $total = array_sum($counts);
        $message = sprintf(
            '%d deputados, %d proposicoes, %d despesas recentes e %d municipios importados.',
            $counts['deputados'],
            $counts['proposicoes'],
            $counts['despesas'],
            $counts['municipios']
        );
        govLogImport($pdo, 'sucesso', $total, $message);
        $pdo->commit();
        return $counts;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        try {
            govLogImport($pdo, 'erro', 0, $exception->getMessage());
        } catch (Throwable $ignored) {
        }
        throw $exception;
    }
}

function govImportDeputies(PDO $pdo): int
{
    $counts = govImportPublicData($pdo);
    return $counts['deputados'];
}

function govDashboardData(PDO $pdo): array
{
    $counts = [
        'deputados' => (int) $pdo->query('SELECT COUNT(*) FROM deputados_sc WHERE ativo = 1')->fetchColumn(),
        'proposicoes' => (int) $pdo->query('SELECT COUNT(*) FROM proposicoes_sc WHERE ativo = 1')->fetchColumn(),
        'despesas' => (int) $pdo->query('SELECT COUNT(*) FROM despesas_recentes_sc')->fetchColumn(),
        'municipios' => (int) $pdo->query('SELECT COUNT(*) FROM municipios_br WHERE ativo = 1')->fetchColumn(),
        'licitacoes' => (int) $pdo->query('SELECT COUNT(*) FROM licitacoes_municipais WHERE ativo = 1')->fetchColumn(),
        'licitacoesEmAndamento' => (int) $pdo->query(
            'SELECT COUNT(*) FROM licitacoes_municipais WHERE ativo = 1 AND em_andamento = 1'
        )->fetchColumn(),
    ];
    $last = $pdo->query(
        "SELECT total_registros, mensagem, criado_em
         FROM importacoes_gov
         WHERE status = 'sucesso'
         ORDER BY id DESC
         LIMIT 1"
    )->fetch() ?: null;
    $deputies = $pdo->query(
        'SELECT id, nome, sigla_partido, url_foto, email
         FROM deputados_sc
         WHERE ativo = 1
         ORDER BY nome ASC'
    )->fetchAll();
    $propositions = $pdo->query(
        'SELECT id, sigla_tipo, numero, ano, ementa, data_apresentacao
         FROM proposicoes_sc
         WHERE ativo = 1
         ORDER BY data_apresentacao DESC, id DESC
         LIMIT 8'
    )->fetchAll();
    $expenses = $pdo->query(
        'SELECT d.chave, d.tipo_despesa, d.data_documento, d.valor_liquido,
                d.fornecedor, p.nome AS deputado_nome, p.sigla_partido
         FROM despesas_recentes_sc d
         INNER JOIN deputados_sc p ON p.id = d.deputado_id
         ORDER BY d.data_documento DESC, d.chave DESC
         LIMIT 8'
    )->fetchAll();
    $municipalities = $pdo->query(
        'SELECT id, nome, uf_sigla, uf_nome, regiao_nome, regiao_imediata_nome
         FROM municipios_br
         WHERE ativo = 1
         ORDER BY nome ASC
         LIMIT 12'
    )->fetchAll();
    $states = (int) $pdo->query(
        'SELECT COUNT(DISTINCT uf_sigla) FROM municipios_br WHERE ativo = 1'
    )->fetchColumn();

    return [
        'total' => $counts['deputados'],
        'counts' => $counts,
        'last' => $last,
        'deputies' => $deputies,
        'propositions' => $propositions,
        'expenses' => $expenses,
        'municipalities' => $municipalities,
        'states' => $states,
    ];
}
