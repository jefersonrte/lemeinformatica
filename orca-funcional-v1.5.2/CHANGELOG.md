# Histórico de versões

## 1.5.2 — 2026-09-24

### Base de referência (IA da prévia)
- base ampliada de 42 para **59 orçamentos reais** (9.430 itens): 17 obras novas, entre elas residências (230–755 m²), edifícios multifamiliares (388 m² a 28.723 m²), hemocentro, reforma de apartamento, guarita de condomínio, parques e praças;
- etapas e descrições também anonimizadas (nomes de ruas e praças viram "via pública"); instalações existentes recebem a correção na próxima migração, sem perder tipologia/área ajustadas na tela;
- orçamento sintético CAIXA (custo por serviço + ficha-resumo com área de construção) aceito como referência;
- agrupamento do acervo: pastas de entrega, revisão, parecer e "perspectivas" pertencem à mesma obra; cópias do mesmo orçamento com outro nome são ignoradas; memórias de cálculo, cotações, bancos SINAPI e listas de material ficam de fora;
- tipologia: "3 casas" é geminado, reforma de apartamento é reforma, manutenção de estrutura é reforma, hemocentro/hospital é institucional.

### Importador de planilhas
- coluna de descrição sem rótulo ao lado de "ITEM" (modelo "ITEM | | UNID. | QUANT. | MATERIAL | MÃO OBRA") — Parque Barueri (R$ 19,1 mi) e Parque Costa Azul (R$ 4,28 mi) agora conferem ao centavo com a planilha;
- novo leitor do **resumo de materiais da estrutura** (Eberick/TQS): concreto e fôrmas separados entre fundação e estrutura, aço por bitola (ø 5,0 como CA-60) e fck lido do arquivo.

### Prévia de obra
- várias pranchas no mesmo envio (até 12 PDFs): quadro de áreas, ambientes e esquadrias são unidos entre as folhas;
- tipologia detectada pelo texto da planta (pavimento tipo/apartamentos → multifamiliar; residência acima de 1.200 m² → multifamiliar), com aviso para confirmar;
- quadros de esquadrias em centímetros ("J05 180X215 30 Correr… 02" e "EC200 200 160 80 1 Alumínio…"), inclusive o mesmo código com tamanhos diferentes;
- "Área total construída da edificação" reconhecida no quadro geral de áreas;
- precificação dos quantitativos: preferência por obras da mesma família (edificação, urbano, infraestrutura), versões resumidas da descrição quando a completa não acha nada (antes da vírgula, nome popular de plantas), termo principal obrigatório ("balizador" não vira "poste") e um preço por obra de origem na mediana;
- administração, impostos e BDI em linha única são calculados como percentual típico dos custos diretos das obras de referência;
- faixa econômico–alto pelos percentis 20–80 interpolados.

### Validação com o acervo
- preços item a item ("deixa um de fora", 16 obras, 2.940 itens): erro mediano de 16% por item e de 16% no total, cobrindo 94% do valor;
- custo só pela área (30 edificações): erro mediano de 25%;
- plantas reais às cegas: Casa Silas 0,99×, Parko (9.368 m²) 0,97×, 3 casas 1,24×, Cella 1,31×.

### Acervo local
- `php scripts/acervo.php importar` cadastra clientes e obras **fictícios** (nomes, endereços e cidades inventados e estáveis) com os orçamentos reais, renomeia os já importados e confere cada total gravado contra a planilha de origem.

## 1.5.1 — 2026-09-24

- novo tema **Grafite escuro** como padrão: fundo grafite, cartões em camadas, botões claros com texto escuro e detalhe índigo (o Grafite "ao contrário");
- o tema claro passa a se chamar **Grafite claro**; quem já escolheu um tema mantém a escolha (cookie `orca_tema`).

## 1.5.0 — 2026-09-24

### Interface
- CSS reescrito em uma única camada baseada em tokens (antes eram três camadas sobrepostas 1.0/1.1/1.2);
- botão **Aparência** na barra superior e no login;
- **6 temas prontos**: Grafite (novo padrão, neutro moderno com detalhe índigo), Leme, Noturno (escuro), Canteiro, Esmeralda e Terracota;
- **tema Personalizado**: degradê montado com dois controles de matiz (cor principal e cor do degradê), fundo claro ou escuro e 6 degradês prontos (Oceano, Pôr do sol, Uva, Floresta, Ardósia, Coral);
- **5 disposições do menu**: Topo com submenus (padrão), Lateral com grupos recolhíveis, Trilho de ícones, Duas faixas (páginas do grupo em abas) e Dock flutuante na base da tela; no celular todas viram gaveta;
- menu do administrador organizado em grupos: Projetos, Orçamentos, Suprimentos e Sistema (acabou o menu cortado em telas médias);
- tela de login redesenhada em dois painéis;
- painéis sem cores fixas: indicadores, gráficos e alertas seguem o tema; ícones no lugar de emojis;
- preferências salvas nos cookies `orca_tema`, `orca_cor` e `orca_layout` (sem migração de banco).

