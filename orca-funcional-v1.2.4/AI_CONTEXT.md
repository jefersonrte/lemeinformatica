# Contexto para IA — Orçamentista Leme

## Missão

Manter e evoluir o Orçamentista sem perder compatibilidade, isolamento de clientes, histórico de versões ou segurança de produção.

## Estado de referência

- versão da aplicação: ler `VERSION`;
- stack: PHP 8.1+, MySQL/InnoDB, HTML/CSS/JavaScript, Composer;
- arquitetura: páginas PHP renderizadas no servidor, domínio em `src/Domain`, infraestrutura em `src/Infrastructure`;
- banco publicado: tabelas lógicas transformadas pelo prefixo configurado, atualmente `orca12_`;
- perfis: `admin` e `cliente`;
- versão funcional publicada no momento desta documentação: `/orca-funcional-v1.2.4/`.

## Leia nesta ordem

1. `docs/INDEX.md`
2. `docs/ARCHITECTURE.md`
3. `docs/DATA_MODEL.md`
4. `docs/PROCESS_FLOWS.md`
5. `docs/EXTENSION_GUIDE.md`
6. `docs/DEPLOY.md`
7. `CHANGELOG.md`
8. código diretamente relacionado à tarefa

## Fontes de verdade

- configuração e precedência: `src/Support/Config.php`;
- inicialização: `bootstrap/app.php`;
- autenticação/autorização: `includes/auth.php` e `src/Security/SessionManager.php`;
- schema inicial: `database/schema.sql`;
- evolução do banco: `database/migrations/`;
- nomes de tabela prefixáveis: `src/Infrastructure/TablePrefixer.php`;
- cálculos/regras extraídas: `src/Domain/`;
- fluxo real das telas: `admin/`, `cliente/`, `plantas.php` e `planta-arquivo.php`;
- versão: `VERSION`; histórico: `CHANGELOG.md` e tags Git.

## Restrições obrigatórias

- Não solicitar, ler, copiar, imprimir ou versionar senhas, chaves, cookies, dumps ou dados pessoais de produção.
- Não incluir `config/runtime.php`, `.env*`, `vendor/`, uploads, backups, logs ou arquivos gerados.
- Não confiar em ID da URL para autorização de cliente; filtrar sempre por `clientes.usuario_id`.
- Validar CSRF em toda mutação feita por sessão.
- Usar prepared statements para valores e transação para gravações relacionadas.
- Não escrever prefixo como `orca12_` diretamente no SQL da aplicação; usar nomes lógicos e registrar tabelas novas no `TablePrefixer`.
- Alterar banco por migração numerada, preservando instalações existentes.
- Nunca mover tag existente ou apagar uma versão histórica do domínio.
- Não publicar nem executar migração destrutiva sem backup e autorização explícita.

## Protocolo de alteração

1. identificar fluxo, entidades e perfis afetados;
2. inspecionar alterações locais antes de editar;
3. implementar a menor mudança compatível;
4. colocar regra reutilizável em `src/Domain`;
5. atualizar testes e documentação;
6. executar `composer test`, `composer lint` e `git diff --check`;
7. descrever impacto de dados, segurança, implantação e rollback;
8. somente versionar/publicar quando solicitado e quando todas as validações estiverem aprovadas.

## Resultado esperado de outra IA

Ao concluir uma tarefa, informe:

- o que mudou e por quê;
- arquivos alterados;
- fluxo de dados afetado;
- testes executados e resultado;
- migração/configuração necessária;
- riscos conhecidos e rollback;
- versão/commit/tag, se a publicação tiver sido autorizada.

Para criar o pacote portátil, execute `powershell -ExecutionPolicy Bypass -File scripts/export-ai-context.ps1`. Veja `docs/AI_HANDOFF.md`.
