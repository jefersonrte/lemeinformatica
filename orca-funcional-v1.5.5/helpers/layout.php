<?php
// helpers/layout.php — funções de layout reutilizáveis

function pageHead(string $title, array $extraCss = []): void {
    $base = defined('APP_URL') ? APP_URL : '';
    $tema = \App\Support\Theme::current();
    $info = \App\Support\Theme::get($tema);
    $disposicao = \App\Support\Layout::current();
    $extraHtml = '';
    if ($tema === \App\Support\Theme::CUSTOM) {
        $cor = \App\Support\Theme::custom();
        $extraHtml = ' data-tone="' . $cor['tom'] . '" style="--h1:' . $cor['h1'] . ';--h2:' . $cor['h2'] . '"';
    }
    echo '<!DOCTYPE html><html lang="pt-BR" data-theme="' . $tema . '" data-layout="' . $disposicao . '"' . $extraHtml . '><head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<meta name="theme-color" content="' . $info['meta'] . '">
<meta name="color-scheme" content="' . $info['esquema'] . '">
<title>' . htmlspecialchars($title) . ' · ' . APP_NAME . '</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap">
<link rel="stylesheet" href="' . $base . '/assets/css/style.css?v=' . rawurlencode(APP_VERSION) . '">
<link rel="stylesheet" href="' . $base . '/assets/css/consulta.css?v=' . rawurlencode(APP_VERSION) . '">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
';
    foreach ($extraCss as $css) {
        echo '<link rel="stylesheet" href="' . htmlspecialchars($css) . '">';
    }
    echo '</head><body>';
}

/** Botão "Aparência": temas prontos, degradê personalizado e disposições do menu (cookies via app.js). */
function themePicker(string $id = 'themeMenu', bool $comLayout = true): string {
    $atual = \App\Support\Theme::current();
    $cor = \App\Support\Theme::custom();
    $id = htmlspecialchars($id, ENT_QUOTES, 'UTF-8');
    $html = '<div class="theme-picker" data-theme-picker>'
        . '<button class="theme-toggle" type="button" aria-haspopup="true" aria-expanded="false" aria-controls="' . $id . '" title="Aparência do site">'
        . '<span class="theme-dot" aria-hidden="true"></span><span>Aparência</span></button>'
        . '<div class="theme-menu' . ($comLayout ? ' has-layouts' : '') . '" id="' . $id . '">'
        . '<div class="appearance-col">'
        . '<div class="theme-menu-title" id="' . $id . 'Tema">Tema</div><div role="radiogroup" aria-labelledby="' . $id . 'Tema">';
    foreach (\App\Support\Theme::all() as $chave => $tema) {
        $a = $tema['amostra'];
        $html .= '<button type="button" class="theme-option" role="radio" aria-checked="' . ($chave === $atual ? 'true' : 'false') . '"'
            . ' data-theme-option="' . $chave . '" data-theme-meta="' . $tema['meta'] . '" data-theme-scheme="' . $tema['esquema'] . '">'
            . '<span class="theme-swatch" aria-hidden="true" style="--sw-bg:' . $a['bg'] . ';--sw-nav:' . $a['nav'] . ';--sw-primary:' . $a['primary'] . ';--sw-accent:' . $a['accent'] . '"></span>'
            . '<span><strong>' . htmlspecialchars($tema['nome']) . '</strong><small>' . htmlspecialchars($tema['descricao']) . '</small></span>'
            . '<i class="fa-solid fa-check" aria-hidden="true"></i></button>';
    }
    $custom = \App\Support\Theme::CUSTOM;
    $html .= '<button type="button" class="theme-option" role="radio" aria-checked="' . ($atual === $custom ? 'true' : 'false') . '" data-theme-option="' . $custom . '">'
        . '<span class="theme-swatch custom-swatch" aria-hidden="true" data-custom-preview style="--h1:' . $cor['h1'] . ';--h2:' . $cor['h2'] . '"></span>'
        . '<span><strong>Personalizado</strong><small>Monte seu degradê abaixo</small></span>'
        . '<i class="fa-solid fa-check" aria-hidden="true"></i></button>'
        . '</div>';

    $html .= '<div class="custom-theme" data-custom-theme data-h1="' . $cor['h1'] . '" data-h2="' . $cor['h2'] . '" data-tone="' . $cor['tom'] . '">'
        . '<div class="gradient-presets" aria-label="Degradês prontos">';
    foreach (\App\Support\Theme::GRADIENTES as $nome => [$h1, $h2]) {
        $html .= '<button type="button" class="gradient-chip" data-gradient="' . $h1 . ',' . $h2 . '" title="' . htmlspecialchars($nome) . '" aria-label="Degradê ' . htmlspecialchars($nome) . '" style="--h1:' . $h1 . ';--h2:' . $h2 . '"></button>';
    }
    $html .= '</div>'
        . '<label class="hue-field"><span>Cor principal</span><input type="range" class="hue-slider" min="0" max="360" step="1" value="' . $cor['h1'] . '" data-hue="h1"></label>'
        . '<label class="hue-field"><span>Cor do degradê</span><input type="range" class="hue-slider" min="0" max="360" step="1" value="' . $cor['h2'] . '" data-hue="h2"></label>'
        . '<div class="tone-toggle" role="group" aria-label="Fundo do tema personalizado">'
        . '<button type="button" data-tone-option="claro" aria-pressed="' . ($cor['tom'] === 'claro' ? 'true' : 'false') . '"><i class="fa-regular fa-sun"></i> Claro</button>'
        . '<button type="button" data-tone-option="escuro" aria-pressed="' . ($cor['tom'] === 'escuro' ? 'true' : 'false') . '"><i class="fa-regular fa-moon"></i> Escuro</button>'
        . '</div></div></div>';

    if ($comLayout) {
        $layoutAtual = \App\Support\Layout::current();
        $html .= '<div class="appearance-col"><div class="theme-menu-title" id="' . $id . 'Layout">Disposição do menu</div><div class="layout-options" role="radiogroup" aria-labelledby="' . $id . 'Layout">';
        foreach (\App\Support\Layout::all() as $chave => $layout) {
            $html .= '<button type="button" class="layout-option" role="radio" aria-checked="' . ($chave === $layoutAtual ? 'true' : 'false') . '" data-layout-option="' . $chave . '">'
                . '<span class="layout-thumb layout-thumb-' . $chave . '" aria-hidden="true"><i></i><b></b></span>'
                . '<span><strong>' . htmlspecialchars($layout['nome']) . '</strong><small>' . htmlspecialchars($layout['descricao']) . '</small></span>'
                . '<i class="fa-solid fa-check" aria-hidden="true"></i></button>';
        }
        $html .= '</div></div>';
    }
    return $html . '</div></div>';
}

