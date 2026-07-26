<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$context = pet_boot_page();
if ($context['perfil'] !== 'admin') {
    http_response_code(403);
    echo 'Apenas administradores podem instalar o modulo.';
    exit;
}

$message = '';
$error = '';
$installedVersion = null;

try {
    $result = db()->query("SHOW TABLES LIKE 'pet_schema_migrations'");
    if ($result && $result->num_rows > 0) {
        $versionResult = db()->query('SELECT versao FROM pet_schema_migrations ORDER BY aplicado_em DESC LIMIT 1');
        $installedVersion = $versionResult->fetch_assoc()['versao'] ?? null;
    }
} catch (Throwable $exception) {
    $error = 'Nao foi possivel verificar o banco.';
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!validate_api_csrf($_POST['csrf_token'] ?? null)) {
        $error = 'A sessao de seguranca expirou. Atualize a pagina.';
    } else {
        try {
            $migrationPath = __DIR__ . '/sql/migrations/001_v1_0_0_pet_core.sql';
            $sql = file_get_contents($migrationPath);
            if (!is_string($sql) || trim($sql) === '') {
                throw new RuntimeException('Migracao nao encontrada.');
            }

            $conn = db();
            if (!$conn->multi_query($sql)) {
                throw new RuntimeException('Falha ao executar a migracao.');
            }
            do {
                if ($result = $conn->store_result()) {
                    $result->free();
                }
            } while ($conn->more_results() && $conn->next_result());

            if ($conn->errno) {
                throw new mysqli_sql_exception($conn->error, $conn->errno);
            }

            $installedVersion = PET_VERSION;
            $message = 'Modulo Pet instalado ou atualizado com sucesso.';
            pet_audit($context, 'instalar', 'modulo_pet', null, ['versao' => PET_VERSION]);
        } catch (Throwable $exception) {
            error_log('[PET INSTALL] ' . $exception->getMessage());
            $error = 'A instalacao nao foi concluida. Verifique o log do servidor e o usuario do banco.';
        }
    }
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Instalacao - Leme Pet</title>
    <link rel="stylesheet" href="frontend/css/app.css?v=<?= rawurlencode(PET_VERSION) ?>">
</head>
<body>
    <main class="content">
        <section class="data-section" style="max-width:720px;margin:40px auto">
            <div class="panel-heading">
                <div>
                    <p class="section-kicker">Administrador</p>
                    <h1 style="margin:4px 0 0;font-size:24px">Instalacao do Leme Pet</h1>
                </div>
                <span class="badge info">v<?= htmlspecialchars(PET_VERSION, ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <div style="padding:22px">
                <?php if ($message): ?><p class="badge success"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
                <?php if ($error): ?><p class="badge danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
                <p>Versao instalada: <strong><?= htmlspecialchars((string) ($installedVersion ?: 'nao instalada'), ENT_QUOTES, 'UTF-8') ?></strong></p>
                <p>A migracao cria somente tabelas com o prefixo <code>pet_</code> e pode ser executada novamente com seguranca.</p>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(api_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                    <button class="button primary" type="submit">Instalar ou atualizar banco</button>
                    <a class="button secondary" href="./">Voltar ao sistema</a>
                </form>
            </div>
        </section>
    </main>
</body>
</html>
