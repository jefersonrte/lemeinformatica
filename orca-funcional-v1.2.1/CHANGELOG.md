# Histórico de versões

Todas as versões publicadas recebem uma tag Git e uma cópia de código no domínio antes da promoção para `/orca`.

## 1.2.1 — 2026-08-12

- recupera com segurança o formulário de login quando a sessão ou o token CSRF expira;
- preserva a validação CSRF obrigatória nos demais formulários;
- mantém compatibilidade com o login administrativo compartilhado do domínio.

## 1.2.0 — 2026-08-12

- navegação horizontal com acesso visível a todos os módulos do sistema;
- menu móvel completo e acessível;
- central consolidada de plantas e documentos, com filtros por obra, tipo e texto;
- indicadores de imagens, PDFs e volume armazenado;
- galeria responsiva com miniaturas protegidas e atalhos para versão e histórico.
- leitor imersivo animado com transição entre pranchas, zoom e navegação por teclado;
- integração segura com o login administrativo do domínio;
- instalação isolada por prefixo de tabelas e teste transacional de produção.

## 1.1.0 — 2026-08-11

- arquitetura modular com bootstrap único, configuração privada de runtime e serviços de domínio;
- novo visual responsivo, botões, navegação e tela de login modernizados;
- dashboard executivo com orçado, cotado, realizado, desvio e gráficos por projeto;
- central de plantas com visualização protegida de PDF/imagem e histórico por obra;
- exportação CSV de orçamento, health check e migrações autenticadas;
- testes automatizados, CI, deploy FTPS versionado e rotina de rollback.

## 1.0.0-claude — 2026-06-24

- versão inicial criada pela Claude;
- preservada integralmente na tag `v1.0.0-claude`.
