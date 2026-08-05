# API do Leme Pet

Base:

`https://lemeinformatica.com.br/pet/api/`

Todos os endpoints exigem a sessao do sistema. Operacoes de escrita tambem
exigem o cabecalho `X-CSRF-TOKEN`.

O endpoint `relatorios.php` tambem aceita a chave de integracao no cabecalho
`X-API-KEY`. A chave e lida somente da configuracao privada do servidor.
O dashboard Pet usa alternativamente um token SSO no cabecalho `Authorization`.

## Dashboard

```http
GET dashboard.php
```

O visualizador recebe apenas totais e distribuicao por especie.

## Tutores

```http
GET    tutores.php?q=nome&page=1&limit=25
GET    tutores.php?id=10
POST   tutores.php
PUT    tutores.php?id=10
DELETE tutores.php?id=10
```

Exemplo:

```json
{
  "nome": "Maria da Silva",
  "cpf": "00000000000",
  "email": "maria@example.com",
  "telefone": "11999999999",
  "cidade": "Sao Paulo",
  "uf": "SP"
}
```

## Animais

```http
GET    animais.php?q=thor
GET    animais.php?id=25
POST   animais.php
PUT    animais.php?id=25
DELETE animais.php?id=25
```

Exemplo:

```json
{
  "tutor_id": 10,
  "nome": "Thor",
  "especie": "Canina",
  "raca": "Sem raca definida",
  "sexo": "macho",
  "porte": "medio",
  "castrado": true
}
```

## Atendimentos

```http
GET  atendimentos.php?animal_id=25
GET  atendimentos.php?id=80
POST atendimentos.php
PUT  atendimentos.php?id=80
```

Somente administrador ou veterinario vinculado pode salvar dados clinicos.
Operadores podem criar registros agendados.

## Internacoes

```http
GET  internacoes.php?status=ativa
GET  internacoes.php?id=15
POST internacoes.php
PUT  internacoes.php?id=15
```

## Evolucoes

```http
GET  evolucoes.php?internacao_id=15
POST evolucoes.php
```

## Prontuario

```http
GET historico.php?animal_id=25
```

Retorna cadastro do animal, atendimentos, internacoes e evolucoes.

## Fotografias

```http
POST fotos.php
Content-Type: multipart/form-data
X-CSRF-TOKEN: token-da-sessao
```

Campos:

- `tipo`: `tutor`, `animal` ou `veterinario`;
- `id`: identificador do cadastro;
- `foto`: JPG, PNG ou WebP de ate 5 MB.

## Banho e tosa

```http
GET  servicos.php
POST servicos.php
PUT  servicos.php?id=8

GET  banho-tosa.php?status=agendado&q=mel
GET  banho-tosa.php?id=12
POST banho-tosa.php
PUT  banho-tosa.php?id=12
```

Exemplo de agendamento:

```json
{
  "animal_id": 25,
  "inicio_em": "2026-08-10T09:00",
  "status": "confirmado",
  "profissional_nome": "Equipe de estetica",
  "servicos": [
    { "servico_id": 1, "quantidade": 1 },
    { "servico_id": 3, "quantidade": 1 }
  ]
}
```

Preco, nome e duracao dos servicos sao sempre recalculados no servidor.

## Produtos e estoque

```http
GET  produtos.php?q=racao&estoque_baixo=1
GET  produtos.php?id=5
POST produtos.php
PUT  produtos.php?id=5

GET  estoque.php?produto_id=5
POST estoque.php
```

Exemplo de movimento manual:

```json
{
  "produto_id": 5,
  "tipo": "entrada",
  "quantidade": 10,
  "custo_unitario": 42.5,
  "motivo": "Compra do fornecedor"
}
```

## Vendas

```http
GET  vendas.php?status=concluida
GET  vendas.php?id=20
POST vendas.php
PUT  vendas.php?id=20
```

Exemplo de venda:

```json
{
  "tutor_id": 10,
  "forma_pagamento": "pix",
  "desconto": 5,
  "itens": [
    { "produto_id": 5, "quantidade": 2 },
    { "produto_id": 9, "quantidade": 1 }
  ]
}
```

O servidor consulta o preco atual, bloqueia os produtos, valida o saldo e
registra venda, itens e movimentos de estoque na mesma transacao. O `PUT`
cancela uma venda e exige `{ "motivo": "..." }`; somente admin pode executar.

## Relatorio integrado

```http
GET relatorios.php
X-API-KEY: chave-protegida
```

Retorna somente totais e series de vendas, categorias, estetica e especies.
Nao retorna nomes, contatos, fotografias ou dados de prontuario.

## Autenticacao do dashboard

```http
POST sso.php
Content-Type: application/json

{ "codigo": "codigo-unico-de-64-caracteres" }
```

A troca devolve um token somente uma vez. Consultas seguintes usam
`Authorization: Bearer token`. `GET sso.php` valida o usuario e `POST sso.php`
com Bearer revoga a sessao integrada.

## Resposta de erro

```json
{
  "ok": false,
  "codigo": "DADOS_INVALIDOS",
  "erro": "Revise os campos destacados.",
  "campos": {
    "telefone": "Informe telefone com DDD."
  }
}
```
