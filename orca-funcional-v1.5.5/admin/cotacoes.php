<?php
require_once __DIR__ . '/../bootstrap/app.php';

requireAdmin();
$db = getDB();

$statusRotulos = ['pendente' => 'Pendente', 'enviada' => 'Enviada', 'respondida' => 'Respondida', 'aceita' => 'Aceita', 'recusada' => 'Recusada'];
$statusF = (string) ($_GET['status'] ?? '');
if (!isset($statusRotulos[$statusF])) $statusF = '';
$search  = trim((string) ($_GET['q'] ?? ''));
$pag     = max(1,(int)($_GET['pag']??1));
$limit   = 15; $offset = ($pag-1)*$limit;
$ordem = ordenacaoAtual(['fornecedor' => 'f.nome', 'orcamento' => 'o.titulo', 'enviada' => 'co.data_envio', 'resposta' => 'co.data_resposta', 'criada' => 'co.criado_em'], 'criada');

$wheres=[]; $params=[];
if ($search !== '') $wheres[] = \App\Domain\Consulta\TermoBusca::condicao(['f.nome', 'o.titulo', 'ob.nome', 'c.razao_social'], \App\Domain\Consulta\TermoBusca::termos($search), $params);
$from = 'FROM cotacoes co JOIN fornecedores f ON f.id=co.fornecedor_id JOIN orcamentos o ON o.id=co.orcamento_id JOIN obras ob ON ob.id=o.obra_id JOIN clientes c ON c.id=o.cliente_id';
$contagens = $db->prepare("SELECT co.status, COUNT(*) n $from " . ($wheres ? 'WHERE ' . implode(' AND ', $wheres) : '') . ' GROUP BY co.status');
$contagens->execute($params);
$porStatus = $contagens->fetchAll(PDO::FETCH_KEY_PAIR);
$abas = ['' => ['Todas', array_sum($porStatus)]];
foreach ($statusRotulos as $chave => $rotulo) $abas[$chave] = [$rotulo, (int) ($porStatus[$chave] ?? 0)];
if ($statusF) { $wheres[]='co.status=?'; $params[]=$statusF; }
$where = $wheres ? 'WHERE '.implode(' AND ',$wheres) : '';
$total = (int) ($statusF ? ($porStatus[$statusF] ?? 0) : array_sum($porStatus));
$orcamentosAbertos = $db->query("SELECT o.id, o.titulo, ob.nome AS obra FROM orcamentos o JOIN obras ob ON ob.id=o.obra_id WHERE o.status IN ('rascunho','aguardando_cotacao','cotado') ORDER BY o.criado_em DESC LIMIT 200")->fetchAll();

$stmt = $db->prepare("SELECT co.*,f.nome as fornecedor,o.titulo as orcamento,ob.nome as obra,c.razao_social
    FROM cotacoes co
    JOIN fornecedores f ON f.id=co.fornecedor_id
    JOIN orcamentos o ON o.id=co.orcamento_id
    JOIN obras ob ON ob.id=o.obra_id
    JOIN clientes c ON c.id=o.cliente_id
    $where ORDER BY {$ordem['sql']}, co.id DESC LIMIT $limit OFFSET $offset");
$stmt->execute($params);
$cotacoes = $stmt->fetchAll();

pageHead('Cotações');
?>
<div class="layout">
<?php sidebar('cotacoes'); ?>
<div class="main">
<?php topbar('Cotações'); ?>
<div class="content">
<?php flashMessage(); ?>
<div class="card">
    <div class="card-header" style="flex-wrap:wrap;gap:10px">
        <h2>Cotações</h2>
        <form class="flex gap-2" style="flex-wrap:wrap" onsubmit="if(this.id.value){location.href='<?= APP_URL ?>/admin/orcamento_detalhe.php?id='+encodeURIComponent(this.id.value)+'#cotar'}return false">
            <select name="id" class="form-control" style="width:280px;min-height:36px" aria-label="Orçamento para cotar" required>
                <option value="">Escolha o orçamento…</option>
                <?php foreach ($orcamentosAbertos as $oa): ?><option value="<?= $oa['id'] ?>"><?= sanitize($oa['titulo'] . ' — ' . $oa['obra']) ?></option><?php endforeach; ?>
            </select>
            <button class="btn btn-primary btn-sm"><i class="fa-solid fa-paper-plane"></i> Pedir cotação</button>
        </form>
    </div>
    <form method="get" class="query-bar" role="search">
        <?php if ($statusF): ?><input type="hidden" name="status" value="<?= sanitize($statusF) ?>"><?php endif; ?>
        <label class="query-search">
            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
            <input type="search" name="q" value="<?= sanitize($search) ?>" placeholder="Buscar por fornecedor, orçamento, obra ou cliente…" aria-label="Buscar cotações" data-autosubmit>
        </label>
        <button class="btn btn-outline btn-sm">Buscar</button>
    </form>
    <?= abasStatus($abas, $statusF) ?>
    <?= chipsFiltros(['q' => $search !== '' ? 'Busca: “' . $search . '”' : '']) ?>
    <div class="table-wrap">
        <table>
            <thead><tr><?= thOrdenavel('Fornecedor', 'fornecedor', $ordem) ?><?= thOrdenavel('Orçamento', 'orcamento', $ordem) ?><th>Obra</th><th>Canal</th><th>Status</th><?= thOrdenavel('Enviada', 'enviada', $ordem) ?><?= thOrdenavel('Resposta', 'resposta', $ordem) ?><th></th></tr></thead>
            <tbody>
            <?php foreach ($cotacoes as $c): ?>
            <tr>
                <td><?= sanitize($c['fornecedor']) ?></td>
                <td class="text-sm"><?= sanitize($c['orcamento']) ?></td>
                <td class="text-xs text-muted"><?= sanitize($c['obra']) ?></td>
                <td class="text-xs"><?= $c['canal_envio'] ?></td>
                <td><?= statusBadge($c['status']) ?></td>
                <td class="text-xs"><?= $c['data_envio'] ? date('d/m/y H:i',strtotime($c['data_envio'])) : '—' ?></td>
                <td class="text-xs"><?= $c['data_resposta'] ? date('d/m/y H:i',strtotime($c['data_resposta'])) : '—' ?></td>
                <td><a href="<?= APP_URL ?>/admin/cotacao_detalhe.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-outline">Ver</a></td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$cotacoes): ?><tr><td colspan="8" class="text-center text-muted">Nenhuma cotação.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="list-footer"><?= resumoResultados($total, $pag, $limit) ?><?php paginacao($total,$limit,$pag,urlConsulta()); ?></div>
</div>
</div></div></div>
<?php pageFoot(); ?>
