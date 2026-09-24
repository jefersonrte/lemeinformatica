# Histórico de versões

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
