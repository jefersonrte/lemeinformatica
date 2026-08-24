<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap/app.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$configuredKey = (string) \App\Support\Config::get('security.migration_key', '');
$receivedKey = (string) ($_SERVER['HTTP_X_MIGRATION_KEY'] ?? '');
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $configuredKey === '' || !hash_equals($configuredKey, $receivedKey)) {
    http_response_code(404);
    echo json_encode(['ok' => false]);
    exit;
}

$db = null;
try {
    $db = getDB();
    $checks = [];
    foreach (['usuarios', 'clientes', 'obras', 'obra_etapas', 'categorias', 'fornecedores', 'produtos', 'orcamentos', 'orcamento_itens', 'cotacoes', 'cotacao_itens', 'compras', 'obra_plantas', 'logs'] as $table) {
        $checks['table_' . $table] = (int) $db->query("SELECT COUNT(*) FROM {$table}")->fetchColumn() >= 0;
    }

    $db->beginTransaction();
    $suffix = bin2hex(random_bytes(5));
    $db->prepare('INSERT INTO usuarios (nome,email,senha,role,ativo,email_verificado) VALUES (?,?,?,?,1,1)')
        ->execute(['Teste automatizado', "smoke-{$suffix}@orca.invalid", password_hash($suffix, PASSWORD_DEFAULT), 'cliente']);
    $userId = (int) $db->lastInsertId();
    $db->prepare('INSERT INTO clientes (usuario_id,razao_social,email,cidade,estado) VALUES (?,?,?,?,?)')
        ->execute([$userId, 'Cliente smoke ' . $suffix, "smoke-{$suffix}@orca.invalid", 'Florianópolis', 'SC']);
    $clientId = (int) $db->lastInsertId();
    $db->prepare('INSERT INTO obras (cliente_id,nome,status,valor_total,progresso) VALUES (?,?,?,?,?)')
        ->execute([$clientId, 'Obra smoke ' . $suffix, 'em_andamento', 100000, 50]);
    $workId = (int) $db->lastInsertId();
    $db->prepare('INSERT INTO obra_etapas (obra_id,nome,ordem,status,progresso) VALUES (?,?,?,?,?)')
        ->execute([$workId, 'Etapa smoke', 1, 'em_andamento', 50]);
    $db->prepare('INSERT INTO categorias (nome,descricao) VALUES (?,?)')->execute(['Categoria ' . $suffix, 'Teste automatizado']);
    $categoryId = (int) $db->lastInsertId();
    $db->prepare('INSERT INTO fornecedores (nome,email,ativo) VALUES (?,?,1)')->execute(['Fornecedor ' . $suffix, "fornecedor-{$suffix}@orca.invalid"]);
    $supplierId = (int) $db->lastInsertId();
    $db->prepare('INSERT INTO fornecedor_categorias (fornecedor_id,categoria_id) VALUES (?,?)')->execute([$supplierId, $categoryId]);
    $db->prepare('INSERT INTO produtos (categoria_id,codigo,nome,unidade) VALUES (?,?,?,?)')->execute([$categoryId, 'SMK-' . $suffix, 'Produto smoke', 'UN']);

    $service = new \App\Domain\Orcamento\OrcamentoService($db);
    $budgetId = $service->criar($workId, 'Orçamento smoke', 'manual', 'Teste transacional', [[
        'descricao' => 'Item smoke', 'unidade' => 'UN', 'quantidade' => 2, 'preco_unitario' => 125, 'categoria_id' => $categoryId,
    ]]);
    $budgetTotal = (float) $db->query('SELECT total_estimado FROM orcamentos WHERE id=' . $budgetId)->fetchColumn();
    $checks['calculo_orcamento'] = abs($budgetTotal - 250.0) < 0.001;
    $budgetItemId = (int) $db->query('SELECT id FROM orcamento_itens WHERE orcamento_id=' . $budgetId . ' LIMIT 1')->fetchColumn();

    $db->prepare("INSERT INTO cotacoes (orcamento_id,fornecedor_id,status,canal_envio,mensagem) VALUES (?,?,'respondida','manual',?)")
        ->execute([$budgetId, $supplierId, 'Teste automatizado']);
    $quotationId = (int) $db->lastInsertId();
    $db->prepare('INSERT INTO cotacao_itens (cotacao_id,orcamento_item_id,descricao,unidade,quantidade,preco_unitario) VALUES (?,?,?,?,?,?)')
        ->execute([$quotationId, $budgetItemId, 'Item smoke', 'UN', 2, 110]);
    $db->prepare("INSERT INTO compras (obra_id,cotacao_id,fornecedor_id,status,valor_total) VALUES (?,?,?,'confirmado',?)")
        ->execute([$workId, $quotationId, $supplierId, 220]);
    $db->prepare('INSERT INTO logs (usuario_id,acao,tabela,registro_id,detalhe,ip) VALUES (?,?,?,?,?,?)')
        ->execute([$userId, 'smoke_test', 'obras', $workId, 'Teste de integração', '127.0.0.1']);

    $checks['crud_relacional'] = (int) $db->query('SELECT COUNT(*) FROM compras WHERE obra_id=' . $workId)->fetchColumn() === 1;
    $checks['dashboard_financeiro'] = (float) $db->query('SELECT COALESCE(SUM(valor_total),0) FROM compras WHERE obra_id=' . $workId)->fetchColumn() === 220.0;
    $checks['plantas_demo'] = (int) $db->query('SELECT COUNT(*) FROM obra_plantas')->fetchColumn() >= 4;
    $svgSeguro = \App\Domain\Obra\SvgSanitizer::sanitize(
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><path d="M0 0h10v10z"/></svg>'
    );
    $checks['svg_seguro'] = str_contains($svgSeguro, '<svg') && str_contains($svgSeguro, '<path');
    try {
        \App\Domain\Obra\SvgSanitizer::sanitize(
            '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'
        );
        $checks['svg_malicioso_bloqueado'] = false;
    } catch (InvalidArgumentException) {
        $checks['svg_malicioso_bloqueado'] = true;
    }
    $db->rollBack();

    $failed = array_keys(array_filter($checks, static fn (bool $ok): bool => !$ok));
    http_response_code($failed === [] ? 200 : 500);
    echo json_encode(['ok' => $failed === [], 'version' => APP_VERSION, 'checks' => count($checks), 'failed' => $failed], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    if ($db instanceof PDO && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log('[smoke] ' . $exception);
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Falha no teste de integração.']);
}