/** Estrutura do menu por perfil: itens soltos ou grupos com submenu. */
function menuItens(bool $isAdmin): array {
    if (!$isAdmin) {
        return [
            ['dashboard', 'Resumo', 'fa-solid fa-chart-pie', '/cliente/dashboard.php'],
            ['plantas', 'Plantas', 'fa-regular fa-map', '/plantas.php'],
            ['obras', 'Minhas obras', 'fa-solid fa-building', '/cliente/obras.php'],
            ['orcamentos', 'Orçamentos', 'fa-solid fa-file-invoice-dollar', '/cliente/orcamentos.php'],
            ['consulta', 'Consulta de preços', 'fa-solid fa-magnifying-glass-dollar', '/consulta_precos.php'],
        ];
    }
    return [
        ['dashboard', 'Dashboard', 'fa-solid fa-chart-line', '/admin/dashboard.php'],
        ['grupo' => 'Projetos', 'icone' => 'fa-solid fa-compass-drafting', 'itens' => [
            ['previa', 'Prévia de obra', 'fa-solid fa-wand-magic-sparkles', '/admin/previa_obra.php'],
            ['obras', 'Obras', 'fa-solid fa-building', '/admin/obras.php'],
            ['plantas', 'Plantas', 'fa-regular fa-map', '/plantas.php'],
            ['clientes', 'Clientes', 'fa-solid fa-users', '/admin/clientes.php'],
        ]],
        ['grupo' => 'Orçamentos', 'icone' => 'fa-solid fa-file-invoice-dollar', 'itens' => [
            ['orcamentos', 'Orçamentos', 'fa-solid fa-file-invoice-dollar', '/admin/orcamentos.php'],
            ['consulta', 'Consulta de preços', 'fa-solid fa-magnifying-glass-dollar', '/consulta_precos.php'],
            ['caixa', 'Planilha Caixa / SINAPI', 'fa-solid fa-table-list', '/admin/planilha_caixa.php'],
            ['referencias', 'Base de referência', 'fa-solid fa-database', '/admin/referencias.php'],
        ]],
        ['grupo' => 'Suprimentos', 'icone' => 'fa-solid fa-truck-field', 'itens' => [
            ['cotacoes', 'Cotações', 'fa-solid fa-paper-plane', '/admin/cotacoes.php'],
            ['leitura', 'Leitura de cotação', 'fa-solid fa-file-import', '/admin/leitura_cotacao.php'],
            ['compras', 'Compras', 'fa-solid fa-cart-shopping', '/admin/compras.php'],
            ['fornecedores', 'Fornecedores', 'fa-solid fa-truck-field', '/admin/fornecedores.php'],
            ['produtos', 'Produtos', 'fa-solid fa-boxes-stacked', '/admin/produtos.php'],
            ['categorias', 'Categorias', 'fa-solid fa-tags', '/admin/categorias.php'],
        ]],
        ['grupo' => 'Sistema', 'icone' => 'fa-solid fa-gear', 'itens' => [
            ['usuarios', 'Usuários', 'fa-solid fa-user-shield', '/admin/usuarios.php'],
            ['logs', 'Histórico', 'fa-solid fa-clock-rotate-left', '/admin/logs.php'],
        ]],
    ];
}

