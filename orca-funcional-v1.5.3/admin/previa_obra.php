<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap/app.php';

use App\Domain\Estimativa\Classificador;
use App\Domain\Estimativa\EstimativaService;
use App\Domain\Estimativa\PreviaObraService;
use App\Domain\Orcamento\OrcamentoCalculator;

requireAdmin();
$db = getDB();
$service = new PreviaObraService($db);
$pastaPrevias = rtrim(UPLOAD_DIR, '/') . '/previas';

// Limpa prévias com mais de 2 dias
foreach (glob($pastaPrevias . '/*', GLOB_ONLYDIR) ?: [] as $antiga) {
    if (filemtime($antiga) < time() - 172800) {
        array_map('unlink', glob($antiga . '/*') ?: []);
        @rmdir($antiga);
    }
}

$token = preg_replace('/[^a-f0-9]/', '', (string) ($_GET['p'] ?? $_POST['token'] ?? '')) ?? '';
$permitida = $token !== '' && isset($_SESSION['previas'][$token]);
$pasta = $permitida ? $pastaPrevias . '/' . $token : null;
$previa = $pasta && is_file($pasta . '/resultado.json') ? json_decode((string) file_get_contents($pasta . '/resultado.json'), true) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'analisar') {
        $novoToken = bin2hex(random_bytes(12));
        $destino = $pastaPrevias . '/' . $novoToken;
        if (!is_dir($destino) && !mkdir($destino, 0750, true) && !is_dir($destino)) {
            setFlash('error', 'Não foi possível preparar a pasta da prévia.');
            redirect(APP_URL . '/admin/previa_obra.php');
        }
        $entrada = [
            'area' => OrcamentoCalculator::decimal($_POST['area'] ?? 0) ?: null,
            'tipologia' => (string) ($_POST['tipologia'] ?? ''),
            'padrao' => (string) ($_POST['padrao'] ?? 'medio'),
            'descricao' => trim((string) ($_POST['descricao'] ?? '')),
        ];
        $limiteMb = max(UPLOAD_MAX_MB, 80); // plantas A0 com várias pranchas costumam passar de 20 MB
        $limite = $limiteMb * 1024 * 1024;
        // Várias pranchas podem ser enviadas juntas (planta, quadro de áreas, esquadrias em arquivos separados)
        $arquivos = ['planta' => [], 'planilha' => []];
        foreach (['planta', 'planilha'] as $campo) {
            $bruto = $_FILES[$campo] ?? null;
            if (!$bruto) {
                continue;
            }
            foreach ((array) $bruto['name'] as $i => $nome) {
                $arquivos[$campo][] = ['name' => (string) $nome, 'tmp_name' => ((array) $bruto['tmp_name'])[$i], 'error' => (int) ((array) $bruto['error'])[$i], 'size' => (int) ((array) $bruto['size'])[$i]];
            }
        }
        $extensoes = ['planta' => ['pdf', 'png', 'jpg', 'jpeg', 'webp'], 'planilha' => ['xlsx', 'xls', 'xlsm', 'ods', 'csv']];
        $nomesPlantas = [];
        foreach ($arquivos as $campo => $lista) {
            foreach (array_slice($lista, 0, $campo === 'planta' ? 12 : 1) as $posicao => $arquivo) {
                if ($arquivo['error'] === UPLOAD_ERR_NO_FILE) {
                    continue;
                }
                $ext = strtolower(pathinfo($arquivo['name'], PATHINFO_EXTENSION));
                if ($arquivo['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($arquivo['tmp_name']) || $arquivo['size'] > $limite || !in_array($ext, $extensoes[$campo], true)) {
                    setFlash('error', ($campo === 'planta' ? 'Planta' : 'Planilha') . ' inválida (' . sanitize($arquivo['name']) . '): use ' . implode(', ', $extensoes[$campo]) . ' até ' . $limiteMb . 'MB.');
                    redirect(APP_URL . '/admin/previa_obra.php');
                }
                if ($campo === 'planta') {
                    $alvo = $destino . '/planta-' . ($posicao + 1) . '.' . $ext;
                    move_uploaded_file($arquivo['tmp_name'], $alvo);
                    $entrada['plantas'][] = ['caminho' => $alvo, 'nome' => $arquivo['name']];
                    $nomesPlantas[basename($alvo)] = mb_substr(basename($arquivo['name']), 0, 200);
                } else {
                    $alvo = $destino . '/planilha.' . $ext;
                    move_uploaded_file($arquivo['tmp_name'], $alvo);
                    $entrada['planilha'] = $alvo;
                    $entrada['planilha_ext'] = $ext === 'xlsm' ? 'xlsx' : $ext;
                }
            }
        }
        if ($nomesPlantas !== []) {
            file_put_contents($destino . '/plantas.json', json_encode($nomesPlantas, JSON_UNESCAPED_UNICODE));
            $entrada['planta_nome'] = implode(', ', $nomesPlantas);
        }
        if (empty($entrada['plantas']) && empty($entrada['planilha']) && empty($entrada['area'])) {
            setFlash('error', 'Envie uma planta, uma planilha de quantitativos ou informe a área construída.');
            redirect(APP_URL . '/admin/previa_obra.php');
        }
        try {
            $resultado = $service->gerar($entrada);
        } catch (Throwable $exception) {
            error_log('[previa_obra] ' . $exception);
            setFlash('error', 'Não foi possível gerar a prévia: ' . $exception->getMessage());
            redirect(APP_URL . '/admin/previa_obra.php');
        }
        $resultado['entrada'] = array_intersect_key($entrada, array_flip(['area', 'tipologia', 'padrao', 'descricao', 'planta_nome']));
        file_put_contents($destino . '/resultado.json', json_encode($resultado, JSON_UNESCAPED_UNICODE));
        $_SESSION['previas'][$novoToken] = time();
        logAction('previa_obra', 'obras', 0, 'Prévia ' . $novoToken);
        redirect(APP_URL . '/admin/previa_obra.php?p=' . $novoToken);
    }

    if ($acao === 'gerar_orcamento' && $previa) {
        $obraId = (int) ($_POST['obra_id'] ?? 0);
        $modo = ($_POST['modo'] ?? '') === 'quantitativos' ? 'quantitativos' : 'parametrico';
        $titulo = trim((string) ($_POST['titulo'] ?? '')) ?: 'Prévia de obra — ' . date('d/m/Y');
        try {
            $itens = $service->itensOrcamento($previa, $modo);
            $obs = $modo === 'parametrico'
                ? sprintf('Gerado pela prévia de obra: %s m², %s, padrão %s, R$ %s/m² (mediana de %d obras reais atualizadas pelo INCC). Revise quantidades e preços.',
                    number_format((float) $previa['area'], 2, ',', '.'), Classificador::TIPOLOGIAS[$previa['tipologia']] ?? $previa['tipologia'],
                    EstimativaService::PADROES[$previa['padrao']] ?? $previa['padrao'], number_format((float) $previa['estimativa']['custo_m2_usado'], 2, ',', '.'),
                    count($previa['estimativa']['referencias']))
                : 'Gerado a partir da planilha de quantitativos, com preços da planilha ou sugeridos pela base de referência.';
            $orcamentoId = (new \App\Domain\Orcamento\OrcamentoService($db))->criar($obraId, $titulo, 'excel', $obs, $itens);
            if ($previa['area'] > 0) {
                $db->prepare('UPDATE obras SET area_construida = COALESCE(area_construida, ?), tipologia = COALESCE(tipologia, ?), padrao = COALESCE(padrao, ?) WHERE id = ?')
                    ->execute([$previa['area'], $previa['tipologia'], $previa['padrao'], $obraId]);
            }
            if (!empty($_POST['salvar_planta']) && $pasta) {
                $nomes = is_file($pasta . '/plantas.json') ? (array) json_decode((string) file_get_contents($pasta . '/plantas.json'), true) : [];
                foreach (glob($pasta . '/planta*') ?: [] as $arquivoPlanta) {
                    if (preg_match('/\.(nome|json)$/', $arquivoPlanta)) {
                        continue;
                    }
                    $nome = (string) ($nomes[basename($arquivoPlanta)] ?? (is_file($pasta . '/planta.nome') ? file_get_contents($pasta . '/planta.nome') : basename($arquivoPlanta)));
                    try {
                        (new \App\Domain\Obra\PlantaService($db, UPLOAD_DIR, max(UPLOAD_MAX_MB, 80)))->armazenar(
                            $obraId, mb_substr(pathinfo($nome, PATHINFO_FILENAME), 0, 180), 'Planta usada na prévia de obra.',
                            ['name' => $nome, 'tmp_name' => $arquivoPlanta, 'error' => UPLOAD_ERR_OK, 'size' => filesize($arquivoPlanta)],
                            currentUserId(), true
                        );
                    } catch (Throwable $exception) {
                        $avisoPlanta = 'O orçamento foi criado, mas a planta não pôde ser salva na obra: ' . $exception->getMessage();
                    }
                }
            }
        } catch (InvalidArgumentException $exception) {
            setFlash('error', $exception->getMessage());
            redirect(APP_URL . '/admin/previa_obra.php?p=' . $token);
        }
        logAction('orcamento_criado', 'orcamentos', $orcamentoId, $titulo . ' (prévia)');
        setFlash(isset($avisoPlanta) ? 'warning' : 'success', $avisoPlanta ?? 'Orçamento gerado em rascunho a partir da prévia. Revise os itens antes de enviar cotações.');
        redirect(APP_URL . '/admin/orcamento_detalhe.php?id=' . $orcamentoId);
    }
    redirect(APP_URL . '/admin/previa_obra.php' . ($permitida ? '?p=' . $token : ''));
}

