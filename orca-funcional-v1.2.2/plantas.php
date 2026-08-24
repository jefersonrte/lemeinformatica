<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap/app.php';
requireLogin();

$db = getDB();
$admin = isAdmin();
$obraId = max(0, (int) ($_GET['obra_id'] ?? $_POST['obra_id'] ?? 0));
$tipo = (string) ($_GET['tipo'] ?? '');
$busca = trim(mb_substr((string) ($_GET['q'] ?? ''), 0, 100));

if (!in_array($tipo, ['', 'image', 'pdf'], true)) {
    $tipo = '';
}

if ($admin) {
    $obrasStatement = $db->query(
        'SELECT o.id, o.nome, c.razao_social FROM obras o '
        . 'JOIN clientes c ON c.id = o.cliente_id ORDER BY o.nome, c.razao_social'
    );
} else {
    $obrasStatement = $db->prepare(
        'SELECT o.id, o.nome, c.razao_social FROM obras o '
        . 'JOIN clientes c ON c.id = o.cliente_id WHERE c.usuario_id = ? ORDER BY o.nome, c.razao_social'
    );
    $obrasStatement->execute([currentUserId()]);
}
$obras = $obrasStatement->fetchAll();
$obrasPermitidas = array_map('intval', array_column($obras, 'id'));

if ($obraId > 0 && !in_array($obraId, $obrasPermitidas, true)) {
    http_response_code(404);
    exit('Projeto não encontrado.');
}

$service = new \App\Domain\Obra\PlantaService($db, UPLOAD_DIR, UPLOAD_MAX_MB);
if ($admin && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    try {
        if ($obraId <= 0 || !in_array($obraId, $obrasPermitidas, true)) {
            throw new InvalidArgumentException('Selecione uma obra válida.');
        }
        $plantaId = $service->armazenar(
            $obraId,
            (string) ($_POST['titulo'] ?? ''),
            (string) ($_POST['descricao'] ?? ''),
            $_FILES['arquivo'] ?? [],
            currentUserId() ?: null
        );
        logAction('planta_enviada', 'obra_plantas', $plantaId, (string) ($_POST['titulo'] ?? ''));
        setFlash('success', 'Nova versão da planta publicada com sucesso.');
    } catch (InvalidArgumentException|RuntimeException $exception) {
        setFlash('error', $exception->getMessage());
    }
    redirect(APP_URL . '/plantas.php?obra_id=' . $obraId);
}

$sql = "SELECT p.*, o.nome AS obra_nome, c.razao_social,
        CASE WHEN p.versao = (
            SELECT MAX(p2.versao) FROM obra_plantas p2
            WHERE p2.obra_id = p.obra_id AND p2.titulo = p.titulo
        ) THEN 1 ELSE 0 END AS versao_atual
    FROM obra_plantas p
    JOIN obras o ON o.id = p.obra_id
    JOIN clientes c ON c.id = o.cliente_id
    WHERE 1 = 1";
$params = [];

if (!$admin) {
    $sql .= ' AND c.usuario_id = ?';
    $params[] = currentUserId();
}
if ($obraId > 0) {
    $sql .= ' AND p.obra_id = ?';
    $params[] = $obraId;
}
if ($tipo === 'image') {
    $sql .= " AND p.mime_type LIKE 'image/%'";
} elseif ($tipo === 'pdf') {
    $sql .= " AND p.mime_type = 'application/pdf'";
}
if ($busca !== '') {
    $sql .= ' AND (p.titulo LIKE ? OR p.descricao LIKE ? OR p.nome_original LIKE ? OR o.nome LIKE ?)';
    $termo = '%' . $busca . '%';
    array_push($params, $termo, $termo, $termo, $termo);
}
$sql .= ' ORDER BY p.criado_em DESC, p.id DESC';

$plantasStatement = $db->prepare($sql);
$plantasStatement->execute($params);
$plantas = $plantasStatement->fetchAll();

