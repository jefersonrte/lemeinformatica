<?php
// helpers/layout.php — funções de layout reutilizáveis

function pageHead(string $title, array $extraCss = []): void {
    $base = defined('APP_URL') ? APP_URL : '';
    echo '<!DOCTYPE html><html lang="pt-BR"><head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<meta name="theme-color" content="#07152f">
<title>' . htmlspecialchars($title) . ' · ' . APP_NAME . '</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap">
<link rel="stylesheet" href="' . $base . '/assets/css/style.css?v=' . rawurlencode(APP_VERSION) . '">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
';
    foreach ($extraCss as $css) {
        echo '<link rel="stylesheet" href="' . htmlspecialchars($css) . '">';
    }
    echo '</head><body>';
}

function pageFoot(array $extraJs = []): void {
    $base = defined('APP_URL') ? APP_URL : '';
    echo '<script src="' . $base . '/assets/js/app.js?v=' . rawurlencode(APP_VERSION) . '"></script>';
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
            <?php if ($isAdmin): ?>
            <div class="nav-section">Principal</div>
            <a href="<?= $base ?>/admin/dashboard.php" class="nav-link <?= $active === 'dashboard' ? 'active' : '' ?>"><span class="icon"><i class="fa-solid fa-chart-line"></i></span> Dashboard</a>
            <a href="<?= $base ?>/admin/planilha_caixa.php" class="nav-link <?= $active === 'caixa' ? 'active' : '' ?>"><span class="icon"><i class="fa-solid fa-table-list"></i></span> Planilha Caixa</a>
            <a href="<?= $base ?>/plantas.php" class="nav-link <?= $active === 'plantas' ? 'active' : '' ?>"><span class="icon"><i class="fa-regular fa-map"></i></span> Plantas</a>
            <a href="<?= $base ?>/admin/clientes.php" class="nav-link <?= $active === 'clientes' ? 'active' : '' ?>"><span class="icon"><i class="fa-solid fa-users"></i></span> Clientes</a>
            <a href="<?= $base ?>/admin/obras.php" class="nav-link <?= $active === 'obras' ? 'active' : '' ?>"><span class="icon"><i class="fa-solid fa-building"></i></span> Obras</a>
            <div class="nav-section">Orçamento e compras</div>
            <a href="<?= $base ?>/admin/orcamentos.php" class="nav-link <?= $active === 'orcamentos' ? 'active' : '' ?>"><span class="icon"><i class="fa-solid fa-file-invoice-dollar"></i></span> Orçamentos</a>
            <a href="<?= $base ?>/admin/cotacoes.php" class="nav-link <?= $active === 'cotacoes' ? 'active' : '' ?>"><span class="icon"><i class="fa-solid fa-paper-plane"></i></span> Cotações</a>
            <a href="<?= $base ?>/admin/leitura_cotacao.php" class="nav-link <?= $active === 'leitura' ? 'active' : '' ?>"><span class="icon"><i class="fa-solid fa-file-import"></i></span> Leitura</a>
            <a href="<?= $base ?>/admin/produtos.php" class="nav-link <?= $active === 'produtos' ? 'active' : '' ?>"><span class="icon"><i class="fa-solid fa-boxes-stacked"></i></span> Produtos</a>
            <a href="<?= $base ?>/admin/categorias.php" class="nav-link <?= $active === 'categorias' ? 'active' : '' ?>"><span class="icon"><i class="fa-solid fa-tags"></i></span> Categorias</a>
            <a href="<?= $base ?>/admin/fornecedores.php" class="nav-link <?= $active === 'fornecedores' ? 'active' : '' ?>"><span class="icon"><i class="fa-solid fa-truck-field"></i></span> Fornecedores</a>
            <a href="<?= $base ?>/admin/compras.php" class="nav-link <?= $active === 'compras' ? 'active' : '' ?>"><span class="icon"><i class="fa-solid fa-cart-shopping"></i></span> Compras</a>
            <div class="nav-section">Sistema</div>
            <a href="<?= $base ?>/admin/usuarios.php" class="nav-link <?= $active === 'usuarios' ? 'active' : '' ?>"><span class="icon"><i class="fa-solid fa-user-shield"></i></span> Usuários</a>
            <a href="<?= $base ?>/admin/logs.php" class="nav-link <?= $active === 'logs' ? 'active' : '' ?>"><span class="icon"><i class="fa-solid fa-clock-rotate-left"></i></span> Histórico</a>
            <?php else: ?>
            <div class="nav-section">Minha Área</div>
            <a href="<?= $base ?>/cliente/dashboard.php" class="nav-link <?= $active === 'dashboard' ? 'active' : '' ?>"><span class="icon"><i class="fa-solid fa-chart-pie"></i></span> Resumo</a>
            <a href="<?= $base ?>/plantas.php" class="nav-link <?= $active === 'plantas' ? 'active' : '' ?>"><span class="icon"><i class="fa-regular fa-map"></i></span> Plantas</a>
            <a href="<?= $base ?>/cliente/obras.php" class="nav-link <?= $active === 'obras' ? 'active' : '' ?>"><span class="icon"><i class="fa-solid fa-building"></i></span> Minhas obras</a>
            <a href="<?= $base ?>/cliente/orcamentos.php" class="nav-link <?= $active === 'orcamentos' ? 'active' : '' ?>"><span class="icon"><i class="fa-solid fa-file-invoice-dollar"></i></span> Orçamentos</a>
            <?php endif; ?>
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
        <div class="topbar-user">
            <span class="version-chip">v' . htmlspecialchars(APP_VERSION) . '</span>
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
        'success' => '✅', 'error' => '❌', 'warning' => '⚠️', default => 'ℹ️',
    };
    echo '<div class="alert ' . $type . '">' . $icon . ' ' . htmlspecialchars($f['msg']) . '</div>';
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
    ];
    [$cls, $label] = $map[$status] ?? ['badge-gray', $status];
    return '<span class="badge ' . $cls . '">' . $label . '</span>';
}

function paginacao(int $total, int $porPagina, int $pagAtual, string $urlBase): void {
    $totalPag = (int)ceil($total / $porPagina);
    if ($totalPag <= 1) return;
    echo '<div class="pagination">';
    $prev = max(1, $pagAtual - 1);
    $next = min($totalPag, $pagAtual + 1);
    echo '<button class="page-btn" onclick="location.href=\'' . $urlBase . '&pag=' . $prev . '\'" ' . ($pagAtual <= 1 ? 'disabled' : '') . '>&#8592;</button>';
    for ($i = 1; $i <= $totalPag; $i++) {
        $active = $i === $pagAtual ? 'active' : '';
        echo '<button class="page-btn ' . $active . '" onclick="location.href=\'' . $urlBase . '&pag=' . $i . '\'">' . $i . '</button>';
    }
    echo '<button class="page-btn" onclick="location.href=\'' . $urlBase . '&pag=' . $next . '\'" ' . ($pagAtual >= $totalPag ? 'disabled' : '') . '>&#8594;</button>';
    echo '</div>';
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
