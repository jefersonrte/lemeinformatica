<?php
declare(strict_types=1);

// Copie para runtime.php apenas no servidor. runtime.php não entra no Git.
return [
    'app' => [
        'url' => 'https://lemeinformatica.com.br/orca',
        'environment' => 'production',
    ],
    'database' => [
        'host' => 'localhost',
        'port' => 3306,
        'name' => 'nome_do_banco',
        'user' => 'usuario_do_banco',
        'pass' => 'senha_do_banco',
        'prefix' => 'orca12_',
    ],
    'mail' => [
        'host' => 'smtp.exemplo.com',
        'port' => 587,
        'user' => 'usuario_smtp',
        'pass' => 'senha_smtp',
        'from' => 'orcamentos@lemeinformatica.com.br',
    ],
    'whatsapp' => ['api_key' => ''],
    'security' => ['migration_key' => 'gere-uma-chave-longa'],
    'demo' => ['enabled' => false],
];