$imagens = count(array_filter($plantas, static fn (array $planta): bool => str_starts_with((string) $planta['mime_type'], 'image/')));
$pdfs = count($plantas) - $imagens;
$volume = array_sum(array_map('intval', array_column($plantas, 'tamanho')));
$obraSelecionada = null;
foreach ($obras as $obra) {
    if ((int) $obra['id'] === $obraId) {
        $obraSelecionada = $obra;
        break;
    }
}

function plantFileSize(int $bytes): string
{
    if ($bytes < 1024) {
        return $bytes . ' B';
    }
    if ($bytes < 1024 * 1024) {
        return number_format($bytes / 1024, 1, ',', '.') . ' KB';
    }
    if ($bytes < 1024 * 1024 * 1024) {
        return number_format($bytes / (1024 * 1024), 1, ',', '.') . ' MB';
    }
    return number_format($bytes / (1024 * 1024 * 1024), 1, ',', '.') . ' GB';
}

pageHead('Plantas e documentos');
?>
<div class="layout">
<?php sidebar('plantas'); ?>
<div class="main">
<?php topbar('Plantas e documentos'); ?>
<main class="content">
<?php flashMessage(); ?>

<div class="catalog-heading">
    <div>
        <h1>Plantas e documentos</h1>
        <p>Acervo técnico protegido — acesso organizado por obra, cliente, tipo e versão.</p>
    </div>
    <?php if ($admin && $obras): ?>
    <button class="btn btn-primary" type="button" onclick="openModal('modalNovaPlanta')">
        <i class="fa-solid fa-cloud-arrow-up"></i> Publicar documento
    </button>
    <?php endif; ?>
</div>

<form class="catalog-filter" method="get">
    <div class="form-group">
        <label class="form-label" for="filtroObra">Obra</label>
        <select class="form-control" id="filtroObra" name="obra_id">
            <option value="0">Todas as obras</option>
            <?php foreach ($obras as $obra): ?>
            <option value="<?= (int) $obra['id'] ?>" <?= (int) $obra['id'] === $obraId ? 'selected' : '' ?>>
                <?= sanitize($obra['nome']) ?> — <?= sanitize($obra['razao_social']) ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group">
        <label class="form-label" for="filtroTipo">Tipo</label>
        <select class="form-control" id="filtroTipo" name="tipo">
            <option value="" <?= $tipo === '' ? 'selected' : '' ?>>Todos os tipos</option>
            <option value="image" <?= $tipo === 'image' ? 'selected' : '' ?>>Imagens</option>
            <option value="pdf" <?= $tipo === 'pdf' ? 'selected' : '' ?>>Documentos PDF</option>
        </select>
    </div>
    <div class="form-group">
        <label class="form-label" for="filtroBusca">Buscar</label>
        <input class="form-control" id="filtroBusca" name="q" value="<?= sanitize($busca) ?>" placeholder="Ex.: planta, elétrico, cobertura">
    </div>
    <div class="catalog-filter-actions">
        <button class="btn btn-primary" type="submit"><i class="fa-solid fa-filter"></i> Filtrar</button>
        <?php if ($obraId || $tipo || $busca): ?><a class="btn btn-secondary" href="<?= APP_URL ?>/plantas.php" title="Limpar filtros"><i class="fa-solid fa-rotate-left"></i></a><?php endif; ?>
    </div>
</form>

<section class="catalog-metrics" aria-label="Resumo do acervo filtrado">
    <div class="catalog-metric"><strong><?= count($plantas) ?></strong><span>Documentos</span></div>
    <div class="catalog-metric"><strong><?= $imagens ?></strong><span>Imagens</span></div>
    <div class="catalog-metric"><strong><?= $pdfs ?></strong><span>Arquivos PDF</span></div>
    <div class="catalog-metric"><strong><?= plantFileSize($volume) ?></strong><span>Volume filtrado</span></div>
</section>

