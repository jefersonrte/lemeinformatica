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
