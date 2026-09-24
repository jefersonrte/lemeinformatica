<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap/app.php';

use App\Domain\Estimativa\Classificador;
use App\Domain\Estimativa\Incc;
use App\Domain\Estimativa\ReferenciaService;
use App\Domain\Orcamento\OrcamentoCalculator;

requireAdmin();
$db = getDB();
$service = new ReferenciaService($db);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    try {
        if (($_POST['acao'] ?? '') === 'atualizar') {
            $service->atualizar(
                (int) ($_POST['id'] ?? 0),
                (string) ($_POST['tipologia'] ?? ''),
                OrcamentoCalculator::decimal($_POST['area'] ?? 0) ?: null,
                !empty($_POST['ativo'])
            );
            setFlash('success', 'Referência atualizada.');
        } elseif (($_POST['acao'] ?? '') === 'promover') {
            $service->promoverOrcamento((int) ($_POST['orcamento_id'] ?? 0), (string) ($_POST['tipologia'] ?? ''), OrcamentoCalculator::decimal($_POST['area'] ?? 0) ?: null);
            logAction('referencia_criada', 'orcamentos', (int) ($_POST['orcamento_id'] ?? 0));
            setFlash('success', 'Orçamento incluído na base de referência. As próximas prévias já o consideram.');
        }
    } catch (InvalidArgumentException $exception) {
        setFlash('error', $exception->getMessage());
    }
    redirect(APP_URL . '/admin/referencias.php' . (isset($_GET['t']) ? '?t=' . urlencode((string) $_GET['t']) : ''));
}

$filtro = (string) ($_GET['t'] ?? '');
$where = isset(Classificador::TIPOLOGIAS[$filtro]) ? 'WHERE r.tipologia = ' . $db->quote($filtro) : '';
$referencias = $db->query(
    "SELECT r.*, (SELECT COUNT(*) FROM referencia_itens ri WHERE ri.referencia_id = r.id) AS itens FROM referencias r $where ORDER BY r.tipologia, r.area_construida IS NULL, r.codigo"
)->fetchAll();
$resumo = $db->query('SELECT tipologia, COUNT(*) AS total, SUM(ativo = 1 AND area_construida > 0) AS uteis FROM referencias GROUP BY tipologia')->fetchAll(PDO::FETCH_UNIQUE);
$aprovados = $db->query(
    "SELECT o.id, o.titulo, ob.nome AS obra, ob.area_construida FROM orcamentos o JOIN obras ob ON ob.id = o.obra_id "
    . "WHERE o.status = 'aprovado' AND NOT EXISTS (SELECT 1 FROM referencias r WHERE r.orcamento_id = o.id) ORDER BY o.criado_em DESC LIMIT 100"
)->fetchAll();
$precos = $db->query('SELECT fonte, localidade, data_base, COUNT(*) AS total FROM precos_base GROUP BY fonte, localidade, data_base')->fetchAll();

pageHead('Base de referência');
?>
<div class="layout">
<?php sidebar('referencias'); ?>
<div class="main">
<?php topbar('Base de referência da prévia'); ?>
<div class="content">
<?php flashMessage(); ?>

<div class="card mb-4">
    <div class="card-body">
        <p class="text-sm">A prévia de obra compara a nova obra com estes orçamentos reais (sem nomes de clientes). Somente referências <strong>ativas e com área</strong> entram no cálculo de custo por m²; todas entram no banco de preços para itens de quantitativos.</p>
        <div class="flex gap-2 mt-3" style="flex-wrap:wrap">
            <a href="?" class="btn btn-sm <?= $filtro === '' ? 'btn-primary' : 'btn-outline' ?>">Todas (<?= count($referencias) ?>)</a>
            <?php foreach (Classificador::TIPOLOGIAS as $chave => $rotulo): ?>
            <a href="?t=<?= $chave ?>" class="btn btn-sm <?= $filtro === $chave ? 'btn-primary' : 'btn-outline' ?>"><?= sanitize($rotulo) ?> (<?= (int) ($resumo[$chave]['uteis'] ?? 0) ?>/<?= (int) ($resumo[$chave]['total'] ?? 0) ?>)</a>
            <?php endforeach; ?>
        </div>
        <?php foreach ($precos as $p): ?>
        <p class="text-xs text-muted mt-2">Banco de preços: <?= sanitize($p['fonte']) ?> <?= sanitize((string) $p['localidade']) ?> <?= $p['data_base'] ? date('m/Y', strtotime($p['data_base'])) : '' ?> — <?= number_format((int) $p['total'], 0, ',', '.') ?> itens.</p>
        <?php endforeach; ?>
    </div>
