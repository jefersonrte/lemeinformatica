<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$context = pet_boot_api();
pet_require_permission($context, 'ver_prontuario');

try {
    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

    if ($method === 'GET') {
        pet_atendimentos_listar();
    } elseif ($method === 'POST') {
        pet_atendimentos_criar($context);
    } elseif ($method === 'PUT') {
        pet_atendimentos_atualizar($context);
    } else {
        json_response(['ok' => false, 'erro' => 'Metodo nao permitido.'], 405);
    }
} catch (Throwable $exception) {
    pet_api_exception($exception, 'Nao foi possivel salvar o atendimento.');
}

function pet_atendimento_payload(array $input, array $context): array
{
    $animalId = filter_var($input['animal_id'] ?? null, FILTER_VALIDATE_INT);
    $type = pet_text($input['tipo'] ?? 'consulta', 30);
    $status = pet_text($input['status'] ?? 'agendado', 30);
    $startsAt = pet_nullable_datetime($input['inicio_em'] ?? null);
    $endsAt = pet_nullable_datetime($input['fim_em'] ?? null);
    $reason = pet_text($input['motivo'] ?? '', 500);
    $allowedTypes = ['consulta', 'retorno', 'emergencia', 'vacina', 'exame', 'procedimento'];
    $allowedStatuses = ['agendado', 'em_atendimento', 'concluido', 'cancelado'];
    $errors = [];

    if (!$animalId || !pet_record_exists('pet_animais', (int) $animalId)) {
        $errors['animal_id'] = 'Selecione um animal.';
    }
    if (!in_array($type, $allowedTypes, true)) {
        $errors['tipo'] = 'Tipo de atendimento invalido.';
    }
    if (!in_array($status, $allowedStatuses, true)) {
        $errors['status'] = 'Status invalido.';
    }
    if ($startsAt === null) {
        $errors['inicio_em'] = 'Informe data e hora.';
    }
    if ($reason === '') {
        $errors['motivo'] = 'Informe o motivo do atendimento.';
    }

    $vetId = null;
    if ($context['veterinario_id'] !== null) {
        $vetId = (int) $context['veterinario_id'];
    } elseif ($context['perfil'] === 'admin') {
        $selectedVet = filter_var($input['veterinario_id'] ?? null, FILTER_VALIDATE_INT);
        $vetId = $selectedVet ? (int) $selectedVet : null;
    }

    if ($vetId !== null && !pet_record_exists('pet_veterinarios', $vetId)) {
        $errors['veterinario_id'] = 'Veterinario invalido.';
    }
    if (in_array($status, ['em_atendimento', 'concluido'], true) && $vetId === null) {
        $errors['veterinario_id'] = 'Defina o veterinario responsavel.';
    }

    $clinicalFields = [
        'anamnese', 'exame_clinico', 'diagnostico', 'conduta', 'prescricao', 'exames_solicitados',
        'peso_kg', 'temperatura_c', 'frequencia_cardiaca', 'frequencia_respiratoria', 'mucosas', 'hidratacao',
    ];
    $hasClinicalData = false;
    foreach ($clinicalFields as $field) {
        if (($input[$field] ?? '') !== '' && ($input[$field] ?? null) !== null) {
            $hasClinicalData = true;
            break;
        }
    }

    if (($hasClinicalData || in_array($status, ['em_atendimento', 'concluido'], true))
        && !pet_can($context, 'editar_prontuario')) {
        json_response([
            'ok' => false,
            'erro' => 'Somente veterinarios vinculados ou administradores podem registrar dados clinicos.'
        ], 403);
    }

    if ($status === 'concluido'
        && (pet_text($input['diagnostico'] ?? '', 5000) === '' || pet_text($input['conduta'] ?? '', 5000) === '')) {
        $errors['diagnostico'] = 'Atendimento concluido exige diagnostico e conduta.';
    }

    if ($errors) {
        pet_validation_error($errors);
    }

    return [
        'animal_id' => (int) $animalId,
        'veterinario_id' => $vetId,
        'tipo' => $type,
        'status' => $status,
        'inicio_em' => $startsAt,
        'fim_em' => $endsAt,
        'motivo' => $reason,
        'anamnese' => pet_nullable_text($input['anamnese'] ?? null, 10000),
        'exame_clinico' => pet_nullable_text($input['exame_clinico'] ?? null, 10000),
        'peso_kg' => pet_nullable_decimal($input['peso_kg'] ?? null, 0, 9999),
        'temperatura_c' => pet_nullable_decimal($input['temperatura_c'] ?? null, 20, 50),
        'frequencia_cardiaca' => (($value = filter_var($input['frequencia_cardiaca'] ?? null, FILTER_VALIDATE_INT)) && $value > 0) ? (int) $value : null,
        'frequencia_respiratoria' => (($value = filter_var($input['frequencia_respiratoria'] ?? null, FILTER_VALIDATE_INT)) && $value > 0) ? (int) $value : null,
        'mucosas' => pet_nullable_text($input['mucosas'] ?? null, 100),
        'hidratacao' => pet_nullable_text($input['hidratacao'] ?? null, 100),
        'diagnostico' => pet_nullable_text($input['diagnostico'] ?? null, 10000),
        'conduta' => pet_nullable_text($input['conduta'] ?? null, 10000),
        'prescricao' => pet_nullable_text($input['prescricao'] ?? null, 10000),
        'exames_solicitados' => pet_nullable_text($input['exames_solicitados'] ?? null, 10000),
        'retorno_em' => pet_nullable_date($input['retorno_em'] ?? null),
    ];
}

