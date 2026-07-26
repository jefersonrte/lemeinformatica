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
  |
  +-- pet/api/*.php
        +-- includes/bootstrap.php
        +-- includes/permissions.php
        +-- includes/validation.php
        +-- includes/uploads.php
        +-- banco MySQL
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
