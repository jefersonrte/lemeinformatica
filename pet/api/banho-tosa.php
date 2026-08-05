<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/modules/estetica/functions.php';

$context = pet_boot_api();
pet_require_permission($context, 'ver_estetica');

try {
    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if ($method === 'GET') {
        pet_estetica_listar();
    } elseif ($method === 'POST') {
        pet_require_permission($context, 'gerenciar_estetica');
        pet_estetica_criar($context);
    } elseif ($method === 'PUT') {
        pet_require_permission($context, 'gerenciar_estetica');
        pet_estetica_atualizar($context);
    } else {
        json_response(['ok' => false, 'erro' => 'Metodo nao permitido.'], 405);
    }
} catch (Throwable $exception) {
    pet_api_exception($exception, 'Nao foi possivel salvar o agendamento de banho e tosa.');
}

function pet_estetica_payload(array $input): array
{
    $animalId = filter_var($input['animal_id'] ?? null, FILTER_VALIDATE_INT);
    $status = pet_text($input['status'] ?? 'agendado', 30);
    $startsAt = pet_nullable_datetime($input['inicio_em'] ?? null);
    $quote = pet_estetica_quote($input);
    $errors = [];

    if (!$animalId || !pet_record_exists('pet_animais', (int) $animalId)) {
        $errors['animal_id'] = 'Selecione um animal.';
    }
    if (!in_array($status, ['agendado', 'confirmado', 'em_atendimento', 'concluido', 'cancelado', 'nao_compareceu'], true)) {
        $errors['status'] = 'Status invalido.';
    }
    if ($startsAt === null) $errors['inicio_em'] = 'Informe a data e hora.';
    if ($errors) pet_validation_error($errors);

    $expectedEnd = pet_nullable_datetime($input['fim_previsto_em'] ?? null);
    if ($expectedEnd === null) {
        $expectedEnd = (new DateTimeImmutable((string) $startsAt))
            ->modify('+' . $quote['duracao_minutos'] . ' minutes')
            ->format('Y-m-d H:i:s');
    }
    $finishedAt = pet_nullable_datetime($input['fim_em'] ?? null);
    if ($status === 'concluido' && $finishedAt === null) {
        $finishedAt = date('Y-m-d H:i:s');
    }

    return [
        'animal_id' => (int) $animalId,
        'status' => $status,
        'inicio_em' => $startsAt,
        'fim_previsto_em' => $expectedEnd,
        'fim_em' => $finishedAt,
        'profissional_nome' => pet_nullable_text($input['profissional_nome'] ?? null, 140),
        'observacoes_entrada' => pet_nullable_text($input['observacoes_entrada'] ?? null, 10000),
        'observacoes_saida' => pet_nullable_text($input['observacoes_saida'] ?? null, 10000),
        'valor_total' => $quote['total'],
        'itens' => $quote['itens'],
    ];
}