function pet_atendimentos_listar(): void
{
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if ($id) {
        $record = pet_execute(
            "SELECT a.*, p.nome AS animal_nome, p.especie, p.raca, p.foto_caminho AS animal_foto,
                    t.id AS tutor_id, t.nome AS tutor_nome, t.telefone AS tutor_telefone,
                    u.nome AS veterinario_nome, v.crmv, v.uf_crmv
             FROM pet_atendimentos a
             INNER JOIN pet_animais p ON p.id = a.animal_id
             INNER JOIN pet_tutores t ON t.id = p.tutor_id
             LEFT JOIN pet_veterinarios v ON v.id = a.veterinario_id
             LEFT JOIN usuarios_admin u ON u.id = v.usuario_id
             WHERE a.id = ?
             LIMIT 1",
            'i',
            [(int) $id]
        )->get_result()->fetch_assoc();
        if (!$record) {
            json_response(['ok' => false, 'erro' => 'Atendimento nao encontrado.'], 404);
        }
        json_response(['ok' => true, 'data' => $record]);
    }

    $pagination = pet_pagination(20, 100);
    $animalId = (int) (filter_input(INPUT_GET, 'animal_id', FILTER_VALIDATE_INT) ?: 0);
    $status = pet_text($_GET['status'] ?? '', 30);
    $search = '%' . pet_text($_GET['q'] ?? '', 100) . '%';

    $records = pet_execute(
        "SELECT a.id, a.animal_id, a.veterinario_id, a.tipo, a.status, a.inicio_em,
                a.fim_em, a.motivo, a.diagnostico,
                p.nome AS animal_nome, p.especie, p.foto_caminho AS animal_foto,
                t.nome AS tutor_nome,
                COALESCE(u.nome, 'A definir') AS veterinario_nome
         FROM pet_atendimentos a
         INNER JOIN pet_animais p ON p.id = a.animal_id
         INNER JOIN pet_tutores t ON t.id = p.tutor_id
         LEFT JOIN pet_veterinarios v ON v.id = a.veterinario_id
         LEFT JOIN usuarios_admin u ON u.id = v.usuario_id
         WHERE (? = 0 OR a.animal_id = ?)
           AND (? = '' OR a.status = ?)
           AND (p.nome LIKE ? OR t.nome LIKE ? OR a.motivo LIKE ?)
         ORDER BY a.inicio_em DESC
         LIMIT ? OFFSET ?",
        'iisssssii',
        [
            $animalId, $animalId, $status, $status, $search, $search, $search,
            $pagination['limit'], $pagination['offset'],
        ]
    )->get_result()->fetch_all(MYSQLI_ASSOC);

    json_response(['ok' => true, 'data' => $records, 'meta' => $pagination]);
}

