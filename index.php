<?php
declare(strict_types=1);

$scriptPath = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
if ($scriptPath === '/pet/index.php' || $scriptPath === '/pet/') {
    define('PET_PUBLIC_BASE', '/pet/pet/');
    require __DIR__ . '/pet/index.php';
    exit;
}

header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Permissions-Policy: camera=(), microphone=(), geolocation=()");
header("Content-Security-Policy: default-src 'self'; img-src 'self'; style-src 'self'; script-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'");

$projects = [
    [
        'name' => 'Orçamentista',
        'description' => 'Versão atual recomendada, com login recuperável, dashboard financeiro e visualizador animado de plantas.',
        'url' => 'https://lemeinformatica.com.br/orca-funcional-v1.2.1/',
        'category' => 'Orçamentos e obras',
        'module' => 'orca',
        'version' => 'v1.2.1',
        'status' => 'Recomendado',
        'status_class' => 'recommended',
        'accent' => 'mint',
        'local' => true,
    ],
    [
        'name' => 'Orçamentista funcional',
        'description' => 'Versão funcional anterior preservada para consulta e continuidade do histórico.',
        'url' => 'https://lemeinformatica.com.br/orca-funcional-v1.2.0/',
        'category' => 'Orçamentos e obras',
        'module' => 'orca',
        'version' => 'v1.2.0',
        'status' => 'Funcional',
        'status_class' => 'stable',
        'accent' => 'blue',
        'local' => true,
    ],
    [
        'name' => 'Orçamentista visual',
        'description' => 'Demonstração pública do dashboard e da galeria animada, com dados fictícios.',
        'url' => 'https://lemeinformatica.com.br/orca-v1.2.0/',
        'category' => 'Demonstração',
        'module' => 'orca',
        'version' => 'v1.2.0',
        'status' => 'Demonstração',
        'status_class' => 'preview',
        'accent' => 'cyan',
        'local' => true,
    ],
    [
        'name' => 'Orçamentista original',
        'description' => 'Aplicação original criada pela Claude, mantida sem alterações como referência histórica.',
        'url' => 'https://lemeinformatica.com.br/orca/',
        'category' => 'Versão histórica',
        'module' => 'orca',
        'version' => 'v1.0.0',
        'status' => 'Legado',
        'status_class' => 'legacy',
        'accent' => 'violet',
        'local' => true,
    ],
    [
        'name' => 'Clinica Pet',
        'description' => 'Atendimentos, prontuarios, internacoes, tutores e animais.',
        'url' => 'https://lemeinformatica.com.br/pet/',
        'category' => 'Operacao veterinaria',
        'module' => 'pet',
        'version' => 'v1.1.1',
        'status' => 'Funcional',
        'status_class' => 'stable',
        'accent' => 'mint',
        'local' => true,
    ],
    [
        'name' => 'Dashboard Pet',
        'description' => 'Indicadores gerenciais e visao consolidada da operacao Pet.',
        'url' => 'https://lemesolucoesemti.com.br/pet/',
        'category' => 'Dados e BI',
        'module' => 'pet',
        'version' => 'v1.1.1',
        'status' => 'Protegido',
        'status_class' => 'protected',
        'accent' => 'cyan',
        'local' => false,
    ],
    [
        'name' => 'Relatorios Power BI',
        'description' => 'Graficos, indicadores e bases operacionais integradas a API principal.',
        'url' => 'https://lemesolucoesemti.com.br/powerbi/',
        'category' => 'Analise de dados',
        'module' => 'data',
        'version' => 'Produção',
        'status' => 'Protegido',
        'status_class' => 'protected',
        'accent' => 'violet',
        'local' => false,
    ],
    [
        'name' => 'Dados Publicos SC',
        'description' => 'Consulta de deputados, proposicoes e dados legislativos de Santa Catarina.',
        'url' => 'https://lemeinformatica.com.br/gov/',
        'category' => 'Governo aberto',
        'module' => 'data',
        'version' => 'Produção',
        'status' => 'Público',
        'status_class' => 'public',
        'accent' => 'yellow',
        'local' => true,
    ],
    [
        'name' => 'Brasil em Dados',
        'description' => 'Painel nacional de dados publicos para pesquisa e acompanhamento.',
        'url' => 'https://lemesolucoesemti.com.br/gov/',
        'category' => 'Inteligencia publica',
        'module' => 'data',
        'version' => 'Produção',
        'status' => 'Público',
        'status_class' => 'public',
        'accent' => 'coral',
        'local' => false,
    ],
    [
        'name' => 'Investimentos',
        'description' => 'Monitoramento do mercado brasileiro, ativos e sinais operacionais.',
        'url' => 'https://lemesolucoesemti.com.br/invest/',
        'category' => 'Mercado financeiro',
        'module' => 'data',
        'version' => 'Produção',
        'status' => 'Funcional',
        'status_class' => 'stable',
        'accent' => 'blue',
        'local' => false,
    ],
    [
        'name' => 'Nuvem / Nextcloud',
        'description' => 'Acesso central aos arquivos compartilhados e ao ambiente Nextcloud.',
        'url' => 'https://lemesolucoesemti.com.br/cloud/',
        'category' => 'Arquivos e colaboracao',
        'module' => 'services',
        'version' => 'v34.0.2',
        'status' => 'Protegido',
        'status_class' => 'protected',
        'accent' => 'cyan',
        'local' => false,
    ],
    [
        'name' => 'Administracao e API',
        'description' => 'Acesso protegido aos cadastros, usuarios e servicos de integracao.',
        'url' => 'https://lemeinformatica.com.br/pet/login.php',
        'category' => 'Gestao central',
        'module' => 'services',
        'version' => 'v1.3.0',
        'status' => 'Protegido',
        'status_class' => 'protected',
        'accent' => 'blue',
        'local' => true,
    ],
];
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#07110d">
    <meta name="description" content="Central de projetos da Leme Informatica">
    <title>Projetos | Leme Informatica</title>
    <link rel="stylesheet" href="frontend/css/home.css?v=1.4.0">
    <script src="frontend/js/home.js?v=1.4.0" defer></script>
