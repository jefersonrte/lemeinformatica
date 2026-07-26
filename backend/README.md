# Backend

O backend publicado permanece nos arquivos PHP da raiz e em `includes/` para preservar as URLs consumidas pelo Power BI e pelo outro dominio.

- `animais.php`, `alimentos.php` e `usuarios.php`: endpoints da API.
- `login-processa.php` e `logout.php`: autenticacao.
- `includes/`: banco, sessao, seguranca e funcoes HTTP.

Os arquivos de apresentacao ficam em `frontend/`.
