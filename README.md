# Leme Informatica

Sistema central de autenticacao, API e operacao da Leme Informatica.

## Modulos

- central publica de projetos dos dois dominios;
- API de animais e alimentos;
- usuarios e perfis compartilhados entre os dominios;
- dashboard operacional;
- dados publicos de Santa Catarina em `gov/`;
- [Leme Pet](pet/README.md): gestao veterinaria, prontuario e internacao.

## Producao

- Sistema principal: `https://lemeinformatica.com.br/`
- Modulo Pet: `https://lemeinformatica.com.br/pet/`
- Dados Publicos SC: `https://lemeinformatica.com.br/gov/`

## Central de projetos

A pagina inicial apresenta os projetos ativos da Leme Informatica e da Leme
Solucoes em TI. A manutencao do catalogo esta documentada em
[`docs/PROJECT_HUB.md`](docs/PROJECT_HUB.md).

A versao atual do catalogo fica em `PROJECT_HUB_VERSION`.

## Seguranca

Os arquivos `includes/config.php`, `includes/database.runtime.php` e qualquer
`.env` sao exclusivos do servidor e nao devem ser enviados ao GitHub.

## Versoes do Leme Pet

A versao atual fica em `pet/VERSION`. Alteracoes, migracoes e roteiros de teste
ficam em `pet/CHANGELOG.md`, `pet/sql/migrations` e `pet/docs/versions`.