<section class="document-section">
    <div class="document-section-header">
        <h2><?= $obraSelecionada ? 'Pranchas de ' . sanitize($obraSelecionada['nome']) : 'Pranchas e documentos' ?></h2>
        <span><?= count($plantas) ?> resultado(s)</span>
    </div>

    <?php if ($plantas): ?>
    <div class="document-gallery">
        <?php foreach ($plantas as $plantaIndex => $planta):
            $imagem = str_starts_with((string) $planta['mime_type'], 'image/');
            $extensao = $imagem ? match ($planta['mime_type']) {
                'image/png' => 'PNG',
                'image/webp' => 'WEBP',
                'image/svg+xml' => 'SVG',
                default => 'JPG',
            } : 'PDF';
            $arquivoUrl = APP_URL . '/planta-arquivo.php?id=' . (int) $planta['id'];
            $historicoUrl = APP_URL . '/plantas.php?obra_id=' . (int) $planta['obra_id'] . '&q=' . rawurlencode((string) $planta['titulo']);
        ?>
        <article class="document-card" style="--card-index:<?= (int) $plantaIndex ?>">
            <button class="document-preview plant-preview-trigger" type="button"
                    data-source="<?= $arquivoUrl ?>"
                    data-mime="<?= sanitize((string) $planta['mime_type']) ?>"
                    data-title="<?= sanitize((string) $planta['titulo']) ?>"
                    data-project="<?= sanitize((string) $planta['obra_nome']) ?>"
                    data-client="<?= sanitize((string) $planta['razao_social']) ?>"
                    data-version="<?= (int) $planta['versao'] ?>"
                    data-description="<?= sanitize((string) ($planta['descricao'] ?? '')) ?>"
                    aria-label="Visualizar <?= sanitize($planta['titulo']) ?> no leitor animado">
                <span class="document-type-chip"><?= $imagem ? 'Imagem técnica' : 'Documento técnico' ?></span>
                <span class="document-version">v<?= (int) $planta['versao'] ?><?= (int) $planta['versao_atual'] === 1 ? ' · atual' : '' ?></span>
                <?php if ($imagem): ?>
                    <img src="<?= $arquivoUrl ?>&amp;thumb=1" loading="lazy" alt="Miniatura de <?= sanitize($planta['titulo']) ?>">
                <?php else: ?>
                    <span class="file-placeholder"><i class="fa-regular fa-file-pdf"></i><strong>PDF</strong></span>
                <?php endif; ?>
                <span class="document-extension <?= $imagem ? '' : 'pdf' ?>"><?= $extensao ?></span>
            </button>
            <div class="document-body">
                <strong class="document-title" title="<?= sanitize($planta['titulo']) ?>"><?= sanitize($planta['titulo']) ?></strong>
                <span class="document-meta" title="<?= sanitize($planta['obra_nome']) ?> — <?= sanitize($planta['razao_social']) ?>"><?= sanitize($planta['obra_nome']) ?> — <?= sanitize($planta['razao_social']) ?></span>
                <span class="document-meta"><?= sanitize($planta['nome_original']) ?> · <?= plantFileSize((int) $planta['tamanho']) ?></span>
                <div class="document-details">
                    <span><?= date('d/m/Y', strtotime((string) $planta['criado_em'])) ?></span>
                    <span class="document-actions">
                        <a href="<?= $historicoUrl ?>" title="Ver histórico desta planta"><i class="fa-solid fa-clock-rotate-left"></i></a>
                        <a href="<?= $arquivoUrl ?>" target="_blank" rel="noopener" title="Abrir documento"><i class="fa-solid fa-arrow-up-right-from-square"></i></a>
                    </span>
                </div>
            </div>
        </article>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="empty-state">
        <span class="empty-icon"><i class="fa-regular fa-map"></i></span>
        <h2>Nenhum documento encontrado</h2>
        <p><?= $obras ? 'Ajuste os filtros ou publique a primeira planta técnica desta seleção.' : 'Cadastre uma obra antes de iniciar o acervo técnico.' ?></p>
    </div>
    <?php endif; ?>
</section>