$obras = $db->query('SELECT o.id, o.nome, c.razao_social FROM obras o JOIN clientes c ON c.id = o.cliente_id ORDER BY o.nome')->fetchAll();
$totalReferencias = (int) $db->query('SELECT COUNT(*) FROM referencias WHERE ativo = 1')->fetchColumn();
$totalPrecos = (int) $db->query('SELECT COUNT(*) FROM precos_base')->fetchColumn();
$brl = static fn (float $v): string => 'R$ ' . number_format($v, 2, ',', '.');
$num = static fn (float $v, int $d = 2): string => number_format($v, $d, ',', '.');
$estimativa = $previa['estimativa'] ?? null;
$quantitativos = $previa['quantitativos'] ?? null;
$planta = $previa['planta'] ?? null;

pageHead('Prévia de obra');
?>
<div class="layout">
<?php sidebar('previa'); ?>
<div class="main">
<?php topbar('Prévia de obra'); ?>
<div class="content">
<?php flashMessage(); ?>

<div class="card mb-4">
    <div class="card-header" style="flex-wrap:wrap;gap:8px">
        <h2><i class="fa-solid fa-wand-magic-sparkles"></i> Nova prévia: planta e/ou planilha → materiais e custo</h2>
        <span class="text-xs text-muted">Base: <?= $totalReferencias ?> orçamentos reais · <?= $num($totalPrecos, 0) ?> preços SINAPI</span>
    </div>
    <form method="post" enctype="multipart/form-data" class="card-body" id="formPrevia">
        <input type="hidden" name="csrf_token" value="<?= csrf() ?>">
        <input type="hidden" name="acao" value="analisar">
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Planta arquitetônica (PDF — uma ou várias pranchas)</label>
                <input type="file" name="planta[]" class="form-control" accept=".pdf,.png,.jpg,.jpeg,.webp" multiple>
                <p class="form-text">Lê a área construída do quadro de áreas, os ambientes, a tipologia e o quadro de esquadrias. Selecione várias pranchas de uma vez se o quadro estiver em outra folha (até 12 arquivos de <?= max(UPLOAD_MAX_MB, 80) ?> MB).</p>
            </div>
            <div class="form-group">
                <label class="form-label">Planilha de quantitativos (opcional)</label>
                <input type="file" name="planilha" class="form-control" accept=".xlsx,.xls,.xlsm,.ods,.csv">
                <p class="form-text">Itens sem preço recebem o preço de itens semelhantes das obras reais e do SINAPI.</p>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Área construída (m²)</label>
                <input type="number" name="area" class="form-control" step="any" min="0" placeholder="Automática pela planta" value="<?= sanitize((string) ($previa['entrada']['area'] ?? '')) ?>">
            </div>
            <div class="form-group">
                <label class="form-label">Tipologia</label>
                <select name="tipologia" class="form-control">
                    <option value="">Detectar automaticamente</option>
                    <?php foreach (Classificador::TIPOLOGIAS as $chave => $rotulo): ?>
                    <option value="<?= $chave ?>" <?= ($previa['entrada']['tipologia'] ?? '') === $chave ? 'selected' : '' ?>><?= $rotulo ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Padrão de acabamento</label>
                <select name="padrao" class="form-control">
                    <?php foreach (EstimativaService::PADROES as $chave => $rotulo): ?>
                    <option value="<?= $chave ?>" <?= ($previa['entrada']['padrao'] ?? 'medio') === $chave ? 'selected' : '' ?>><?= $rotulo ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-group">
            <label class="form-label">Descrição curta (opcional)</label>
            <input type="text" name="descricao" class="form-control" maxlength="200" placeholder="Ex.: residência unifamiliar de 2 pavimentos" value="<?= sanitize((string) ($previa['entrada']['descricao'] ?? '')) ?>">
        </div>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-calculator"></i> Gerar prévia</button>
    </form>
