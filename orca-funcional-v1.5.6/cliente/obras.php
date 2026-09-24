<?php
require_once __DIR__ . '/../bootstrap/app.php';

requireLogin();
if (isAdmin()) redirect(APP_URL.'/admin/obras.php');

$db  = getDB();
$uid = currentUserId();
$cliente = $db->prepare('SELECT id FROM clientes WHERE usuario_id=?'); $cliente->execute([$uid]); $cid = (int)$cliente->fetchColumn();

$statusRotulos = ['planejamento' => 'Planejamento', 'em_andamento' => 'Em andamento', 'pausada' => 'Pausada', 'concluida' => 'Concluída', 'cancelada' => 'Cancelada'];
$status = (string) ($_GET['status'] ?? '');
if (!isset($statusRotulos[$status])) $status = '';
$search = trim((string) ($_GET['q'] ?? ''));
$pag = max(1,(int)($_GET['pag']??1)); $limit=12; $offset=($pag-1)*$limit;

$wheres = ['cliente_id = ?']; $params = [$cid];
if ($search !== '') $wheres[] = \App\Domain\Consulta\TermoBusca::condicao(['nome', "COALESCE(cidade,'')", "COALESCE(endereco,'')"], \App\Domain\Consulta\TermoBusca::termos($search), $params);
$contagens = $db->prepare('SELECT status, COUNT(*) n FROM obras WHERE ' . implode(' AND ', $wheres) . ' GROUP BY status');
$contagens->execute($params);
$porStatus = $contagens->fetchAll(PDO::FETCH_KEY_PAIR);
$abas = ['' => ['Todas', array_sum($porStatus)]];
foreach ($statusRotulos as $chave => $rotulo) if (!empty($porStatus[$chave]) || $status === $chave) $abas[$chave] = [$rotulo, (int) ($porStatus[$chave] ?? 0)];
if ($status) { $wheres[] = 'status = ?'; $params[] = $status; }
$total = (int) ($status ? ($porStatus[$status] ?? 0) : array_sum($porStatus));

$obras = $db->prepare('SELECT * FROM obras WHERE ' . implode(' AND ', $wheres) . ' ORDER BY criado_em DESC LIMIT '.$limit.' OFFSET '.$offset);
$obras->execute($params);
$obras = $obras->fetchAll();
$termos = \App\Domain\Consulta\TermoBusca::termos($search);

pageHead('Minhas Obras');
?>
<div class="layout">
<?php sidebar('obras'); ?>
<div class="main">
<?php topbar('Minhas Obras'); ?>
<div class="content">
<?php flashMessage(); ?>
<div class="card mb-4 query-card">
    <form method="get" class="query-bar" role="search">
        <?php if ($status): ?><input type="hidden" name="status" value="<?= sanitize($status) ?>"><?php endif; ?>
        <label class="query-search">
            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
            <input type="search" name="q" value="<?= sanitize($search) ?>" placeholder="Buscar por nome, cidade ou endereço…" aria-label="Buscar obras" data-autosubmit>
        </label>
        <button class="btn btn-outline btn-sm">Buscar</button>
    </form>
    <?= abasStatus($abas, $status) ?>
    <?= chipsFiltros(['q' => $search !== '' ? 'Busca: “' . $search . '”' : '']) ?>
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:16px">
<?php foreach ($obras as $o): ?>
<div class="card" style="display:flex;flex-direction:column">
    <div class="card-body" style="flex:1">
        <div class="flex justify-between items-start mb-2">
            <h3 style="font-size:.95rem;font-weight:700"><?= \App\Domain\Consulta\TermoBusca::destacar(trim($o['nome']), $termos) ?></h3>
            <?= statusBadge($o['status']) ?>
        </div>
        <?php if ($o['cidade']): ?>
        <p class="text-xs text-muted mb-2">📍 <?= sanitize($o['cidade'] . ($o['estado'] ? '/'.$o['estado'] : '')) ?></p>
        <?php endif; ?>
        <div class="progress mb-1"><div class="progress-bar" data-width="<?= $o['progresso'] ?>" style="width:0"></div></div>
        <div class="flex justify-between text-xs text-muted">
            <span><?= $o['progresso'] ?>% concluído</span>
            <?php if ($o['data_prev_fim']): ?><span>Prev. <?= date('d/m/y',strtotime($o['data_prev_fim'])) ?></span><?php endif; ?>
        </div>
    </div>
    <div style="padding:12px 20px;border-top:1px solid var(--neutral-100)">
        <a href="<?= APP_URL ?>/cliente/obra_detalhe.php?id=<?= $o['id'] ?>" class="btn btn-outline btn-sm w-full" style="justify-content:center">Ver Detalhes</a>
    </div>
</div>
<?php endforeach; ?>
<?php if (!$obras): ?>
<div class="card"><div class="card-body text-center text-muted">Nenhuma obra cadastrada ainda.</div></div>
<?php endif; ?>
</div>

<div class="list-footer"><?= resumoResultados($total, $pag, $limit) ?><?php paginacao($total,$limit,$pag,urlConsulta()); ?></div>

</div></div></div>
<?php pageFoot(); ?>
