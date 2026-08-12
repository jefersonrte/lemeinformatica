<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/lib.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
header('X-Content-Type-Options: nosniff');

function tenderApiResponse(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function tenderApiSlice(string $text, int $length): string
{
    return function_exists('mb_substr')
        ? mb_substr($text, 0, $length, 'UTF-8')
        : substr($text, 0, $length);
}

function tenderApiList(PDO $pdo): never
{
    $search = tenderApiSlice(trim((string) ($_GET['busca'] ?? '')), 100);
    $city = trim((string) ($_GET['cidade'] ?? ''));
    $sources = govProcurementSources();
    if ($city !== '' && !isset($sources[$city])) {
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
    $sector = trim((string) ($_GET['setor'] ?? ''));
    if (!in_array($sector, ['', 'ti', 'obras', 'saude', 'seguranca', 'outros'], true)) {
        $sector = '';
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
        $conditions[] = 'em_andamento = 1 AND (data_fim IS NULL OR data_fim >= NOW())';
    } elseif ($situation === 'encerradas') {
        $conditions[] = 'em_andamento = 0';
    }
    if ($category !== '') {
        $conditions[] = 'categoria = :categoria';
        $parameters[':categoria'] = $category;
    }
    if ($sector !== '') {
        $conditions[] = 'setor = :setor';
        $parameters[':setor'] = $sector;
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
            situacao, situacao_codigo AS situacaoCodigo, categoria, setor, fonte,
            em_andamento AS emAndamento, data_inicio AS dataInicio, data_fim AS dataFim,
            valor_estimado AS valorEstimado, url_processo AS urlProcesso,
            atualizado_em AS atualizadoEm
         FROM licitacoes_municipais
         WHERE ' . $where . '
         ORDER BY em_andamento DESC,
                  CASE WHEN setor = "ti" THEN 0 ELSE 1 END ASC,
                  CASE WHEN data_fim IS NULL THEN 1 ELSE 0 END ASC,
                  data_fim ASC, codigo_processo DESC
         LIMIT ' . $limit . ' OFFSET ' . $offset
    );
    $dataStatement->execute($parameters);

    $summary = $pdo->query(
        'SELECT COUNT(*) AS total,
                SUM(em_andamento = 1 AND (data_fim IS NULL OR data_fim >= NOW())) AS emAndamento,
                SUM(setor = "ti") AS ti,
                SUM(setor = "obras") AS obras,
                SUM(setor = "saude") AS saude,
                SUM(setor = "seguranca") AS seguranca,
                MAX(atualizado_em) AS atualizadoEm
         FROM licitacoes_municipais
         WHERE ativo = 1'
    )->fetch() ?: [];

    $cityRows = $pdo->query(
        'SELECT cidade_slug, cidade_nome, COUNT(*) AS total,
                SUM(em_andamento = 1 AND (data_fim IS NULL OR data_fim >= NOW())) AS emAndamento,
                SUM(setor = "ti" AND em_andamento = 1 AND (data_fim IS NULL OR data_fim >= NOW())) AS tiEmAndamento,
                MAX(atualizado_em) AS atualizadoEm
         FROM licitacoes_municipais
         WHERE ativo = 1
         GROUP BY cidade_slug, cidade_nome'
    )->fetchAll();
    $cityCounts = [];
    foreach ($cityRows as $row) {
        $cityCounts[$row['cidade_slug']] = $row;
    }

    $cities = [];
    foreach ($sources as $slug => $source) {
        $row = $cityCounts[$slug] ?? [];
        $cities[] = [
            'slug' => $slug,
            'nome' => $source['nome'],
            'codigoIbge' => $source['codigoIbge'],
            'fonte' => strtoupper($source['driver']),
            'portal' => $source['portal'],
            'total' => (int) ($row['total'] ?? 0),
            'emAndamento' => (int) ($row['emAndamento'] ?? 0),
            'tiEmAndamento' => (int) ($row['tiEmAndamento'] ?? 0),
            'atualizadoEm' => $row['atualizadoEm'] ?? null,
        ];
    }

    tenderApiResponse([
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
                'setor' => $sector,
            ],
            'totais' => [
                'todos' => (int) ($summary['total'] ?? 0),
                'emAndamento' => (int) ($summary['emAndamento'] ?? 0),
                'ti' => (int) ($summary['ti'] ?? 0),
                'obras' => (int) ($summary['obras'] ?? 0),
                'saude' => (int) ($summary['saude'] ?? 0),
                'seguranca' => (int) ($summary['seguranca'] ?? 0),
            ],
            'cidades' => $cities,
            'atualizadoEm' => $summary['atualizadoEm'] ?? null,
            'fonte' => 'Portais de licitacoes municipais e API publica do PNCP',
        ],
    ]);
}