## 1.4.1 — 2026-09-24

- base de referência ampliada para 42 orçamentos reais (Casa MR 230 m² e Residência JR 755 m², anonimizadas);
- leitura da área total quando o quadro traz "computável / não computável / total" em qualquer ordem;
- plantas sem quadro de áreas em texto: soma dos ambientes pela prancha de maior área (evita contar a mesma sala em várias pranchas) e aviso para conferir a área;
- `scripts/acervo.php` preserva os códigos já publicados e acrescenta novas referências ao final; aceita pastas por link simbólico;
- teste às cegas: Casa MR estimada só pela planta em R$ 1,01 mi contra R$ 1,07 mi reais (−5%).

## 1.4.0 — 2026-09-24

### Prévia de obra (planta/planilha → materiais e custo)
- nova tela **Prévia de obra**: envia a planta em PDF e/ou uma planilha de quantitativos e recebe custo aproximado, custo por etapa, materiais-chave com quantidades e esquadrias precificadas;
- `PlantaAnalyzer` lê do texto do PDF a área construída (quadro de áreas), terreno, pavimentos, ambientes e o quadro de esquadrias (formato tradicional e Revit);
- `EstimativaService`: custo por m² das obras comparáveis (mediana ponderada por porte e data, faixa econômico–alto), distribuição por etapa, consumo por m² de concreto, aço, fôrmas, alvenaria, revestimentos, pisos, forro, pintura, telhado etc.;
- listas de quantitativos sem preço recebem preço por semelhança com itens de orçamentos reais e o SINAPI (diâmetro/bitola considerados);
- gera o orçamento em rascunho na obra escolhida e salva a planta na obra;
- validação "deixa um de fora" com o acervo: erro típico de ~25% nas edificações, compatível com estimativa preliminar.

### Base de referência
- 40 orçamentos reais anonimizados (5.021 itens) e 11.352 preços SINAPI Florianópolis 05/2025 carregados pela migração (tabelas `referencias`, `referencia_itens`, `precos_base`, migração 006);
- valores atualizados pelo INCC (`Incc`); etapas e materiais padronizados pelo `Classificador`;
- tela **Base de referência** para corrigir tipologia/área, desativar obras e incluir orçamentos aprovados; botão "Usar como referência" no orçamento aprovado;
- obras ganham área construída, tipologia e padrão.

### Importador
- colunas de especificação (item, dimensão, diâmetro, bitola) completam a descrição; seções viram etapas;
- total da própria linha prevalece em itens percentuais; "Sub-total" com hífen reconhecido;
- área, data-base e título da obra lidos do topo da planilha;
- `scripts/acervo.php` (CLI) para inventariar, anonimizar e importar o acervo de orçamentos reais.

## 1.3.0 — 2026-09-24

### Importação de planilhas reais
- novo `PlanilhaOrcamentoParser`: localiza o cabeçalho em qualquer linha, junta sub-cabeçalhos ("Unitário/Total"), reconhece etapas numeradas, subgrupos, linhas de subtotal/total e observações após o total geral;
- soma preço de material + mão de obra, separa custo sem BDI e preço com BDI e calcula o percentual de BDI;
- lê o valor calculado das fórmulas, escolhe automaticamente a aba mais completa e permite escolher outra;
- compara o total importado com o total informado na planilha e avisa divergências;
- validado com 11 orçamentos reais (totais idênticos, diferença máxima de arredondamento de R$ 1,74 em R$ 13 milhões);
- CSV com separador automático e números pt-BR; `.xlsb` recusado com orientação.

### Orçamento
- itens com etapa e ordem, BDI por orçamento e vínculo de revisão (migração 005);
- aprovar, reprovar, cancelar e reabrir com transições validadas em `OrcamentoStatus`;
- edição de itens (preserva preços cotados), nova revisão e exclusão protegida;
- detalhe agrupado por etapa com subtotal e percentual; CSV com etapa, custo direto, BDI e total;
- dashboard considera o orçamento vigente de cada obra (aprovado ou a revisão mais recente).

