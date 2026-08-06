<?php
declare(strict_types=1);

require_once __DIR__ . '/lib.php';

date_default_timezone_set('America/Sao_Paulo');
session_set_cookie_params([
    'httponly' => true,
    'secure' => true,
    'samesite' => 'Strict',
]);
session_start();

if (empty($_SESSION['gov_csrf'])) {
    $_SESSION['gov_csrf'] = bin2hex(random_bytes(24));
}

$message = $_SESSION['gov_flash'] ?? null;
unset($_SESSION['gov_flash']);
$error = null;
$dashboard = [
    'total' => 0,
    'counts' => [
        'deputados' => 0,
        'proposicoes' => 0,
        'despesas' => 0,
        'municipios' => 0,
        'licitacoes' => 0,
        'licitacoesEmAndamento' => 0,
    ],
    'last' => null,
    'deputies' => [],
    'propositions' => [],
    'expenses' => [],
    'municipalities' => [],
    'states' => 0,
];

try {
    $pdo = govPdo();
    govEnsureSchema($pdo);

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $postedToken = (string) ($_POST['csrf_token'] ?? '');
        $password = (string) ($_POST['import_password'] ?? '');
        if (!hash_equals($_SESSION['gov_csrf'], $postedToken)) {
            throw new RuntimeException('A sessao expirou. Atualize a pagina e tente novamente.');
        }
        if (!hash_equals(govConfig()['import_password_sha256'], hash('sha256', $password))) {
            throw new RuntimeException('Senha de importacao incorreta.');
        }

        $collection = (string) ($_POST['collection'] ?? 'dados-publicos');
        if ($collection === 'licitacoes') {
            $city = trim((string) ($_POST['cidade'] ?? ''));
            $counts = govImportMunicipalProcurements($pdo, $city !== '' ? $city : null);
            $_SESSION['gov_flash'] = sprintf(
                'Licitacoes atualizadas: %d processos armazenados, sendo %d em andamento.',
                $counts['total'],
                $counts['emAndamento']
            );
        } else {
            $counts = govImportPublicData($pdo);
            $_SESSION['gov_flash'] = sprintf(
                'Importacao concluida: %d deputados, %d proposicoes, %d despesas recentes e %d municipios.',
                $counts['deputados'],
                $counts['proposicoes'],
                $counts['despesas'],
                $counts['municipios']
            );
        }
        header('Location: /gov/');
        exit;
    }

    $dashboard = govDashboardData($pdo);
} catch (Throwable $exception) {
    $error = $exception->getMessage();
    error_log('[GOV IMPORT] ' . $exception->getMessage());
}

