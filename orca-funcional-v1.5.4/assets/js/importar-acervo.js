/* Importar acervo: envia o pacote ZIP em partes e importa obra por obra (cada requisição curta). */
(function () {
    'use strict';
    var painel = document.getElementById('importarAcervo');
    if (!painel) {
        return;
    }
    var PARTE = 4 * 1024 * 1024;
    var url = painel.dataset.url;
    var csrf = painel.dataset.csrf;
    var progresso = document.getElementById('progressoAcervo');
    var etapa = document.getElementById('etapaAcervo');
    var barra = document.getElementById('barraAcervo');
    var log = document.getElementById('logAcervo');
    var ocupado = false;

    function post(dados) {
        dados.append('csrf_token', csrf);
        return fetch(url, { method: 'POST', body: dados, credentials: 'same-origin', headers: { 'X-CSRF-Token': csrf } })
            .then(function (resposta) {
                return resposta.json().catch(function () {
                    throw new Error('Resposta inesperada do servidor (HTTP ' + resposta.status + ').');
                });
            })
            .then(function (json) {
                if (!json.ok) {
                    throw new Error(json.erro || 'Falha no servidor.');
                }
                return json;
            });
    }

    function registrar(texto, erro) {
        var item = document.createElement('li');
        item.textContent = texto;
        if (erro) {
            item.className = 'text-danger';
        }
        log.appendChild(item);
        log.scrollTop = log.scrollHeight;
    }

    function enviar(arquivo) {
        var total = Math.max(1, Math.ceil(arquivo.size / PARTE));
        var indice = 0;
        function proxima() {
            var dados = new FormData();
            dados.append('acao', 'parte');
            dados.append('indice', String(indice));
            dados.append('total', String(total));
            dados.append('parte', arquivo.slice(indice * PARTE, (indice + 1) * PARTE), 'parte.bin');
            return post(dados).then(function (json) {
                indice++;
                barra.value = Math.round(indice / total * 100);
                etapa.textContent = 'Enviando pacote: ' + indice + ' de ' + total + ' partes';
                return json.concluido ? json.obras : proxima();
            });
        }
        return proxima();
    }

    function importar(obras) {
        var indice = 0;
        var somas = { orcamentos: 0, plantas: 0 };
        function proxima() {
            if (indice >= obras) {
                etapa.textContent = 'Concluído: ' + obras + ' obras, ' + somas.orcamentos + ' orçamentos e ' + somas.plantas + ' plantas novas.';
                barra.value = 100;
                return Promise.resolve();
            }
            etapa.textContent = 'Importando obra ' + (indice + 1) + ' de ' + obras + '…';
            var dados = new FormData();
            dados.append('acao', 'processar');
            dados.append('indice', String(indice));
            return post(dados).then(function (json) {
                somas.orcamentos += json.orcamentos;
                somas.plantas += json.plantas;
                registrar(json.obra + ' — ' + json.orcamentos + ' orçamento(s), ' + json.plantas + ' planta(s)' + (json.avisos.length ? ' · avisos: ' + json.avisos.join('; ') : ''), json.avisos.length > 0);
            }, function (erro) {
                registrar('Obra ' + (indice + 1) + ': ' + erro.message, true);
            }).then(function () {
                indice++;
                barra.value = Math.round(indice / obras * 100);
                return proxima();
            });
        }
        return proxima();
    }

    function executar(passos) {
        if (ocupado) {
            return;
        }
        ocupado = true;
        progresso.hidden = false;
        log.innerHTML = '';
        barra.value = 0;
        window.onbeforeunload = function () { return 'A importação ainda está em andamento.'; };
        passos().catch(function (erro) {
            etapa.textContent = 'Interrompido: ' + erro.message;
            registrar(erro.message, true);
        }).then(function () {
            ocupado = false;
            window.onbeforeunload = null;
        });
    }

    document.getElementById('enviarPacote').addEventListener('click', function () {
        var arquivo = document.getElementById('arquivoPacote').files[0];
        if (!arquivo) {
            window.alert('Selecione o pacote .zip do acervo.');
            return;
        }
        executar(function () {
            return enviar(arquivo).then(importar);
        });
    });
    var reprocessar = document.getElementById('processarPacote');
    if (reprocessar) {
        reprocessar.addEventListener('click', function () {
            executar(function () {
                return importar(parseInt(painel.dataset.obras, 10) || 0);
            });
        });
    }
    // Permite enviar um pacote já carregado na página (ex.: arrastado ou obtido por script)
    window.orcaImportarAcervo = function (arquivo) {
        executar(function () {
            return enviar(arquivo).then(importar);
        });
    };
})();
