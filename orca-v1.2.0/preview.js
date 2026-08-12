document.querySelectorAll('[data-preview-link]').forEach(function (link) {
  link.addEventListener('click', function (event) {
    if (link.getAttribute('href') === '#') {
      event.preventDefault();
      alert('Este módulo faz parte do sistema funcional. Nesta demonstração visual, use Dashboard ou Plantas.');
    }
  });
});

const filterButton = document.querySelector('[data-filter]');
if (filterButton) {
  filterButton.addEventListener('click', function () {
    const type = document.getElementById('tipo').value;
    const search = document.getElementById('busca').value.trim().toLocaleLowerCase('pt-BR');
    let visible = 0;
    document.querySelectorAll('.doc').forEach(function (card) {
      const matchesType = !type || card.dataset.type === type;
      const matchesText = !search || card.dataset.search.includes(search);
      const show = matchesType && matchesText;
      card.style.display = show ? '' : 'none';
      if (show) visible++;
    });
    document.getElementById('resultado').textContent = visible + ' resultado(s)';
    document.querySelector('.empty').style.display = visible ? 'none' : 'block';
  });
}

const plantCards = Array.from(document.querySelectorAll('.doc[data-type="image"]'));
if (plantCards.length) {
  const viewer = document.createElement('div');
  viewer.className = 'plant-viewer';
  viewer.hidden = true;
  viewer.innerHTML = `
    <div class="plant-viewer-backdrop" data-viewer-close></div>
    <section class="plant-viewer-panel" role="dialog" aria-modal="true" aria-labelledby="plantViewerTitle">
      <header class="plant-viewer-head">
        <div>
          <span class="plant-viewer-kicker">Visualizador técnico · v1.2.0</span>
          <h2 id="plantViewerTitle">Planta</h2>
          <p id="plantViewerMeta"></p>
        </div>
        <button class="viewer-icon" type="button" data-viewer-close aria-label="Fechar visualizador">×</button>
      </header>
      <div class="plant-viewer-stage">
        <div class="plant-grid-lines"></div>
        <div class="plant-viewer-canvas" id="plantViewerCanvas"></div>
        <div class="plant-scan" aria-hidden="true"></div>
        <span class="plant-coordinate top">REV · 1.2.0</span>
        <span class="plant-coordinate bottom">LEME · PROJETO DIGITAL</span>
      </div>
      <footer class="plant-viewer-controls">
        <div class="viewer-group">
          <button type="button" class="viewer-control" data-viewer-prev>← Anterior</button>
          <span id="plantViewerCounter">1 / 1</span>
          <button type="button" class="viewer-control" data-viewer-next>Próxima →</button>
        </div>
        <div class="viewer-group">
          <button type="button" class="viewer-icon" data-viewer-out aria-label="Reduzir zoom">−</button>
          <strong id="plantViewerZoom">100%</strong>
          <button type="button" class="viewer-icon" data-viewer-in aria-label="Ampliar zoom">＋</button>
        </div>
      </footer>
    </section>`;
  document.body.appendChild(viewer);

  const canvas = viewer.querySelector('#plantViewerCanvas');
  const title = viewer.querySelector('#plantViewerTitle');
  const meta = viewer.querySelector('#plantViewerMeta');
  const counter = viewer.querySelector('#plantViewerCounter');
  const zoomLabel = viewer.querySelector('#plantViewerZoom');
  let activePlant = 0;
  let plantZoom = 1;
  let lastTrigger = null;

  function renderPlant(index, animate = true) {
    activePlant = (index + plantCards.length) % plantCards.length;
    const card = plantCards[activePlant];
    const source = card.querySelector('.preview svg');
    const name = card.querySelector('.doc-body strong');
    const details = card.querySelectorAll('.doc-body small');
    title.textContent = name ? name.textContent : 'Planta técnica';
    meta.textContent = Array.from(details).map((item) => item.textContent).join(' · ');
    counter.textContent = `${activePlant + 1} / ${plantCards.length}`;
    canvas.replaceChildren(source.cloneNode(true));
    plantZoom = 1;
    applyZoom();
    if (animate) {
      viewer.classList.remove('is-scanning');
      void viewer.offsetWidth;
      viewer.classList.add('is-scanning');
    }
  }

  function applyZoom() {
    const drawing = canvas.querySelector('svg');
    if (drawing) drawing.style.transform = `scale(${plantZoom})`;
    zoomLabel.textContent = `${Math.round(plantZoom * 100)}%`;
  }

  function openViewer(index, trigger) {
    lastTrigger = trigger;
    renderPlant(index);
    viewer.hidden = false;
    document.body.classList.add('viewer-open');
    requestAnimationFrame(() => viewer.classList.add('is-open'));
    viewer.querySelector('[data-viewer-close]').focus();
  }

  function closeViewer() {
    viewer.classList.remove('is-open', 'is-scanning');
    document.body.classList.remove('viewer-open');
    window.setTimeout(() => {
      viewer.hidden = true;
      if (lastTrigger) lastTrigger.focus();
    }, 220);
  }

  plantCards.forEach((card, index) => {
    card.style.setProperty('--plant-order', index);
    card.tabIndex = 0;
    card.setAttribute('role', 'button');
    card.setAttribute('aria-label', `Visualizar ${card.querySelector('.doc-body strong').textContent}`);
    card.addEventListener('click', () => openViewer(index, card));
    card.addEventListener('keydown', (event) => {
      if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        openViewer(index, card);
      }
    });
  });

  viewer.querySelectorAll('[data-viewer-close]').forEach((button) => button.addEventListener('click', closeViewer));
  viewer.querySelector('[data-viewer-prev]').addEventListener('click', () => renderPlant(activePlant - 1));
  viewer.querySelector('[data-viewer-next]').addEventListener('click', () => renderPlant(activePlant + 1));
  viewer.querySelector('[data-viewer-in]').addEventListener('click', () => {
    plantZoom = Math.min(2.4, plantZoom + .2);
    applyZoom();
  });
  viewer.querySelector('[data-viewer-out]').addEventListener('click', () => {
    plantZoom = Math.max(.6, plantZoom - .2);
    applyZoom();
  });
  viewer.querySelector('.plant-viewer-stage').addEventListener('wheel', (event) => {
    event.preventDefault();
    plantZoom = Math.min(2.4, Math.max(.6, plantZoom + (event.deltaY < 0 ? .1 : -.1)));
    applyZoom();
  }, { passive: false });
  document.addEventListener('keydown', (event) => {
    if (viewer.hidden) return;
    if (event.key === 'Escape') closeViewer();
    if (event.key === 'ArrowLeft') renderPlant(activePlant - 1);
    if (event.key === 'ArrowRight') renderPlant(activePlant + 1);
    if (event.key === '+' || event.key === '=') {
      plantZoom = Math.min(2.4, plantZoom + .2);
      applyZoom();
    }
    if (event.key === '-') {
      plantZoom = Math.max(.6, plantZoom - .2);
      applyZoom();
    }
  });
}
