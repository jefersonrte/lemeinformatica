<?php
declare(strict_types=1);

// Busca global: JSON para a paleta Ctrl+K (?formato=json) e página de resultados completa.
require_once __DIR__ . '/bootstrap/app.php';
requireLogin();

use App\Domain\Consulta\BuscaGlobal;
use App\Domain\Consulta\TermoBusca;

$db = getDB();
$consulta = trim(mb_substr((string) ($_GET['q'] ?? ''), 0, 120));
$busca = new BuscaGlobal($db, escopoClienteId($db), APP_URL);

if (($_GET['formato'] ?? '') === 'json') {
    header('Cache-Control: no-store');
    jsonResponse($busca->buscar($consulta, 5));
}

$resultado = $busca->buscar($consulta, 20);
$termos = $resultado['termos'];
$totalGeral = array_sum(array_column($resultado['grupos'], 'total'));

pageHead('Busca');
?>
<div class="layout">
<?php sidebar(''); ?>
<div class="main">
<?php topbar('Busca'); ?>
<div class="content">
<div class="card mb-4">
    <form method="get" class="query-bar query-bar-lg" role="search">
        <label class="query-search">
            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
            <input type="search" name="q" value="<?= sanitize($consulta) ?>" placeholder="Busque orçamentos, obras, clientes, fornecedores, produtos ou materiais…" aria-label="Buscar em todo o sistema" autofocus>
        </label>
        <button class="btn btn-primary btn-sm">Buscar</button>
    </form>
    <?php if ($consulta !== ''): ?>
    <p class="query-hint"><?= $totalGeral ?> resultado(s) para <strong>“<?= sanitize($consulta) ?>”</strong>. Todas as palavras precisam aparecer, em qualquer ordem, com ou sem acento.</p>
    <?php else: ?>
    <p class="query-hint">Dica: pressione <kbd>Ctrl</kbd> + <kbd>K</kbd> (ou <kbd>/</kbd>) em qualquer tela para buscar sem sair da página.</p>
    <?php endif; ?>
</div>

<?php if ($consulta !== '' && !$resultado['grupos']): ?>
<div class="card"><div class="empty-state">
    <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
    <strong>Nada encontrado para “<?= sanitize($consulta) ?>”.</strong>
    <span>Confira a grafia, use menos palavras ou termos mais gerais (ex.: “porcelanato” em vez de “porcelanato 60x60 acetinado”).</span>
</div></div>
<?php endif; ?>

<div class="search-groups">
<?php foreach ($resultado['grupos'] as $grupo): ?>
<section class="card search-group">
    <div class="card-header">
        <h2><i class="<?= $grupo['icone'] ?>" aria-hidden="true"></i> <?= sanitize($grupo['titulo']) ?> <span class="badge badge-gray"><?= $grupo['total'] ?></span></h2>
        <?php if ($grupo['total'] > count($grupo['itens']) || $grupo['chave'] === 'itens'): ?><a class="btn btn-sm btn-outline" href="<?= sanitize($grupo['mais']) ?>">Ver todos</a><?php endif; ?>
    </div>
    <ul class="search-list">
        <?php foreach ($grupo['itens'] as $item): ?>
        <li><a href="<?= sanitize($item['url']) ?>">
            <span class="search-list-main">
                <strong><?= TermoBusca::destacar(trim((string) $item['titulo']), $termos) ?></strong>
                <?php if (!empty($item['detalhe'])): ?><small><?= TermoBusca::destacar(trim((string) $item['detalhe']), $termos) ?></small><?php endif; ?>
            </span>
            <?php if (isset($item['valor'])): ?><span class="text-sm">R$ <?= number_format((float) $item['valor'], 2, ',', '.') ?></span><?php endif; ?>
            <?php if (!empty($item['status'])): ?><?= statusBadge($item['status']) ?><?php endif; ?>
            <i class="fa-solid fa-chevron-right text-muted" aria-hidden="true"></i>
        </a></li>
        <?php endforeach; ?>
    </ul>
</section>
<?php endforeach; ?>
</div>
</div></div></div>
<?php pageFoot(); ?>
