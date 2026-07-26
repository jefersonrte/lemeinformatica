# Usuarios na API principal

## Configuracao no servidor

Copie `includes/config.example.php` para `includes/config.php` somente na hospedagem. O arquivo real contem credenciais e nao deve ser enviado ao GitHub.

O banco configurado aqui e a fonte central de autenticacao dos dois sites. A
migracao nao cria credenciais padrao. Provisione o administrador por um canal
seguro e armazene somente o resultado de `password_hash()`.

## O que foi adicionado

- `usuarios.php`: endpoint JSON para listar, criar, editar e desativar usuarios.
- `usuarios-admin.php`: tela administrativa que usa a `X-API-KEY` para acessar o endpoint.
- `sql/create_auth_tables.sql`: tabelas de autenticacao, sem senha padrao.

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

## Dados de demonstracao

O arquivo `sql/seed_1000_animais_alimentos.sql` cria 500 animais e 500 alimentos. A carga usa o lote `animais_alimentos_1000_v1` e nao duplica os registros se for executada novamente.

Depois de publicar os arquivos da API, envie um `POST` para `/carga-demo.php` com a `X-API-KEY` e o JSON:

```json
{
  "confirmacao": "CRIAR_1000_REGISTROS"
}
```

Os alimentos ficam disponiveis em `GET /alimentos.php`.
