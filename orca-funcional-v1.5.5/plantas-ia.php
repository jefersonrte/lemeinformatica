<?php
declare(strict_types=1);

// Junção das plantas: as pranchas da obra são lidas e montadas numa imagem única,
// com animação em SVG e os dados extraídos das pranchas em PDF.
require_once __DIR__ . '/bootstrap/app.php';
requireLogin();

use App\Domain\Estimativa\PlantaAnalyzer;
use App\Infrastructure\PdfTextExtractor;

$db = getDB();
$obraId = max(0, (int) ($_GET['obra_id'] ?? 0));
if (isAdmin()) {
    $statement = $db->prepare('SELECT o.id, o.nome, c.razao_social FROM obras o JOIN clientes c ON c.id = o.cliente_id WHERE o.id = ?');
    $statement->execute([$obraId]);
} else {
    $statement = $db->prepare('SELECT o.id, o.nome, c.razao_social FROM obras o JOIN clientes c ON c.id = o.cliente_id WHERE o.id = ? AND c.usuario_id = ?');
    $statement->execute([$obraId, currentUserId()]);
}
$obra = $statement->fetch();
if (!$obra) {
    http_response_code(404);
    exit('Projeto não encontrado.');
}

// Somente a versão atual de cada prancha
$statement = $db->prepare(
    'SELECT p.id, p.titulo, p.descricao, p.arquivo, p.mime_type, p.versao, p.tamanho FROM obra_plantas p '
    . 'WHERE p.obra_id = ? AND p.versao = (SELECT MAX(p2.versao) FROM obra_plantas p2 WHERE p2.obra_id = p.obra_id AND p2.titulo = p.titulo)'
);
$statement->execute([$obraId]);
$plantas = $statement->fetchAll();
usort($plantas, static fn (array $a, array $b): int => strnatcasecmp((string) $a['titulo'], (string) $b['titulo']));
$plantas = array_slice($plantas, 0, 24);

/** Análise de uma prancha em PDF, guardada ao lado do arquivo para não reler o PDF a cada exame. */
$analisar = static function (array $planta): ?array {
    if ($planta['mime_type'] !== 'application/pdf') {
        return null;
    }
    $raiz = realpath(UPLOAD_DIR);
    $arquivo = $raiz !== false ? realpath($raiz . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, (string) $planta['arquivo'])) : false;
    if ($arquivo === false || !str_starts_with($arquivo, $raiz . DIRECTORY_SEPARATOR) || !is_file($arquivo)) {
        return null;
    }
    $cache = $arquivo . '.analise.json';
    // refaz a leitura se o PDF ou as regras do analisador mudaram depois do cache
    $regras = (string) (new ReflectionClass(PlantaAnalyzer::class))->getFileName();
    if (is_file($cache) && filemtime($cache) >= max(filemtime($arquivo), (int) @filemtime($regras))) {
        $salva = json_decode((string) file_get_contents($cache), true);
        if (is_array($salva)) {
            return $salva;
        }
    }
    try {
        $resultado = (new PlantaAnalyzer())->analisar((new PdfTextExtractor())->extrair($arquivo));
    } catch (RuntimeException $exception) {
        error_log('[plantas-ia] ' . $exception->getMessage());
        return null;
    }
    @file_put_contents($cache, json_encode($resultado, JSON_UNESCAPED_UNICODE), LOCK_EX);
    return $resultado;
};

if (isset($_GET['analise']) || isset($_GET['fusao'])) {
    @set_time_limit(180);
    session_write_close(); // libera a sessão: as pranchas são lidas em sequência pelo navegador
    if (isset($_GET['fusao'])) {
        $analises = array_values(array_filter(array_map($analisar, $plantas)));
        jsonResponse(['ok' => true, 'analise' => (new PlantaAnalyzer())->fundir($analises), 'lidas' => count($analises)]);
    }
    $id = (int) $_GET['analise'];
    foreach ($plantas as $planta) {
        if ((int) $planta['id'] === $id) {
            jsonResponse(['ok' => true, 'analise' => $analisar($planta)]);
        }
    }
    jsonResponse(['ok' => false, 'error' => 'Prancha não encontrada.'], 404);
}

$fatias = array_map(static fn (array $planta): array => [
    'id' => (int) $planta['id'],
    'titulo' => (string) $planta['titulo'],
    'descricao' => (string) ($planta['descricao'] ?? ''),
    'mime' => (string) $planta['mime_type'],
    'versao' => (int) $planta['versao'],
    'src' => APP_URL . '/planta-arquivo.php?id=' . (int) $planta['id'] . '&v=' . (int) $planta['versao'] . '-' . (int) $planta['tamanho'],
], $plantas);
$config = [
    'obra' => ['id' => (int) $obra['id'], 'nome' => (string) $obra['nome'], 'cliente' => (string) $obra['razao_social']],
    'fatias' => $fatias,
    'endpoint' => APP_URL . '/plantas-ia.php?obra_id=' . (int) $obra['id'],
    'pdfWorker' => 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js',
];
$voltar = APP_URL . '/plantas.php?obra_id=' . (int) $obra['id'];

