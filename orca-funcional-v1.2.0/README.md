# Orcamentista — Leme Informática

Aplicação web em PHP 8.1+ e MySQL para clientes, obras, orçamentos, cotações, compras, acompanhamento financeiro e plantas versionadas.

## Versões preservadas

- `v1.0.0-claude`: linha de base original criada pela Claude.
- `v1.1.0`: arquitetura modular, visual moderno, plantas e painel financeiro.
- `v1.2.0`: navegação horizontal completa e catálogo técnico de plantas e documentos.

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

- [Arquitetura](docs/ARCHITECTURE.md)
- [Deploy, versões e rollback](docs/DEPLOY.md)

Segredos, `config/runtime.php`, uploads e backups nunca devem ser adicionados ao Git.
