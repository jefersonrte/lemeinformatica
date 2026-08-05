<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$context = pet_boot_page();
$permissions = $context['permissoes'];
$isViewer = $context['perfil'] === 'visualizador';
$contextPayload = json_encode([
    'csrf' => api_csrf_token(),
    'version' => PET_VERSION,
    'user' => [
        'id' => $context['id'],
        'name' => $context['nome'],
        'email' => $context['email'],
        'profile' => $context['perfil'],
        'veterinarianId' => $context['veterinario_id'],
        'crmv' => $context['crmv'],
        'specialty' => $context['especialidade'],
    ],
    'permissions' => $permissions,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP);
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>Leme Pet - Gestao veterinaria</title>
    <link rel="stylesheet" href="frontend/css/app.css?v=<?= rawurlencode(PET_VERSION) ?>">
    <link rel="stylesheet" href="frontend/css/modules/commerce.css?v=<?= rawurlencode(PET_VERSION) ?>">
</head>
<body>
    <script id="petContext" type="application/json"><?= $contextPayload ?></script>

    <div class="app-shell">
        <aside class="sidebar" id="sidebar">
            <div class="brand">
                <span class="brand-mark" aria-hidden="true">LP</span>
                <div>
                    <strong>Leme Pet</strong>
                    <span>Gestao veterinaria</span>
                </div>
            </div>

            <nav class="main-nav" aria-label="Areas do sistema">
                <button class="nav-item active" type="button" data-view="dashboard">Dashboard</button>
                <?php if ($permissions['ver_cadastros']): ?>
                    <button class="nav-item" type="button" data-view="tutores">Tutores</button>
                    <button class="nav-item" type="button" data-view="animais">Animais</button>
                <?php endif; ?>
                <?php if ($permissions['ver_prontuario']): ?>
                    <button class="nav-item" type="button" data-view="atendimentos">Atendimentos</button>
                    <button class="nav-item" type="button" data-view="internacoes">Internacoes</button>
                <?php endif; ?>
                <?php if ($permissions['ver_estetica']): ?>
                    <button class="nav-item" type="button" data-view="banho_tosa">Banho e tosa</button>
                <?php endif; ?>
                <?php if ($permissions['ver_comercial']): ?>
                    <button class="nav-item" type="button" data-view="produtos">Produtos e estoque</button>
                    <button class="nav-item" type="button" data-view="vendas">Vendas</button>
                <?php endif; ?>
                <?php if ($permissions['gerenciar_equipe']): ?>
                    <button class="nav-item" type="button" data-view="equipe">Equipe veterinaria</button>
                <?php endif; ?>
            </nav>

            <div class="sidebar-footer">
                <div class="user-summary">
                    <span class="avatar initials"><?= htmlspecialchars(strtoupper(substr($context['nome'], 0, 2)), ENT_QUOTES, 'UTF-8') ?></span>
                    <div>
                        <strong><?= htmlspecialchars($context['nome'], ENT_QUOTES, 'UTF-8') ?></strong>
                        <span>
                            <?= $context['veterinario_id'] ? 'Veterinario' : htmlspecialchars(ucfirst($context['perfil']), ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </div>
                </div>
                <div class="sidebar-actions">
                    <a href="../painel.php">Painel principal</a>
                    <form method="post" action="../logout.php">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(api_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                        <button type="submit">Sair</button>
                    </form>
                </div>
                <small>Versao <?= htmlspecialchars(PET_VERSION, ENT_QUOTES, 'UTF-8') ?></small>
            </div>
        </aside>

        <div class="workspace">
            <header class="topbar">
                <button class="menu-button" id="menuButton" type="button" aria-label="Abrir menu" aria-controls="sidebar" aria-expanded="false">
                    <span></span><span></span><span></span>
                </button>
                <div>
                    <p class="eyebrow">Leme Informatica</p>
                    <h1 id="pageTitle">Dashboard Pet</h1>
                </div>
                <div class="topbar-meta">
                    <span id="currentDate"></span>
                    <?php if ($context['crmv']): ?>
                        <strong><?= htmlspecialchars($context['crmv'], ENT_QUOTES, 'UTF-8') ?></strong>
                    <?php endif; ?>
                </div>
            </header>

            <main class="content">
                <section class="view active" data-view-panel="dashboard">
                    <div class="section-heading">
                        <div>
                            <p class="section-kicker">Visao operacional</p>
                            <h2><?= $isViewer ? 'Indicadores da clinica' : 'Resumo de hoje' ?></h2>
                        </div>
                        <button class="button secondary" id="refreshDashboard" type="button">Atualizar</button>
                    </div>

                    <div class="metric-grid" id="metricGrid">
                        <article class="metric"><span>Tutores ativos</span><strong id="metricOwners">0</strong><small>cadastros</small></article>
                        <article class="metric"><span>Animais ativos</span><strong id="metricAnimals">0</strong><small>pacientes</small></article>
                        <article class="metric"><span>Atendimentos hoje</span><strong id="metricAppointments">0</strong><small>agenda</small></article>
                        <article class="metric critical"><span>Internados agora</span><strong id="metricAdmissions">0</strong><small>em acompanhamento</small></article>
                    </div>

                    <div class="metric-grid commerce-metrics" id="commerceMetricGrid">
                        <article class="metric"><span>Banho e tosa hoje</span><strong id="metricGroomingToday">0</strong><small>agendamentos</small></article>
                        <article class="metric"><span>Vendas hoje</span><strong id="metricSalesToday">R$ 0,00</strong><small>faturamento</small></article>
                        <article class="metric warning"><span>Estoque baixo</span><strong id="metricLowStock">0</strong><small>itens para repor</small></article>
                        <article class="metric"><span>Produtos ativos</span><strong id="metricProducts">0</strong><small>catalogo</small></article>
                    </div>

                    <div class="dashboard-grid">
                        <section class="data-section">
                            <div class="panel-heading">
                                <div>
                                    <h3>Pacientes por especie</h3>
                                    <p>Distribuicao dos animais ativos</p>
                                </div>
                            </div>
                            <div class="bar-chart" id="speciesChart">
                                <p class="empty-state">Carregando indicadores...</p>
                            </div>
                        </section>

                        <?php if (!$isViewer): ?>
                            <section class="data-section">
                                <div class="panel-heading">
                                    <div>
                                        <h3>Internacoes ativas</h3>
                                        <p>Prioridade clinica e localizacao</p>
                                    </div>
                                    <button class="text-button" type="button" data-open-view="internacoes">Ver todas</button>
                                </div>
                                <div id="dashboardAdmissions" class="compact-list">
                                    <p class="empty-state">Carregando internacoes...</p>
                                </div>
                            </section>

                            <section class="data-section dashboard-wide">
                                <div class="panel-heading">
                                    <div>
                                        <h3>Proximos atendimentos</h3>
                                        <p>Agenda a partir de agora</p>
                                    </div>
                                    <button class="text-button" type="button" data-open-view="atendimentos">Abrir agenda</button>
                                </div>
                                <div class="table-wrap">
                                    <table>
                                        <thead><tr><th>Horario</th><th>Paciente</th><th>Tutor</th><th>Tipo</th><th>Veterinario</th><th>Status</th></tr></thead>
                                        <tbody id="dashboardAppointments"><tr><td colspan="6">Carregando agenda...</td></tr></tbody>
                                    </table>
                                </div>
                            </section>
                        <?php endif; ?>
                    </div>
                </section>

                <?php if ($permissions['ver_cadastros']): ?>
                    <section class="view" data-view-panel="tutores" hidden>
                        <div class="section-heading">
                            <div><p class="section-kicker">Pessoas</p><h2>Tutores</h2></div>
                            <?php if ($permissions['editar_cadastros']): ?>
                                <button class="button primary" id="newOwnerButton" type="button">Novo tutor</button>
                            <?php endif; ?>
                        </div>
                        <div class="toolbar">
                            <label class="search-field"><span>Buscar</span><input id="ownerSearch" type="search" placeholder="Nome, CPF, telefone ou e-mail"></label>
                            <span class="result-count" id="ownerCount">0 registros</span>
                        </div>
                        <div class="data-section table-section">
                            <div class="table-wrap">
                                <table>
                                    <thead><tr><th>Tutor</th><th>Contato</th><th>Cidade</th><th>Animais</th><th>Cadastro</th><th></th></tr></thead>
                                    <tbody id="ownersTable"><tr><td colspan="6">Carregando tutores...</td></tr></tbody>
                                </table>
                            </div>
                        </div>
                    </section>

                    <section class="view" data-view-panel="animais" hidden>
                        <div class="section-heading">
                            <div><p class="section-kicker">Pacientes</p><h2>Animais</h2></div>
                            <?php if ($permissions['editar_cadastros']): ?>
                                <button class="button primary" id="newAnimalButton" type="button">Novo animal</button>
                            <?php endif; ?>
                        </div>
                        <div class="toolbar">
                            <label class="search-field"><span>Buscar</span><input id="animalSearch" type="search" placeholder="Animal, tutor, especie, raca ou microchip"></label>
                            <span class="result-count" id="animalCount">0 registros</span>
                        </div>
                        <div class="data-section table-section">
                            <div class="table-wrap">
                                <table>
                                    <thead><tr><th>Paciente</th><th>Tutor</th><th>Sexo</th><th>Nascimento</th><th>Peso</th><th>Status</th><th></th></tr></thead>
                                    <tbody id="animalsTable"><tr><td colspan="7">Carregando animais...</td></tr></tbody>
                                </table>
                            </div>
                        </div>
                    </section>
                <?php endif; ?>

                <?php if ($permissions['ver_prontuario']): ?>
                    <section class="view" data-view-panel="atendimentos" hidden>
                        <div class="section-heading">
                            <div><p class="section-kicker">Prontuario</p><h2>Atendimentos</h2></div>
                            <button class="button primary" id="newAppointmentButton" type="button">Novo atendimento</button>
                        </div>
                        <div class="toolbar split-toolbar">
                            <label class="search-field"><span>Buscar</span><input id="appointmentSearch" type="search" placeholder="Paciente, tutor ou motivo"></label>
                            <label class="filter-field"><span>Status</span>
                                <select id="appointmentStatusFilter">
                                    <option value="">Todos</option>
                                    <option value="agendado">Agendado</option>
                                    <option value="em_atendimento">Em atendimento</option>
                                    <option value="concluido">Concluido</option>
                                    <option value="cancelado">Cancelado</option>
                                </select>
                            </label>
                        </div>
                        <div class="data-section table-section">
                            <div class="table-wrap">
                                <table>
                                    <thead><tr><th>Data</th><th>Paciente</th><th>Tutor</th><th>Motivo</th><th>Veterinario</th><th>Status</th><th></th></tr></thead>
                                    <tbody id="appointmentsTable"><tr><td colspan="7">Carregando atendimentos...</td></tr></tbody>
                                </table>
                            </div>
                        </div>
                    </section>

                    <section class="view" data-view-panel="internacoes" hidden>
                        <div class="section-heading">
                            <div><p class="section-kicker">Hospital</p><h2>Internacoes</h2></div>
                            <?php if ($permissions['gerenciar_internacao']): ?>
                                <button class="button primary" id="newAdmissionButton" type="button">Nova internacao</button>
                            <?php endif; ?>
                        </div>
                        <div class="toolbar">
                            <label class="filter-field"><span>Status</span>
                                <select id="admissionStatusFilter">
                                    <option value="">Todas</option>
                                    <option value="ativa" selected>Ativas</option>
                                    <option value="alta">Alta</option>
                                    <option value="transferencia">Transferencia</option>
                                    <option value="obito">Obito</option>
                                    <option value="cancelada">Cancelada</option>
                                </select>
                            </label>
                        </div>
                        <div class="admission-grid" id="admissionsGrid">
                            <p class="empty-state">Carregando internacoes...</p>
                        </div>
                    </section>
                <?php endif; ?>

                <?php if ($permissions['gerenciar_equipe']): ?>
                    <section class="view" data-view-panel="equipe" hidden>
                        <div class="section-heading">
                            <div><p class="section-kicker">Acesso clinico</p><h2>Equipe veterinaria</h2></div>
                            <button class="button primary" id="newVetButton" type="button">Vincular veterinario</button>
                        </div>
                        <div class="data-section table-section">
                            <div class="table-wrap">
                                <table>
                                    <thead><tr><th>Profissional</th><th>CRMV</th><th>Especialidade</th><th>Contato</th><th>Status</th><th></th></tr></thead>
                                    <tbody id="vetsTable"><tr><td colspan="6">Carregando equipe...</td></tr></tbody>
                                </table>
                            </div>
                        </div>
                    </section>
                <?php endif; ?>

                <?php if ($permissions['ver_estetica']): ?>
                    <section class="view" data-view-panel="banho_tosa" hidden>
                        <div class="section-heading">
                            <div><p class="section-kicker">Estetica pet</p><h2>Banho e tosa</h2></div>
                            <?php if ($permissions['gerenciar_estetica']): ?>
                                <div class="heading-actions">
                                    <button class="button secondary" id="newServiceButton" type="button">Novo servico</button>
                                    <button class="button primary" id="newGroomingButton" type="button">Novo agendamento</button>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="toolbar split-toolbar">
                            <label class="search-field"><span>Buscar</span><input id="groomingSearch" type="search" placeholder="Animal, tutor ou servico"></label>
                            <label class="filter-field"><span>Status</span>
                                <select id="groomingStatusFilter">
                                    <option value="">Todos</option><option value="agendado">Agendado</option>
                                    <option value="confirmado">Confirmado</option><option value="em_atendimento">Em atendimento</option>
                                    <option value="concluido">Concluido</option><option value="cancelado">Cancelado</option>
                                    <option value="nao_compareceu">Nao compareceu</option>
                                </select>
                            </label>
                        </div>
                        <div class="data-section table-section">
                            <div class="table-wrap"><table>
                                <thead><tr><th>Data</th><th>Paciente</th><th>Servicos</th><th>Profissional</th><th>Valor</th><th>Status</th><th></th></tr></thead>
                                <tbody id="groomingTable"><tr><td colspan="7">Carregando agenda...</td></tr></tbody>
                            </table></div>
                        </div>
                    </section>
                <?php endif; ?>

                <?php if ($permissions['ver_comercial']): ?>
                    <section class="view" data-view-panel="produtos" hidden>
                        <div class="section-heading">
                            <div><p class="section-kicker">Comercial</p><h2>Produtos e estoque</h2></div>
                            <?php if ($permissions['gerenciar_produtos']): ?>
                                <button class="button primary" id="newProductButton" type="button">Novo produto</button>
                            <?php endif; ?>
                        </div>
                        <div class="toolbar split-toolbar">
                            <label class="search-field"><span>Buscar</span><input id="productSearch" type="search" placeholder="Produto, SKU, marca ou codigo de barras"></label>
                            <label class="checkbox-field inline-check"><input id="lowStockFilter" type="checkbox"><span>Somente estoque baixo</span></label>
                        </div>
                        <div class="data-section table-section">
                            <div class="table-wrap"><table>
                                <thead><tr><th>Produto</th><th>Categoria</th><th>Preco</th><th>Estoque</th><th>Minimo</th><th>Status</th><th></th></tr></thead>
                                <tbody id="productsTable"><tr><td colspan="7">Carregando produtos...</td></tr></tbody>
                            </table></div>
                        </div>
                    </section>

                    <section class="view" data-view-panel="vendas" hidden>
                        <div class="section-heading">
                            <div><p class="section-kicker">Ponto de venda</p><h2>Vendas</h2></div>
                            <?php if ($permissions['registrar_venda']): ?>
                                <button class="button primary" id="newSaleButton" type="button">Nova venda</button>
                            <?php endif; ?>
                        </div>
                        <div class="toolbar split-toolbar">
                            <label class="search-field"><span>Buscar</span><input id="saleSearch" type="search" placeholder="Numero, tutor ou produto"></label>
                            <label class="filter-field"><span>Status</span><select id="saleStatusFilter"><option value="">Todas</option><option value="concluida">Concluidas</option><option value="cancelada">Canceladas</option></select></label>
                        </div>
                        <div class="data-section table-section">
                            <div class="table-wrap"><table>
                                <thead><tr><th>Venda</th><th>Data</th><th>Cliente</th><th>Itens</th><th>Pagamento</th><th>Total</th><th>Status</th><th></th></tr></thead>
                                <tbody id="salesTable"><tr><td colspan="8">Carregando vendas...</td></tr></tbody>
                            </table></div>
                        </div>
                    </section>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <dialog class="modal" id="ownerDialog">
        <form id="ownerForm" method="dialog" enctype="multipart/form-data">
            <div class="modal-header">
                <div><p class="section-kicker">Cadastro completo</p><h2 id="ownerDialogTitle">Novo tutor</h2></div>
                <button class="icon-button" type="button" data-close-dialog aria-label="Fechar">&times;</button>
            </div>
            <input type="hidden" name="id">
            <div class="photo-field">
                <div class="photo-preview" data-photo-preview><span>Foto</span></div>
                <label class="button secondary">Selecionar foto<input type="file" name="foto" accept="image/jpeg,image/png,image/webp" hidden></label>
                <small>JPG, PNG ou WebP. Maximo 5 MB.</small>
            </div>
            <fieldset>
                <legend>Identificacao</legend>
                <div class="form-grid">
                    <label class="span-2">Nome completo<input name="nome" maxlength="160" required></label>
                    <label>CPF<input name="cpf" inputmode="numeric" maxlength="14"></label>
                    <label>RG<input name="rg" maxlength="30"></label>
                    <label>Data de nascimento<input name="data_nascimento" type="date"></label>
                    <label>Genero<input name="genero" maxlength="30"></label>
                    <label>Estado civil<input name="estado_civil" maxlength="30"></label>
                    <label>Profissao<input name="profissao" maxlength="100"></label>
                </div>
            </fieldset>
            <fieldset>
                <legend>Contato</legend>
                <div class="form-grid">
                    <label>E-mail<input name="email" type="email" maxlength="160"></label>
                    <label>Telefone<input name="telefone" type="tel" maxlength="20" required></label>
                    <label>WhatsApp<input name="whatsapp" type="tel" maxlength="20"></label>
                    <label>CEP<input name="cep" inputmode="numeric" maxlength="9"></label>
                    <label class="span-2">Logradouro<input name="logradouro" maxlength="180"></label>
                    <label>Numero<input name="numero" maxlength="20"></label>
                    <label>Complemento<input name="complemento" maxlength="100"></label>
                    <label>Bairro<input name="bairro" maxlength="100"></label>
                    <label>Cidade<input name="cidade" maxlength="100"></label>
                    <label>UF<input name="uf" maxlength="2"></label>
                </div>
            </fieldset>
            <fieldset>
                <legend>Emergencia e observacoes</legend>
                <div class="form-grid">
                    <label>Contato de emergencia<input name="contato_emergencia_nome" maxlength="160"></label>
                    <label>Telefone de emergencia<input name="contato_emergencia_telefone" maxlength="20"></label>
                    <label class="span-2">Observacoes<textarea name="observacoes" rows="3" maxlength="5000"></textarea></label>
                </div>
            </fieldset>
            <div class="modal-actions"><button class="button secondary" type="button" data-close-dialog>Cancelar</button><button class="button primary" type="submit">Salvar tutor</button></div>
        </form>
    </dialog>

    <dialog class="modal" id="animalDialog">
        <form id="animalForm" method="dialog" enctype="multipart/form-data">
            <div class="modal-header">
                <div><p class="section-kicker">Paciente</p><h2 id="animalDialogTitle">Novo animal</h2></div>
                <button class="icon-button" type="button" data-close-dialog aria-label="Fechar">&times;</button>
            </div>
            <input type="hidden" name="id">
            <div class="photo-field">
                <div class="photo-preview" data-photo-preview><span>Foto</span></div>
                <label class="button secondary">Selecionar foto<input type="file" name="foto" accept="image/jpeg,image/png,image/webp" hidden></label>
                <small>JPG, PNG ou WebP. Maximo 5 MB.</small>
            </div>
            <fieldset>
                <legend>Identificacao</legend>
                <div class="form-grid">
                    <label class="span-2">Tutor<select name="tutor_id" required></select></label>
                    <label>Nome<input name="nome" maxlength="120" required></label>
                    <label>Especie<input name="especie" maxlength="60" placeholder="Canina, felina..." required></label>
                    <label>Raca<input name="raca" maxlength="120"></label>
                    <label>Sexo<select name="sexo"><option value="indefinido">Indefinido</option><option value="macho">Macho</option><option value="femea">Femea</option></select></label>
                    <label>Nascimento<input name="data_nascimento" type="date"></label>
                    <label>Cor/pelagem<input name="cor" maxlength="100"></label>
                    <label>Peso atual (kg)<input name="peso_kg" type="number" min="0" max="9999" step="0.01"></label>
                    <label>Porte<select name="porte"><option value="nao_aplicavel">Nao se aplica</option><option value="mini">Mini</option><option value="pequeno">Pequeno</option><option value="medio">Medio</option><option value="grande">Grande</option><option value="gigante">Gigante</option></select></label>
                    <label>Microchip<input name="microchip" maxlength="80"></label>
                    <label>Tipo sanguineo<input name="tipo_sanguineo" maxlength="30"></label>
                    <label class="checkbox-field"><input name="castrado" type="checkbox"><span>Castrado</span></label>
                </div>
            </fieldset>
            <fieldset>
                <legend>Informacoes clinicas permanentes</legend>
                <div class="form-grid">
                    <label>Alergias<textarea name="alergias" rows="3" maxlength="5000"></textarea></label>
                    <label>Condicoes preexistentes<textarea name="condicoes_preexistentes" rows="3" maxlength="5000"></textarea></label>
                    <label class="span-2">Observacoes<textarea name="observacoes" rows="3" maxlength="5000"></textarea></label>
                </div>
            </fieldset>
            <div class="modal-actions"><button class="button secondary" type="button" data-close-dialog>Cancelar</button><button class="button primary" type="submit">Salvar animal</button></div>
        </form>
    </dialog>

    <dialog class="modal wide" id="appointmentDialog">
        <form id="appointmentForm" method="dialog">
            <div class="modal-header">
                <div><p class="section-kicker">Prontuario</p><h2 id="appointmentDialogTitle">Novo atendimento</h2></div>
                <button class="icon-button" type="button" data-close-dialog aria-label="Fechar">&times;</button>
            </div>
            <input type="hidden" name="id">
            <fieldset>
                <legend>Agenda</legend>
                <div class="form-grid">
                    <label class="span-2">Paciente<select name="animal_id" required></select></label>
                    <label>Tipo<select name="tipo"><option value="consulta">Consulta</option><option value="retorno">Retorno</option><option value="emergencia">Emergencia</option><option value="vacina">Vacina</option><option value="exame">Exame</option><option value="procedimento">Procedimento</option></select></label>
                    <label>Status<select name="status"><option value="agendado">Agendado</option><option value="em_atendimento">Em atendimento</option><option value="concluido">Concluido</option><option value="cancelado">Cancelado</option></select></label>
                    <label>Inicio<input name="inicio_em" type="datetime-local" required></label>
                    <label>Fim<input name="fim_em" type="datetime-local"></label>
                    <label class="span-2">Motivo<textarea name="motivo" rows="2" maxlength="500" required></textarea></label>
                    <?php if ($context['perfil'] === 'admin'): ?><label class="span-2">Veterinario<select name="veterinario_id"></select></label><?php endif; ?>
                </div>
            </fieldset>
            <?php if ($permissions['editar_prontuario']): ?>
                <fieldset>
                    <legend>Avaliacao clinica</legend>
                    <div class="form-grid clinical-grid">
                        <label class="span-2">Anamnese<textarea name="anamnese" rows="4" maxlength="10000"></textarea></label>
                        <label class="span-2">Exame clinico<textarea name="exame_clinico" rows="4" maxlength="10000"></textarea></label>
                        <label>Peso (kg)<input name="peso_kg" type="number" min="0" step="0.01"></label>
                        <label>Temperatura (C)<input name="temperatura_c" type="number" min="20" max="50" step="0.1"></label>
                        <label>Frequencia cardiaca<input name="frequencia_cardiaca" type="number" min="1"></label>
                        <label>Frequencia respiratoria<input name="frequencia_respiratoria" type="number" min="1"></label>
                        <label>Mucosas<input name="mucosas" maxlength="100"></label>
                        <label>Hidratacao<input name="hidratacao" maxlength="100"></label>
                    </div>
                </fieldset>
                <fieldset>
                    <legend>Conclusao</legend>
                    <div class="form-grid">
                        <label>Diagnostico<textarea name="diagnostico" rows="4" maxlength="10000"></textarea></label>
                        <label>Conduta<textarea name="conduta" rows="4" maxlength="10000"></textarea></label>
                        <label>Prescricao<textarea name="prescricao" rows="4" maxlength="10000"></textarea></label>
                        <label>Exames solicitados<textarea name="exames_solicitados" rows="4" maxlength="10000"></textarea></label>
                        <label>Retorno<input name="retorno_em" type="date"></label>
                    </div>
                </fieldset>
            <?php endif; ?>
            <div class="modal-actions"><button class="button secondary" type="button" data-close-dialog>Cancelar</button><button class="button primary" type="submit">Salvar atendimento</button></div>
        </form>
    </dialog>

    <dialog class="modal wide" id="admissionDialog">
        <form id="admissionForm" method="dialog">
            <div class="modal-header">
                <div><p class="section-kicker">Hospital</p><h2 id="admissionDialogTitle">Nova internacao</h2></div>
                <button class="icon-button" type="button" data-close-dialog aria-label="Fechar">&times;</button>
            </div>
            <input type="hidden" name="id">
            <div class="form-grid">
                <label class="span-2">Paciente<select name="animal_id" required></select></label>
                <label>Status<select name="status"><option value="ativa">Ativa</option><option value="alta">Alta</option><option value="transferencia">Transferencia</option><option value="obito">Obito</option><option value="cancelada">Cancelada</option></select></label>
                <label>Risco<select name="classificacao_risco"><option value="baixo">Baixo</option><option value="moderado" selected>Moderado</option><option value="alto">Alto</option><option value="critico">Critico</option></select></label>
                <label>Entrada<input name="entrada_em" type="datetime-local" required></label>
                <label>Previsao de alta<input name="previsao_alta_em" type="datetime-local"></label>
                <label>Saida<input name="saida_em" type="datetime-local"></label>
                <label>Setor<input name="setor" maxlength="80"></label>
                <label>Leito<input name="leito" maxlength="40"></label>
                <label>Veterinario responsavel<select name="veterinario_responsavel_id"></select></label>
                <label class="span-2">Motivo<textarea name="motivo" rows="3" maxlength="500" required></textarea></label>
                <?php if ($permissions['editar_prontuario']): ?>
                    <label>Diagnostico inicial<textarea name="diagnostico_inicial" rows="4" maxlength="10000"></textarea></label>
                    <label>Plano de cuidados<textarea name="plano_cuidados" rows="4" maxlength="10000"></textarea></label>
                    <label class="span-2">Resumo de alta<textarea name="resumo_alta" rows="4" maxlength="10000"></textarea></label>
                <?php endif; ?>
            </div>
            <div class="modal-actions"><button class="button secondary" type="button" data-close-dialog>Cancelar</button><button class="button primary" type="submit">Salvar internacao</button></div>
        </form>
    </dialog>

    <dialog class="modal" id="evolutionDialog">
        <form id="evolutionForm" method="dialog">
            <div class="modal-header">
                <div><p class="section-kicker">Internacao</p><h2>Nova evolucao</h2></div>
                <button class="icon-button" type="button" data-close-dialog aria-label="Fechar">&times;</button>
            </div>
            <input type="hidden" name="internacao_id">
            <div class="form-grid clinical-grid">
                <label class="span-2">Data e hora<input name="registrado_em" type="datetime-local" required></label>
                <label>Peso (kg)<input name="peso_kg" type="number" min="0" step="0.01"></label>
                <label>Temperatura (C)<input name="temperatura_c" type="number" min="20" max="50" step="0.1"></label>
                <label>Frequencia cardiaca<input name="frequencia_cardiaca" type="number" min="1"></label>
                <label>Frequencia respiratoria<input name="frequencia_respiratoria" type="number" min="1"></label>
                <label>Glicemia (mg/dL)<input name="glicemia_mg_dl" type="number" min="0" step="0.01"></label>
                <label>Pressao arterial<input name="pressao_arterial" maxlength="40"></label>
                <label>Alimentacao<input name="alimentacao" maxlength="255"></label>
                <label>Eliminacoes<input name="eliminacoes" maxlength="255"></label>
                <label>Medicacoes<textarea name="medicacoes" rows="3" maxlength="10000"></textarea></label>
                <label>Procedimentos<textarea name="procedimentos" rows="3" maxlength="10000"></textarea></label>
                <label class="span-2">Evolucao clinica<textarea name="observacoes" rows="5" maxlength="10000" required></textarea></label>
            </div>
            <div class="modal-actions"><button class="button secondary" type="button" data-close-dialog>Cancelar</button><button class="button primary" type="submit">Registrar evolucao</button></div>
        </form>
    </dialog>

    <dialog class="modal" id="vetDialog">
        <form id="vetForm" method="dialog" enctype="multipart/form-data">
            <div class="modal-header">
                <div><p class="section-kicker">Equipe</p><h2 id="vetDialogTitle">Vincular veterinario</h2></div>
                <button class="icon-button" type="button" data-close-dialog aria-label="Fechar">&times;</button>
            </div>
            <input type="hidden" name="id">
            <div class="photo-field">
                <div class="photo-preview" data-photo-preview><span>Foto</span></div>
                <label class="button secondary">Selecionar foto<input type="file" name="foto" accept="image/jpeg,image/png,image/webp" hidden></label>
            </div>
            <div class="form-grid">
                <label class="span-2">Usuario do sistema<select name="usuario_id" required></select></label>
                <label>CRMV<input name="crmv" maxlength="30" required></label>
                <label>UF do CRMV<input name="uf_crmv" maxlength="2" required></label>
                <label>Especialidade<input name="especialidade" maxlength="120"></label>
                <label>Telefone profissional<input name="telefone_profissional" maxlength="20"></label>
                <label class="span-2">Apresentacao<textarea name="biografia" rows="4" maxlength="500"></textarea></label>
                <label class="checkbox-field"><input name="ativo" type="checkbox" checked><span>Profissional ativo</span></label>
            </div>
            <div class="modal-actions"><button class="button secondary" type="button" data-close-dialog>Cancelar</button><button class="button primary" type="submit">Salvar veterinario</button></div>
        </form>
    </dialog>

    <dialog class="modal record-modal" id="recordDialog">
        <div class="modal-header sticky">
            <div><p class="section-kicker">Historico clinico</p><h2 id="recordTitle">Prontuario</h2></div>
            <button class="icon-button" type="button" data-close-dialog aria-label="Fechar">&times;</button>
        </div>
        <div id="recordContent"><p class="empty-state">Carregando prontuario...</p></div>
    </dialog>

    <dialog class="modal" id="groomingDialog">
        <form id="groomingForm" method="dialog">
            <div class="modal-header"><div><p class="section-kicker">Estetica pet</p><h2 id="groomingDialogTitle">Novo agendamento</h2></div><button class="icon-button" type="button" data-commerce-close aria-label="Fechar">&times;</button></div>
            <input type="hidden" name="id">
            <div class="form-grid commerce-form">
                <label class="span-2">Paciente<select name="animal_id" required></select></label>
                <label class="span-2">Servico<select name="servico_id" required></select></label>
                <label>Inicio<input name="inicio_em" type="datetime-local" required></label>
                <label>Status<select name="status"><option value="agendado">Agendado</option><option value="confirmado">Confirmado</option><option value="em_atendimento">Em atendimento</option><option value="concluido">Concluido</option><option value="cancelado">Cancelado</option><option value="nao_compareceu">Nao compareceu</option></select></label>
                <label>Fim previsto<input name="fim_previsto_em" type="datetime-local"></label>
                <label>Fim realizado<input name="fim_em" type="datetime-local"></label>
                <label class="span-2">Profissional<input name="profissional_nome" maxlength="140"></label>
                <label>Observacoes de entrada<textarea name="observacoes_entrada" rows="3" maxlength="10000"></textarea></label>
                <label>Observacoes de saida<textarea name="observacoes_saida" rows="3" maxlength="10000"></textarea></label>
            </div>
            <div class="modal-actions"><button class="button secondary" type="button" data-commerce-close>Cancelar</button><button class="button primary" type="submit">Salvar agendamento</button></div>
        </form>
    </dialog>

    <dialog class="modal" id="serviceDialog">
        <form id="serviceForm" method="dialog">
            <div class="modal-header"><div><p class="section-kicker">Catalogo de servicos</p><h2>Novo servico</h2></div><button class="icon-button" type="button" data-commerce-close aria-label="Fechar">&times;</button></div>
            <div class="form-grid commerce-form">
                <label>Codigo<input name="codigo" maxlength="50" required></label><label>Nome<input name="nome" maxlength="140" required></label>
                <label>Categoria<select name="categoria"><option value="banho">Banho</option><option value="tosa">Tosa</option><option value="spa">Spa</option><option value="higiene">Higiene</option><option value="outro">Outro</option></select></label>
                <label>Duracao (minutos)<input name="duracao_minutos" type="number" min="5" max="1440" value="60" required></label>
                <label>Preco<input name="preco" type="number" min="0" step="0.01" required></label>
                <label class="checkbox-field"><input name="ativo" type="checkbox" checked><span>Servico ativo</span></label>
                <label class="span-2">Descricao<textarea name="descricao" rows="3" maxlength="500"></textarea></label>
            </div>
            <div class="modal-actions"><button class="button secondary" type="button" data-commerce-close>Cancelar</button><button class="button primary" type="submit">Salvar servico</button></div>
        </form>
    </dialog>

    <dialog class="modal" id="productDialog">
        <form id="productForm" method="dialog">
            <div class="modal-header"><div><p class="section-kicker">Catalogo comercial</p><h2 id="productDialogTitle">Novo produto</h2></div><button class="icon-button" type="button" data-commerce-close aria-label="Fechar">&times;</button></div>
            <input type="hidden" name="id">
            <div class="form-grid commerce-form">
                <label>SKU<input name="sku" maxlength="60" required></label><label>Nome<input name="nome" maxlength="180" required></label>
                <label>Categoria<select name="categoria"><option value="racao">Racao</option><option value="petisco">Petisco</option><option value="higiene">Higiene</option><option value="acessorio">Acessorio</option><option value="medicamento">Medicamento</option><option value="outro">Outro</option></select></label>
                <label>Unidade<input name="unidade" maxlength="20" value="un" required></label>
                <label>Marca<input name="marca" maxlength="100"></label><label>Codigo de barras<input name="codigo_barras" maxlength="80"></label>
                <label>Preco de custo<input name="preco_custo" type="number" min="0" step="0.01"></label><label>Preco de venda<input name="preco_venda" type="number" min="0" step="0.01" required></label>
                <label data-initial-stock>Estoque inicial<input name="estoque_inicial" type="number" min="0" step="0.001" value="0"></label><label>Estoque minimo<input name="estoque_minimo" type="number" min="0" step="0.001" value="0"></label>
                <label class="checkbox-field"><input name="controla_estoque" type="checkbox" checked><span>Controlar estoque</span></label><label class="checkbox-field"><input name="ativo" type="checkbox" checked><span>Produto ativo</span></label>
            </div>
            <div class="modal-actions"><button class="button secondary" type="button" data-commerce-close>Cancelar</button><button class="button primary" type="submit">Salvar produto</button></div>
        </form>
    </dialog>

    <dialog class="modal" id="stockDialog">
        <form id="stockForm" method="dialog">
            <div class="modal-header"><div><p class="section-kicker">Movimento manual</p><h2>Atualizar estoque</h2></div><button class="icon-button" type="button" data-commerce-close aria-label="Fechar">&times;</button></div>
            <div class="form-grid commerce-form">
                <label class="span-2">Produto<select name="produto_id" required></select></label>
                <label>Tipo<select name="tipo"><option value="entrada">Entrada</option><option value="saida">Saida</option><option value="ajuste_positivo">Ajuste positivo</option><option value="ajuste_negativo">Ajuste negativo</option></select></label>
                <label>Quantidade<input name="quantidade" type="number" min="0.001" step="0.001" required></label>
                <label>Custo unitario<input name="custo_unitario" type="number" min="0" step="0.01"></label>
                <label class="span-2">Motivo<textarea name="motivo" rows="3" maxlength="500" required></textarea></label>
            </div>
            <div class="modal-actions"><button class="button secondary" type="button" data-commerce-close>Cancelar</button><button class="button primary" type="submit">Confirmar movimento</button></div>
        </form>
    </dialog>

    <dialog class="modal wide" id="saleDialog">
        <form id="saleForm" method="dialog">
            <div class="modal-header"><div><p class="section-kicker">Ponto de venda</p><h2>Nova venda</h2></div><button class="icon-button" type="button" data-commerce-close aria-label="Fechar">&times;</button></div>
            <div class="form-grid commerce-form sale-header">
                <label class="span-2">Tutor (opcional)<select name="tutor_id"><option value="">Consumidor nao identificado</option></select></label>
                <label>Forma de pagamento<select name="forma_pagamento"><option value="pix">PIX</option><option value="dinheiro">Dinheiro</option><option value="debito">Debito</option><option value="credito">Credito</option><option value="outro">Outro</option></select></label>
                <label>Desconto<input name="desconto" type="number" min="0" step="0.01" value="0"></label>
            </div>
            <div class="sale-items-section">
                <div class="panel-heading"><div><h3>Itens da venda</h3><p>Precos e estoque sao confirmados pelo servidor</p></div><button class="button secondary" id="addSaleItemButton" type="button">Adicionar item</button></div>
                <div id="saleItems" class="sale-items"></div>
                <div class="sale-total"><span>Total estimado</span><strong id="saleEstimatedTotal">R$ 0,00</strong></div>
            </div>
            <div class="form-grid commerce-form"><label class="span-2">Observacoes<textarea name="observacoes" rows="2" maxlength="1000"></textarea></label></div>
            <div class="modal-actions"><button class="button secondary" type="button" data-commerce-close>Cancelar</button><button class="button primary" type="submit">Concluir venda</button></div>
        </form>
    </dialog>

    <div class="toast-region" id="toastRegion" aria-live="polite" aria-atomic="true"></div>
    <div class="loading-overlay" id="loadingOverlay" hidden><span></span><p>Processando...</p></div>

    <script src="frontend/js/app.js?v=<?= rawurlencode(PET_VERSION) ?>"></script>
    <script src="frontend/js/modules/commerce.js?v=<?= rawurlencode(PET_VERSION) ?>"></script>
</body>
</html>