pageHead('Junção das plantas', [APP_URL . '/assets/css/plantas-ia.css?v=' . rawurlencode(APP_VERSION)]);
?>
<div class="layout">
<?php sidebar('plantas'); ?>
<div class="main">
<?php topbar('Junção das plantas'); ?>
<main class="content">
<div class="catalog-heading">
    <div>
        <h1>Junção das plantas</h1>
        <p><?= sanitize($obra['nome']) ?> — <?= sanitize($obra['razao_social']) ?> · <?= count($fatias) ?> prancha(s)</p>
    </div>
    <div class="uniao-acoes">
        <a class="btn btn-secondary" href="<?= sanitize($voltar) ?>"><i class="fa-solid fa-arrow-left"></i> Plantas</a>
        <?php if ($fatias): ?>
        <button class="btn btn-secondary" type="button" id="uniaoRepetir" hidden><i class="fa-solid fa-rotate-right"></i> Repetir</button>
        <button class="btn btn-primary" type="button" id="uniaoBaixar" hidden><i class="fa-solid fa-download"></i> Baixar imagem unida</button>
        <?php endif; ?>
    </div>
</div>

<?php if (!$fatias): ?>
<div class="empty-state">
    <span class="empty-icon"><i class="fa-regular fa-map"></i></span>
    <h2>Nenhuma prancha nesta obra</h2>
    <p>Publique as plantas da obra para montar a junção.</p>
</div>
<?php else: ?>
<section class="card uniao-card">
    <header class="card-header">
        <h2>Montagem</h2>
        <ol class="uniao-etapas" id="uniaoEtapas">
            <li data-etapa="1">Recebendo</li>
            <li data-etapa="2">Lendo</li>
            <li data-etapa="3">Unindo</li>
            <li data-etapa="4">Pronto</li>
        </ol>
    </header>
    <div class="uniao-palco">
        <svg id="uniaoSvg" viewBox="0 0 1600 900" role="img" aria-label="Animação da junção das plantas de <?= sanitize($obra['nome']) ?>">
            <defs>
                <pattern id="uniaoGrade" width="40" height="40" patternUnits="userSpaceOnUse"><path d="M40 0H0V40" fill="none" class="uniao-grade"/></pattern>
                <linearGradient id="uniaoFaixa" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0" class="uniao-faixa-0"/><stop offset=".85" class="uniao-faixa-1"/><stop offset="1" class="uniao-faixa-2"/>
                </linearGradient>
                <linearGradient id="uniaoBrilho" x1="0" y1="0" x2="1" y2="0">
                    <stop offset="0" stop-color="#fff" stop-opacity="0"/><stop offset=".5" stop-color="#fff" stop-opacity=".55"/><stop offset="1" stop-color="#fff" stop-opacity="0"/>
                </linearGradient>
                <clipPath id="uniaoRecorte"><rect id="uniaoRecorteRect" x="0" y="0" width="0" height="0"/></clipPath>
            </defs>
            <rect width="1600" height="900" fill="url(#uniaoGrade)"/>
            <g id="uniaoLigacoes"></g>
            <g id="uniaoPecas"></g>
            <rect id="uniaoContorno" class="uniao-contorno" pathLength="100" x="0" y="0" width="0" height="0" rx="10"/>
            <g clip-path="url(#uniaoRecorte)"><rect id="uniaoLuz" x="-400" y="0" width="360" height="900" fill="url(#uniaoBrilho)" opacity="0"/></g>
        </svg>
    </div>
    <footer class="uniao-rodape">
        <div class="uniao-progresso"><span id="uniaoProgresso"></span></div>
        <p id="uniaoStatus">Preparando as pranchas…</p>
    </footer>
</section>

<section class="catalog-metrics uniao-metricas" aria-label="Dados extraídos das pranchas">
    <div class="catalog-metric"><strong id="uniaoArea">—</strong><span>Área construída (m²)</span></div>
    <div class="catalog-metric"><strong id="uniaoPavimentos">—</strong><span>Pavimentos</span></div>
    <div class="catalog-metric"><strong id="uniaoAmbientes">—</strong><span>Ambientes</span></div>
    <div class="catalog-metric"><strong id="uniaoEsquadrias">—</strong><span>Esquadrias</span></div>
</section>
<p class="uniao-aviso" id="uniaoAviso" hidden></p>
<?php endif; ?>
</main></div></div>
<script type="application/json" id="uniaoConfig"><?= json_encode($config, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<?php pageFoot($fatias ? ['https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js', APP_URL . '/assets/js/plantas-ia.js?v=' . rawurlencode(APP_VERSION)] : []); ?>
