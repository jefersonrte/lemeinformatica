<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$context = pet_boot_api();
pet_require_permission($context, 'ver_prontuario');

try {
    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

    if ($method === 'GET') {
        pet_internacoes_listar();
    } elseif ($method === 'POST') {
        pet_require_permission($context, 'gerenciar_internacao');
        pet_internacoes_criar($context);
    } elseif ($method === 'PUT') {
        pet_require_permission($context, 'gerenciar_internacao');
        pet_internacoes_atualizar($context);
    } else {
        json_response(['ok' => false, 'erro' => 'Metodo nao permitido.'], 405);
    }
} catch (Throwable $exception) {
    pet_api_exception($exception, 'Nao foi possivel salvar a internacao.');
}

function pet_internacao_payload(array $input, array $context): array
{
    $animalId = filter_var($input['animal_id'] ?? null, FILTER_VALIDATE_INT);
    $status = pet_text($input['status'] ?? 'ativa', 30);
    $risk = pet_text($input['classificacao_risco'] ?? 'moderado', 30);
    $startsAt = pet_nullable_datetime($input['entrada_em'] ?? null);
    $reason = pet_text($input['motivo'] ?? '', 500);
    $errors = [];

    if (!$animalId || !pet_record_exists('pet_animais', (int) $animalId)) {
        $errors['animal_id'] = 'Selecione um animal.';
    }
    if (!in_array($status, ['ativa', 'alta', 'transferencia', 'obito', 'cancelada'], true)) {
        $errors['status'] = 'Status invalido.';
    }
    if (!in_array($risk, ['baixo', 'moderado', 'alto', 'critico'], true)) {
        $errors['classificacao_risco'] = 'Classificacao de risco invalida.';
    }
    if ($startsAt === null) {
        $errors['entrada_em'] = 'Informe a entrada.';
    }
    if ($reason === '') {
        $errors['motivo'] = 'Informe o motivo da internacao.';
    }

    $vetId = null;
    if ($context['veterinario_id'] !== null) {
        $vetId = (int) $context['veterinario_id'];
    } else {
        $selected = filter_var($input['veterinario_responsavel_id'] ?? null, FILTER_VALIDATE_INT);
        $vetId = $selected ? (int) $selected : null;
    }
    if ($vetId !== null && !pet_record_exists('pet_veterinarios', $vetId)) {
        $errors['veterinario_responsavel_id'] = 'Veterinario invalido.';
    }

    $careId = filter_var($input['atendimento_origem_id'] ?? null, FILTER_VALIDATE_INT);
    if ($careId && !pet_record_exists('pet_atendimentos', (int) $careId)) {
        $errors['atendimento_origem_id'] = 'Atendimento de origem invalido.';
    }

    $dischargedAt = pet_nullable_datetime($input['saida_em'] ?? null);
    if ($status !== 'ativa' && $status !== 'cancelada' && $dischargedAt === null) {
        $errors['saida_em'] = 'Informe a data de saida.';
    }

    $hasClinicalData = pet_text($input['diagnostico_inicial'] ?? '', 10000) !== ''
        || pet_text($input['plano_cuidados'] ?? '', 10000) !== ''
        || pet_text($input['resumo_alta'] ?? '', 10000) !== '';
    if ($hasClinicalData && !pet_can($context, 'editar_prontuario')) {
        json_response([
            'ok' => false,
            'erro' => 'Somente veterinarios vinculados ou administradores podem registrar informacoes clinicas.'
        ], 403);
    }

    if ($errors) {
        pet_validation_error($errors);
    }

    return [
        'animal_id' => (int) $animalId,
        'veterinario_responsavel_id' => $vetId,
        'atendimento_origem_id' => $careId ? (int) $careId : null,
        'status' => $status,
        'entrada_em' => $startsAt,
        'previsao_alta_em' => pet_nullable_datetime($input['previsao_alta_em'] ?? null),
        'saida_em' => $dischargedAt,
        'setor' => pet_nullable_text($input['setor'] ?? null, 80),
        'leito' => pet_nullable_text($input['leito'] ?? null, 40),
        'classificacao_risco' => $risk,
        'motivo' => $reason,
        'diagnostico_inicial' => pet_nullable_text($input['diagnostico_inicial'] ?? null, 10000),
        'plano_cuidados' => pet_nullable_text($input['plano_cuidados'] ?? null, 10000),
        'resumo_alta' => pet_nullable_text($input['resumo_alta'] ?? null, 10000),
    ];
}

