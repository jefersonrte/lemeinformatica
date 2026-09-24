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

    // ---- Cadastro "um por um" pelos formulários do sistema: cliente → obra → orçamentos → plantas ----
    var base = painel.dataset.base;

    function formulario(caminho, campos) {
        var dados = new FormData();
        dados.append('csrf_token', csrf);
        Object.keys(campos).forEach(function (chave) {
            var valor = campos[chave];
            if (Array.isArray(valor)) {
                valor.forEach(function (v) { dados.append(chave + '[]', v === null || v === undefined ? '' : String(v)); });
            } else {
                dados.append(chave, valor === null || valor === undefined ? '' : String(valor));
            }
        });
        return fetch(base + caminho, { method: 'POST', body: dados, credentials: 'same-origin' }).then(function (resposta) {
            if (!resposta.ok) {
                throw new Error(caminho + ' respondeu HTTP ' + resposta.status);
            }
            return resposta;
        });
    }

    function consulta(email, obra) {
        return fetch(url + '?json=consulta&email=' + encodeURIComponent(email) + '&obra=' + encodeURIComponent(obra || ''), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (json) {
                if (!json.ok) {
                    throw new Error(json.erro || 'Falha na consulta.');
                }
                return json;
            });
    }

    function brl(valor) {
        return 'R$ ' + Number(valor).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function cadastrarObra(obra, indice) {
        var cliente = obra.cliente;
        var dadosObra = obra.obra;
        var resumo = { cliente: 'existente', obra: 'existente', orcamentos: [], plantas: 0 };
        return consulta(cliente.chave, dadosObra.nome).then(function (atual) {
            if (atual.cliente_id) {
                return atual;
            }
            resumo.cliente = 'cadastrado';
            return formulario('/admin/clientes.php', {
                acao: 'criar', nome: cliente.nome, razao_social: cliente.nome, email_cliente: cliente.chave, senha: '',
                cidade: cliente.cidade || '', estado: cliente.estado || 'SC', obs: cliente.obs || ''
            }).then(function () { return consulta(cliente.chave, dadosObra.nome); });
        }).then(function (atual) {
            if (!atual.cliente_id) {
                throw new Error('o cliente ' + cliente.nome + ' não foi gravado');
            }
            if (atual.obra_id) {
                return atual;
            }
            resumo.obra = 'cadastrada';
            return formulario('/admin/obras.php', {
                acao: 'criar', cliente_id: atual.cliente_id, nome: dadosObra.nome, descricao: dadosObra.descricao || '',
                endereco: dadosObra.endereco || '', cidade: dadosObra.cidade || '', estado: dadosObra.estado || 'SC',
                status: dadosObra.status || 'concluida', data_inicio: '', data_prev_fim: '', valor_total: dadosObra.valor_total || 0,
                progresso: dadosObra.progresso || 100, area_construida: dadosObra.area_construida || '', tipologia: dadosObra.tipologia || '', padrao: dadosObra.padrao || ''
            }).then(function () { return consulta(cliente.chave, dadosObra.nome); });
        }).then(function (atual) {
            if (!atual.obra_id) {
                throw new Error('a obra ' + dadosObra.nome + ' não foi gravada');
            }
            // Orçamentos que já existem com o mesmo título não são repetidos
            var existentes = {};
            atual.orcamentos.forEach(function (o) { existentes[o.titulo] = (existentes[o.titulo] || 0) + 1; });
            var fila = Promise.resolve();
            obra.orcamentos.forEach(function (orcamento) {
                fila = fila.then(function () {
                    if (existentes[orcamento.titulo] > 0) {
                        existentes[orcamento.titulo]--;
                        return;
                    }
                    return formulario('/admin/orcamento_novo.php', {
                        acao: 'salvar', obra_id: atual.obra_id, titulo: orcamento.titulo, tipo_origem: orcamento.tipo_origem || 'excel',
                        obs: orcamento.obs || '', bdi_percentual: orcamento.bdi || 0,
                        // mesmo formato que o editor de itens envia ao salvar
                        itens_json: JSON.stringify(orcamento.itens.map(function (i) {
                            return { etapa: i.etapa || '', descricao: i.descricao, unidade: i.unidade || 'UN', quantidade: i.quantidade, preco_unitario: i.preco_unitario };
                        }))
                    }).then(function (resposta) {
                        var id = (resposta.url.match(/[?&]id=(\d+)/) || [])[1];
                        if (!id) {
                            throw new Error('o orçamento "' + orcamento.titulo + '" não foi gravado');
                        }
                        var passo = orcamento.status && orcamento.status !== 'rascunho'
                            ? formulario('/admin/orcamento_detalhe.php?id=' + id, { acao: 'status', status: orcamento.status })
                            : Promise.resolve();
                        return passo.then(function () { return consulta(cliente.chave, dadosObra.nome); }).then(function (conferencia) {
                            var gravado = conferencia.orcamentos.filter(function (o) { return String(o.id) === id; })[0];
                            var confere = gravado && Math.abs(Number(gravado.total_estimado) - Number(orcamento.total)) <= 0.05 && Number(gravado.itens) === orcamento.itens.length;
                            resumo.orcamentos.push({ id: id, titulo: orcamento.titulo, total: gravado ? Number(gravado.total_estimado) : 0, esperado: orcamento.total, confere: confere, status: gravado ? gravado.status : '?' });
                        });
                    });
                });
            });
            return fila;
        }).then(function () {
            // Plantas: o importador reconhece a obra e os orçamentos já cadastrados e só anexa os arquivos
            var dados = new FormData();
            dados.append('acao', 'processar');
            dados.append('indice', String(indice));
            return post(dados).then(function (json) {
                resumo.plantas = json.plantas;
                resumo.obraId = json.obra_id;
                resumo.avisos = json.avisos;
                resumo.duplicados = json.orcamentos; // deveria ser 0: tudo já veio pelas telas
                return resumo;
            });
        });
    }

    function registrarObra(obra, resumo) {
        var item = document.createElement('li');
        var falhas = resumo.orcamentos.filter(function (o) { return !o.confere; });
        var link = document.createElement('a');
        link.href = base + '/admin/obra_detalhe.php?id=' + resumo.obraId;
        link.textContent = obra.obra.nome;
        link.target = '_blank';
        item.appendChild(document.createTextNode(obra.cliente.nome + ' (' + resumo.cliente + ') → '));
        item.appendChild(link);
        item.appendChild(document.createTextNode(' (' + resumo.obra + ') → ' + (resumo.orcamentos.length
            ? resumo.orcamentos.map(function (o) { return (o.confere ? '✔ ' : '✘ ') + o.titulo + ' ' + brl(o.total) + (o.confere ? '' : ' (esperado ' + brl(o.esperado) + ')') + ' [' + o.status + ']'; }).join('; ')
            : 'orçamentos já cadastrados') + ' → ' + resumo.plantas + ' planta(s)' + (resumo.avisos && resumo.avisos.length ? ' · avisos: ' + resumo.avisos.join('; ') : '')));
        if (falhas.length || resumo.duplicados) {
            item.className = 'text-danger';
        }
        log.appendChild(item);
        log.scrollTop = log.scrollHeight;
        return falhas.length;
    }

    function cadastrarPelasTelas() {
        etapa.textContent = 'Lendo o pacote…';
        return fetch(url + '?json=manifesto', { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (manifesto) {
            if (!manifesto.ok) {
                throw new Error(manifesto.erro || 'Pacote indisponível: envie o pacote primeiro.');
            }
            var obras = manifesto.obras;
            var indice = 0;
            var divergencias = 0;
            function proxima() {
                if (indice >= obras.length) {
                    etapa.textContent = 'Concluído: ' + obras.length + ' obras cadastradas pelas telas' + (divergencias ? ', ' + divergencias + ' orçamento(s) com total divergente (em vermelho).' : ', todos os totais conferem.');
                    barra.value = 100;
                    return Promise.resolve();
                }
                var obra = obras[indice];
                etapa.textContent = 'Obra ' + (indice + 1) + ' de ' + obras.length + ': ' + obra.obra.nome;
                return cadastrarObra(obra, indice).then(function (resumo) {
                    divergencias += registrarObra(obra, resumo);
                }, function (erro) {
                    registrar('Obra ' + (indice + 1) + ' (' + obra.obra.nome + '): ' + erro.message, true);
                }).then(function () {
                    indice++;
                    barra.value = Math.round(indice / obras.length * 100);
                    return proxima();
                });
            }
            return proxima();
        });
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
        var pelasTelas = document.getElementById('modoTelas') && document.getElementById('modoTelas').checked;
        executar(function () {
            return enviar(arquivo).then(pelasTelas ? cadastrarPelasTelas : importar);
        });
    });
    var botaoTelas = document.getElementById('cadastrarPelasTelas');
    if (botaoTelas) {
        botaoTelas.addEventListener('click', function () {
            executar(cadastrarPelasTelas);
        });
    }
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
