# API do Leme Pet

Base:

`https://lemeinformatica.com.br/pet/api/`

Todos os endpoints exigem a sessao do sistema. Operacoes de escrita tambem
exigem o cabecalho `X-CSRF-TOKEN`.

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