function menuLink(array $item, string $active, string $classe = 'nav-link'): string {
    [$chave, $rotulo, $icone, $caminho] = $item;
    $ativo = $chave === $active;
    return '<a href="' . APP_URL . $caminho . '" class="' . $classe . ($ativo ? ' active' : '') . '"' . ($ativo ? ' aria-current="page"' : '') . ' title="' . htmlspecialchars($rotulo) . '">'
        . '<span class="icon"><i class="' . $icone . '"></i></span><span class="nav-label">' . htmlspecialchars($rotulo) . '</span></a>';
}

function pageFoot(array $extraJs = []): void {
    $base = defined('APP_URL') ? APP_URL : '';
    echo '<script src="' . $base . '/assets/js/app.js?v=' . rawurlencode(APP_VERSION) . '"></script>';
    echo '<script src="' . $base . '/assets/js/consulta.js?v=' . rawurlencode(APP_VERSION) . '"></script>';
    foreach ($extraJs as $js) {
        echo '<script src="' . htmlspecialchars($js) . '"></script>';
    }
    echo '</body></html>';
}

function sidebar(string $active = ''): void {
    $role = currentUserRole();
    $nome = htmlspecialchars($_SESSION['user_nome'] ?? 'Usuário');
    $inicial = strtoupper(mb_substr($nome, 0, 1));
    $base = APP_URL;
    $isAdmin = ($role === 'admin');
    $home = $isAdmin ? $base . '/admin/dashboard.php' : $base . '/cliente/dashboard.php';
    ?>
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-frame">
        <a class="sidebar-brand" href="<?= $home ?>" aria-label="Ir para o painel">
            <div class="logo-icon"><i class="fa-solid fa-compass-drafting"></i></div>
            <div><h1><?= APP_NAME ?></h1><span><?= $isAdmin ? 'Gestão de projetos' : 'Portal do cliente' ?></span></div>
        </a>
        <button class="nav-close" type="button" onclick="toggleSidebar()" aria-label="Fechar menu"><i class="fa-solid fa-xmark"></i></button>
        <nav class="sidebar-nav" aria-label="Módulos do sistema">
            <?php foreach (menuItens($isAdmin) as $i => $item): ?>
                <?php if (!isset($item['grupo'])): ?>
                    <?= menuLink($item, $active) ?>
                <?php else:
                    $grupoAtivo = in_array($active, array_column($item['itens'], 0), true);
                    $subId = 'navGrupo' . $i; ?>
                <div class="nav-group<?= $grupoAtivo ? ' has-active' : '' ?>" data-nav-group>
                    <button type="button" class="nav-link nav-group-toggle<?= $grupoAtivo ? ' active' : '' ?>" aria-expanded="false" aria-controls="<?= $subId ?>" title="<?= sanitize($item['grupo']) ?>">
                        <span class="icon"><i class="<?= $item['icone'] ?>"></i></span><span class="nav-label"><?= sanitize($item['grupo']) ?></span><i class="fa-solid fa-chevron-down nav-caret" aria-hidden="true"></i>
                    </button>
                    <div class="nav-submenu" id="<?= $subId ?>">
                        <div class="nav-submenu-title"><?= sanitize($item['grupo']) ?></div>
                        <?php foreach ($item['itens'] as $sub): ?><?= menuLink($sub, $active, 'nav-sublink') ?><?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </nav>
        <div class="sidebar-footer">
            <div class="sidebar-user"><span class="avatar avatar-small"><?= $inicial ?></span><span><?= $nome ?></span></div>
            <a class="logout-link" href="<?= $base ?>/logout.php" title="Sair"><i class="fa-solid fa-arrow-right-from-bracket"></i><span>Sair</span></a>
        </div>
        </div>
    </aside>
    <?php
}

