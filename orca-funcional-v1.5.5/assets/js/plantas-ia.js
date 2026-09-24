// Junção das plantas: as pranchas chegam espalhadas, têm o contorno desenhado, são lidas,
// ligadas entre si e encaixadas numa imagem única — tudo em SVG, com as cores do tema.
(function () {
    const configEl = document.getElementById('uniaoConfig');
    const svg = document.getElementById('uniaoSvg');
    if (!configEl || !svg) return;
    const cfg = JSON.parse(configEl.textContent);
    const fatias = cfg.fatias || [];
    if (fatias.length === 0) return;

    const NS = 'http://www.w3.org/2000/svg';
    const W = 1600;
    const H = 900;
    const reduzido = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const num = new Intl.NumberFormat('pt-BR', { maximumFractionDigits: 2 });
    const $ = (id) => document.getElementById(id);
    const els = {
        pecas: $('uniaoPecas'), ligacoes: $('uniaoLigacoes'), contorno: $('uniaoContorno'), luz: $('uniaoLuz'), recorte: $('uniaoRecorteRect'),
        etapas: $('uniaoEtapas'), progresso: $('uniaoProgresso'), status: $('uniaoStatus'), repetir: $('uniaoRepetir'), baixar: $('uniaoBaixar'),
        area: $('uniaoArea'), pavimentos: $('uniaoPavimentos'), ambientes: $('uniaoAmbientes'), esquadrias: $('uniaoEsquadrias'), aviso: $('uniaoAviso'),
    };
    // ligações e rótulos de leitura ficam por cima das pranchas
    svg.insertBefore(els.ligacoes, els.contorno);
    const chips = criar('g', {}, svg);

    if (window.pdfjsLib) window.pdfjsLib.GlobalWorkerOptions.workerSrc = cfg.pdfWorker;

    const imagens = new Map(); // id -> {url, ratio, img}
    const analises = new Map(); // id -> Promise<análise|null>
    let rodada = 0;
    let razao = 0.707; // altura/largura das pranchas; recalculada pela mediana das que abriram
    let grade = null;
    let pecas = [];

    // Rótulo curto: sem o prefixo comum dos títulos ("544ROM-... - Planta de piso - ")
    const rotulos = (function () {
        const titulos = fatias.map((f) => f.titulo);
        let prefixo = titulos.length > 1 ? titulos[0] : '';
        titulos.forEach((t) => { while (prefixo && !t.startsWith(prefixo)) prefixo = prefixo.slice(0, -1); });
        const corte = Math.max(prefixo.lastIndexOf(' '), prefixo.lastIndexOf('-'), prefixo.lastIndexOf('_'));
        return titulos.map((t) => ((corte > 4 ? t.slice(corte + 1) : t).replace(/^[\s\-_]+/, '') || t).slice(0, 34));
    })();

    function criar(tag, attrs, pai) {
        const el = document.createElementNS(NS, tag);
        Object.entries(attrs).forEach(([k, v]) => el.setAttribute(k, String(v)));
        if (pai) pai.appendChild(el);
        return el;
    }
    const pausa = (ms, token) => new Promise((resolve) => setTimeout(() => resolve(token === rodada), reduzido ? 0 : ms));
    const quadro = () => new Promise((resolve) => requestAnimationFrame(() => requestAnimationFrame(resolve)));

    function etapa(n) {
        els.etapas.querySelectorAll('li').forEach((li) => {
            const e = Number(li.dataset.etapa);
            li.classList.toggle('is-feita', e < n || n === 5);
            li.classList.toggle('is-atual', e === n);
        });
    }
    function progresso(pct, texto) {
        els.progresso.style.width = Math.max(0, Math.min(100, pct)) + '%';
        if (texto) els.status.textContent = texto;
    }

    // ---------- arquivos: PDF (1ª página via pdf.js) ou imagem, reduzidos para a animação ----------
    function reduzir(largura, altura, max) {
        const escala = Math.min(1, max / Math.max(largura, altura));
        return [Math.max(1, Math.round(largura * escala)), Math.max(1, Math.round(altura * escala))];
    }
    async function preparar(fatia) {
        if (imagens.has(fatia.id)) return imagens.get(fatia.id);
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');
        if (fatia.mime === 'application/pdf') {
            if (!window.pdfjsLib) throw new Error('leitor de PDF indisponível');
            const doc = await window.pdfjsLib.getDocument({ url: fatia.src, withCredentials: true }).promise;
            try {
                const pagina = await doc.getPage(1);
                const base = pagina.getViewport({ scale: 1 });
                const [w] = reduzir(base.width, base.height, 1600);
                const viewport = pagina.getViewport({ scale: w / base.width });
                canvas.width = Math.round(viewport.width);
                canvas.height = Math.round(viewport.height);
                ctx.fillStyle = '#fff';
                ctx.fillRect(0, 0, canvas.width, canvas.height);
                await pagina.render({ canvasContext: ctx, viewport }).promise;
            } finally {
                doc.destroy();
            }
        } else {
            const img = new Image();
            img.src = fatia.src;
            await img.decode();
            [canvas.width, canvas.height] = reduzir(img.naturalWidth, img.naturalHeight, 1600);
            ctx.fillStyle = '#fff';
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
        }
        const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', .88));
        const url = URL.createObjectURL(blob);
        const img = new Image();
        img.src = url;
        await img.decode();
        const pronto = { url, img, ratio: canvas.height / canvas.width };
        imagens.set(fatia.id, pronto);
        return pronto;
    }
    // uma leitura de texto por vez, para não travar o download das pranchas no servidor
    let filaLeitura = Promise.resolve();
    function analisar(fatia) {
        if (!analises.has(fatia.id)) {
            analises.set(fatia.id, fatia.mime !== 'application/pdf' ? Promise.resolve(null)
                : (filaLeitura = filaLeitura.then(() => fetch(cfg.endpoint + '&analise=' + fatia.id, { credentials: 'same-origin' })
                    .then((r) => r.json()).then((json) => (json.ok ? json.analise : null)).catch(() => null))));
        }
        return analises.get(fatia.id);
    }

    // ---------- grade final: colunas que deixam as pranchas maiores dentro do palco ----------
    function calcularGrade(n) {
        let melhor = null;
        for (let c = 1; c <= n; c++) {
            const r = Math.ceil(n / c);
            const w = Math.min((W - 140) / c, (H - 120) / (r * razao));
            if (!melhor || w > melhor.w) melhor = { cols: c, rows: r, w };
        }
        return { ...melhor, h: melhor.w * razao };
    }
    function vaga(i, gap) {
        const { cols, rows, w, h } = grade;
        const linha = Math.floor(i / cols);
        const naLinha = Math.min(cols, pecas.length - linha * cols); // só as pranchas que abriram
        const larguraTotal = cols * w + (cols - 1) * gap;
        const alturaTotal = rows * h + (rows - 1) * gap;
        const recuo = (cols - naLinha) * (w + gap) / 2; // última linha centralizada
        return {
            x: (W - larguraTotal) / 2 + recuo + w / 2 + (i % cols) * (w + gap),
            y: (H - alturaTotal) / 2 + h / 2 + linha * (h + gap),
        };
    }
    function mover(peca, x, y, giro, escala) {
        peca.pos = { x, y, giro, escala };
        peca.g.style.transform = 'translate(' + x + 'px,' + y + 'px) rotate(' + giro + 'deg) scale(' + escala + ')';
    }

    function criarPeca(i) {
        const { w, h } = grade;
        const g = criar('g', { class: 'uniao-peca' }, els.pecas);
        criar('rect', { class: 'uniao-papel', x: -w / 2, y: -h / 2, width: w, height: h, rx: 6 }, g);
        const img = criar('image', { class: 'uniao-img', x: -w / 2 + 4, y: -h / 2 + 4, width: w - 8, height: h - 8, preserveAspectRatio: 'xMidYMid meet' }, g);
        const recortada = criar('g', { 'clip-path': 'url(#uniaoPecaRecorte)' }, g);
        const faixa = criar('rect', { class: 'uniao-faixa', x: -w / 2, y: -h / 2 - h * .35, width: w, height: h * .35, fill: 'url(#uniaoFaixa)' }, recortada);
        criar('rect', { class: 'uniao-borda', x: -w / 2, y: -h / 2, width: w, height: h, rx: 6, pathLength: 100 }, g);
        const rotulo = criar('g', { class: 'uniao-rotulo' }, g);
        const fundo = criar('rect', { x: -w / 2 + 10, y: -h / 2 + 10, height: 26, rx: 13 }, rotulo);
        const texto = criar('text', { x: -w / 2 + 22, y: -h / 2 + 28 }, rotulo);
        texto.textContent = rotulos[i];
        fundo.setAttribute('width', String(Math.min(w - 20, texto.getComputedTextLength() + 24)));
        return { i, fatia: fatias[i], g, img, faixa, pos: null };
    }

    // ---------- etapa 1: as pranchas chegam numa fila e se espalham pela mesa ----------
    function posicoesFila(n) {
        const lado = Math.min(150, (W - 160) / Math.min(n, 8) - 16);
        const porLinha = Math.min(n, 8);
        const linhas = Math.ceil(n / porLinha);
        return Array.from({ length: n }, (_, i) => {
            const linha = Math.floor(i / porLinha);
            const naLinha = Math.min(porLinha, n - linha * porLinha);
            return {
                x: W / 2 + (i % porLinha - (naLinha - 1) / 2) * (lado + 16),
                y: H / 2 + (linha - (linhas - 1) / 2) * (lado * .8 + 16),
                lado,
            };
        });
    }
    async function receber(token) {
        const lugares = posicoesFila(fatias.length);
        const fila = criar('g', {}, els.pecas);
        const vagas = lugares.map((l, i) => {
            const g = criar('g', { class: 'uniao-peca', style: 'transform: translate(' + l.x + 'px,' + l.y + 'px)' }, fila);
            const w = l.lado;
            const h = l.lado * .72;
            criar('rect', { class: 'uniao-papel', x: -w / 2, y: -h / 2, width: w, height: h, rx: 5 }, g);
            const img = criar('image', { class: 'uniao-img', x: -w / 2 + 3, y: -h / 2 + 3, width: w - 6, height: h - 6, preserveAspectRatio: 'xMidYMid meet' }, g);
            criar('rect', { class: 'uniao-borda', x: -w / 2, y: -h / 2, width: w, height: h, rx: 5, pathLength: 100 }, g);
            return { g, img, lugar: l, i };
        });
        let recebidas = 0;
        const pendentes = vagas.slice();
        // até 3 arquivos por vez: PDFs grandes pesam no navegador
        await Promise.all([0, 1, 2].map(async () => {
            while (pendentes.length && token === rodada) {
                const v = pendentes.shift();
                v.g.classList.add('is-desenhada');
                try {
                    v.pronto = await preparar(fatias[v.i]);
                    if (token !== rodada) return;
                    v.img.setAttribute('href', v.pronto.url);
                    v.g.classList.add('is-visivel');
                } catch (e) {
                    v.g.setAttribute('opacity', '.25');
                }
                recebidas++;
                progresso(recebidas / vagas.length * 35, 'Recebendo prancha ' + recebidas + ' de ' + vagas.length + ' · ' + rotulos[v.i]);
            }
        }));
        return { fila, vagas: vagas.filter((v) => v.pronto) };
    }
    async function espalhar(peca, lugar, token) {
        const margem = grade.w * .3;
        const x = margem + Math.random() * (W - 2 * margem);
        const y = margem * razao + Math.random() * (H - 2 * margem * razao);
        const giro = (Math.random() - .5) * 26;
        const escala = Math.min(.5, 380 / grade.w);
        peca.g.style.transition = 'none';
        mover(peca, lugar.x, lugar.y, 0, lugar.lado / grade.w);
        peca.g.classList.add('is-visivel');
        await quadro();
        peca.g.style.transition = '';
        mover(peca, x, y, giro, escala);
        await pausa(500, token);
        peca.g.classList.add('is-desenhada');
    }

    // ---------- etapa 2: faixa de leitura e pontos encontrados ----------
    function achado(analise, i) {
        if (!analise) return fatias[i].mime === 'application/pdf' ? 'PDF sem texto' : 'planta lida';
        if (analise.area_construida) return num.format(analise.area_construida) + ' m²';
        const esq = (analise.esquadrias || []).reduce((s, e) => s + e.quantidade, 0);
        if (esq) return esq + ' esquadrias';
        if (analise.ambientes && analise.ambientes.total) return analise.ambientes.total + ' ambientes';
        return 'texto lido';
    }
    function chip(texto, x, y) {
        const g = criar('g', { class: 'uniao-chip', opacity: 0 }, chips);
        const t = criar('text', { x: x, y: y + 5, 'text-anchor': 'middle' }, g);
        t.textContent = texto;
        const larg = t.getComputedTextLength() + 26;
        g.insertBefore(criar('rect', { x: x - larg / 2, y: y - 16, width: larg, height: 32, rx: 16 }), t);
        g.animate([{ opacity: 0, transform: 'translateY(10px)' }, { opacity: 1, transform: 'translateY(0)' }], { duration: 300, fill: 'forwards', easing: 'ease-out' });
        setTimeout(() => g.animate([{ opacity: 1 }, { opacity: 0 }], { duration: 400, fill: 'forwards' }).finished.then(() => g.remove()), reduzido ? 0 : 1500);
    }
    async function ler(peca, token) {
        const { w, h } = grade;
        const { x, y, giro, escala } = peca.pos;
        mover(peca, x, y, giro * .4, escala * 1.12);
        peca.faixa.animate([
            { opacity: 1, transform: 'translateY(0)' },
            { opacity: 1, transform: 'translateY(' + (h * 1.35) + 'px)', offset: .92 },
            { opacity: 0, transform: 'translateY(' + (h * 1.35) + 'px)' },
        ], { duration: reduzido ? 1 : 1000, easing: 'ease-in-out' });
        for (let k = 0; k < 3; k++) {
            const px = (Math.random() - .5) * w * .7;
            const py = (Math.random() - .5) * h * .7;
            const ponto = criar('circle', { class: 'uniao-ponto', cx: px, cy: py, r: 9 }, peca.g);
            const halo = criar('circle', { class: 'uniao-ponto-halo', cx: px, cy: py, r: 9 }, peca.g);
            const atraso = 200 + k * 220;
            ponto.animate([{ opacity: 0 }, { opacity: 1, offset: .2 }, { opacity: 1, offset: .8 }, { opacity: 0 }], { duration: 1400, delay: atraso, fill: 'both' });
            halo.animate([{ opacity: .9, r: 9 }, { opacity: 0, r: 40 }], { duration: 900, delay: atraso, fill: 'both' });
            setTimeout(() => { ponto.remove(); halo.remove(); }, reduzido ? 0 : atraso + 1500);
        }
        const analise = await analisar(peca.fatia);
        if (token !== rodada) return;
        chip(achado(analise, peca.i), x, y - (h * escala) / 2 - 22);
        await pausa(500, token);
        mover(peca, x, y, giro, escala);
    }

    // ---------- etapa 3: ligações entre vizinhas e encaixe ----------
    function ligar(token) {
        const { cols } = grade;
        const centros = pecas.map((_, i) => vaga(i, 44));
        let atraso = 0;
        centros.forEach((c, i) => {
            const vizinhas = [];
            if ((i + 1) % cols !== 0 && i + 1 < centros.length) vizinhas.push(i + 1);
            if (i + cols < centros.length) vizinhas.push(i + cols);
            vizinhas.forEach((j) => {
                const d = centros[j];
                const curva = (d.x - c.x) * .12 - (d.y - c.y) * .12;
                const caminho = criar('path', { class: 'uniao-ligacao', pathLength: 100, d: 'M' + c.x + ' ' + c.y + ' Q' + ((c.x + d.x) / 2 + curva) + ' ' + ((c.y + d.y) / 2 - curva) + ' ' + d.x + ' ' + d.y }, els.ligacoes);
                setTimeout(() => { if (token === rodada) caminho.classList.add('is-desenhada'); }, reduzido ? 0 : atraso);
                atraso += 70;
            });
            const no = criar('circle', { class: 'uniao-no', cx: c.x, cy: c.y, r: 11 }, els.ligacoes);
            no.animate([{ r: 0 }, { r: 15, offset: .6 }, { r: 11 }], { duration: 500, delay: reduzido ? 0 : i * 70, fill: 'both' });
        });
    }
    async function unir(token) {
        pecas.forEach((p, i) => {
            setTimeout(() => { if (token === rodada) { const v = vaga(i, 44); mover(p, v.x, v.y, 0, 1); } }, reduzido ? 0 : i * 60);
        });
        if (!(await pausa(1300 + pecas.length * 60, token))) return false;
        ligar(token);
        if (!(await pausa(1400 + pecas.length * 70, token))) return false;
        els.ligacoes.classList.add('is-saindo');
        pecas.forEach((p, i) => { const v = vaga(i, 0); mover(p, v.x, v.y, 0, 1); p.g.classList.add('is-unida'); });
        if (!(await pausa(1300, token))) return false;

        const { cols, rows, w, h } = grade;
        const cx = (W - cols * w) / 2;
        const cy = (H - rows * h) / 2;
        [els.contorno, els.recorte].forEach((r) => {
            r.setAttribute('x', String(cx - 6)); r.setAttribute('y', String(cy - 6));
            r.setAttribute('width', String(cols * w + 12)); r.setAttribute('height', String(rows * h + 12));
        });
        els.contorno.classList.add('is-desenhada');
        els.luz.setAttribute('y', String(cy));
        els.luz.setAttribute('height', String(rows * h));
        els.luz.animate([{ opacity: 1, transform: 'translateX(' + cx + 'px)' }, { opacity: 1, transform: 'translateX(' + (cx + cols * w + 400) + 'px)' }],
            { duration: reduzido ? 1 : 1500, delay: 500, easing: 'ease-in-out' });
        return pausa(1600, token);
    }

    // ---------- etapa 4: dados unidos das pranchas ----------
    function contar(el, valor, formato) {
        if (valor === null || valor === undefined) { el.textContent = '—'; return; }
        const inicio = performance.now();
        const dur = reduzido ? 1 : 900;
        (function passo(agora) {
            const t = Math.min(1, (agora - inicio) / dur);
            el.textContent = formato(valor * (1 - Math.pow(1 - t, 3)));
            if (t < 1) requestAnimationFrame(passo);
            else el.textContent = formato(valor);
        })(inicio);
    }
    async function mostrarDados(token) {
        let fusao = null;
        try {
            const r = await fetch(cfg.endpoint + '&fusao=1', { credentials: 'same-origin' });
            const json = await r.json();
            fusao = json.ok ? json.analise : null;
        } catch (e) { fusao = null; }
        if (token !== rodada) return;
        const temPdf = fatias.some((f) => f.mime === 'application/pdf');
        const util = fusao && fusao.texto_util;
        contar(els.area, util ? fusao.area_construida : null, (v) => num.format(Math.round(v * 100) / 100));
        contar(els.pavimentos, util ? fusao.pavimentos : null, (v) => String(Math.round(v)));
        contar(els.ambientes, util ? fusao.ambientes.total : null, (v) => String(Math.round(v)));
        contar(els.esquadrias, util ? fusao.esquadrias.reduce((s, e) => s + e.quantidade, 0) : null, (v) => String(Math.round(v)));
        const aviso = !temPdf ? 'As pranchas desta obra são imagens: a junção é visual e as medidas não podem ser lidas automaticamente.'
            : (fusao && fusao.avisos && fusao.avisos.length ? fusao.avisos.join(' ') : '');
        els.aviso.hidden = aviso === '';
        els.aviso.textContent = aviso;
    }

    // ---------- sequência ----------
    async function executar() {
        const token = ++rodada;
        els.repetir.hidden = true;
        els.baixar.hidden = true;
        els.pecas.innerHTML = '';
        els.ligacoes.innerHTML = '';
        els.ligacoes.classList.remove('is-saindo');
        chips.innerHTML = '';
        els.contorno.classList.remove('is-desenhada');
        els.aviso.hidden = true;
        [els.area, els.pavimentos, els.ambientes, els.esquadrias].forEach((el) => { el.textContent = '—'; });

        etapa(1);
        fatias.forEach(analisar);
        progresso(0, 'Recebendo as pranchas…');
        const { fila, vagas } = await receber(token);
        if (token !== rodada) return;
        if (vagas.length === 0) { progresso(0, 'Não foi possível abrir as pranchas desta obra.'); return; }
        const falhas = fatias.length - vagas.length;

        // formato das peças: mediana das pranchas (plantas em pé ou deitadas)
        const razoes = vagas.map((v) => v.pronto.ratio).sort((a, b) => a - b);
        razao = Math.max(.45, Math.min(1.6, razoes[Math.floor(razoes.length / 2)]));
        grade = calcularGrade(vagas.length);
        let recorte = svg.querySelector('#uniaoPecaRecorte');
        if (!recorte) {
            recorte = criar('clipPath', { id: 'uniaoPecaRecorte' }, svg.querySelector('defs'));
            criar('rect', {}, recorte);
        }
        const r = recorte.firstChild;
        r.setAttribute('x', String(-grade.w / 2)); r.setAttribute('y', String(-grade.h / 2));
        r.setAttribute('width', String(grade.w)); r.setAttribute('height', String(grade.h));
        if (!(await pausa(500, token))) return;

        pecas = vagas.map((v) => {
            const peca = criarPeca(v.i);
            peca.img.setAttribute('href', v.pronto.url);
            return peca;
        });
        fila.remove();
        pecas.forEach((p, k) => setTimeout(() => { if (token === rodada) espalhar(p, vagas[k].lugar, token); }, reduzido ? 0 : k * 90));
        progresso(40, 'Espalhando as pranchas na mesa…');
        if (!(await pausa(1400 + pecas.length * 90, token))) return;

        etapa(2);
        for (let k = 0; k < pecas.length; k++) {
            progresso(40 + (k + 1) / pecas.length * 30, 'Lendo ' + rotulos[pecas[k].i] + ' (' + (k + 1) + ' de ' + pecas.length + ')');
            ler(pecas[k], token);
            if (!(await pausa(520, token))) return;
        }
        if (!(await pausa(1400, token))) return;

        etapa(3);
        progresso(80, 'Unindo ' + pecas.length + ' pranchas em uma imagem…');
        if (!(await unir(token))) return;

        etapa(5);
        progresso(100, 'Pronto: ' + pecas.length + ' pranchas unidas em uma imagem' + (falhas ? ' · ' + falhas + ' não abriram' : ''));
        els.repetir.hidden = false;
        els.baixar.hidden = false;
        await mostrarDados(token);
    }

    // ---------- imagem unida para baixar (PNG) ----------
    async function baixar() {
        const { cols, rows } = grade;
        const cw = 1200;
        const ch = Math.round(cw * razao);
        const canvas = document.createElement('canvas');
        canvas.width = cols * cw;
        canvas.height = rows * ch;
        const ctx = canvas.getContext('2d');
        ctx.fillStyle = '#fff';
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        const escala = cw / grade.w;
        const origem = { x: (W - cols * grade.w) / 2, y: (H - rows * grade.h) / 2 };
        pecas.forEach((p, k) => {
            const pronto = imagens.get(p.fatia.id);
            const v = vaga(k, 0);
            const x0 = (v.x - grade.w / 2 - origem.x) * escala;
            const y0 = (v.y - grade.h / 2 - origem.y) * escala;
            const caber = Math.min(cw / pronto.img.naturalWidth, ch / pronto.img.naturalHeight);
            const iw = pronto.img.naturalWidth * caber;
            const ih = pronto.img.naturalHeight * caber;
            ctx.drawImage(pronto.img, x0 + (cw - iw) / 2, y0 + (ch - ih) / 2, iw, ih);
            ctx.strokeStyle = '#c3cfdd';
            ctx.lineWidth = 2;
            ctx.strokeRect(x0 + 1, y0 + 1, cw - 2, ch - 2);
            ctx.font = '700 26px Manrope, sans-serif';
            const larg = ctx.measureText(rotulos[p.i]).width + 30;
            ctx.fillStyle = '#1f5d99';
            ctx.beginPath();
            ctx.roundRect(x0 + 14, y0 + 14, larg, 40, 20);
            ctx.fill();
            ctx.fillStyle = '#fff';
            ctx.fillText(rotulos[p.i], x0 + 29, y0 + 43);
        });
        const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/png'));
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = 'plantas-unidas-' + (cfg.obra.nome || 'obra').normalize('NFD').replace(/[^\w]+/g, '-').replace(/^-|-$/g, '').toLowerCase() + '.png';
        document.body.appendChild(link);
        link.click();
        link.remove();
        setTimeout(() => URL.revokeObjectURL(link.href), 5000);
    }

    els.repetir.addEventListener('click', executar);
    els.baixar.addEventListener('click', baixar);
    executar();
})();
