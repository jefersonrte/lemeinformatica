<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/lib.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
header('X-Content-Type-Options: nosniff');

function apiResponse(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function apiMunicipalities(PDO $pdo): never
{
    $search = trim((string) ($_GET['busca'] ?? ''));
    $search = mb_substr($search, 0, 60);
    $state = strtoupper(trim((string) ($_GET['uf'] ?? '')));
    if ($state !== '' && !preg_match('/^[A-Z]{2}$/', $state)) {
        $state = '';
    }
    $limit = max(12, min(100, (int) ($_GET['limite'] ?? 48)));
    $requestedPage = max(1, (int) ($_GET['pagina'] ?? 1));

    $conditions = ['ativo = 1'];
    $parameters = [];
    if ($search !== '') {
        $conditions[] = 'nome LIKE :busca';
        $parameters[':busca'] = '%' . $search . '%';
    }
    if ($state !== '') {
        $conditions[] = 'uf_sigla = :uf';
        $parameters[':uf'] = $state;
    }
    $where = implode(' AND ', $conditions);

    $countStatement = $pdo->prepare('SELECT COUNT(*) FROM municipios_br WHERE ' . $where);
    $countStatement->execute($parameters);
    $total = (int) $countStatement->fetchColumn();
    $totalPages = max(1, (int) ceil($total / $limit));
    $page = min($requestedPage, $totalPages);
    $offset = ($page - 1) * $limit;

    $dataStatement = $pdo->prepare(
        'SELECT
            id,
            nome,
            uf_id AS ufId,
            uf_sigla AS ufSigla,
            uf_nome AS ufNome,
            regiao_id AS regiaoId,
            regiao_sigla AS regiaoSigla,
            regiao_nome AS regiaoNome,
            regiao_imediata_id AS regiaoImediataId,
            regiao_imediata_nome AS regiaoImediataNome,
            regiao_intermediaria_id AS regiaoIntermediariaId,
            regiao_intermediaria_nome AS regiaoIntermediariaNome,
            microrregiao_nome AS microrregiaoNome,
            mesorregiao_nome AS mesorregiaoNome
         FROM municipios_br
         WHERE ' . $where . '
         ORDER BY nome ASC, uf_sigla ASC
         LIMIT ' . $limit . ' OFFSET ' . $offset
    );
    $dataStatement->execute($parameters);

    apiResponse([
        'ok' => true,
        'dados' => $dataStatement->fetchAll(),
        'meta' => [
            'colecao' => 'municipios',
            'total' => $total,
            'pagina' => $page,
            'limite' => $limit,
            'totalPaginas' => $totalPages,
            'filtros' => ['busca' => $search, 'uf' => $state],
            'fonte' => 'IBGE - API de Localidades',
        ],
    ]);
}

function apiMunicipalProcurements(PDO $pdo): never
{
    $search = mb_substr(trim((string) ($_GET['busca'] ?? '')), 0, 100);
    $city = trim((string) ($_GET['cidade'] ?? ''));
    if (!in_array($city, ['', 'florianopolis', 'sao-jose'], true)) {
        $city = '';
    }
    $situation = trim((string) ($_GET['situacao'] ?? 'andamento'));
    if (!in_array($situation, ['andamento', 'encerradas', 'todas'], true)) {
        $situation = 'andamento';
    }
    $category = trim((string) ($_GET['categoria'] ?? ''));
    if (!in_array($category, ['', 'produtos', 'servicos', 'outros'], true)) {
        $category = '';
    }
    $limit = max(12, min(100, (int) ($_GET['limite'] ?? 48)));
    $requestedPage = max(1, (int) ($_GET['pagina'] ?? 1));

    $conditions = ['ativo = 1'];
    $parameters = [];
    if ($city !== '') {
        $conditions[] = 'cidade_slug = :cidade';
        $parameters[':cidade'] = $city;
    }
    if ($situation === 'andamento') {
        $conditions[] = 'em_andamento = 1';
    } elseif ($situation === 'encerradas') {
        $conditions[] = 'em_andamento = 0';
    }
    if ($category !== '') {
        $conditions[] = 'categoria = :categoria';
        $parameters[':categoria'] = $category;
    }
    if ($search !== '') {
        $conditions[] = '(
            objeto LIKE :busca_objeto OR orgao LIKE :busca_orgao OR unidade LIKE :busca_unidade
            OR numero_processo LIKE :busca_processo OR numero_edital LIKE :busca_edital
            OR modalidade LIKE :busca_modalidade OR situacao LIKE :busca_situacao
        )';
        foreach (['objeto', 'orgao', 'unidade', 'processo', 'edital', 'modalidade', 'situacao'] as $field) {
            $parameters[':busca_' . $field] = '%' . $search . '%';
        }
    }
    $where = implode(' AND ', $conditions);
    $countStatement = $pdo->prepare('SELECT COUNT(*) FROM licitacoes_municipais WHERE ' . $where);
    $countStatement->execute($parameters);
    $total = (int) $countStatement->fetchColumn();
    $totalPages = max(1, (int) ceil($total / $limit));
    $page = min($requestedPage, $totalPages);
    $offset = ($page - 1) * $limit;

    $dataStatement = $pdo->prepare(
        'SELECT
            cidade_slug AS cidadeSlug, cidade_nome AS cidade, codigo_processo AS codigoProcesso,
            codigo_modulo AS codigoModulo, codigo_edital AS codigoEdital,
            numero_processo AS numeroProcesso, numero_edital AS numeroEdital,
            orgao, unidade, objeto, modalidade, tipo_modalidade AS tipoModalidade,
            situacao, situacao_codigo AS situacaoCodigo, categoria,
            em_andamento AS emAndamento, data_inicio AS dataInicio, data_fim AS dataFim,
            valor_estimado AS valorEstimado, url_processo AS urlProcesso,
            atualizado_em AS atualizadoEm
         FROM licitacoes_municipais
         WHERE ' . $where . '
         ORDER BY em_andamento DESC,
                  CASE WHEN data_fim IS NULL THEN 1 ELSE 0 END ASC,
                  data_fim ASC, codigo_processo DESC
         LIMIT ' . $limit . ' OFFSET ' . $offset
    );
    $dataStatement->execute($parameters);
    $summary = $pdo->query(
        'SELECT
            COUNT(*) AS total,
            SUM(em_andamento = 1) AS emAndamento,
            SUM(cidade_slug = "florianopolis") AS florianopolis,
            SUM(cidade_slug = "sao-jose") AS saoJose,
            SUM(cidade_slug = "florianopolis" AND em_andamento = 1) AS florianopolisEmAndamento,
            SUM(cidade_slug = "sao-jose" AND em_andamento = 1) AS saoJoseEmAndamento,
            MAX(atualizado_em) AS atualizadoEm
         FROM licitacoes_municipais
         WHERE ativo = 1'
    )->fetch() ?: [];
    $statuses = $pdo->query(
        'SELECT situacao, COUNT(*) AS total
         FROM licitacoes_municipais
         WHERE ativo = 1
         GROUP BY situacao
         ORDER BY total DESC, situacao ASC
         LIMIT 30'
    )->fetchAll();

    apiResponse([
        'ok' => true,
        'dados' => $dataStatement->fetchAll(),
        'meta' => [
            'colecao' => 'licitacoes',
            'total' => $total,
            'pagina' => $page,
            'limite' => $limit,
            'totalPaginas' => $totalPages,
            'filtros' => [
                'busca' => $search,
                'cidade' => $city,
                'situacao' => $situation,
                'categoria' => $category,
            ],
            'totais' => [
                'todos' => (int) ($summary['total'] ?? 0),
                'emAndamento' => (int) ($summary['emAndamento'] ?? 0),
                'florianopolis' => (int) ($summary['florianopolis'] ?? 0),
                'saoJose' => (int) ($summary['saoJose'] ?? 0),
                'florianopolisEmAndamento' => (int) ($summary['florianopolisEmAndamento'] ?? 0),
                'saoJoseEmAndamento' => (int) ($summary['saoJoseEmAndamento'] ?? 0),
            ],
            'situacoes' => $statuses,
            'atualizadoEm' => $summary['atualizadoEm'] ?? null,
            'fonte' => 'Portais de Compras Eletronicas de Florianopolis e Sao Jose (Paradigma WBC)',
        ],
    ]);
}

