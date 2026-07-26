<?php
declare(strict_types=1);

function pet_text($value, int $maxLength = 255): string
{
    $text = trim((string) $value);
    if (function_exists('mb_substr')) {
        return mb_substr($text, 0, $maxLength, 'UTF-8');
    }
    return substr($text, 0, $maxLength);
}

function pet_nullable_text($value, int $maxLength = 255): ?string
{
    $text = pet_text($value, $maxLength);
    return $text === '' ? null : $text;
}

function pet_digits($value, int $maxLength = 20): string
{
    return substr((string) preg_replace('/\D+/', '', (string) $value), 0, $maxLength);
}

function pet_bool($value): int
{
    $parsed = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    return $parsed === true ? 1 : 0;
}

function pet_nullable_date($value): ?string
{
    $date = trim((string) $value);
    if ($date === '') {
        return null;
    }

    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    $errors = DateTimeImmutable::getLastErrors();
    if (!$parsed || ($errors !== false && ($errors['warning_count'] || $errors['error_count']))) {
        return null;
    }

    return $parsed->format('Y-m-d');
}

function pet_nullable_datetime($value): ?string
{
    $date = trim((string) $value);
    if ($date === '') {
        return null;
    }

    foreach (['Y-m-d\TH:i', 'Y-m-d H:i:s', 'Y-m-d H:i'] as $format) {
        $parsed = DateTimeImmutable::createFromFormat('!' . $format, $date);
        $errors = DateTimeImmutable::getLastErrors();
        if ($parsed && ($errors === false || (!$errors['warning_count'] && !$errors['error_count']))) {
            return $parsed->format('Y-m-d H:i:s');
        }
    }

    return null;
}

function pet_nullable_decimal($value, float $min = 0, float $max = 9999): ?float
{
    if ($value === null || $value === '') {
        return null;
    }

    $normalized = str_replace(',', '.', (string) $value);
    if (!is_numeric($normalized)) {
        return null;
    }

    $number = (float) $normalized;
    if ($number < $min || $number > $max) {
        return null;
    }

    return round($number, 2);
}

function pet_validate_cpf(string $cpf): bool
{
    $cpf = pet_digits($cpf, 11);
    if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) {
        return false;
    }

    for ($digit = 9; $digit < 11; $digit++) {
        $sum = 0;
        for ($index = 0; $index < $digit; $index++) {
            $sum += ((int) $cpf[$index]) * (($digit + 1) - $index);
        }
        $expected = ((10 * $sum) % 11) % 10;
        if ((int) $cpf[$digit] !== $expected) {
            return false;
        }
    }

    return true;
}

function pet_json_input(): array
{
    return request_json();
}

function pet_query_id(): int
{
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if (!$id || $id < 1) {
        json_response(['ok' => false, 'erro' => 'Informe um identificador valido.'], 422);
    }
    return (int) $id;
}

function pet_pagination(int $defaultLimit = 25, int $maxLimit = 100): array
{
    $page = max(1, (int) (filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1));
    $limit = max(1, (int) (filter_input(INPUT_GET, 'limit', FILTER_VALIDATE_INT) ?: $defaultLimit));
    $limit = min($limit, $maxLimit);

    return [
        'page' => $page,
        'limit' => $limit,
        'offset' => ($page - 1) * $limit,
    ];
}

function pet_validation_error(array $fields): void
{
    json_response([
        'ok' => false,
        'codigo' => 'DADOS_INVALIDOS',
        'erro' => 'Revise os campos destacados.',
        'campos' => $fields,
    ], 422);
}

function pet_execute(string $sql, string $types = '', array $values = []): mysqli_stmt
{
    $stmt = db()->prepare($sql);

    if ($types !== '') {
        $references = [];
        foreach ($values as $index => $value) {
            $references[$index] = &$values[$index];
        }
        $stmt->bind_param($types, ...$references);
    }

    $stmt->execute();
    return $stmt;
}

function pet_record_exists(string $table, int $id): bool
{
    $allowed = ['pet_tutores', 'pet_animais', 'pet_atendimentos', 'pet_internacoes', 'pet_veterinarios'];
    if (!in_array($table, $allowed, true)) {
        throw new InvalidArgumentException('Tabela nao permitida.');
    }

    $stmt = pet_execute("SELECT id FROM {$table} WHERE id = ? LIMIT 1", 'i', [$id]);
    return $stmt->get_result()->fetch_assoc() !== null;
}
