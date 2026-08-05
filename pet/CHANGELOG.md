# Changelog

Todas as alteracoes relevantes do Leme Pet sao registradas neste arquivo.

## [1.1.1] - 2026-08-05

### Adicionado

- autenticacao central entre dominios com codigo unico e token servidor a servidor;
- sessao independente do dashboard sem copia de senha, banco de usuarios ou API key;
- revogacao do token ao fechar o painel analitico.

### Corrigido

- deploy passa a publicar o runtime completo e o arquivo `VERSION`;
- health check compara versao da aplicacao e da tabela de migracoes.

## [1.1.0] - 2026-08-05

### Adicionado

- modulo de banho e tosa com catalogo de servicos, agenda e acompanhamento de status;
- catalogo de produtos com racao, petiscos, higiene, acessorios e medicamentos;
- controle transacional de estoque com entradas, saidas, ajustes, vendas e estornos;
- ponto de venda com varios itens, desconto, forma de pagamento e cancelamento auditado;
- indicadores clinicos, de estetica, estoque e faturamento no painel operacional;
- API agregada protegida para o dashboard do dominio Leme Solucoes em TI;
- migrador versionado executado automaticamente no deploy;
- frontend modular em `frontend/*/modules` e regras de dominio em `modules`.

### Seguranca

- o dashboard entre dominios recebe somente totais e series agregadas;
- venda e baixa de estoque acontecem na mesma transacao;
- somente administradores podem cancelar vendas, com estorno e motivo obrigatorio.

## [1.0.1] - 2026-07-26

### Adicionado

- carga demonstrativa versionada com 100 tutores, 200 animais, 50 atendimentos e 2 internacoes;
- provisionamento protegido por API key, transacional e idempotente;
- validacao automatica dos totais da carga antes da confirmacao;
- health check protegido para sessao e banco em producao;
- deploy FTPS automatizado para a raiz real do dominio.

### Corrigido

- destino de publicacao na Hostinger;
- geracao segura da configuracao privada no pipeline;
- inicializacao de sessao e limites de autenticacao com valores padrao compativeis.

## [1.0.0] - 2026-07-25

### Adicionado

- cadastro detalhado de tutores e animais;
- vinculo de varios animais por tutor;
- fotografia segura para tutores, animais e veterinarios;
- atendimentos, anamnese, exame clinico, diagnostico, conduta e prescricao;
- internacoes com risco, setor, leito, alta e evolucoes;
- linha do tempo do prontuario;
- dashboard responsivo com indicadores e distribuicao por especie;
- perfil visualizador limitado a dados agregados;
- vinculacao de veterinarios a usuarios da autenticacao central;
- auditoria das operacoes do modulo;
- migracao idempotente e instalador administrativo;
- documentacao de arquitetura, API e implantacao.
# Changelog

Todas as alteracoes relevantes do Leme Pet sao registradas neste arquivo.

## [1.0.0] - 2026-07-25

### Adicionado

- cadastro detalhado de tutores e animais;
- vinculo de varios animais por tutor;
- fotografia segura para tutores, animais e veterinarios;
- atendimentos, anamnese, exame clinico, diagnostico, conduta e prescricao;
- internacoes com risco, setor, leito, alta e evolucoes;
- linha do tempo do prontuario;
- dashboard responsivo com indicadores e distribuicao por especie;
- perfil visualizador limitado a dados agregados;
- vinculacao de veterinarios a usuarios da autenticacao central;
- auditoria das operacoes do modulo;
- migracao idempotente e instalador administrativo;
- documentacao de arquitetura, API e implantacao.
