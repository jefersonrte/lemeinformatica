# Central de projetos 1.1.2

Data: 05/08/2026

## Correcao de montagem

A integracao Git da Hostinger publica o repositorio dentro de `/pet`. Como o
modulo tambem vive na pasta `pet/` do repositorio, os arquivos reais ficam em
`/pet/pet/`.

Esta versao mantem a URL publica `/pet/` por meio de:

- despacho seguro do `index.php` da raiz para o modulo interno;
- elemento `base` somente quando a aplicacao esta montada nesse formato;
- adaptadores de SSO e relatorios na raiz publicada;
- preservacao do destino Power BI na sessao central durante o login;
- preservacao da estrutura modular no Git, sem duplicar o codigo do Pet.