function pet_estetica_listar(): void
{
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if ($id) {
        $record = pet_execute(
            "SELECT a.*, p.nome AS animal_nome, p.especie, p.raca,
                    t.id AS tutor_id, t.nome AS tutor_nome, t.telefone AS tutor_telefone
             FROM pet_banho_tosa_agendamentos a
             INNER JOIN pet_animais p ON p.id = a.animal_id
             INNER JOIN pet_tutores t ON t.id = p.tutor_id
             WHERE a.id = ? LIMIT 1",
            'i',
            [(int) $id]
        )->get_result()->fetch_assoc();
        if (!$record) json_response(['ok' => false, 'erro' => 'Agendamento nao encontrado.'], 404);
        $record['servicos'] = pet_execute(
            'SELECT servico_id, servico_nome, quantidade, preco_unitario, subtotal, observacoes
             FROM pet_banho_tosa_itens WHERE agendamento_id = ? ORDER BY id',
            'i',
            [(int) $id]
        )->get_result()->fetch_all(MYSQLI_ASSOC);
        json_response(['ok' => true, 'data' => $record]);
    }

    $status = pet_text($_GET['status'] ?? '', 30);
    $date = pet_nullable_date($_GET['data'] ?? null);
    $search = '%' . pet_text($_GET['q'] ?? '', 100) . '%';
    $records = pet_execute(
        "SELECT a.id, a.animal_id, a.status, a.inicio_em, a.fim_previsto_em, a.fim_em,
                a.profissional_nome, a.valor_total, a.observacoes_entrada, a.observacoes_saida,
                p.nome AS animal_nome, p.especie, p.raca, p.foto_caminho AS animal_foto,
                t.nome AS tutor_nome, t.telefone AS tutor_telefone,
                (SELECT GROUP_CONCAT(i.servico_nome ORDER BY i.id SEPARATOR ', ')
                 FROM pet_banho_tosa_itens i
                 WHERE i.agendamento_id = a.id) AS servicos_nomes
         FROM pet_banho_tosa_agendamentos a
         INNER JOIN pet_animais p ON p.id = a.animal_id
         INNER JOIN pet_tutores t ON t.id = p.tutor_id
         WHERE (? = '' OR a.status = ?)
           AND (? IS NULL OR DATE(a.inicio_em) = ?)
           AND (p.nome LIKE ? OR t.nome LIKE ? OR EXISTS (
                SELECT 1 FROM pet_banho_tosa_itens item
                WHERE item.agendamento_id = a.id AND item.servico_nome LIKE ?
           ))
         ORDER BY a.inicio_em DESC
         LIMIT 100",
        'sssssss',
        [$status, $status, $date, $date, $search, $search, $search]
    )->get_result()->fetch_all(MYSQLI_ASSOC);
    json_response(['ok' => true, 'data' => $records]);
}

function pet_estetica_criar(array $context): void
{
    $data = pet_estetica_payload(pet_json_input());
    $conn = db();
    $conn->begin_transaction();
    try {
        $userId = (int) $context['id'];
        $stmt = $conn->prepare(
            'INSERT INTO pet_banho_tosa_agendamentos
                (animal_id, status, inicio_em, fim_previsto_em, fim_em, profissional_nome,
                 observacoes_entrada, observacoes_saida, valor_total, criado_por)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->bind_param(
            'isssssssdi',
            $data['animal_id'], $data['status'], $data['inicio_em'], $data['fim_previsto_em'],
            $data['fim_em'], $data['profissional_nome'], $data['observacoes_entrada'],
            $data['observacoes_saida'], $data['valor_total'], $userId
        );
        $stmt->execute();
        $id = (int) $stmt->insert_id;
        pet_estetica_replace_items($conn, $id, $data['itens']);
        $conn->commit();
    } catch (Throwable $exception) {
        $conn->rollback();
        throw $exception;
    }

    pet_audit($context, 'criar', 'banho_tosa', $id, ['animal_id' => $data['animal_id'], 'valor_total' => $data['valor_total']]);
    json_response(['ok' => true, 'mensagem' => 'Agendamento criado.', 'data' => ['id' => $id]], 201);
}

function pet_estetica_atualizar(array $context): void
{
    $id = pet_query_id();
    if (!pet_record_exists('pet_banho_tosa_agendamentos', $id)) {
        json_response(['ok' => false, 'erro' => 'Agendamento nao encontrado.'], 404);
    }
    $data = pet_estetica_payload(pet_json_input());
    $conn = db();
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare(
            'UPDATE pet_banho_tosa_agendamentos SET animal_id = ?, status = ?, inicio_em = ?,
                fim_previsto_em = ?, fim_em = ?, profissional_nome = ?, observacoes_entrada = ?,
                observacoes_saida = ?, valor_total = ? WHERE id = ?'
        );
        $stmt->bind_param(
            'isssssssdi',
            $data['animal_id'], $data['status'], $data['inicio_em'], $data['fim_previsto_em'],
            $data['fim_em'], $data['profissional_nome'], $data['observacoes_entrada'],
            $data['observacoes_saida'], $data['valor_total'], $id
        );
        $stmt->execute();
        pet_estetica_replace_items($conn, $id, $data['itens']);
        $conn->commit();
    } catch (Throwable $exception) {
        $conn->rollback();
        throw $exception;
    }

    pet_audit($context, 'atualizar', 'banho_tosa', $id, ['status' => $data['status']]);
    json_response(['ok' => true, 'mensagem' => 'Agendamento atualizado.', 'data' => ['id' => $id]]);
}
