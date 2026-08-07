# Central de projetos

Versao atual: `1.3.0`
Publicacao: 06/08/2026

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

## Rotas catalogadas

| Projeto | Destino |
|---|---|
| Clinica Pet | `https://lemeinformatica.com.br/pet/` |
| Dashboard Pet | `https://lemesolucoesemti.com.br/pet/` |
| Relatorios Power BI | `https://lemesolucoesemti.com.br/powerbi/` |
| Dados Publicos SC | `https://lemeinformatica.com.br/gov/` |
| Brasil em Dados | `https://lemesolucoesemti.com.br/gov/` |
| Investimentos | `https://lemesolucoesemti.com.br/invest/` |
| Nuvem / Nextcloud | `https://lemesolucoesemti.com.br/cloud/` |
| Administracao e API | `https://lemeinformatica.com.br/pet/login.php` |

## Versoes

- `1.3.0`: centraliza o cadastro de usuarios, replica conta, perfil, senha e
  status no dashboard e provisiona a mesma identidade no Nextcloud.
- `1.1.2`: adiciona compatibilidade com o repositorio montado pela Hostinger
  dentro de `/pet`, preservando a URL publica original do modulo.
- `1.1.1`: estende o SSO central para o Power BI, mantendo uma unica senha nos
  dois dominios.
- `1.1.0`: inclui Power BI e Nextcloud, restaura projetos ausentes e corrige os
  destinos compartilhados entre os dominios.
- `1.0.0`: primeira central publica de projetos.

## Seguranca

A pagina nao contem credenciais. Os cabecalhos restringem recursos a arquivos do
proprio dominio, bloqueiam incorporacao por terceiros e desabilitam camera,
microfone e geolocalizacao.
