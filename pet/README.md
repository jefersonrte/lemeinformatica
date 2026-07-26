# Leme Pet

Modulo de gestao veterinaria publicado em:

`https://lemeinformatica.com.br/pet/`

## Escopo da versao 1.0.0

- cadastro completo de tutores com fotografia;
- varios animais vinculados ao mesmo tutor;
- cadastro clinico do animal com fotografia;
- agenda e atendimento veterinario;
- prontuario com linha do tempo;
- internacao, alta e evolucoes clinicas;
- equipe veterinaria vinculada aos usuarios existentes;
- dashboard agregado para o perfil visualizador;
- auditoria e controle de permissao no backend.

## Estrutura

```text
pet/
  api/                 Endpoints JSON protegidos por sessao
  docs/                Arquitetura, API, implantacao e versoes
  frontend/css/        Estilos do modulo
  frontend/js/         Aplicacao do navegador
  includes/            Bootstrap, permissoes, validacao e fotos
  sql/migrations/      Migracoes versionadas
  uploads/             Fotografias; scripts bloqueados por .htaccess
  index.php            Aplicacao principal
  install.php          Instalador de banco somente para admin
  VERSION              Versao atual
```

## Instalacao

1. Publique a pasta `pet` na raiz do dominio.
2. Entre com um usuario administrador.
3. Acesse `/pet/install.php`.
4. Execute a migracao.
5. Abra `/pet/` e vincule os veterinarios aos usuarios do sistema.

Consulte `docs/DEPLOY.md` antes de publicar.

## Perfis

| Perfil | Indicadores | Cadastros | Prontuario | Internacao | Equipe |
|---|---:|---:|---:|---:|---:|
| Admin | Sim | Sim | Sim | Sim | Sim |
| Operador | Sim | Sim | Leitura/agenda | Sim | Nao |
| Veterinario vinculado | Sim | Leitura | Sim | Sim | Nao |
| Visualizador | Sim | Nao | Nao | Nao | Nao |

O veterinario continua usando um usuario `admin` ou `operador` da autenticacao
central e recebe a permissao clinica ao ser vinculado em `pet_veterinarios`.

## Seguranca

- nao versionar `includes/config.php` nem credenciais;
- fotografias limitadas a 5 MB e aos formatos JPG, PNG e WebP;
- nomes de arquivos gerados aleatoriamente;
- CSRF obrigatorio para escrita;
- permissoes verificadas em cada endpoint;
- prontuario indisponivel para o perfil visualizador;
- exclusoes cadastrais sao logicas para preservar o historico.
