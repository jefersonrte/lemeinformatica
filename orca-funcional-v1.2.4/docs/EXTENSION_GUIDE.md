# Guia de melhorias e extensão

## 1. Onde alterar cada função

| Objetivo | Arquivo principal | Arquivos relacionados |
|---|---|---|
| Cores, botões, responsividade e cards | `assets/css/style.css` | `helpers/layout.php`, `assets/js/app.js` |
| Menu, cabeçalho, scripts globais | `helpers/layout.php` | `assets/css/style.css` |
| Dashboard e gráficos | `admin/dashboard.php` | schema de compras/orçamentos, Chart.js |
| Visualização e filtros de plantas | `plantas.php` | `planta-arquivo.php`, `assets/css/style.css`, `assets/js/app.js` |
| Validação, versão e gravação de planta | `src/Domain/Obra/PlantaService.php` | `database/schema.sql`, migrações |
| Login/CSRF/perfis | `login.php`, `includes/auth.php` | `src/Security/SessionManager.php`, `src/Support/Config.php` |
| Integração com administrador central | `includes/auth.php` | tabela externa `usuarios_admin` |
| CRUD de cliente/obra/catálogo | página correspondente em `admin/` | schema e `helpers/layout.php` |
| Progresso da obra | `admin/obra_detalhe.php` | `obra_etapas`, `obras` |
| Cálculo e criação do orçamento | `src/Domain/Orcamento/` | `admin/orcamento_novo.php`, `admin/planilha_caixa.php` |
| Novo importador | `helpers/file_import.php` | telas de importação, Composer se necessário |
| Geração/envio de cotação | `src/Domain/Cotacao/MensagemCotacao.php` | `admin/cotacao_enviar.php`, `helpers/mailer.php` |
| Leitura/consolidação de resposta | `admin/leitura_cotacao.php` | `admin/cotacao_detalhe.php`, tabelas de cotação |
| Compra e custo realizado | `admin/compras.php` | `admin/dashboard.php`, nova migração se houver itens/pagamentos |
| Portal do cliente | `cliente/` | sempre repetir filtro por `clientes.usuario_id` |
| Banco ou tabela nova | `database/migrations/` | `database/schema.sql`, `TablePrefixer.php`, `smoke.php` |
| Configuração de ambiente | `src/Support/Config.php` | `config/runtime.example.php`, workflow de deploy |
| Testes | `tests/run.php`, `tests/lint.php`, `smoke.php` | workflow CI do repositório hospedeiro |
| Publicação | repositório hospedeiro `.github/workflows/deploy-hostinger.yml` | `docs/DEPLOY.md` |

## 2. Regra para uma melhoria segura

Uma melhoria de negócio deve seguir este caminho:

1. definir entrada, saída, perfil autorizado e estados afetados;
2. colocar cálculo/regra em `src/Domain`, não no HTML;
3. usar transação quando houver mais de uma gravação dependente;
4. criar migração aditiva se o modelo mudar;
5. preservar prefixo de tabelas e filtros de cliente;
6. adicionar teste de domínio e ampliar o smoke quando houver nova entidade;
7. atualizar a documentação e o changelog;
8. publicar em uma pasta de versão nova quando a mudança tiver risco funcional.

## 3. Prioridades recomendadas

### Prioridade alta — segurança e consistência

1. **Unificar validação de uploads de resposta de cotação.**
   `admin/cotacao_detalhe.php` ainda grava `arquivo_resp` usando a extensão recebida. Extraia um serviço equivalente ao `PlantaService`, valide MIME, tamanho, nome aleatório e sirva o arquivo por endpoint autorizado.

2. **Validar estados no servidor.**
   Centralize listas e transições de obra, etapa, orçamento, cotação e compra em classes de domínio. Hoje algumas páginas recebem o status do formulário e dependem principalmente do `ENUM` do banco.

3. **Transacionar consolidação de cotação.**
   A resposta altera cotação, itens e cabeçalho. Envolva essas operações em uma transação para evitar total parcial se uma instrução falhar.

4. **Evitar duplicidade na reimportação de cotação.**
   `admin/leitura_cotacao.php` acrescenta linhas a `cotacao_itens`. Defina se uma nova importação substitui a anterior ou cria uma versão e implemente a regra explicitamente.

5. **Separar exclusão de arquivamento.**
   Cliente, obra e fornecedor possuem cascatas amplas. Prefira coluna `arquivado_em`/`ativo`, confirmação reforçada e log antes de exclusão definitiva.

### Prioridade média — modelo financeiro

1. **Definir realizado, comprometido e pago.**
   Hoje toda compra não cancelada entra no realizado. Uma evolução deve separar:
   - cotado: proposta recebida;
   - comprometido: pedido aprovado/confirmado;
   - realizado: entrega ou medição;
   - pago: lançamento financeiro quitado.

2. **Adicionar itens de compra.**
   Crie `compra_itens` vinculada a `orcamento_itens`/`cotacao_itens`. Isso permite saldo por material, entrega parcial e comparação correta por categoria.

3. **Criar medições e pagamentos.**
   Novas tabelas sugeridas: `medicoes`, `medicao_itens`, `pagamentos`. Não reutilize `compras.valor_total` para conceitos diferentes.