</div>

<?php if ($previa): ?>
<?php foreach ($previa['avisos'] as $aviso): ?><div class="alert alert-warning alert-persist" style="display:block"><?= sanitize($aviso) ?></div><?php endforeach; ?>

<?php if ($planta): ?>
<div class="card mb-4">
    <div class="card-header"><h2>Leitura da planta<?= !empty($previa['entrada']['planta_nome']) ? ' — ' . sanitize($previa['entrada']['planta_nome']) : '' ?></h2></div>
    <div class="card-body">
        <div class="flex gap-4" style="flex-wrap:wrap">
            <div><div class="text-xs text-muted">Área construída</div><strong><?= $planta['area_construida'] ? $num($planta['area_construida']) . ' m²' : '—' ?></strong><div class="text-xs text-muted"><?= sanitize($planta['area_origem']) ?></div></div>
            <div><div class="text-xs text-muted">Terreno</div><strong><?= $planta['area_terreno'] ? $num($planta['area_terreno']) . ' m²' : '—' ?></strong></div>
            <div><div class="text-xs text-muted">Pavimentos identificados</div><strong><?= (int) $planta['pavimentos'] ?></strong></div>
            <div><div class="text-xs text-muted">Ambientes com área</div><strong><?= (int) $planta['ambientes']['total'] ?></strong></div>
            <div><div class="text-xs text-muted">Esquadrias no quadro</div><strong><?= count($planta['esquadrias']) ?> tipos · <?= array_sum(array_column($planta['esquadrias'], 'quantidade')) ?> un.</strong></div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($estimativa): ?>