function pet_internacoes_listar(): void
{
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if ($id) {
        $record = pet_execute(
            "SELECT i.*, p.nome AS animal_nome, p.especie, p.raca, p.foto_caminho AS animal_foto,
                    t.id AS tutor_id, t.nome AS tutor_nome, t.telefone AS tutor_telefone,
                    u.nome AS veterinario_nome
             FROM pet_internacoes i
             INNER JOIN pet_animais p ON p.id = i.animal_id
             INNER JOIN pet_tutores t ON t.id = p.tutor_id
             LEFT JOIN pet_veterinarios v ON v.id = i.veterinario_responsavel_id
             LEFT JOIN usuarios_admin u ON u.id = v.usuario_id
             WHERE i.id = ?
             LIMIT 1",
            'i',
            [(int) $id]
        )->get_result()->fetch_assoc();
        if (!$record) {
            json_response(['ok' => false, 'erro' => 'Internacao nao encontrada.'], 404);
        }
        json_response(['ok' => true, 'data' => $record]);
    }

    $status = pet_text($_GET['status'] ?? '', 30);
    $animalId = (int) (filter_input(INPUT_GET, 'animal_id', FILTER_VALIDATE_INT) ?: 0);
    $records = pet_execute(
        "SELECT i.id, i.animal_id, i.status, i.entrada_em, i.previsao_alta_em, i.saida_em,
                i.setor, i.leito, i.classificacao_risco, i.motivo,
                p.nome AS animal_nome, p.especie, p.foto_caminho AS animal_foto,
                t.nome AS tutor_nome, COALESCE(u.nome, 'A definir') AS veterinario_nome,
                (SELECT COUNT(*) FROM pet_internacao_evolucoes e WHERE e.internacao_id = i.id) AS total_evolucoes
         FROM pet_internacoes i
         INNER JOIN pet_animais p ON p.id = i.animal_id
         INNER JOIN pet_tutores t ON t.id = p.tutor_id
         LEFT JOIN pet_veterinarios v ON v.id = i.veterinario_responsavel_id
         LEFT JOIN usuarios_admin u ON u.id = v.usuario_id
         WHERE (? = '' OR i.status = ?)
           AND (? = 0 OR i.animal_id = ?)
         ORDER BY FIELD(i.status, 'ativa', 'alta', 'transferencia', 'obito', 'cancelada'),
                  i.entrada_em DESC
         LIMIT 100",
        'ssii',
        [$status, $status, $animalId, $animalId]
    )->get_result()->fetch_all(MYSQLI_ASSOC);

    json_response(['ok' => true, 'data' => $records]);
}

function pet_internacoes_criar(array $context): void
{
    $data = pet_internacao_payload(pet_json_input(), $context);

    $active = pet_execute(
        "SELECT id FROM pet_internacoes WHERE animal_id = ? AND status = 'ativa' LIMIT 1",
        'i',
        [$data['animal_id']]
    )->get_result()->fetch_assoc();
    if ($active) {
        json_response(['ok' => false, 'erro' => 'Este animal ja possui uma internacao ativa.'], 409);
    }

    $userId = (int) $context['id'];
    $stmt = pet_execute(
        'INSERT INTO pet_internacoes
            (animal_id, veterinario_responsavel_id, atendimento_origem_id, status,
             entrada_em, previsao_alta_em, saida_em, setor, leito, classificacao_risco,
             motivo, diagnostico_inicial, plano_cuidados, resumo_alta, criado_por)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        'iiisssssssssssi',
        [
            $data['animal_id'], $data['veterinario_responsavel_id'], $data['atendimento_origem_id'],
            $data['status'], $data['entrada_em'], $data['previsao_alta_em'], $data['saida_em'],
            $data['setor'], $data['leito'], $data['classificacao_risco'], $data['motivo'],
            $data['diagnostico_inicial'], $data['plano_cuidados'], $data['resumo_alta'], $userId,
        ]
    );
    $id = (int) $stmt->insert_id;
    pet_audit($context, 'criar', 'internacao', $id, ['animal_id' => $data['animal_id']]);
    json_response(['ok' => true, 'mensagem' => 'Internacao aberta.', 'data' => ['id' => $id]], 201);
}

function pet_internacoes_atualizar(array $context): void
{
    $id = pet_query_id();
    if (!pet_record_exists('pet_internacoes', $id)) {
        json_response(['ok' => false, 'erro' => 'Internacao nao encontrada.'], 404);
    }

    $data = pet_internacao_payload(pet_json_input(), $context);

    if (!pet_can($context, 'editar_prontuario')) {
        pet_execute(
            'UPDATE pet_internacoes SET
                animal_id = ?, veterinario_responsavel_id = ?, atendimento_origem_id = ?,
                status = ?, entrada_em = ?, previsao_alta_em = ?, saida_em = ?, setor = ?,
                leito = ?, classificacao_risco = ?, motivo = ?
             WHERE id = ?',
            'iiissssssssi',
            [
                $data['animal_id'], $data['veterinario_responsavel_id'], $data['atendimento_origem_id'],
                $data['status'], $data['entrada_em'], $data['previsao_alta_em'], $data['saida_em'],
                $data['setor'], $data['leito'], $data['classificacao_risco'], $data['motivo'], $id,
            ]
        );
        pet_audit($context, 'atualizar_operacao', 'internacao', $id, ['status' => $data['status']]);
        json_response(['ok' => true, 'mensagem' => 'Internacao atualizada.', 'data' => ['id' => $id]]);
    }

    pet_execute(
        'UPDATE pet_internacoes SET
            animal_id = ?, veterinario_responsavel_id = ?, atendimento_origem_id = ?,
            status = ?, entrada_em = ?, previsao_alta_em = ?, saida_em = ?, setor = ?,
            leito = ?, classificacao_risco = ?, motivo = ?, diagnostico_inicial = ?,
            plano_cuidados = ?, resumo_alta = ?
         WHERE id = ?',
        'iiisssssssssssi',
        [
            $data['animal_id'], $data['veterinario_responsavel_id'], $data['atendimento_origem_id'],
            $data['status'], $data['entrada_em'], $data['previsao_alta_em'], $data['saida_em'],
            $data['setor'], $data['leito'], $data['classificacao_risco'], $data['motivo'],
            $data['diagnostico_inicial'], $data['plano_cuidados'], $data['resumo_alta'], $id,
        ]
    );
    pet_audit($context, 'atualizar', 'internacao', $id, ['status' => $data['status']]);
    json_response(['ok' => true, 'mensagem' => 'Internacao atualizada.', 'data' => ['id' => $id]]);
}
