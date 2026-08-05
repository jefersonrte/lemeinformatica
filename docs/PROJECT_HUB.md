# Central de projetos

Versao inicial: `1.0.0`  
Publicacao: 05/08/2026

## Objetivo

A pagina inicial de `lemeinformatica.com.br` funciona como um menu publico para
os projetos dos dois dominios Leme. Sistemas protegidos continuam exigindo login
em suas proprias rotas.

## Estrutura

- `index.php`: registro dos projetos e HTML semantico.
- `frontend/css/home.css`: layout responsivo e estados visuais.
- `frontend/js/home.js`: menu movel, tecla Escape e ano do rodape.
- `frontend/assets/matrix-city-v2.webp`: imagem de fundo otimizada.

## Adicionar um projeto

Inclua um item no array `$projects` de `index.php` com os campos:

```php
[
    'name' => 'Nome do projeto',
    'description' => 'Descricao curta e objetiva.',
    'url' => 'https://dominio.example/projeto/',
    'category' => 'Categoria',
    'accent' => 'cyan',
    'local' => true,
]
```

Os valores de `accent` disponiveis sao `mint`, `cyan`, `yellow`, `coral`,
`violet` e `blue`. Use `local` para identificar projetos hospedados no dominio
da pagina atual.

## Seguranca

A pagina nao contem credenciais. Os cabecalhos restringem recursos a arquivos do
proprio dominio, bloqueiam incorporacao por terceiros e desabilitam camera,
microfone e geolocalizacao.
