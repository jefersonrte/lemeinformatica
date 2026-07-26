<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/session.php';

apply_page_security_headers();
$currentUser = require_api_page_login();
$csrfToken = api_csrf_token();
$canWrite = in_array($currentUser['perfil'], ['admin', 'operador'], true);
$canManageUsers = $currentUser['perfil'] === 'admin';
$isViewer = $currentUser['perfil'] === 'visualizador';
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Painel - Leme Informatica</title>
    <link rel="stylesheet" href="frontend/css/painel.css?v=20260714-viewer-powerbi">
</head>
<body>
    <div
        id="appContext"
        data-csrf="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>"
        data-can-write="<?= $canWrite ? '1' : '0' ?>"
        data-profile="<?= htmlspecialchars($currentUser['perfil'], ENT_QUOTES, 'UTF-8') ?>"
        hidden
    ></div>

    <div class="app-shell<?= $isViewer ? ' viewer-shell' : '' ?>">
        <header class="topbar">
            <div class="brand-block">
                <span class="brand-mark" aria-hidden="true">LI</span>
                <div>
                    <p class="eyebrow">Leme Informatica</p>
                    <h1><?= $isViewer ? 'Dashboard' : 'Animais e alimentos' ?></h1>
                </div>
            </div>

            <div class="account-block">
                <div class="account-copy">
                    <strong><?= htmlspecialchars($currentUser['nome'], ENT_QUOTES, 'UTF-8') ?></strong>
                    <span><?= htmlspecialchars(ucfirst($currentUser['perfil']), ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <a class="button secondary" href="pet/">Sistema Pet</a>
                <?php if ($canManageUsers): ?>
                    <a class="button secondary" href="usuarios-admin.php">Usuarios</a>
                <?php endif; ?>
                <form method="post" action="logout.php">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <button class="button secondary" type="submit">Sair</button>
                </form>
            </div>
        </header>

        <?php if ($canWrite): ?>
            <nav class="view-tabs" aria-label="Areas do sistema">
                <button class="view-tab active" type="button" data-view="dashboard" aria-selected="true">Dashboard</button>
                <button class="view-tab" type="button" data-view="animals" aria-selected="false">Cadastrar animal</button>
                <button class="view-tab" type="button" data-view="foods" aria-selected="false">Cadastrar alimento</button>
            </nav>
        <?php endif; ?>

        <main>
            <section class="app-view" data-view-panel="dashboard">
                <div class="section-heading">
                    <div>
                        <h2>Totais do banco</h2>
                        <p id="dashboardStatus">Carregando informacoes...</p>
                    </div>
                    <button id="refreshDashboard" class="button" type="button">Atualizar</button>
                </div>

                <div class="metric-grid">
                    <article class="metric blue">
                        <span>Total de animais</span>
                        <strong id="totalAnimals">0</strong>
                    </article>
                    <article class="metric green">
                        <span>Total de alimentos</span>
                        <strong id="totalFoods">0</strong>
                    </article>
                    <article class="metric amber">
                        <span>Racas cadastradas</span>
                        <strong id="totalBreeds">0</strong>
                    </article>
                    <article class="metric coral">
                        <span>Categorias de alimentos</span>
                        <strong id="totalCategories">0</strong>
                    </article>
                </div>

                <?php if ($isViewer): ?>
                    <section class="viewer-report" aria-labelledby="viewerReportTitle">
                        <div class="viewer-report-heading">
                            <div>
                                <p class="report-kicker">Power BI</p>
                                <h2 id="viewerReportTitle">Distribuicao dos cadastros</h2>
                                <p>Graficos atualizados diretamente pelo banco principal.</p>
                            </div>
                            <span class="live-badge">Dados em tempo real</span>
                        </div>

                        <div class="viewer-chart-grid">
                            <article class="viewer-chart-panel">
                                <div class="chart-title-row">
                                    <h3>Animais por porte</h3>
                                    <span>Quantidade</span>
                                </div>
                                <div id="viewerSizeChart" class="viewer-bar-chart" aria-live="polite">
                                    <p class="chart-empty">Carregando grafico...</p>
                                </div>
                            </article>

                            <article class="viewer-chart-panel">
                                <div class="chart-title-row">
                                    <h3>Alimentos por categoria</h3>
                                    <span>Quantidade</span>
                                </div>
                                <div id="viewerCategoryChart" class="viewer-bar-chart" aria-live="polite">
                                    <p class="chart-empty">Carregando grafico...</p>
                                </div>
                            </article>
                        </div>
                    </section>

                    <div class="viewer-note">
                        <strong>Consulta atualizada</strong>
                        <span id="viewerUpdatedAt">Aguardando atualizacao.</span>
                    </div>
                <?php else: ?>
                    <div class="recent-grid">
                        <section class="panel">
                            <div class="panel-heading">
                                <h3>Animais recentes</h3>
                                <button class="text-button" type="button" data-open-view="animals">Novo animal</button>
                            </div>
                            <div class="table-wrap">
                                <table>
                                    <thead><tr><th>Nome</th><th>Raca</th><th>Porte</th></tr></thead>
                                    <tbody id="recentAnimals"><tr><td colspan="3">Carregando...</td></tr></tbody>
                                </table>
                            </div>
                        </section>

                        <section class="panel">
                            <div class="panel-heading">
                                <h3>Alimentos recentes</h3>
                                <button class="text-button" type="button" data-open-view="foods">Novo alimento</button>
                            </div>
                            <div class="table-wrap">
                                <table>
                                    <thead><tr><th>Nome</th><th>Categoria</th><th>Preco</th></tr></thead>
                                    <tbody id="recentFoods"><tr><td colspan="3">Carregando...</td></tr></tbody>
                                </table>
                            </div>
                        </section>
                    </div>
                <?php endif; ?>
            </section>

            <?php if ($canWrite): ?>
                <section class="app-view" data-view-panel="animals" hidden>
                    <div class="section-heading">
                        <div>
                            <h2>Cadastrar animal</h2>
                            <p>O registro sera salvo no banco principal.</p>
                        </div>
                        <p id="animalStatus" class="form-status" aria-live="polite"></p>
                    </div>

                    <form id="animalForm" class="entry-form">
                        <label>
                            Nome
                            <input id="animalName" type="text" maxlength="100" required placeholder="Ex.: Thor">
                        </label>
                        <label>
                            Raca
                            <input id="animalBreed" type="text" maxlength="100" required placeholder="Ex.: Vira-lata">
                        </label>
                        <label>
                            Porte
                            <select id="animalSize" required>
                                <option value="">Selecione</option>
                                <option value="Pequeno">Pequeno</option>
                                <option value="Medio">Medio</option>
                                <option value="Grande">Grande</option>
                            </select>
                        </label>
                        <button class="button primary-action" type="submit">Cadastrar animal</button>
                    </form>
                </section>

                <section class="app-view" data-view-panel="foods" hidden>
                    <div class="section-heading">
                        <div>
                            <h2>Cadastrar alimento</h2>
                            <p>Informe os dados comerciais do alimento.</p>
                        </div>
                        <p id="foodStatus" class="form-status" aria-live="polite"></p>
                    </div>

                    <form id="foodForm" class="entry-form food-form">
                        <label>
                            Nome
                            <input id="foodName" type="text" maxlength="120" required placeholder="Ex.: Arroz integral">
                        </label>
                        <label>
                            Categoria
                            <input id="foodCategory" type="text" maxlength="100" required placeholder="Ex.: Graos">
                        </label>
                        <label>
                            Unidade
                            <select id="foodUnit" required>
                                <option value="">Selecione</option>
                                <option value="kg">kg</option>
                                <option value="g">g</option>
                                <option value="un">un</option>
                                <option value="l">l</option>
                                <option value="ml">ml</option>
                                <option value="pacote">pacote</option>
                            </select>
                        </label>
                        <label>
                            Preco
                            <input id="foodPrice" type="number" min="0" step="0.01" required placeholder="0,00">
                        </label>
                        <button class="button primary-action" type="submit">Cadastrar alimento</button>
                    </form>
                </section>
            <?php endif; ?>
        </main>
    </div>

    <script src="frontend/js/painel.js?v=20260714-viewer-powerbi"></script>
</body>
</html>
