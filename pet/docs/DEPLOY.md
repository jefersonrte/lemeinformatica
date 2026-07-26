# Implantacao do Leme Pet

## Pre-requisitos

- PHP 8.0 ou superior;
- MySQL 5.7 ou superior;
- extensoes `mysqli`, `fileinfo` e `json`;
- HTTPS ativo;
- usuario do banco com permissao para criar e alterar tabelas.

## Fluxo pelo GitHub

1. Criar uma branch `feature/pet-v1`.
2. Publicar os arquivos do modulo e as alteracoes de login.
3. Revisar o diff para garantir que nenhum `config.php` foi incluido.
4. Fazer merge na branch de producao.
5. Aguardar a implantacao da Hostinger.
6. Entrar como administrador e executar `/pet/install.php`.
7. Executar o roteiro em `docs/versions/1.0.0.md`.
8. Criar a tag `pet-v1.0.0`.

## Arquivos fora do modulo

Esta versao tambem altera:

- `login.php`: aceita destino seguro `next=pet`;
- `login-processa.php`: retorna ao Pet depois do login;
- `painel.php`: adiciona o acesso ao Sistema Pet.

## Permissoes de diretorio

- diretorios: `0755`;
- arquivos PHP/CSS/JS: `0644`;
- `pet/uploads`: gravavel pelo PHP sem permitir execucao de scripts.

## Reversao

1. Reverter o commit da versao.
2. Manter as tabelas `pet_` para preservar o prontuario.
3. Se a remocao definitiva for aprovada, exportar o banco antes de apagar
   qualquer tabela.

Nunca apagar tabelas clinicas durante uma reversao comum.