<div class="card mb-4">
    <div class="card-body">
        <div class="flex gap-4" style="flex-wrap:wrap;align-items:flex-end">
            <div><div class="text-xs text-muted">Custo direto estimado</div><div style="font-size:1.8rem;font-weight:800;color:var(--primary)"><?= $brl((float) $estimativa['total']) ?></div>
                <div class="text-xs text-muted">Faixa provável: <?= $brl((float) $estimativa['total_faixa'][0]) ?> a <?= $brl((float) $estimativa['total_faixa'][1]) ?></div></div>
            <div><div class="text-xs text-muted">Área</div><strong><?= $num((float) $estimativa['area']) ?> m²</strong></div>
            <div><div class="text-xs text-muted">Tipologia</div><strong><?= sanitize(Classificador::TIPOLOGIAS[$estimativa['tipologia']]) ?></strong></div>
            <div><div class="text-xs text-muted">Custo/m² (<?= sanitize(EstimativaService::PADROES[$estimativa['padrao']]) ?>)</div><strong><?= $brl((float) $estimativa['custo_m2_usado']) ?></strong>
                <div class="text-xs text-muted">econ. <?= $num((float) $estimativa['custo_m2']['economico'], 0) ?> · médio <?= $num((float) $estimativa['custo_m2']['medio'], 0) ?> · alto <?= $num((float) $estimativa['custo_m2']['alto'], 0) ?></div></div>
            <div><div class="text-xs text-muted">Obras reais comparadas</div><strong><?= count($estimativa['referencias']) ?></strong></div>
        </div>
        <p class="text-xs text-muted mt-3">Estimativa paramétrica para estudo de viabilidade: mediana ponderada (porte e data) de orçamentos reais atualizados pelo INCC. Não substitui o levantamento de quantitativos do projeto executivo.</p>
    </div>
</div>

<div class="tabs" id="tabsPrevia">
    <button class="tab-link active" data-tab="tabEtapas" onclick="switchTab('tabsPrevia','tabEtapas')">Custo por etapa</button>
    <button class="tab-link" data-tab="tabMateriais" onclick="switchTab('tabsPrevia','tabMateriais')">Materiais e quantidades</button>
    <?php if (!empty($estimativa['esquadrias']['itens'])): ?><button class="tab-link" data-tab="tabEsquadrias" onclick="switchTab('tabsPrevia','tabEsquadrias')">Esquadrias da planta</button><?php endif; ?>
    <button class="tab-link" data-tab="tabRefs" onclick="switchTab('tabsPrevia','tabRefs')">Obras de referência</button>
