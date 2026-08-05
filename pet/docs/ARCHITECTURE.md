# Arquitetura do Leme Pet

## Contexto

O modulo vive em `lemeinformatica.com.br/pet` e reutiliza:

- a sessao `LEME_API_SESSAO`;
- a tabela `usuarios_admin`;
- a conexao MySQL central;
- o token CSRF da aplicacao existente.

Nao existe uma segunda senha ou base de usuarios.

## Camadas

```text
Navegador
  |
  +-- pet/index.php
  |     +-- frontend/css/app.css
  |     +-- frontend/js/app.js
  |     +-- frontend/{css,js}/modules/commerce.*
  |
  +-- pet/api/*.php
        +-- includes/bootstrap.php
        +-- includes/permissions.php
        +-- includes/validation.php
        +-- includes/uploads.php
        +-- modules/estetica/functions.php
        +-- modules/comercial/functions.php
        +-- banco MySQL

Leme Solucoes em TI
  +-- pet/api/dashboard.php
        +-- api-client.php
        +-- HTTPS + X-API-KEY
        +-- lemeinformatica.com.br/pet/api/relatorios.php
```

## Modelo de permissao

O perfil central controla o acesso administrativo. O vinculo em
`pet_veterinarios` concede a capacidade de registrar prontuario e evolucao.

- `admin`: acesso completo;
- `operador`: cadastros, agenda e internacao;
- usuario vinculado como veterinario: leitura e escrita clinica;
- `visualizador`: apenas dashboard agregado.

Toda verificacao acontece novamente na API.

## Modelo de dados

- `pet_tutores`: pessoas responsaveis pelos animais;
- `pet_animais`: pacientes, sempre vinculados a um tutor;
- `pet_veterinarios`: perfil profissional ligado a `usuarios_admin`;
- `pet_atendimentos`: consultas e demais eventos clinicos;
- `pet_internacoes`: episodio de hospitalizacao;
- `pet_internacao_evolucoes`: medicoes e notas durante a internacao;
- `pet_audit_log`: trilha de operacoes;
- `pet_schema_migrations`: versao instalada.
- `pet_servicos`: catalogo da estetica;
- `pet_banho_tosa_agendamentos` e `pet_banho_tosa_itens`: agenda e servicos;
- `pet_produtos`: catalogo e saldo atual;
- `pet_estoque_movimentos`: razao imutavel do estoque;
- `pet_vendas` e `pet_venda_itens`: cabecalho e itens comerciais.

## Fronteira entre dominios

O banco principal continua somente em `lemeinformatica.com.br`. O dominio
`lemesolucoesemti.com.br` chama uma API agregada pelo backend e entrega o JSON
ao navegador autenticado. A API key nunca e enviada ao navegador. Os relatorios
nao incluem informacoes pessoais nem registros clinicos.

## Modularidade

Cada nova area deve ter sua regra de negocio em `pet/modules/{area}`, endpoints
em `pet/api` e recursos visuais em `pet/frontend/*/modules`. As tabelas entram
por uma nova migracao numerada, sem alterar migracoes ja publicadas.

## Fotografias

As imagens ficam em `pet/uploads/{categoria}`. O caminho salvo no banco e
relativo ao modulo. O servidor bloqueia arquivos executaveis nesse diretorio.

## Evolucao de versao

Cada versao deve atualizar:

1. `VERSION`;
2. `CHANGELOG.md`;
3. uma migracao nova em `sql/migrations`;
4. uma nota em `docs/versions`;
5. os testes funcionais;
6. o historico geral do projeto.