</head>
<body data-site="informatica">
    <img class="scene" src="frontend/assets/matrix-city-v2.webp" alt="" aria-hidden="true">
    <div class="backdrop" aria-hidden="true"></div>

    <header class="site-header">
        <a class="brand" href="/" aria-label="Pagina inicial da Leme Informatica">
            <span class="brand-mark">LI</span>
            <span>
                <strong>Leme Informatica</strong>
                <small>Central de projetos</small>
            </span>
        </a>

        <button class="menu-toggle" type="button" aria-expanded="false" aria-controls="domain-nav">Menu</button>
        <nav class="domain-nav" id="domain-nav" aria-label="Empresas Leme">
            <a href="https://lemeinformatica.com.br/" aria-current="page">Leme Informatica</a>
            <a href="https://lemesolucoesemti.com.br/">Leme Solucoes em TI</a>
        </nav>
    </header>

    <main>
        <section class="intro" aria-labelledby="page-title">
            <p class="eyebrow">Tecnologia aplicada a operacao</p>
            <h1 id="page-title">Todos os projetos Leme em um so lugar.</h1>
            <p class="intro-copy">Escolha o módulo e a versão que deseja acessar. Ambientes históricos permanecem disponíveis e os protegidos solicitarão seu login.</p>
            <div class="intro-actions">
                <a class="primary-action" href="https://lemeinformatica.com.br/orca-funcional-v1.2.1/">Abrir Orçamentista v1.2.1</a>
                <a class="secondary-action" href="#projects-title">Escolher outro módulo</a>
            </div>
        </section>

        <section class="projects" aria-labelledby="projects-title">
            <div class="section-heading">
                <div>
                    <p class="eyebrow">Menu principal</p>
                    <h2 id="projects-title">Projetos disponiveis</h2>
                </div>
                <p><span class="status-dot" aria-hidden="true"></span> <?= count($projects) ?> acessos verificados</p>
            </div>

            <div class="project-controls" aria-label="Filtros de projetos">
                <label class="project-search" for="project-search">
                    <span>Buscar módulo ou versão</span>
                    <input id="project-search" type="search" placeholder="Ex.: Orca, Pet, v1.2.0" autocomplete="off">
                </label>
                <div class="project-filters" role="group" aria-label="Filtrar por módulo">
                    <button class="filter-button is-active" type="button" data-project-filter="all">Todos</button>
                    <button class="filter-button" type="button" data-project-filter="orca">Orçamentista</button>
                    <button class="filter-button" type="button" data-project-filter="pet">Pet</button>
                    <button class="filter-button" type="button" data-project-filter="data">Dados</button>
                    <button class="filter-button" type="button" data-project-filter="services">Serviços</button>
                </div>
                <p class="project-result" aria-live="polite"><strong id="project-count"><?= count($projects) ?></strong> opções disponíveis</p>
            </div>

            <nav class="project-grid" aria-label="Todos os projetos Leme">
                <?php foreach ($projects as $project): ?>
                    <a class="project-link accent-<?= htmlspecialchars($project['accent'], ENT_QUOTES, 'UTF-8') ?>"
                       href="<?= htmlspecialchars($project['url'], ENT_QUOTES, 'UTF-8') ?>"
                       data-project-card
                       data-module="<?= htmlspecialchars($project['module'], ENT_QUOTES, 'UTF-8') ?>"
                       data-search="<?= htmlspecialchars(implode(' ', [$project['name'], $project['description'], $project['category'], $project['version'], $project['status']]), ENT_QUOTES, 'UTF-8') ?>">
                        <span class="project-meta">
                            <?= htmlspecialchars($project['category'], ENT_QUOTES, 'UTF-8') ?>
                            <?php if ($project['local']): ?><b>Neste dominio</b><?php endif; ?>
                        </span>
                        <span class="project-badges">
                            <b class="version-badge"><?= htmlspecialchars($project['version'], ENT_QUOTES, 'UTF-8') ?></b>
                            <b class="status-badge status-<?= htmlspecialchars($project['status_class'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($project['status'], ENT_QUOTES, 'UTF-8') ?></b>
                        </span>
                        <strong><?= htmlspecialchars($project['name'], ENT_QUOTES, 'UTF-8') ?></strong>
                        <span class="project-description"><?= htmlspecialchars($project['description'], ENT_QUOTES, 'UTF-8') ?></span>
                        <span class="project-open">Abrir projeto <span aria-hidden="true">-&gt;</span></span>
                    </a>
                <?php endforeach; ?>
            </nav>
            <div class="project-empty" hidden>Nenhum módulo encontrado com esse filtro.</div>
        </section>
    </main>

    <footer>
        <span>Leme Informatica</span>
        <span>Ambientes integrados e protegidos</span>
        <span id="current-year"></span>
    </footer>
</body>
</html>
