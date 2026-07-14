# Usuarios na API principal

## Configuracao no servidor

Copie `includes/config.example.php` para `includes/config.php` somente na hospedagem. O arquivo real contem credenciais e nao deve ser enviado ao GitHub.

## O que foi adicionado

- `usuarios.php`: endpoint JSON para listar, criar, editar e desativar usuarios.
- `usuarios-admin.php`: tela administrativa que usa a `X-API-KEY` para acessar o endpoint.
- `sql/create_auth_tables.sql`: tabela `usuarios_admin` e usuario inicial.

## Seguranca

O endpoint usa a mesma protecao por chave da API principal. Envie a chave em `X-API-KEY` ou `Authorization: Bearer`.

## Rotas

- `GET /`: abre a tela administrativa de usuarios.
- `GET /usuarios.php`: lista usuarios.
- `POST /usuarios.php`: cria usuario.
- `PUT /usuarios.php?id=ID`: atualiza usuario.
- `DELETE /usuarios.php?id=ID`: desativa usuario.

## Campos

```json
{
  "nome": "Operador",
  "email": "operador@seudominio.com.br",
  "senha": "NovaSenhaForte",
  "perfil": "operador",
  "ativo": true
}
```

Perfis aceitos: `admin`, `operador`, `visualizador`.

Na edicao, envie `senha` apenas quando quiser trocar a senha.
