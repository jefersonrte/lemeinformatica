<?php
declare(strict_types=1);

function pet_migration_version_from_path(string $path): string
{
    $name = basename($path);
    if (!preg_match('/^\d+_v(\d+)_(\d+)_(\d+)_/', $name, $matches)) {
        throw new RuntimeException('Nome de migracao invalido: ' . $name);
    }

    return sprintf('%d.%d.%d', (int) $matches[1], (int) $matches[2], (int) $matches[3]);
}

function pet_execute_sql_batch(mysqli $conn, string $sql): void
{
    if (!$conn->multi_query($sql)) {
        throw new mysqli_sql_exception($conn->error, $conn->errno);
    }

    while (true) {
        if ($result = $conn->store_result()) {
            $result->free();
        }

        if ($conn->errno) {
            throw new mysqli_sql_exception($conn->error, $conn->errno);
        }

        if (!$conn->more_results()) {
            break;
        }

        if (!$conn->next_result()) {
            throw new mysqli_sql_exception($conn->error, $conn->errno);
        }
    }
}

function pet_applied_migrations(mysqli $conn): array
{
    $table = $conn->query("SHOW TABLES LIKE 'pet_schema_migrations'");
    if (!$table || $table->num_rows === 0) {
        return [];
    }

    $versions = [];
    $result = $conn->query('SELECT versao FROM pet_schema_migrations');
    while ($row = $result->fetch_assoc()) {
        $versions[] = (string) $row['versao'];
    }

    return $versions;
}

function pet_current_schema_version(?mysqli $conn = null): ?string
{
    $versions = pet_applied_migrations($conn ?? db());
    if ($versions === []) {
        return null;
    }

    usort($versions, 'version_compare');
    return (string) end($versions);
}

function pet_apply_migrations(): array
{
    $conn = db();
    $lockResult = $conn->query("SELECT GET_LOCK('leme_pet_migrations', 10) AS adquirido");
    $lock = (int) ($lockResult->fetch_assoc()['adquirido'] ?? 0);
    if ($lock !== 1) {
        throw new RuntimeException('Outra atualizacao do modulo Pet esta em andamento.');
    }

    $appliedNow = [];

    try {
        $paths = glob(PET_ROOT . '/sql/migrations/*.sql') ?: [];
        sort($paths, SORT_NATURAL);
        if ($paths === []) {
            throw new RuntimeException('Nenhuma migracao do modulo Pet foi encontrada.');
        }

        $alreadyApplied = pet_applied_migrations($conn);
        foreach ($paths as $path) {
            $version = pet_migration_version_from_path($path);
            if (in_array($version, $alreadyApplied, true)) {
                continue;
            }

            $sql = file_get_contents($path);
            if (!is_string($sql) || trim($sql) === '') {
                throw new RuntimeException('Migracao vazia: ' . basename($path));
            }

            pet_execute_sql_batch($conn, $sql);
            $appliedNow[] = $version;
            $alreadyApplied[] = $version;
        }

        return [
            'aplicadas' => $appliedNow,
            'versao_banco' => pet_current_schema_version($conn),
        ];
    } finally {
        $conn->query("SELECT RELEASE_LOCK('leme_pet_migrations')");
    }
}