</div>
<div id="tabEtapas" class="tab-pane active"><div class="card"><div class="table-wrap"><table>
    <thead><tr><th>Etapa</th><th>% do custo</th><th>Valor estimado</th><th>R$/m²</th></tr></thead>
    <tbody><?php foreach ($estimativa['etapas'] as $e): ?>
    <tr><td><?= sanitize($e['rotulo']) ?></td><td><div class="progress" style="max-width:160px"><div class="progress-bar" style="width:<?= min(100, $e['percentual'] * 3) ?>%"></div></div><span class="text-xs"><?= $num($e['percentual'], 1) ?>%</span></td>
        <td class="font-bold"><?= $brl((float) $e['valor']) ?></td><td class="text-sm"><?= $num($e['valor'] / max(1, $estimativa['area'])) ?></td></tr>
    <?php endforeach; ?></tbody>
    <tfoot><tr style="font-weight:700"><td>Total</td><td>100%</td><td><?= $brl((float) $estimativa['total']) ?></td><td><?= $num((float) $estimativa['custo_m2_usado']) ?></td></tr></tfoot>
</table></div></div></div>
<div id="tabMateriais" class="tab-pane"><div class="card"><div class="table-wrap"><table>
    <thead><tr><th>Material / serviço</th><th>Consumo por m²</th><th>Quantidade</th><th>Preço unit.</th><th>Total</th><th>Obras</th></tr></thead>
    <tbody><?php foreach ($estimativa['materiais'] as $m): ?>
    <tr><td><?= sanitize($m['rotulo']) ?><div class="text-xs text-muted"><?= sanitize(Classificador::rotuloEtapa($m['etapa'])) ?></div></td>
        <td class="text-sm"><?= $m['coef_m2'] !== null ? $num((float) $m['coef_m2'], 3) . ' ' . sanitize($m['unidade']) . '/m²' : '—' ?></td>
        <td class="font-bold"><?= $num((float) $m['quantidade']) ?> <?= sanitize($m['unidade']) ?></td><td class="text-sm"><?= $brl((float) $m['preco_unitario']) ?></td>
        <td class="font-bold"><?= $brl((float) $m['total']) ?></td><td class="text-xs text-muted"><?= (int) $m['amostras'] ?></td></tr>
    <?php endforeach; ?></tbody>
</table></div><p class="text-xs text-muted" style="padding:12px 24px">Quantidades pelo consumo mediano por m² das obras reais; preços unitários de serviço (material + mão de obra), atualizados pelo INCC.</p></div></div>
<?php if (!empty($estimativa['esquadrias']['itens'])): ?>
<div id="tabEsquadrias" class="tab-pane"><div class="card"><div class="table-wrap"><table>
    <thead><tr><th>Código</th><th>Tipo</th><th>Qtd</th><th>Dimensões</th><th>Área total</th><th>Preço unit.</th><th>Total</th></tr></thead>
    <tbody><?php foreach ($estimativa['esquadrias']['itens'] as $q): ?>
    <tr><td class="font-bold"><?= sanitize($q['codigo']) ?></td><td class="text-sm"><?= sanitize(str_replace('_', '-', $q['tipo'])) ?><div class="text-xs text-muted"><?= sanitize($q['descricao']) ?></div></td>
        <td><?= (int) $q['quantidade'] ?></td><td class="text-sm"><?= $num((float) $q['largura']) ?> × <?= $num((float) $q['altura']) ?> m</td><td class="text-sm"><?= $num((float) $q['area_total']) ?> m²</td>
        <td class="text-sm"><?= $brl((float) $q['preco_unitario']) ?>/<?= sanitize($q['unidade']) ?></td><td class="font-bold"><?= $brl((float) $q['total']) ?></td></tr>
    <?php endforeach; ?></tbody>
    <tfoot><tr style="font-weight:700"><td colspan="6">Total das esquadrias</td><td><?= $brl((float) $estimativa['esquadrias']['total']) ?></td></tr></tfoot>
