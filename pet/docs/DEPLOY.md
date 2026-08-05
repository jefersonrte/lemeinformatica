# Implantacao do Leme Pet

## Pre-requisitos

- PHP 8.0 ou superior;
- MySQL 5.7 ou superior;
- extensoes `mysqli`, `fileinfo` e `json`;
- HTTPS ativo;
- usuario do banco com permissao para criar e alterar tabelas.

## Fluxo pelo GitHub

1. Criar uma branch de funcionalidade.
2. Publicar os arquivos e abrir um pull request.
3. Aguardar o workflow `Leme Pet - CI` validar PHP, JavaScript e arquivos
   privados.
4. Revisar e fazer merge na branch `main`.
5. O workflow `Deploy Hostinger` publica os arquivos rastreados em uma unica
   sessao FTPS persistente.
6. O pipeline chama `/pet/migrate.php` por `POST` com a API key protegida e
   confirma a versao antes do health check.
7. Executar o roteiro da versao em `docs/versions/`.
8. Criar uma tag anotada, por exemplo `pet-v1.0.0`.

O deploy tambem pode ser iniciado manualmente em `Actions > Deploy Hostinger >
Run workflow`.

## Secrets do GitHub

Os seguintes Repository Secrets devem existir em `Settings > Secrets and
variables > Actions`:

- `HOSTINGER_FTP_SERVER`;
- `HOSTINGER_FTP_USERNAME`;
- `HOSTINGER_FTP_PASSWORD`.
- `APP_DB_HOST`, `APP_DB_NAME`, `APP_DB_USERNAME`, `APP_DB_PASSWORD`;
- `APP_API_KEY`.

Os valores nunca devem ser gravados em arquivos, commits, logs ou documentacao.

## Escopo da publicacao

O workflow publica somente arquivos rastreados pelo Git e nao apaga arquivos
remotos. Documentacao Markdown, workflows, scripts SQL da raiz e metadados do
repositorio nao sao enviados ao servidor.

Os arquivos privados `includes/config.php` e
`includes/database.runtime.php` sao preservados no servidor. O workflow
interrompe a publicacao caso um deles seja rastreado por engano.

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
