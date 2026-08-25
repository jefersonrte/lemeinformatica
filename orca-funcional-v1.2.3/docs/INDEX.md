# Documentação técnica do Orçamentista

Esta pasta é a fonte oficial de conhecimento da versão `1.2.3`. Ela descreve o sistema como ele está implementado no código, sem incluir credenciais, chaves, dados pessoais ou cópias do banco de produção.

## Leitura recomendada

1. [Visão geral e arquitetura](ARCHITECTURE.md) — componentes, limites e fluxo de uma requisição.
2. [Modelo de dados](DATA_MODEL.md) — tabelas, relações, estados e regras de cálculo.
3. [Fluxos de processo](PROCESS_FLOWS.md) — login, cadastro, obra, orçamento, cotação, compra, plantas e painel.
4. [Guia de melhorias](EXTENSION_GUIDE.md) — arquivo certo para cada tipo de mudança e cuidados de implementação.
5. [Publicação e operação](DEPLOY.md) — configuração, migração, testes, versionamento e rollback.
6. [Transferência para outra IA](AI_HANDOFF.md) — mapa de contexto, exclusões de segurança e prompt-base.

Para entregar o projeto a outra IA, o ponto de entrada é também o arquivo [AI_CONTEXT.md](../AI_CONTEXT.md), na raiz do repositório.

## Escopo funcional atual

- acesso administrativo local e integração com o administrador central do domínio;
- clientes e usuários de cliente;
- obras, etapas, status e progresso;
- catálogo de categorias, produtos e fornecedores;
- orçamento manual ou importado de Excel, CSV, XML, PDF e planilha CAIXA/SINAPI;
- solicitação, leitura e consolidação de cotações;
- registro de compras e custo realizado;
- painel financeiro com orçado, cotado e realizado;
- plantas e documentos técnicos versionados, com acesso por obra;
- logs, migrações, verificação de saúde e teste transacional protegido.

## Regra de atualização

Qualquer alteração funcional deve atualizar, no mesmo commit:

- o código e seus testes;
- `CHANGELOG.md` quando houver impacto de versão;
- os documentos afetados nesta pasta;
- `AI_CONTEXT.md` quando a arquitetura, os comandos ou os limites de segurança mudarem.

Os arquivos `config/runtime.php`, uploads, backups e credenciais nunca fazem parte da documentação nem do pacote para IA.
