<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap/app.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$configuredKey = (string) \App\Support\Config::get('security.migration_key', '');
$receivedKey = (string) ($_SERVER['HTTP_X_MIGRATION_KEY'] ?? '');
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $configuredKey === '' || !hash_equals($configuredKey, $receivedKey)) {
    http_response_code(404);
    echo json_encode(['ok' => false]);
    exit;
}

try {
    $db = getDB();
    $installer = new \App\Infrastructure\DatabaseInstaller($db, __DIR__);
    $installer->installBaseSchema();
    $db->exec(
        'CREATE TABLE IF NOT EXISTS schema_migrations ('
        . 'versao VARCHAR(30) PRIMARY KEY, aplicado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP'
        . ') ENGINE=InnoDB'
    );

    $aplicadas = $db->query('SELECT versao FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
    $novas = [];
    foreach (glob(__DIR__ . '/database/migrations/*.sql') ?: [] as $file) {
        $version = pathinfo($file, PATHINFO_FILENAME);
        if (in_array($version, $aplicadas, true)) {
            continue;
        }

        $installer->executeStatements((string) file_get_contents($file));
        $insert = $db->prepare('INSERT INTO schema_migrations (versao) VALUES (?)');
        $insert->execute([$version]);
        $novas[] = $version;
    }

    $seeded = $installer->seedDemo();
    echo json_encode(['ok' => true, 'version' => APP_VERSION, 'migrations' => $novas, 'seeded' => $seeded], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    error_log('[migration] ' . $exception);
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Falha ao aplicar migrações.']);
}