4. **Materializar relatórios somente quando necessário.**
   Primeiro extraia as consultas do dashboard para um serviço. Cache ou tabela de indicadores só deve entrar depois de medir lentidão.

### Prioridade média — plantas

1. Agrupar versões pelo título e abrir um histórico comparável.
2. Adicionar zoom/pan para imagens e PDF.js para PDFs, mantendo a autorização em `planta-arquivo.php`.
3. Criar miniaturas assíncronas e armazenadas para arquivos grandes.
4. Adicionar estado `ativo/arquivado`, disciplina/tipo e revisão aprovada.
5. Implementar exclusão segura do arquivo físico apenas depois de atualizar banco e auditoria.
6. Para DWG/IFC, converter em serviço isolado; não transmitir arquivo de engenharia diretamente para uma biblioteca sem validação.

### Prioridade média — arquitetura e testes

1. Extrair CRUD das páginas para serviços/repositórios, começando por cotação e compra.
2. Introduzir PHPUnit para regras de domínio e autorização.
3. Criar testes de integração com banco descartável e prefixo real.
4. Adicionar teste end-to-end de login, orçamento, cotação, compra e planta.
5. Corrigir o indicador “Cotações Recebidas” em `cliente/dashboard.php`: a consulta usa `cotacoes.status='cotado'`, valor que não existe no enum; deve refletir `respondida` ou uma regra de negócio nova.
6. Padronizar respostas de erro e registrar falhas com identificador de correlação sem dados sensíveis.

## 4. Extensões sugeridas por módulo

### Dashboard avançado

Crie `src/Domain/Financeiro/DashboardService.php` e mova para ele as consultas de `admin/dashboard.php`. Retorne um DTO/array com:

- totais por período, obra, categoria e fornecedor;
- comprometido, realizado e pago separados;
- variação absoluta e percentual;
- tendência mensal;
- alertas de estouro e atraso.

Depois mantenha `admin/dashboard.php` responsável apenas por filtros, serialização segura e renderização. Índices prováveis devem ser confirmados com `EXPLAIN`, não adicionados por suposição.

### Comparador de cotações

Crie um serviço que leia todas as `cotacao_itens` de um orçamento, normalize unidade/quantidade e produza uma matriz item × fornecedor. O “vencedor” deve considerar frete, impostos, prazo e validade; não escolha automaticamente apenas pelo menor preço sem registrar a regra.

### Aprovação do cliente

Adicione uma tabela de eventos de aprovação em vez de apenas sobrescrever `orcamentos.status`:

```text
orcamento_aprovacoes(id, orcamento_id, usuario_id, decisao, observacao, criado_em)
```

O cliente deve poder decidir somente sobre orçamento pertencente à sua empresa. Grave IP/data e não permita editar o evento concluído.

### API para integração

Se uma API for necessária, crie uma entrada separada (`api/v1/`) com autenticação própria, JSON padronizado, rate limit e autorização por recurso. Não transforme `health.php`, `migrate.php` ou `smoke.php` em API funcional.

## 5. Padrões obrigatórios

### Consulta de cliente

Nunca autorize apenas pelo ID recebido na URL. A consulta deve incluir a propriedade:

```php
SELECT o.*
FROM obras o
JOIN clientes c ON c.id = o.cliente_id
WHERE o.id = ? AND c.usuario_id = ?
```

### Mutações

Toda mutação de tela deve executar, nesta ordem:

```php
requireAdmin(); // ou requireLogin + regra de propriedade
verifyCsrf();
// validar e normalizar
// executar serviço/transação
logAction(...);
setFlash(...);
redirect(...);
```

### Arquivos

- conferir erro de upload e `is_uploaded_file`;
- limitar bytes antes de processar;
- detectar MIME com `finfo`, sem confiar na extensão;
- gerar nome aleatório;
- armazenar fora de acesso público direto ou bloquear o diretório;
- resolver com `realpath` e verificar a raiz antes de ler;
- autorizar novamente no endpoint de entrega;
- não enviar arquivo real nem metadados pessoais a uma IA.
- manter a chave da IA somente em `config/runtime.php`/Secret e nunca no repositório;
- limitar a análise a código, descrição, categorias e candidatos do catálogo, com saída estruturada validada e fallback local.

### SQL

- usar placeholders para valores;
- não concatenar filtro sem whitelist;
- nunca inserir o prefixo manualmente nas consultas da aplicação;
- registrar toda tabela lógica nova no `TablePrefixer`;
- aplicar alterações por migração numerada.

## 6. Checklist antes do merge

- [ ] perfil e propriedade verificados;
- [ ] CSRF presente em POST/PUT/DELETE equivalente;
- [ ] entrada validada e saída escapada;
- [ ] transação para gravações relacionadas;
- [ ] migração aditiva e compatível;
- [ ] tabela nova registrada no prefixador;
- [ ] nenhuma credencial ou dado de produção no diff;
- [ ] `composer test` e `composer lint` aprovados;
- [ ] smoke ampliado quando o fluxo de dados mudou;
- [ ] documentação e changelog atualizados;
- [ ] backup e rollback definidos para a publicação.
