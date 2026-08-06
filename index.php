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
        'name' => 'Clinica Pet',
        'description' => 'Atendimentos, prontuarios, internacoes, tutores e animais.',
        'url' => 'https://lemeinformatica.com.br/pet/',
        'category' => 'Operacao veterinaria',
        'accent' => 'mint',
        'local' => true,
    ],
    [
        'name' => 'Dashboard Pet',
        'description' => 'Indicadores gerenciais e visao consolidada da operacao Pet.',
        'url' => 'https://lemesolucoesemti.com.br/pet/',
        'category' => 'Dados e BI',
        'accent' => 'cyan',
        'local' => false,
    ],
    [
        'name' => 'Relatorios Power BI',
        'description' => 'Graficos, indicadores e bases operacionais integradas a API principal.',
        'url' => 'https://lemesolucoesemti.com.br/powerbi/',
        'category' => 'Analise de dados',
        'accent' => 'violet',
        'local' => false,
    ],
    [
        'name' => 'Dados Publicos SC',
        'description' => 'Consulta de deputados, proposicoes e dados legislativos de Santa Catarina.',
        'url' => 'https://lemeinformatica.com.br/gov/',
        'category' => 'Governo aberto',
        'accent' => 'yellow',
        'local' => true,
    ],
    [
        'name' => 'Brasil em Dados',
        'description' => 'Painel nacional de dados publicos para pesquisa e acompanhamento.',
        'url' => 'https://lemesolucoesemti.com.br/gov/',
        'category' => 'Inteligencia publica',
        'accent' => 'coral',
        'local' => false,
    ],
    [
        'name' => 'Investimentos',
        'description' => 'Monitoramento do mercado brasileiro, ativos e sinais operacionais.',
        'url' => 'https://lemesolucoesemti.com.br/invest/',
        'category' => 'Mercado financeiro',
        'accent' => 'blue',
        'local' => false,
    ],
    [
        'name' => 'Nuvem / Nextcloud',
        'description' => 'Acesso central aos arquivos compartilhados e ao ambiente Nextcloud.',
        'url' => 'https://lemesolucoesemti.com.br/cloud/',
        'category' => 'Arquivos e colaboracao',
        'accent' => 'cyan',
        'local' => false,
    ],
    [
        'name' => 'Administracao e API',
        'description' => 'Acesso protegido aos cadastros, usuarios e servicos de integracao.',
        'url' => 'https://lemeinformatica.com.br/login.php',
        'category' => 'Gestao central',
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
    <link rel="stylesheet" href="frontend/css/home.css">
    <script src="frontend/js/home.js" defer></script>
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
            <p class="intro-copy">Acesse sistemas, paineis e servicos integrados. Os ambientes protegidos solicitarao seu login antes de abrir.</p>
            <a class="primary-action" href="https://lemeinformatica.com.br/pet/">Abrir Clinica Pet</a>
        </section>

        <section class="projects" aria-labelledby="projects-title">
            <div class="section-heading">
                <div>
                    <p class="eyebrow">Menu principal</p>
                    <h2 id="projects-title">Projetos disponiveis</h2>
                </div>
                <p><span class="status-dot" aria-hidden="true"></span> Rotas monitoradas</p>
            </div>

            <nav class="project-grid" aria-label="Todos os projetos Leme">
                <?php foreach ($projects as $project): ?>
                    <a class="project-link accent-<?= htmlspecialchars($project['accent'], ENT_QUOTES, 'UTF-8') ?>"
                       href="<?= htmlspecialchars($project['url'], ENT_QUOTES, 'UTF-8') ?>">
                        <span class="project-meta">
                            <?= htmlspecialchars($project['category'], ENT_QUOTES, 'UTF-8') ?>
                            <?php if ($project['local']): ?><b>Neste dominio</b><?php endif; ?>
                        </span>
                        <strong><?= htmlspecialchars($project['name'], ENT_QUOTES, 'UTF-8') ?></strong>
                        <span class="project-description"><?= htmlspecialchars($project['description'], ENT_QUOTES, 'UTF-8') ?></span>
                        <span class="project-open">Abrir projeto <span aria-hidden="true">-&gt;</span></span>
                    </a>
                <?php endforeach; ?>
            </nav>
        </section>
    </main>

    <footer>
        <span>Leme Informatica</span>
        <span>Ambientes integrados e protegidos</span>
        <span id="current-year"></span>
    </footer>
</body>
</html>
