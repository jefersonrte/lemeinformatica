<?php
declare(strict_types=1);

namespace App\Infrastructure;

use PDO;
use PDOStatement;

final class PrefixedPDO extends PDO
{
    private TablePrefixer $prefixer;

    public function __construct(string $dsn, string $username, string $password, array $options, string $prefix)
    {
        $this->prefixer = new TablePrefixer($prefix);
        parent::__construct($dsn, $username, $password, $options);
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        return parent::prepare($this->prefixer->transform($query), $options);
    }

    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    {
        $query = $this->prefixer->transform($query);
        return $fetchMode === null ? parent::query($query) : parent::query($query, $fetchMode, ...$fetchModeArgs);
    }

    public function exec(string $statement): int|false
    {
        return parent::exec($this->prefixer->transform($statement));
    }
}