function h($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function moneyBr($value): string
{
    return 'R$ ' . number_format((float) $value, 2, ',', '.');
}

$lastImport = $dashboard['last']['criado_em'] ?? null;
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex,nofollow">
  <meta name="theme-color" content="#06251a">
  <title>GOV SC | Central de dados publicos</title>
  <style>
    :root{color-scheme:dark;--bg:#04120d;--panel:#0a2118;--panel2:#071a13;--line:#1c5b43;--green:#36e59c;--text:#ecfff7;--muted:#93b9aa;--danger:#ff9b9b}*{box-sizing:border-box}html{scroll-behavior:smooth}body{margin:0;min-height:100vh;background:radial-gradient(circle at 80% 0,#0b3827 0,transparent 34%),linear-gradient(135deg,#03100b,#071b13);color:var(--text);font:15px/1.55 Inter,system-ui,-apple-system,"Segoe UI",sans-serif}.wrap{width:min(1180px,calc(100% - 32px));margin:auto;padding:42px 0 64px}.top{display:flex;align-items:flex-end;justify-content:space-between;gap:24px;margin-bottom:24px}.tag{display:inline-block;margin-bottom:8px;color:var(--green);font:700 12px/1.2 ui-monospace,monospace;letter-spacing:.18em}.top h1{margin:0;font-size:clamp(30px,5vw,50px);letter-spacing:-.05em}.top p{max-width:680px;margin:10px 0 0;color:var(--muted)}.badge{flex:0 0 auto;padding:8px 12px;border:1px solid var(--line);border-radius:999px;color:#bfffe2;background:#092319;font:700 12px ui-monospace,monospace}.stats{display:grid;grid-template-columns:repeat(7,1fr);gap:12px;margin:20px 0}.stat{padding:17px;border:1px solid #174d39;border-radius:13px;background:#071b14}.stat strong{display:block;color:var(--green);font-size:25px}.stat span{color:var(--muted);font-size:12px}.grid{display:grid;grid-template-columns:minmax(0,1.05fr) minmax(340px,.95fr);gap:18px}.card{border:1px solid var(--line);border-radius:18px;background:rgba(8,29,21,.92);box-shadow:0 24px 70px rgba(0,0,0,.24);padding:22px}.card h2{margin:0 0 6px;font-size:20px}.card>p{margin:0;color:var(--muted)}.form{display:grid;gap:11px;margin-top:18px}.form label{font-weight:700;font-size:13px}.form input,.form select{width:100%;min-height:46px;border:1px solid #25684e;border-radius:10px;background:#04130d;color:#fff;padding:10px 12px;font:inherit}.form input:focus,.form select:focus{outline:2px solid rgba(54,229,156,.28);border-color:var(--green)}button{min-height:47px;border:0;border-radius:10px;background:var(--green);color:#042016;padding:11px 16px;font:800 13px/1 Inter,system-ui;cursor:pointer;letter-spacing:.04em}button:hover{filter:brightness(1.08)}.notice{margin:0 0 18px;padding:12px 14px;border-radius:10px;border:1px solid #287858;background:#0b3223;color:#cffff0}.notice.error{border-color:#844;color:#ffd4d4;background:#321616}.security{margin-top:15px!important;padding-top:15px;border-top:1px solid #174d39;color:var(--muted);font-size:12px}.source{color:#a8f7d2}.collections{display:grid;gap:10px;margin-top:18px}.collection{display:grid;grid-template-columns:auto 1fr;gap:12px;align-items:start;padding:13px;border:1px solid #174b38;border-radius:12px;background:var(--panel2)}.collection b{display:grid;place-items:center;width:31px;height:31px;border-radius:9px;background:#0d3a29;color:var(--green);font:800 12px ui-monospace,monospace}.collection strong{display:block}.collection span{display:block;color:var(--muted);font-size:12px}.section{margin-top:18px}.section-head{display:flex;align-items:end;justify-content:space-between;gap:20px;margin-bottom:10px}.section-head h2{margin:0;font-size:18px}.section-head span{color:var(--muted);font-size:12px}.preview{display:grid;grid-template-columns:1fr 1fr;gap:9px}.item{padding:13px;border:1px solid #174b38;border-radius:12px;background:var(--panel2)}.item small{display:block;margin-bottom:5px;color:var(--green);font:700 11px ui-monospace,monospace}.item strong{display:block;font-size:13px;line-height:1.35}.item p{display:-webkit-box;overflow:hidden;-webkit-box-orient:vertical;-webkit-line-clamp:3;margin:5px 0 0;color:var(--muted);font-size:12px}.empty{grid-column:1/-1;padding:22px;border:1px dashed #275b47;border-radius:12px;text-align:center;color:var(--muted)}.footer{display:flex;flex-wrap:wrap;gap:8px 20px;margin-top:22px;padding-top:16px;border-top:1px solid #164532;color:var(--muted);font-size:12px}@media(max-width:1100px){.stats{grid-template-columns:repeat(4,1fr)}}@media(max-width:850px){.top{align-items:flex-start;flex-direction:column}.stats{grid-template-columns:1fr 1fr}.grid{grid-template-columns:1fr}.wrap{width:min(100% - 20px,1180px);padding-top:24px}.card{padding:18px}}@media(max-width:520px){.preview,.stats{grid-template-columns:1fr}.badge{display:none}}
  </style>
</head>
<body>
  <main class="wrap">
    <header class="top">
      <div>
        <span class="tag">LEME INFORMATICA / GOV SC</span>
        <h1>Central de dados publicos</h1>
        <p>Importacao manual de representantes, proposicoes, despesas, municipios e do espelho de licitacoes de Florianopolis e Sao Jose.</p>
      </div>
      <span class="badge">IMPORTACAO PROTEGIDA</span>
    </header>

    <?php if ($message): ?><div class="notice"><?= h($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="notice error"><?= h($error) ?></div><?php endif; ?>

    <section class="stats" aria-label="Resumo do banco">
      <div class="stat"><strong><?= (int) $dashboard['counts']['deputados'] ?></strong><span>deputados ativos</span></div>
      <div class="stat"><strong><?= (int) $dashboard['counts']['proposicoes'] ?></strong><span>proposicoes recentes</span></div>
      <div class="stat"><strong><?= (int) $dashboard['counts']['despesas'] ?></strong><span>lancamentos de despesas</span></div>
      <div class="stat"><strong><?= number_format((int) $dashboard['counts']['municipios'], 0, ',', '.') ?></strong><span>municipios do Brasil</span></div>
      <div class="stat"><strong><?= number_format((int) $dashboard['counts']['licitacoes'], 0, ',', '.') ?></strong><span>licitacoes armazenadas</span></div>
      <div class="stat"><strong><?= number_format((int) $dashboard['counts']['licitacoesEmAndamento'], 0, ',', '.') ?></strong><span>licitacoes em andamento</span></div>
      <div class="stat"><strong><?= $lastImport ? h(date('d/m H:i', strtotime($lastImport))) : '--' ?></strong><span>ultima importacao</span></div>
    </section>

    <section class="grid">
      <article class="card">
        <h2>Atualizar todas as colecoes</h2>
        <p>Uma unica acao consulta as fontes oficiais e publica um retrato consistente na API privada.</p>
        <form class="form" method="post" autocomplete="off">
          <input type="hidden" name="csrf_token" value="<?= h($_SESSION['gov_csrf']) ?>">
          <input type="hidden" name="collection" value="dados-publicos">
          <label for="import_password">Senha de importacao</label>
          <input id="import_password" name="import_password" type="password" required autocomplete="current-password" placeholder="Digite a senha administrativa">
          <button type="submit">IMPORTAR DADOS AGORA</button>
        </form>
        <p class="security">A importacao e manual, protegida por senha e token de sessao. O historico registra o resultado de cada tentativa.</p>
      </article>

      <article class="card">
        <h2>Atualizar licitacoes municipais</h2>
        <p>Baixa o cadastro completo e a lista em andamento dos portais de Florianopolis e Sao Jose.</p>
        <form class="form" method="post" autocomplete="off">
          <input type="hidden" name="csrf_token" value="<?= h($_SESSION['gov_csrf']) ?>">
          <input type="hidden" name="collection" value="licitacoes">
          <label for="tender_city">Cidade</label>
          <select id="tender_city" name="cidade" required>
            <option value="florianopolis">Florianopolis</option>
            <option value="sao-jose">Sao Jose</option>
          </select>
          <label for="tender_import_password">Senha de importacao</label>
          <input id="tender_import_password" name="import_password" type="password" required autocomplete="current-password" placeholder="Digite a senha administrativa">
          <button type="submit">BAIXAR LICITACOES AGORA</button>
        </form>
        <p class="security">Atualize uma cidade por vez. A pagina publica reune as duas copias locais com busca, filtros e paginacao.</p>
      </article>

      <article class="card" style="grid-column:1/-1">
        <h2>Exemplos disponiveis</h2>
        <p>As cinco colecoes seguem o mesmo padrao de armazenamento e entrega.</p>
        <div class="collections">
          <div class="collection"><b>01</b><div><strong>Representantes de SC</strong><span>Nome, partido, foto, e-mail, legislatura e registro oficial.</span></div></div>
          <div class="collection"><b>02</b><div><strong>Proposicoes recentes</strong><span>Projetos, requerimentos e pareceres com autoria vinculada a Santa Catarina nos ultimos 45 dias.</span></div></div>
          <div class="collection"><b>03</b><div><strong>Despesas parlamentares</strong><span>Os 8 lancamentos mais recentes de cada deputado no ano corrente, com fornecedor e comprovante.</span></div></div>
          <div class="collection"><b>04</b><div><strong>Municipios do Brasil</strong><span>Mais de 5 mil cadastros com codigo IBGE, UF e divisoes regionais oficiais.</span></div></div>
          <div class="collection"><b>05</b><div><strong>Licitacoes municipais</strong><span>Processos de Florianopolis e Sao Jose, incluindo objeto, edital, modalidade, situacao, prazos e link oficial.</span></div></div>
        </div>
      </article>
    </section>

    <section class="card section">
      <div class="section-head"><h2>Previa de municipios</h2><span><?= number_format((int) $dashboard['counts']['municipios'], 0, ',', '.') ?> registros em <?= (int) $dashboard['states'] ?> UFs</span></div>
      <div class="preview">
        <?php if (!$dashboard['municipalities']): ?>
          <div class="empty">Nenhum municipio importado. Execute a atualizacao do IBGE.</div>
        <?php else: foreach ($dashboard['municipalities'] as $municipality): ?>
          <article class="item">
            <small>CODIGO IBGE <?= (int) $municipality['id'] ?> / <?= h($municipality['uf_sigla']) ?></small>
            <strong><?= h($municipality['nome']) ?></strong>
            <p><?= h($municipality['uf_nome']) ?> &middot; <?= h($municipality['regiao_nome']) ?><br>Regiao imediata: <?= h($municipality['regiao_imediata_nome'] ?: 'nao informada') ?></p>
          </article>
        <?php endforeach; endif; ?>
      </div>
    </section>

    <section class="card section">
      <div class="section-head"><h2>Previa de proposicoes</h2><span>ate 8 registros mais recentes</span></div>
      <div class="preview">
        <?php if (!$dashboard['propositions']): ?>
          <div class="empty">Nenhuma proposicao importada. Execute a atualizacao manual.</div>
        <?php else: foreach ($dashboard['propositions'] as $proposition): ?>
          <article class="item">
            <small><?= h($proposition['sigla_tipo']) ?> <?= (int) $proposition['numero'] ?>/<?= (int) $proposition['ano'] ?></small>
            <strong><?= !empty($proposition['data_apresentacao']) ? h(date('d/m/Y H:i', strtotime($proposition['data_apresentacao']))) : 'Data nao informada' ?></strong>
            <p><?= h($proposition['ementa']) ?></p>
          </article>
        <?php endforeach; endif; ?>
      </div>
    </section>

    <section class="card section">
      <div class="section-head"><h2>Previa de despesas recentes</h2><span>amostra, nao representa o total anual</span></div>
      <div class="preview">
        <?php if (!$dashboard['expenses']): ?>
          <div class="empty">Nenhuma despesa importada. Execute a atualizacao manual.</div>
        <?php else: foreach ($dashboard['expenses'] as $expense): ?>
          <article class="item">
            <small><?= h($expense['deputado_nome']) ?> / <?= h($expense['sigla_partido']) ?></small>
            <strong><?= h(moneyBr($expense['valor_liquido'])) ?> &middot; <?= !empty($expense['data_documento']) ? h(date('d/m/Y', strtotime($expense['data_documento']))) : 'sem data' ?></strong>
            <p><?= h($expense['tipo_despesa']) ?><br><?= h($expense['fornecedor']) ?></p>
          </article>
        <?php endforeach; endif; ?>
      </div>
    </section>

    <footer class="footer"><span>Fontes oficiais:</span><a class="source" href="https://dadosabertos.camara.leg.br/swagger/api.html" target="_blank" rel="noreferrer">Camara dos Deputados</a><a class="source" href="https://servicodados.ibge.gov.br/api/docs/localidades" target="_blank" rel="noreferrer">API de Localidades do IBGE</a><a class="source" href="https://wbc.pmf.sc.gov.br/portal/Mural.aspx?nNmTela=E" target="_blank" rel="noreferrer">Compras de Florianopolis</a><a class="source" href="https://egov.paradigmabs.com.br/saojose/portal/Mural.aspx?nNmTela=E" target="_blank" rel="noreferrer">Compras de Sao Jose</a></footer>
  </main>
</body>
</html>
