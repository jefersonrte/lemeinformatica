# Central de projetos 1.4.0

## Hotfix de compatibilidade

- A rota histórica `orca-funcional-v1.2.0` agora recupera o formulário de
  login quando a sessão expira, mantendo a validação CSRF ativa e evitando a
  página branca `Token CSRF inválido.`.

Publicacao: 12/08/2026

## Alteracoes

- exibe versao e estado em todos os atalhos;
- adiciona busca por nome, categoria e versao;
- adiciona filtros para Orçamentista, Pet, Dados e Servicos;
- inclui as rotas original, demonstrativa e funcionais 1.2.0 e 1.2.1 do Orca;
- destaca a versao recomendada sem remover os historicos.

## Testes

- lint PHP e JavaScript;
- verificacao HTTP de todas as rotas catalogadas;
- filtros e busca em desktop e celular;
- deploy Hostinger e validacao visual da pagina inicial.