</table></div></div></div>
<?php endif; ?>
<div id="tabRefs" class="tab-pane"><div class="card"><div class="table-wrap"><table>
    <thead><tr><th>Referência</th><th>Área</th><th>Data-base</th><th>R$/m² atualizado</th></tr></thead>
    <tbody><?php foreach ($estimativa['referencias'] as $r): ?>
    <tr><td><?= sanitize($r['codigo']) ?> — <?= sanitize($r['titulo']) ?></td><td><?= $num((float) $r['area'], 0) ?> m²</td><td class="text-sm"><?= $r['data_base'] ? date('m/Y', strtotime($r['data_base'])) : '—' ?></td><td class="font-bold"><?= $brl((float) $r['custo_m2']) ?></td></tr>
    <?php endforeach; ?></tbody>
</table></div></div></div>
<?php endif; ?>

<?php if ($quantitativos && $quantitativos['itens']): ?>
<div class="card mb-4 mt-4">
    <div class="card-header" style="flex-wrap:wrap;gap:8px"><h2>Quantitativos precificados (<?= count($quantitativos['itens']) ?> itens)</h2>
        <strong><?= $brl((float) $quantitativos['total']) ?></strong></div>
    <div class="table-wrap"><table>
        <thead><tr><th>Descrição</th><th>Unid.</th><th>Qtd</th><th>Preço</th><th>Origem do preço</th><th>Total</th></tr></thead>
        <tbody><?php foreach ($quantitativos['itens'] as $i): ?>
        <tr><td><?= sanitize($i['descricao']) ?><?php if ($i['etapa']): ?><div class="text-xs text-muted"><?= sanitize((string) $i['etapa']) ?></div><?php endif; ?></td>
            <td class="text-sm"><?= sanitize((string) $i['unidade']) ?></td><td class="text-sm"><?= $num((float) $i['quantidade']) ?></td><td class="text-sm"><?= $brl((float) $i['preco_final']) ?></td>
            <td class="text-xs"><?php if ($i['origem_preco'] === 'sugerido'): ?><span class="badge badge-blue">sugerido · <?= (int) round($i['similaridade'] * 100) ?>%</span><div class="text-muted"><?= sanitize(mb_substr((string) $i['referencia'], 0, 70)) ?> (<?= sanitize((string) $i['fonte']) ?>)</div>
                <?php elseif ($i['origem_preco'] === 'planilha'): ?><span class="badge badge-green">planilha</span><?php else: ?><span class="badge badge-red">sem preço</span><?php endif; ?></td>
            <td class="font-bold"><?= $brl((float) $i['total']) ?></td></tr>
        <?php endforeach; ?></tbody>
    </table></div>
</div>
<?php endif; ?>

<?php if ($estimativa || ($quantitativos && $quantitativos['itens'])): ?>
<div class="card mt-4">
    <div class="card-header"><h2>Gerar orçamento a partir da prévia</h2></div>
    <form method="post" class="card-body">
        <input type="hidden" name="csrf_token" value="<?= csrf() ?>">
        <input type="hidden" name="acao" value="gerar_orcamento">
        <input type="hidden" name="token" value="<?= sanitize($token) ?>">
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Obra *</label>
                <select name="obra_id" class="form-control" required>
                    <option value="">Selecione...</option>
                    <?php foreach ($obras as $o): ?><option value="<?= $o['id'] ?>"><?= sanitize($o['nome']) ?> — <?= sanitize($o['razao_social']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Base dos itens</label>
                <select name="modo" class="form-control">
                    <?php if ($estimativa): ?><option value="parametrico">Prévia paramétrica (materiais + etapas)</option><?php endif; ?>
                    <?php if ($quantitativos && $quantitativos['itens']): ?><option value="quantitativos">Quantitativos precificados</option><?php endif; ?>
                </select>
            </div>
        </div>
        <div class="form-group">
            <label class="form-label">Título</label>
            <input type="text" name="titulo" class="form-control" maxlength="200" value="Prévia — <?= sanitize(pathinfo((string) ($previa['entrada']['planta_nome'] ?? 'obra'), PATHINFO_FILENAME)) ?>">
        </div>
        <?php if ($planta): ?><label class="text-sm"><input type="checkbox" name="salvar_planta" value="1" checked> Salvar a planta na obra escolhida</label><?php endif; ?>
        <div class="mt-3"><button type="submit" class="btn btn-success"><i class="fa-solid fa-file-circle-plus"></i> Gerar orçamento em rascunho</button></div>
    </form>
</div>
<?php endif; ?>
<?php endif; ?>

</div></div></div>
<?php pageFoot(); ?>