<?php if ($plantas): ?>
<div class="plant-viewer" id="plantViewer" aria-hidden="true">
    <button class="plant-viewer-backdrop" type="button" data-viewer-close aria-label="Fechar visualizador"></button>
    <section class="plant-viewer-dialog" role="dialog" aria-modal="true" aria-labelledby="plantViewerTitle">
        <header class="plant-viewer-header">
            <div class="plant-viewer-heading">
                <span class="plant-viewer-kicker"><i class="fa-solid fa-compass-drafting"></i> Leitor técnico</span>
                <h2 id="plantViewerTitle">Planta</h2>
                <p id="plantViewerMeta"></p>
            </div>
            <div class="plant-viewer-toolbar">
                <button type="button" data-viewer-zoom-out title="Reduzir"><i class="fa-solid fa-minus"></i></button>
                <button type="button" data-viewer-zoom-reset title="Restaurar zoom"><span id="plantViewerZoom">100%</span></button>
                <button type="button" data-viewer-zoom-in title="Ampliar"><i class="fa-solid fa-plus"></i></button>
                <a data-viewer-open href="#" target="_blank" rel="noopener" title="Abrir arquivo original"><i class="fa-solid fa-arrow-up-right-from-square"></i></a>
                <button type="button" data-viewer-close title="Fechar"><i class="fa-solid fa-xmark"></i></button>
            </div>
        </header>
        <div class="plant-viewer-stage" id="plantViewerStage"><div class="plant-viewer-loader"><i class="fa-solid fa-circle-notch fa-spin"></i></div></div>
        <footer class="plant-viewer-footer">
            <button type="button" data-viewer-prev><i class="fa-solid fa-arrow-left"></i> Anterior</button>
            <p id="plantViewerDescription">Documento técnico do projeto.</p>
            <button type="button" data-viewer-next>Próxima <i class="fa-solid fa-arrow-right"></i></button>
        </footer>
    </section>
</div>
<?php endif; ?>

<?php if ($admin && $obras): ?>
<div class="modal-overlay" id="modalNovaPlanta">
    <div class="modal">
        <div class="modal-header"><h3>Publicar documento</h3><button class="btn-close" type="button" onclick="closeModal('modalNovaPlanta')">×</button></div>
        <form method="post" enctype="multipart/form-data">
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?= csrf() ?>">
                <div class="form-group">
                    <label class="form-label" for="novaPlantaObra">Obra</label>
                    <select class="form-control" id="novaPlantaObra" name="obra_id" required>
                        <option value="">Selecione a obra</option>
                        <?php foreach ($obras as $obra): ?>
                        <option value="<?= (int) $obra['id'] ?>" <?= (int) $obra['id'] === $obraId ? 'selected' : '' ?>><?= sanitize($obra['nome']) ?> — <?= sanitize($obra['razao_social']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group"><label class="form-label" for="novaPlantaTitulo">Título</label><input class="form-control" id="novaPlantaTitulo" name="titulo" required maxlength="180" placeholder="Ex.: Planta elétrica"></div>
                <div class="form-group"><label class="form-label" for="novaPlantaDescricao">Descrição da versão</label><textarea class="form-control" id="novaPlantaDescricao" name="descricao" rows="3" maxlength="500" placeholder="O que mudou nesta versão?"></textarea></div>
                <div class="form-group"><label class="form-label" for="novaPlantaArquivo">Arquivo</label><input class="form-control" id="novaPlantaArquivo" type="file" name="arquivo" accept=".pdf,.png,.jpg,.jpeg,.webp,.svg" required><small class="form-help">PDF, PNG, JPG, WEBP ou SVG seguro · até <?= UPLOAD_MAX_MB ?> MB</small></div>
            </div>
            <div class="modal-footer"><button class="btn btn-secondary" type="button" onclick="closeModal('modalNovaPlanta')">Cancelar</button><button class="btn btn-primary" type="submit"><i class="fa-solid fa-cloud-arrow-up"></i> Publicar</button></div>
        </form>
    </div>
</div>
<?php endif; ?>
</main></div></div>
<?php pageFoot(); ?>