$config = govConfig();
$providedKey = (string) ($_SERVER['HTTP_X_API_KEY'] ?? '');
if ($providedKey === '' || !hash_equals($config['api_key'], $providedKey)) {
    apiResponse([
        'ok' => false,
        'erro' => 'Nao autorizado.',
    ], 401);
}

$method = (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET');
if (!in_array($method, ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    apiResponse([
        'ok' => false,
        'erro' => 'Metodo nao permitido.',
    ], 405);
}

try {
    $pdo = govPdo();
    govEnsureSchema($pdo);

    if ($method === 'POST') {
        $action = (string) ($_GET['acao'] ?? '');
        if ($action === 'importar_licitacoes') {
            $city = trim((string) ($_GET['cidade'] ?? ''));
            $counts = govImportMunicipalProcurements($pdo, $city !== '' ? $city : null);
            apiResponse([
                'ok' => true,
                'mensagem' => $counts['total'] . ' licitacoes municipais importadas.',
                'totais' => $counts,
            ]);
        }
        if ($action !== 'importar_municipios') {
            apiResponse(['ok' => false, 'erro' => 'Acao de importacao invalida.'], 400);
        }
        $totalMunicipalities = govImportMunicipalities($pdo);
        apiResponse([
            'ok' => true,
            'mensagem' => $totalMunicipalities . ' municipios importados da API do IBGE.',
            'total' => $totalMunicipalities,
        ]);
    }

    if (($_GET['colecao'] ?? '') === 'municipios') {
        apiMunicipalities($pdo);
    }
    if (($_GET['colecao'] ?? '') === 'licitacoes') {
        apiMunicipalProcurements($pdo);
    }

    $deputies = $pdo->query(
        'SELECT
            id,
            uri,
            nome,
            sigla_partido AS siglaPartido,
            sigla_uf AS siglaUf,
            id_legislatura AS idLegislatura,
            url_foto AS urlFoto,
            email,
            atualizado_em AS atualizadoEm
         FROM deputados_sc
         WHERE ativo = 1
         ORDER BY nome ASC'
    )->fetchAll();

    $propositions = $pdo->query(
        'SELECT
            id,
            uri,
            sigla_tipo AS siglaTipo,
            numero,
            ano,
            ementa,
            data_apresentacao AS dataApresentacao
         FROM proposicoes_sc
         WHERE ativo = 1
         ORDER BY data_apresentacao DESC, id DESC'
    )->fetchAll();

    $expenses = $pdo->query(
        'SELECT
            d.chave,
            d.deputado_id AS deputadoId,
            p.nome AS deputadoNome,
            p.sigla_partido AS siglaPartido,
            d.cod_documento AS codDocumento,
            d.ano,
            d.mes,
            d.tipo_despesa AS tipoDespesa,
            d.tipo_documento AS tipoDocumento,
            d.data_documento AS dataDocumento,
            d.valor_documento AS valorDocumento,
            d.valor_liquido AS valorLiquido,
            d.valor_glosa AS valorGlosa,
            d.fornecedor,
            d.url_documento AS urlDocumento
         FROM despesas_recentes_sc d
         INNER JOIN deputados_sc p ON p.id = d.deputado_id
         WHERE p.ativo = 1
         ORDER BY d.data_documento DESC, d.chave DESC'
    )->fetchAll();

    $lastImport = $pdo->query(
        "SELECT criado_em
         FROM importacoes_gov
         WHERE status = 'sucesso'
         ORDER BY id DESC
         LIMIT 1"
    )->fetchColumn() ?: null;

    $municipalityTotal = (int) $pdo->query(
        'SELECT COUNT(*) FROM municipios_br WHERE ativo = 1'
    )->fetchColumn();
    $municipalitiesByState = $pdo->query(
        'SELECT uf_sigla AS sigla, uf_nome AS nome, COUNT(*) AS total
         FROM municipios_br
         WHERE ativo = 1
         GROUP BY uf_sigla, uf_nome
         ORDER BY uf_nome ASC'
    )->fetchAll();

    apiResponse([
        'ok' => true,
        // Mantido para compatibilidade com os primeiros consumidores da API.
        'dados' => $deputies,
        'colecoes' => [
            'deputados' => $deputies,
            'proposicoes' => $propositions,
            'despesasRecentes' => $expenses,
        ],
        'meta' => [
            'total' => count($deputies),
            'totais' => [
                'deputados' => count($deputies),
                'proposicoes' => count($propositions),
                'despesasRecentes' => count($expenses),
                'municipios' => $municipalityTotal,
            ],
            'municipiosPorUf' => $municipalitiesByState,
            'uf' => 'SC',
            'ultimaImportacao' => $lastImport,
            'fonte' => 'Camara dos Deputados - Dados Abertos',
            'observacaoDespesas' => 'Amostra dos 8 lancamentos mais recentes de cada deputado no ano corrente.',
        ],
    ]);
} catch (Throwable $exception) {
    error_log('[GOV API] ' . $exception->getMessage());
    apiResponse([
        'ok' => false,
        'erro' => 'Nao foi possivel consultar os dados no momento.',
    ], 500);
}
