<?php
declare(strict_types=1);

/**
 * Tabela editável de itens de orçamento, compartilhada por "novo" e "editar".
 * Os campos pertencem ao formulário $formId (atributo form), mesmo fora dele.
 */
function orcamentoEditorItens(array $categorias, string $formId, float $bdi = 0.0): void
{
    ?>
<div class="table-wrap">
    <table id="tabelaItens">
        <thead><tr>
            <th style="width:150px">Etapa</th>
            <th style="width:90px">Código</th>
            <th>Descrição *</th>
            <th style="width:80px">Unid.</th>
            <th style="width:90px">Qtd</th>
            <th style="width:110px">Preço Unit.</th>
            <th>Categoria</th>
            <th style="width:110px">Total</th>
            <th style="width:40px"></th>
        </tr></thead>
        <tbody id="itensBody"></tbody>
    </table>
</div>
<div style="padding:12px 24px;display:flex;justify-content:flex-end;align-items:center;gap:16px;flex-wrap:wrap;border-top:1px solid var(--neutral-100)">
    <span class="text-sm text-muted">Custo direto:</span>
    <strong id="totalGeral">R$ 0,00</strong>
    <label class="text-sm text-muted" for="bdiInput">BDI (%)</label>
    <input type="number" name="bdi_percentual" id="bdiInput" form="<?= sanitize($formId) ?>" value="<?= htmlspecialchars((string) $bdi, ENT_QUOTES) ?>" step="any" min="0" max="200" class="form-control" style="width:90px;padding:5px 8px" oninput="recalcTotal()">
    <span class="text-sm text-muted">Total com BDI:</span>
    <strong style="font-size:1.1rem" id="totalComBdi">R$ 0,00</strong>
</div>
<datalist id="etapasSugeridas">
    <?php foreach (['Serviços preliminares', 'Fundação', 'Estrutura', 'Alvenaria e vedação', 'Cobertura', 'Impermeabilização', 'Instalações elétricas', 'Instalações hidrossanitárias', 'Esquadrias', 'Revestimentos', 'Pisos', 'Forros', 'Pintura', 'Louças e metais', 'Limpeza final'] as $etapa): ?>
    <option value="<?= $etapa ?>">
    <?php endforeach; ?>
</datalist>
<script>
const EDITOR_FORM = <?= jsonAttr($formId) ?>;
const CATEGORIAS = <?= json_encode(array_map(fn($c) => ['id' => $c['id'], 'nome' => $c['nome']], $categorias), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
let nextIdx = 0;

function esc(s){ return String(s ?? '').replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;'); }
function normalizarUnidade(valor) {
    const u = String(valor || 'UN').trim().toUpperCase().replace(/\.$/, '');
    const mapa = {'M2':'M²','M3':'M³','UND':'UN','UNID':'UN','PÇ':'PC','PCS':'PC','LT':'L','VERBA':'VB'};
    return mapa[u] || u || 'UN';
}
function unidadeOpts(valor) {
    const uns = ['UN','M','M²','M³','KG','CX','PC','RL','SC','L','GL','KIT','CJ','VB','H','MÊS','T'];
    const atual = normalizarUnidade(valor);
    if (!uns.includes(atual)) uns.push(atual);
    return uns.map(u => `<option ${u===atual?'selected':''}>${esc(u)}</option>`).join('');
}
function selCatHtml(valor) {
    let opts = '<option value="">-- Categoria</option>';
    CATEGORIAS.forEach(c => opts += `<option value="${c.id}" ${String(valor)===String(c.id)?'selected':''}>${esc(c.nome)}</option>`);
    return `<select name="item_cat[]" form="${EDITOR_FORM}" class="form-control" style="font-size:.8rem;padding:4px 6px">${opts}</select>`;
}
function renderLinha(item, idx) {
    const f = `form="${EDITOR_FORM}"`;
    const st = 'font-size:.8rem;padding:5px 7px';
    const aviso = (item.duplicado || item.semelhante) && item.produto_nome_similar
        ? `<div class="text-xs" style="color:#b45309">${item.duplicado ? 'Já cadastrado: ' : 'Semelhante a: '}${esc(item.produto_nome_similar)}</div>` : '';
    return `<tr id="row${idx}">
        <td><input type="hidden" name="item_id[]" ${f} value="${esc(item.id||'')}"><input type="text" name="item_etapa[]" ${f} list="etapasSugeridas" value="${esc(item.etapa||'')}" class="form-control" style="${st}"></td>
        <td><input type="text" name="item_codigo[]" ${f} value="${esc(item.codigo||'')}" class="form-control" style="${st}"></td>
        <td><input type="text" name="item_desc[]" ${f} value="${esc(item.descricao||'')}" class="form-control" style="${st}" required>${aviso}</td>
        <td><select name="item_un[]" ${f} class="form-control" style="font-size:.8rem;padding:4px 6px">${unidadeOpts(item.unidade||'UN')}</select></td>
        <td><input type="number" name="item_qtd[]" ${f} value="${esc(item.quantidade ?? 1)}" step="any" min="0" class="form-control qtd-input" style="${st}" oninput="recalcTotal()"></td>
        <td><input type="number" name="item_preco[]" ${f} value="${esc(item.preco_unitario ?? 0)}" step="any" min="0" class="form-control preco-input" style="${st}" oninput="recalcTotal()"></td>
        <td>${selCatHtml(item.categoria_id || item.categoria_id_sugerida || '')}</td>
        <td class="row-total text-sm font-bold" style="color:var(--primary)">R$ 0,00</td>
        <td><button type="button" class="btn btn-sm btn-danger" title="Remover linha" onclick="removerLinha(${idx})">×</button></td>
    </tr>`;
}
function brl(v) { return 'R$ ' + v.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2}); }
function carregarItens(itens) {
    const lista = itens && itens.length ? itens : [{}];
    nextIdx = 0;
    document.getElementById('itensBody').innerHTML = lista.map(item => renderLinha(item, nextIdx++)).join('');
    recalcTotal();
}
function adicionarLinha() {
    const linhas = document.querySelectorAll('#itensBody [name="item_etapa[]"]');
    const ultimaEtapa = linhas.length ? linhas[linhas.length - 1].value : '';
    document.getElementById('itensBody').insertAdjacentHTML('beforeend', renderLinha({etapa: ultimaEtapa}, nextIdx++));
    recalcTotal();
}
function removerLinha(idx) {
    const row = document.getElementById('row' + idx);
    if (row) { row.remove(); recalcTotal(); }
}
function recalcTotal() {
    let total = 0;
    document.querySelectorAll('#itensBody tr').forEach(row => {
        const q = parseFloat(row.querySelector('.qtd-input')?.value || 0);
        const p = parseFloat(row.querySelector('.preco-input')?.value || 0);
        const t = Math.round(q * p * 100) / 100;
        const el = row.querySelector('.row-total');
        if (el) el.textContent = brl(t);
        total += t;
    });
    const bdi = Math.max(0, parseFloat(document.getElementById('bdiInput').value || 0));
    document.getElementById('totalGeral').textContent = brl(total);
    document.getElementById('totalComBdi').textContent = brl(Math.round(total * (1 + bdi / 100) * 100) / 100);
}
function validarItens() {
    const linhas = document.querySelectorAll('#itensBody tr');
    if (!linhas.length) { alert('Adicione ao menos um item.'); return false; }
    for (const r of linhas) {
        if (!r.querySelector('[name="item_desc[]"]').value.trim()) { alert('Preencha a descrição de todos os itens.'); return false; }
    }
    return true;
}
</script>
    <?php
}

/** Converte os campos item_*[] do POST para o formato do OrcamentoService. */
function orcamentoItensDoPost(array $post): array
{
    $itens = [];
    foreach (($post['item_desc'] ?? []) as $i => $descricao) {
        $itens[] = [
            'id' => $post['item_id'][$i] ?? null,
            'etapa' => $post['item_etapa'][$i] ?? '',
            'ordem' => $i + 1,
            'descricao' => $descricao,
            'unidade' => $post['item_un'][$i] ?? 'UN',
            'quantidade' => $post['item_qtd'][$i] ?? 1,
            'preco_unitario' => $post['item_preco'][$i] ?? 0,
            'categoria_id' => $post['item_cat'][$i] ?? null,
            'codigo' => $post['item_codigo'][$i] ?? null,
        ];
    }
    return $itens;
}
