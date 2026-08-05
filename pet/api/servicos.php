<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$context = pet_boot_api();
pet_require_permission($context, 'ver_estetica');

try {
    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if ($method === 'GET') {
        pet_servicos_listar();
    } elseif ($method === 'POST') {
        pet_require_permission($context, 'gerenciar_estetica');
        pet_servicos_criar($context);
    } elseif ($method === 'PUT') {
        pet_require_permission($context, 'gerenciar_estetica');
        pet_servicos_atualizar($context);
    } else {
        json_response(['ok' => false, 'erro' => 'Metodo nao permitido.'], 405);
    }
} catch (Throwable $exception) {
    pet_api_exception($exception, 'Nao foi possivel salvar o servico.');
}

function pet_servico_payload(array $input): array
{
    $code = strtoupper(pet_text($input['codigo'] ?? '', 50));
    $code = (string) preg_replace('/[^A-Z0-9_-]/', '', $code);
    $name = pet_text($input['nome'] ?? '', 140);
    $category = pet_text($input['categoria'] ?? 'banho', 30);
    $duration = filter_var($input['duracao_minutos'] ?? null, FILTER_VALIDATE_INT);
    $price = pet_nullable_decimal($input['preco'] ?? null, 0, 9999999);
    $errors = [];

    if ($code === '') $errors['codigo'] = 'Informe um codigo unico.';
    if ($name === '') $errors['nome'] = 'Informe o nome do servico.';
    if (!in_array($category, ['banho', 'tosa', 'spa', 'higiene', 'outro'], true)) {
        $errors['categoria'] = 'Categoria invalida.';
    }
    if (!$duration || $duration < 5 || $duration > 1440) {
        $errors['duracao_minutos'] = 'A duracao deve ficar entre 5 e 1440 minutos.';
    }
    if ($price === null) $errors['preco'] = 'Informe um preco valido.';
    if ($errors) pet_validation_error($errors);

    return [
        'codigo' => $code,
        'nome' => $name,
        'categoria' => $category,
        'duracao_minutos' => (int) $duration,
        'preco' => $price,
        'descricao' => pet_nullable_text($input['descricao'] ?? null, 500),
        'ativo' => pet_bool($input['ativo'] ?? true),
    ];
}

function pet_servicos_listar(): void
{
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if ($id) {
        $record = pet_execute('SELECT * FROM pet_servicos WHERE id = ? LIMIT 1', 'i', [(int) $id])
            ->get_result()->fetch_assoc();
        if (!$record) json_response(['ok' => false, 'erro' => 'Servico nao encontrado.'], 404);
        json_response(['ok' => true, 'data' => $record]);
    }

    $includeInactive = pet_bool($_GET['inativos'] ?? false);
    $records = pet_execute(
        'SELECT id, codigo, nome, categoria, duracao_minutos, preco, descricao, ativo
         FROM pet_servicos
         WHERE (? = 1 OR ativo = 1)
         ORDER BY ativo DESC, categoria, nome',
        'i',
        [$includeInactive]
    )->get_result()->fetch_all(MYSQLI_ASSOC);
    json_response(['ok' => true, 'data' => $records]);
}

function pet_servicos_criar(array $context): void
{
    $data = pet_servico_payload(pet_json_input());
    $userId = (int) $context['id'];
    $stmt = pet_execute(
        'INSERT INTO pet_servicos
            (codigo, nome, categoria, duracao_minutos, preco, descricao, ativo, criado_por)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
        'sssidsii',
        [$data['codigo'], $data['nome'], $data['categoria'], $data['duracao_minutos'], $data['preco'], $data['descricao'], $data['ativo'], $userId]
    );
    $id = (int) $stmt->insert_id;
    pet_audit($context, 'criar', 'servico_estetica', $id);
    json_response(['ok' => true, 'mensagem' => 'Servico criado.', 'data' => ['id' => $id]], 201);
}

function pet_servicos_atualizar(array $context): void
{
    $id = pet_query_id();
    if (!pet_record_exists('pet_servicos', $id)) {
        json_response(['ok' => false, 'erro' => 'Servico nao encontrado.'], 404);
    }
    $data = pet_servico_payload(pet_json_input());
    pet_execute(
        'UPDATE pet_servicos SET codigo = ?, nome = ?, categoria = ?, duracao_minutos = ?,
            preco = ?, descricao = ?, ativo = ? WHERE id = ?',
        'sssidsii',
        [$data['codigo'], $data['nome'], $data['categoria'], $data['duracao_minutos'], $data['preco'], $data['descricao'], $data['ativo'], $id]
    );
    pet_audit($context, 'atualizar', 'servico_estetica', $id);
    json_response(['ok' => true, 'mensagem' => 'Servico atualizado.', 'data' => ['id' => $id]]);
}
