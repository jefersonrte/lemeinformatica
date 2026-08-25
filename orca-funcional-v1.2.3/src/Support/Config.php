<?php
declare(strict_types=1);

namespace App\Support;

final class Config
{
    private static array $values = [];
    private static bool $loaded = false;

    public static function load(string $runtimeFile, string $projectRoot): void
    {
        if (self::$loaded) {
            return;
        }

        $versionFile = $projectRoot . '/VERSION';
        $version = is_file($versionFile) ? trim((string) file_get_contents($versionFile)) : 'dev';

        self::$values = [
            'app' => [
                'name' => 'Orçamentista',
                'url' => 'https://lemeinformatica.com.br/orca',
                'environment' => 'production',
                'version' => $version,
                'timezone' => 'America/Sao_Paulo',
            ],
            'database' => [
                'host' => 'localhost',
                'port' => 3306,
                'name' => 'orcamentista',
                'user' => '',
                'pass' => '',
                'charset' => 'utf8mb4',
                'prefix' => '',
            ],
            'mail' => [
                'host' => '',
                'port' => 587,
                'user' => '',
                'pass' => '',
                'from' => '',
                'from_name' => 'Orçamentista - Leme Informática',
            ],
            'whatsapp' => [
                'api_url' => 'https://api.callmebot.com/whatsapp.php',
                'api_key' => '',
            ],
            'security' => [
                'session_name' => 'LEME_ORCA_SESSION',
                'session_lifetime' => 28800,
                'bcrypt_cost' => 12,
                'secure_cookies' => true,
                'login_max_attempts' => 5,
                'login_lock_minutes' => 15,
                'migration_key' => '',
            ],
            'uploads' => [
                'directory' => $projectRoot . '/uploads/',
                'max_mb' => 20,
            ],
            'demo' => [
                'enabled' => false,
            ],
        ];

        if (is_file($runtimeFile)) {
            $runtime = require $runtimeFile;
            if (!is_array($runtime)) {
                throw new \RuntimeException('A configuração runtime deve retornar um array.');
            }
            self::$values = array_replace_recursive(self::$values, $runtime);
        }

        self::applyEnvironmentOverrides();
        self::$loaded = true;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = self::$values;
        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }
        return $value;
    }

    private static function applyEnvironmentOverrides(): void
    {
        $map = [
            'ORCA_APP_URL' => 'app.url',
            'ORCA_APP_ENV' => 'app.environment',
            'ORCA_DB_HOST' => 'database.host',
            'ORCA_DB_PORT' => 'database.port',
            'ORCA_DB_NAME' => 'database.name',
            'ORCA_DB_USER' => 'database.user',
            'ORCA_DB_PASS' => 'database.pass',
            'ORCA_DB_PREFIX' => 'database.prefix',
            'ORCA_MAIL_HOST' => 'mail.host',
            'ORCA_MAIL_PORT' => 'mail.port',
            'ORCA_MAIL_USER' => 'mail.user',
            'ORCA_MAIL_PASS' => 'mail.pass',
            'ORCA_MAIL_FROM' => 'mail.from',
            'ORCA_WA_API_KEY' => 'whatsapp.api_key',
            'ORCA_MIGRATION_KEY' => 'security.migration_key',
            'ORCA_DEMO_ENABLED' => 'demo.enabled',
        ];

        foreach ($map as $environmentName => $configKey) {
            $environmentValue = getenv($environmentName);
            if ($environmentValue !== false && $environmentValue !== '') {
                self::set($configKey, $environmentValue);
            }
        }
    }

    private static function set(string $key, mixed $value): void
    {
        $segments = explode('.', $key);
        $cursor =& self::$values;
        foreach ($segments as $segment) {
            if (!isset($cursor[$segment]) || !is_array($cursor[$segment])) {
                $cursor[$segment] = [];
            }
            $cursor =& $cursor[$segment];
        }
        $cursor = $value;
    }
}