$method = (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET');
if (!in_array($method, ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    tenderApiResponse(['ok' => false, 'erro' => 'Metodo nao permitido.'], 405);
}

if ($method === 'GET') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET');
}

$config = govConfig();
if ($method === 'POST') {
    $providedKey = (string) ($_SERVER['HTTP_X_API_KEY'] ?? '');
    if ($providedKey === '' || !hash_equals($config['api_key'], $providedKey)) {
        tenderApiResponse(['ok' => false, 'erro' => 'Nao autorizado.'], 401);
    }
}

try {
    $pdo = govPdo();
    govEnsureSchema($pdo);

    if ($method === 'GET') {
        tenderApiList($pdo);
    }

    $action = (string) ($_GET['acao'] ?? '');
    if ($action === 'importar') {
        $city = trim((string) ($_GET['cidade'] ?? ''));
        $counts = govImportMunicipalProcurements($pdo, $city !== '' ? $city : null);
        tenderApiResponse(['ok' => true, 'totais' => $counts]);
    }
    if ($action === 'sincronizar') {
        tenderApiResponse(['ok' => true, 'sincronizacao' => govAutoSyncProcurements($pdo, 6)]);
    }
    if ($action === 'reclassificar') {
        $total = govReclassifyProcurements($pdo);
        tenderApiResponse(['ok' => true, 'total' => $total]);
    }
    if ($action === 'receber-pncp') {
        $payload = json_decode((string) file_get_contents('php://input'), true);
        $city = trim((string) ($payload['cidade'] ?? ''));
        $sources = govProcurementSources();
        if (!isset($sources[$city]) || ($sources[$city]['driver'] ?? '') !== 'pncp') {
            tenderApiResponse(['ok' => false, 'erro' => 'Cidade PNCP invalida.'], 422);
        }
        $records = is_array($payload['dados'] ?? null) ? $payload['dados'] : [];
        if (count($records) > 100) {
            tenderApiResponse(['ok' => false, 'erro' => 'Lote PNCP acima do limite.'], 422);
        }
        $page = max(1, (int) ($payload['pagina'] ?? 1));
        $totalPages = max(1, min(100, (int) ($payload['totalPaginas'] ?? 1)));
        govRecordProcurementSync($pdo, $city, 'executando', 0, 'Recebendo lote PNCP automatizado.');
        $counts = govImportPncpProcurements($pdo, $city, $sources[$city], [
            'records' => $records,
            'page' => $page,
            'totalPages' => $totalPages,
        ]);
        govRecordProcurementProgress($pdo, $city, $counts);
        tenderApiResponse(['ok' => true, 'totais' => $counts]);
    }

    tenderApiResponse(['ok' => false, 'erro' => 'Acao de licitacao invalida.'], 400);
} catch (Throwable $exception) {
    $response = ['ok' => false, 'erro' => 'Falha ao consultar licitacoes.'];
    if ($method === 'POST') {
        $response['detalhe'] = $exception->getMessage();
    }
    tenderApiResponse($response, 500);
}