### Correções de botões e fluxos
- salvar orçamento pela tela (itens estavam fora do formulário) e "+ Adicionar Linha" sem perder o que foi digitado;
- editar obra não multiplica mais o valor por 100; compras aceitam "1.234,56";
- abas das telas de detalhe, "+ Novo" após "Editar" e botões Editar com apóstrofo;
- canal "Ambos" na cotação (migração 004), falhas reais de envio informadas, resposta e leitura de cotação em transação e sem duplicar itens;
- arquivo de resposta servido por endpoint autenticado; busca de fornecedores; exclusão de fornecedor com histórico apenas desativa;
- planta publicada na obra escolhida no formulário; unidades importadas preservadas; decimais livres nos preços;
- mensagens amigáveis para e-mail/nome duplicado; usuário cliente recebe perfil; indicador de cotações no portal do cliente;
- avisos "Deprecated" removidos do CSV exportado (PHP 8.4+).

## 1.2.4 — 2026-08-25

- corrige a ordem dos parâmetros no cadastro e na edição de obras;
- grava a nova obra e suas sete etapas padrão dentro de uma transação;
- acrescenta smoke test real do cadastro de obra e da criação automática das etapas;
- analisa itens importados da planilha por código, nome aproximado e termos de categoria;
- sinaliza produtos já cadastrados ou semelhantes e pré-seleciona a categoria sugerida;
- integra opcionalmente a Responses API com saída estruturada, chave exclusiva em Secret e fallback local.

## 1.2.3 — 2026-08-25

- migra automaticamente os uploads físicos das versões anteriores para a nova pasta versionada;
- garante que as quatro plantas SVG demonstrativas existam mesmo quando os registros já estavam no banco;
- valida no smoke test que todos os documentos cadastrados possuem arquivo físico disponível;
- mostra uma mensagem com opção de nova tentativa se uma imagem falhar, em vez de manter o carregamento infinito;
- adiciona uma chave de cache baseada na versão e no tamanho do arquivo.

## 1.2.2 — 2026-08-24

- habilita upload e visualização de plantas SVG no catálogo técnico;
- valida a estrutura XML e rejeita scripts, eventos, elementos ativos e referências externas;
- normaliza o SVG antes do armazenamento e reforça os cabeçalhos da entrega privada;
- adiciona testes de segurança para SVG seguro e arquivos maliciosos.

## 1.2.1 — documentação técnica (2026-08-20)

- documenta arquitetura, segurança, entidades, estados e fórmulas financeiras;
- registra os fluxos completos de login, obra, orçamento, cotação, compra, plantas e dashboard;
- adiciona mapa de pontos de extensão e prioridades de evolução;
- detalha publicação, backup, versionamento duplo e rollback;
- adiciona contexto e exportador seguro para transferência do projeto a outra IA.

## 1.2.1 — hotfix administrativo

- adiciona provisionamento protegido de administradores exclusivos do Orçamentista;
- exige chave de migração, método POST, e-mail válido e senha forte.

Todas as versões publicadas recebem uma tag Git e uma cópia de código no domínio antes da promoção para `/orca`.

## 1.2.1 — 2026-08-12

- recupera com segurança o formulário de login quando a sessão ou o token CSRF expira;
- preserva a validação CSRF obrigatória nos demais formulários;
- mantém compatibilidade com o login administrativo compartilhado do domínio.

## 1.2.0 — 2026-08-12

- navegação horizontal com acesso visível a todos os módulos do sistema;
- menu móvel completo e acessível;
- central consolidada de plantas e documentos, com filtros por obra, tipo e texto;
- indicadores de imagens, PDFs e volume armazenado;
- galeria responsiva com miniaturas protegidas e atalhos para versão e histórico.
- leitor imersivo animado com transição entre pranchas, zoom e navegação por teclado;
- integração segura com o login administrativo do domínio;
- instalação isolada por prefixo de tabelas e teste transacional de produção.

## 1.1.0 — 2026-08-11

- arquitetura modular com bootstrap único, configuração privada de runtime e serviços de domínio;
- novo visual responsivo, botões, navegação e tela de login modernizados;
- dashboard executivo com orçado, cotado, realizado, desvio e gráficos por projeto;
- central de plantas com visualização protegida de PDF/imagem e histórico por obra;
- exportação CSV de orçamento, health check e migrações autenticadas;
- testes automatizados, CI, deploy FTPS versionado e rotina de rollback.

## 1.0.0-claude — 2026-06-24

- versão inicial criada pela Claude;
- preservada integralmente na tag `v1.0.0-claude`.