function topbar(string $title): void {
    $role = currentUserRole();
    $nome = htmlspecialchars($_SESSION['user_nome'] ?? 'Usuário');
    $inicial = strtoupper(mb_substr($nome, 0, 1));
    echo '<div class="topbar"><div class="topbar-inner">
        <button class="hamburger" onclick="toggleSidebar()" aria-label="Abrir menu" aria-controls="sidebar" aria-expanded="false"><i class="fa-solid fa-bars"></i></button>
        <div class="topbar-copy"><span class="topbar-eyebrow">' . ($role === 'admin' ? 'Painel administrativo' : 'Área do cliente') . '</span><div class="topbar-title">' . htmlspecialchars($title) . '</div></div>
        <button type="button" class="search-trigger" data-search-open data-search-url="' . APP_URL . '/busca.php" aria-haspopup="dialog" aria-keyshortcuts="Control+K" title="Buscar em todo o sistema (Ctrl+K)">
            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i><span class="search-trigger-text">Buscar orçamentos, obras, materiais…</span><kbd>Ctrl K</kbd>
        </button>
        <div class="topbar-user">
            <span class="version-chip">v' . htmlspecialchars(APP_VERSION) . '</span>
            ' . themePicker() . '
            <span class="text-sm text-muted hidden sm-show">' . $nome . '</span>
            <div class="avatar">' . $inicial . '</div>
        </div>
    </div></div>';
}

function flashMessage(): void {
    $f = getFlash();
    if (!$f) return;
    $type = match($f['type']) {
        'success' => 'alert-success',
        'error'   => 'alert-error',
        'warning' => 'alert-warning',
        default   => 'alert-info',
    };
    $icon = match($f['type']) {
        'success' => 'fa-circle-check', 'error' => 'fa-circle-xmark', 'warning' => 'fa-triangle-exclamation', default => 'fa-circle-info',
    };
    echo '<div class="alert ' . $type . '" role="status"><i class="fa-solid ' . $icon . '" aria-hidden="true"></i><span>' . htmlspecialchars($f['msg']) . '</span></div>';
}

/** Normaliza unidades vindas de planilhas (M2 → M², UND → UN...). */
function normalizarUnidade(?string $unidade): string {
    $u = rtrim(mb_strtoupper(trim((string) $unidade)), '.');
    $mapa = ['M2' => 'M²', 'M3' => 'M³', 'UND' => 'UN', 'UNID' => 'UN', 'PÇ' => 'PC', 'PCS' => 'PC', 'LT' => 'L', 'VERBA' => 'VB'];
    return mb_substr($mapa[$u] ?? ($u !== '' ? $u : 'UN'), 0, 20);
}

/** Opções de unidade; mantém a unidade original quando ela não está na lista padrão. */
function unidadeOptions(?string $atual = null): string {
    $unidades = ['UN','M','M²','M³','KG','CX','PC','RL','SC','L','GL','KIT','CJ','VB','H','MÊS','T'];
    $selecionada = $atual === null ? null : normalizarUnidade($atual);
    if ($selecionada !== null && !in_array($selecionada, $unidades, true)) {
        $unidades[] = $selecionada;
    }
    $html = '';
    foreach ($unidades as $u) {
        $html .= '<option' . ($u === $selecionada ? ' selected' : '') . '>' . htmlspecialchars($u, ENT_QUOTES, 'UTF-8') . '</option>';
    }
    return $html;
}

