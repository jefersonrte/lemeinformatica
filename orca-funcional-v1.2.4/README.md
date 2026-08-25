# Orcamentista — Leme Informática

Aplicação web em PHP 8.1+ e MySQL para clientes, obras, orçamentos, cotações, compras, acompanhamento financeiro e plantas versionadas.

## Versões preservadas

- `v1.0.0-claude`: linha de base original criada pela Claude.
- `v1.1.0`: arquitetura modular, visual moderno, plantas e painel financeiro.
- `v1.2.0`: navegação horizontal completa e catálogo técnico de plantas e documentos.
- `v1.2.1`: recupera o login com segurança quando a sessão ou o token CSRF expira.
- `v1.2.2`: aceita plantas SVG com sanitização contra scripts e referências externas.
- `v1.2.3`: recupera uploads das versões anteriores e impede carregamento infinito no leitor de plantas.
- `v1.2.4`: corrige o cadastro de obras e adiciona análise inteligente de categoria/duplicidade na planilha CAIXA.

Na instalação versionada, o sistema pode compartilhar a conexão autorizada do domínio sem compartilhar tabelas, usando `database.prefix`. Administradores do domínio podem entrar com o mesmo e-mail e senha; outros perfis não recebem elevação automática de privilégio.

Consulte [CHANGELOG.md](CHANGELOG.md) para o histórico completo.

## Instalação local

1. Execute `composer install`.
2. Importe `database/schema.sql` em um banco vazio.
3. Copie `config/runtime.example.php` para `config/runtime.php` e preencha somente na máquina/servidor.
4. Crie a senha administrativa com `password_hash()` e atualize o usuário inicial.
5. Aponte o servidor web para a pasta do projeto e abra `/login.php`.

## Validação

```bash
php tests/lint.php
php tests/run.php
```

## Documentação

- [Índice da documentação técnica](docs/INDEX.md)
- [Arquitetura e segurança](docs/ARCHITECTURE.md)
- [Modelo de dados](docs/DATA_MODEL.md)
- [Fluxos de processos](docs/PROCESS_FLOWS.md)
- [Pontos de melhoria e extensão](docs/EXTENSION_GUIDE.md)
- [Deploy, versões e rollback](docs/DEPLOY.md)
- [Transferência segura para outra IA](docs/AI_HANDOFF.md)

O arquivo [AI_CONTEXT.md](AI_CONTEXT.md) é o ponto de entrada para outra IA. Para gerar um ZIP somente com arquivos rastreados e sem runtime, uploads ou backups:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/export-ai-context.ps1
```

Segredos, `config/runtime.php`, uploads e backups nunca devem ser adicionados ao Git.