</div>

<div class="card mb-4">
    <div class="table-wrap"><table>
        <thead><tr><th>Referência</th><th>Tipologia</th><th>Área (m²)</th><th>Data-base</th><th>Custo direto</th><th>R$/m² atualizado</th><th>Itens</th><th>Ativa</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($referencias as $r):
            $fator = Incc::fator($r['data_base']);
            $custoM2 = $r['area_construida'] > 0 ? $r['total_direto'] * $fator / $r['area_construida'] : null; ?>
        <tr>
            <td><strong><?= sanitize($r['codigo']) ?></strong><div class="text-xs text-muted"><?= sanitize($r['titulo']) ?> · <?= $r['origem'] === 'orcamento' ? 'orçamento do sistema' : 'acervo' ?></div></td>
            <td>
                <select name="tipologia" form="ref<?= $r['id'] ?>" class="form-control" style="font-size:.8rem;padding:4px 6px;min-width:170px">
                    <?php foreach (Classificador::TIPOLOGIAS as $chave => $rotulo): ?><option value="<?= $chave ?>" <?= $r['tipologia'] === $chave ? 'selected' : '' ?>><?= sanitize($rotulo) ?></option><?php endforeach; ?>
                </select>
            </td>
            <td><input type="number" name="area" form="ref<?= $r['id'] ?>" value="<?= $r['area_construida'] !== null ? (float) $r['area_construida'] : '' ?>" step="any" min="0" class="form-control" style="width:100px;font-size:.8rem;padding:4px 6px"></td>
            <td class="text-sm"><?= $r['data_base'] ? date('m/Y', strtotime($r['data_base'])) : '—' ?><div class="text-xs text-muted">INCC ×<?= number_format($fator, 3, ',', '.') ?></div></td>
            <td class="text-sm">R$ <?= number_format((float) $r['total_direto'], 2, ',', '.') ?></td>
            <td class="font-bold"><?= $custoM2 ? 'R$ ' . number_format($custoM2, 2, ',', '.') : '—' ?></td>
            <td class="text-sm"><?= (int) $r['itens'] ?></td>
            <td><input type="checkbox" name="ativo" value="1" form="ref<?= $r['id'] ?>" <?= $r['ativo'] ? 'checked' : '' ?>></td>
            <td>
                <form method="post" id="ref<?= $r['id'] ?>">
                    <input type="hidden" name="csrf_token" value="<?= csrf() ?>">
                    <input type="hidden" name="acao" value="atualizar">
                    <input type="hidden" name="id" value="<?= $r['id'] ?>">
                    <button class="btn btn-sm btn-outline" type="submit">Salvar</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$referencias): ?><tr><td colspan="9" class="text-center text-muted">Nenhuma referência carregada. Execute as migrações.</td></tr><?php endif; ?>
        </tbody>
    </table></div>
</div>

<div class="card">
    <div class="card-header"><h2>Incluir orçamento aprovado na base</h2></div>
    <form method="post" class="card-body">
        <input type="hidden" name="csrf_token" value="<?= csrf() ?>">
        <input type="hidden" name="acao" value="promover">
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Orçamento aprovado</label>
                <select name="orcamento_id" class="form-control" required>
                    <option value="">Selecione...</option>
                    <?php foreach ($aprovados as $a): ?><option value="<?= $a['id'] ?>"><?= sanitize($a['titulo']) ?> — <?= sanitize($a['obra']) ?><?= $a['area_construida'] ? ' (' . number_format((float) $a['area_construida'], 0, ',', '.') . ' m²)' : '' ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Tipologia</label>
                <select name="tipologia" class="form-control"><?php foreach (Classificador::TIPOLOGIAS as $chave => $rotulo): ?><option value="<?= $chave ?>"><?= sanitize($rotulo) ?></option><?php endforeach; ?></select>
            </div>
            <div class="form-group">
                <label class="form-label">Área construída (m²)</label>
                <input type="number" name="area" class="form-control" step="any" min="0" placeholder="Usa a área da obra">
            </div>
        </div>
        <button class="btn btn-primary" type="submit">Incluir na base</button>
    </form>
</div>

</div></div></div>
<?php pageFoot(); ?>