function statusBadge(string $status): string {
    $map = [
        'planejamento'       => ['badge-blue',  'Planejamento'],
        'em_andamento'       => ['badge-teal',  'Em Andamento'],
        'pausada'            => ['badge-yellow','Pausada'],
        'concluida'          => ['badge-green', 'Concluída'],
        'cancelada'          => ['badge-red',   'Cancelada'],
        'rascunho'           => ['badge-gray',  'Rascunho'],
        'aguardando_cotacao' => ['badge-blue',  'Aguard. Cotação'],
        'cotado'             => ['badge-teal',  'Cotado'],
        'aprovado'           => ['badge-green', 'Aprovado'],
        'reprovado'          => ['badge-red',   'Reprovado'],
        'pendente'           => ['badge-yellow','Pendente'],
        'enviada'            => ['badge-blue',  'Enviada'],
        'respondida'         => ['badge-teal',  'Respondida'],
        'aceita'             => ['badge-green', 'Aceita'],
        'recusada'           => ['badge-red',   'Recusada'],
        'solicitado'         => ['badge-blue',  'Solicitado'],
        'confirmado'         => ['badge-teal',  'Confirmado'],
        'em_producao'        => ['badge-yellow','Em Produção'],
        'enviado'            => ['badge-blue',  'Enviado'],
        'entregue'           => ['badge-green', 'Entregue'],
        'cancelado'          => ['badge-red',   'Cancelado'],
    ];
    [$cls, $label] = $map[$status] ?? ['badge-gray', $status];
    return '<span class="badge badge-status ' . $cls . '">' . $label . '</span>';
}

function paginacao(int $total, int $porPagina, int $pagAtual, string $urlBase): void {
    $totalPag = (int)ceil($total / $porPagina);
    if ($totalPag <= 1) return;
    $sep = str_contains($urlBase, '?') ? '&' : '?';
    $link = static function (int $pagina, string $rotulo, string $classe = '', string $aria = '') use ($urlBase, $sep): string {
        return '<a class="page-btn ' . $classe . '" href="' . htmlspecialchars($urlBase . $sep . 'pag=' . $pagina, ENT_QUOTES, 'UTF-8') . '"' . $aria . '>' . $rotulo . '</a>';
    };
    // Janela de páginas com reticências (1 … 4 5 [6] 7 8 … 20), como nos buscadores.
    $paginas = array_unique(array_filter([1, 2, $pagAtual - 2, $pagAtual - 1, $pagAtual, $pagAtual + 1, $pagAtual + 2, $totalPag - 1, $totalPag], static fn (int $p): bool => $p >= 1 && $p <= $totalPag));
    sort($paginas);
    echo '<nav class="pagination" aria-label="Paginação">';
    echo $pagAtual > 1 ? $link($pagAtual - 1, '&#8592;', '', ' aria-label="Página anterior" rel="prev"') : '<span class="page-btn is-disabled" aria-hidden="true">&#8592;</span>';
    $anterior = 0;
    foreach ($paginas as $p) {
        if ($p - $anterior > 1) echo '<span class="page-gap" aria-hidden="true">…</span>';
        echo $link($p, (string) $p, $p === $pagAtual ? 'active' : '', $p === $pagAtual ? ' aria-current="page"' : '');
        $anterior = $p;
    }
    echo $pagAtual < $totalPag ? $link($pagAtual + 1, '&#8594;', '', ' aria-label="Próxima página" rel="next"') : '<span class="page-btn is-disabled" aria-hidden="true">&#8594;</span>';
    echo '</nav>';
}

function logAction(string $acao, ?string $tabela = null, ?int $registroId = null, ?string $detalhe = null): void {
    try {
        $db = getDB();
        $sql = 'INSERT INTO logs (usuario_id, acao, tabela, registro_id, detalhe, ip) VALUES (?,?,?,?,?,?)';
        $db->prepare($sql)->execute([
            currentUserId() ?: null,
            $acao,
            $tabela,
            $registroId,
            $detalhe,
            $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    } catch (Throwable) {}
}
