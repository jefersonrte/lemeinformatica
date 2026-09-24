<?php
declare(strict_types=1);

namespace App\Infrastructure;

use PDO;
use PDOException;
use RuntimeException;

final class DatabaseConnection
{
    private ?PDO $connection = null;

    public function __construct(private readonly array $config)
    {
    }

    public function get(): PDO
    {
        if ($this->connection instanceof PDO) {
            return $this->connection;
        }

        $host = (string) ($this->config['host'] ?? 'localhost');
        $port = (int) ($this->config['port'] ?? 3306);
        $name = (string) ($this->config['name'] ?? '');
        $charset = (string) ($this->config['charset'] ?? 'utf8mb4');
        $user = (string) ($this->config['user'] ?? '');

        if ($name === '' || $user === '') {
            throw new RuntimeException('Banco de dados ainda não configurado.');
        }

        $dsn = "mysql:host={$host};port={$port};dbname={$name};charset={$charset}";
        try {
            $this->connection = new PrefixedPDO($dsn, $user, (string) ($this->config['pass'] ?? ''), [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ], (string) ($this->config['prefix'] ?? ''));
        } catch (PDOException $exception) {
            error_log('[database] ' . $exception->getMessage());
            throw new RuntimeException('Não foi possível conectar ao banco de dados.', 0, $exception);
        }

        return $this->connection;
    }
}
