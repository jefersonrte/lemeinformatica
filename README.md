# Leme Informatica

Sistema central de autenticacao, API e operacao da Leme Informatica.

## Modulos

- API de animais e alimentos;
- usuarios e perfis compartilhados entre os dominios;
- dashboard operacional;
- [Leme Pet](pet/README.md): gestao veterinaria, prontuario e internacao.

## Producao

- Sistema principal: `https://lemeinformatica.com.br/`
- Modulo Pet: `https://lemeinformatica.com.br/pet/`

## Seguranca

Os arquivos `includes/config.php`, `includes/database.runtime.php` e qualquer
`.env` sao exclusivos do servidor e nao devem ser enviados ao GitHub.

## Versoes do Leme Pet

A versao atual fica em `pet/VERSION`. Alteracoes, migracoes e roteiros de teste
ficam em `pet/CHANGELOG.md`, `pet/sql/migrations` e `pet/docs/versions`.