function pet_atendimentos_criar(array $context): void
{
    $data = pet_atendimento_payload(pet_json_input(), $context);
    $userId = (int) $context['id'];
    $stmt = pet_execute(
        'INSERT INTO pet_atendimentos
            (animal_id, veterinario_id, tipo, status, inicio_em, fim_em, motivo, anamnese,
             exame_clinico, peso_kg, temperatura_c, frequencia_cardiaca,
             frequencia_respiratoria, mucosas, hidratacao, diagnostico, conduta,
             prescricao, exames_solicitados, retorno_em, criado_por)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        'iisssssssddsssssssssi',
        [
            $data['animal_id'], $data['veterinario_id'], $data['tipo'], $data['status'],
            $data['inicio_em'], $data['fim_em'], $data['motivo'], $data['anamnese'],
            $data['exame_clinico'], $data['peso_kg'], $data['temperatura_c'],
            $data['frequencia_cardiaca'], $data['frequencia_respiratoria'], $data['mucosas'],
            $data['hidratacao'], $data['diagnostico'], $data['conduta'], $data['prescricao'],
            $data['exames_solicitados'], $data['retorno_em'], $userId,
        ]
    );
    $id = (int) $stmt->insert_id;
    pet_audit($context, 'criar', 'atendimento', $id, ['animal_id' => $data['animal_id'], 'status' => $data['status']]);
    json_response(['ok' => true, 'mensagem' => 'Atendimento registrado.', 'data' => ['id' => $id]], 201);
}

function pet_atendimentos_atualizar(array $context): void
{
    $id = pet_query_id();
    if (!pet_record_exists('pet_atendimentos', $id)) {
        json_response(['ok' => false, 'erro' => 'Atendimento nao encontrado.'], 404);
    }

    $data = pet_atendimento_payload(pet_json_input(), $context);

    if (!pet_can($context, 'editar_prontuario')) {
        if (!in_array($data['status'], ['agendado', 'cancelado'], true)) {
            json_response([
                'ok' => false,
                'erro' => 'Somente veterinarios vinculados ou administradores podem iniciar ou concluir atendimentos.'
            ], 403);
        }

        pet_execute(
            'UPDATE pet_atendimentos SET
                animal_id = ?, tipo = ?, status = ?, inicio_em = ?, fim_em = ?, motivo = ?
             WHERE id = ?',
            'isssssi',
            [
                $data['animal_id'], $data['tipo'], $data['status'], $data['inicio_em'],
                $data['fim_em'], $data['motivo'], $id,
            ]
        );
        pet_audit($context, 'atualizar_agenda', 'atendimento', $id, ['status' => $data['status']]);
        json_response(['ok' => true, 'mensagem' => 'Agenda atualizada.', 'data' => ['id' => $id]]);
    }

    pet_execute(
        'UPDATE pet_atendimentos SET
            animal_id = ?, veterinario_id = ?, tipo = ?, status = ?, inicio_em = ?, fim_em = ?,
            motivo = ?, anamnese = ?, exame_clinico = ?, peso_kg = ?, temperatura_c = ?,
            frequencia_cardiaca = ?, frequencia_respiratoria = ?, mucosas = ?, hidratacao = ?,
            diagnostico = ?, conduta = ?, prescricao = ?, exames_solicitados = ?, retorno_em = ?
         WHERE id = ?',
        'iisssssssddsssssssssi',
        [
            $data['animal_id'], $data['veterinario_id'], $data['tipo'], $data['status'],
            $data['inicio_em'], $data['fim_em'], $data['motivo'], $data['anamnese'],
            $data['exame_clinico'], $data['peso_kg'], $data['temperatura_c'],
            $data['frequencia_cardiaca'], $data['frequencia_respiratoria'], $data['mucosas'],
            $data['hidratacao'], $data['diagnostico'], $data['conduta'], $data['prescricao'],
            $data['exames_solicitados'], $data['retorno_em'], $id,
        ]
    );
    pet_audit($context, 'atualizar', 'atendimento', $id, ['status' => $data['status']]);
    json_response(['ok' => true, 'mensagem' => 'Atendimento atualizado.', 'data' => ['id' => $id]]);
}
