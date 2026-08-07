# Provisionamento central de usuarios

Versao: `1.3.0`

## Fonte de verdade

`lemeinformatica.com.br/pet/usuarios.php` e a fonte de verdade das identidades
na montagem Git da Hostinger. A tela `/pet/usuarios-admin.php` e o painel
administrativo do segundo dominio usam essa mesma API. Pet, Dashboard Pet e
Power BI recebem a identidade central por SSO.

## Fluxo de criacao

1. Um administrador envia nome, e-mail, perfil, senha e status para
   `usuarios.php`.
2. A API inicia uma transacao e grava o usuario em `usuarios_admin`.
3. A API chama `https://lemesolucoesemti.com.br/api/usuarios-sync.php` com
   `X-API-KEY`.
4. O segundo dominio cria ou atualiza sua conta local com o mesmo hash seguro
   gerado a partir da senha recebida.
5. O endpoint usa a OCS Provisioning API para criar `leme-{id}` no Nextcloud,
   atualizando nome, e-mail, senha e estado ativo.
6. Somente depois das integracoes responderem com sucesso a transacao central e
   confirmada.

## Alteracao e bloqueio

Nome, e-mail, perfil, nova senha e estado ativo seguem o mesmo fluxo. Senha em
branco durante uma edicao preserva a senha atual. Desativar bloqueia o usuario
na API central, no dashboard local e no Nextcloud.

## Seguranca e falhas

- A comunicacao entre dominios exige a chave privada `X-API-KEY`.
- O Nextcloud usa uma senha de aplicativo exclusiva, armazenada somente no
  servidor em `auth/nextcloud-runtime.php`.
- Senhas nunca sao gravadas em logs, documentacao ou Git.
- Se a criacao remota falhar, as transacoes de banco sao revertidas e a API
  retorna `SINCRONIZACAO_USUARIO_FALHOU`.

Exemplo de chamada administrativa interna:

```http
POST /usuarios.php HTTP/1.1
Content-Type: application/json
X-CSRF-TOKEN: token-da-sessao

{"nome":"Usuario Exemplo","email":"usuario@example.com","perfil":"operador","senha":"senha-definida-pelo-admin","ativo":true}
```
