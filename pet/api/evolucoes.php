<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$context = pet_boot_api();
pet_require_permission($context, 'ver_prontuario');

try {
    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

    if ($method === 'GET') {
        $admissionId = (int) (filter_input(INPUT_GET, 'internacao_id', FILTER_VALIDATE_INT) ?: 0);
        if ($admissionId < 1) {
            pet_validation_error(['internacao_id' => 'Informe a internacao.']);
        }
        $records = pet_execute(
            'SELECT e.*, u.nome AS veterinario_nome, v.crmv, v.uf_crmv
             FROM pet_internacao_evolucoes e
             LEFT JOIN pet_veterinarios v ON v.id = e.veterinario_id
             LEFT JOIN usuarios_admin u ON u.id = v.usuario_id
             WHERE e.internacao_id = ?
             ORDER BY e.registrado_em DESC, e.id DESC',
            'i',
            [$admissionId]
        )->get_result()->fetch_all(MYSQLI_ASSOC);
        json_response(['ok' => true, 'data' => $records]);
    }

    if ($method !== 'POST') {
        json_response(['ok' => false, 'erro' => 'Metodo nao permitido.'], 405);
    }

    pet_require_permission($context, 'editar_prontuario');
    $vetId = pet_require_veterinarian($context);
    $input = pet_json_input();
    $admissionId = filter_var($input['internacao_id'] ?? null, FILTER_VALIDATE_INT);
    $registeredAt = pet_nullable_datetime($input['registrado_em'] ?? null);
    $notes = pet_text($input['observacoes'] ?? '', 10000);
    $errors = [];

    if (!$admissionId || !pet_record_exists('pet_internacoes', (int) $admissionId)) {
        $errors['internacao_id'] = 'Internacao invalida.';
    }
    if ($registeredAt === null) {
        $errors['registrado_em'] = 'Informe data e hora da evolucao.';
    }
    if ($notes === '') {
        $errors['observacoes'] = 'Descreva a evolucao clinica.';
    }
    if ($errors) {
        pet_validation_error($errors);
    }

    $stmt = pet_execute(
        'INSERT INTO pet_internacao_evolucoes
            (internacao_id, veterinario_id, registrado_em, peso_kg, temperatura_c,
             frequencia_cardiaca, frequencia_respiratoria, glicemia_mg_dl,
             pressao_arterial, alimentacao, eliminacoes, medicacoes, procedimentos,
             observacoes, criado_por)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        'iisddiidssssssi',
        [
            (int) $admissionId, $vetId ?: null, $registeredAt,
            pet_nullable_decimal($input['peso_kg'] ?? null, 0, 9999),
            pet_nullable_decimal($input['temperatura_c'] ?? null, 20, 50),
            filter_var($input['frequencia_cardiaca'] ?? null, FILTER_VALIDATE_INT) ?: null,
            filter_var($input['frequencia_respiratoria'] ?? null, FILTER_VALIDATE_INT) ?: null,
            pet_nullable_decimal($input['glicemia_mg_dl'] ?? null, 0, 2000),
            pet_nullable_text($input['pressao_arterial'] ?? null, 40),
            pet_nullable_text($input['alimentacao'] ?? null, 255),
            pet_nullable_text($input['eliminacoes'] ?? null, 255),
            pet_nullable_text($input['medicacoes'] ?? null, 10000),
            pet_nullable_text($input['procedimentos'] ?? null, 10000),
            $notes,
            (int) $context['id'],
        ]
    );

    $id = (int) $stmt->insert_id;
    pet_audit($context, 'criar', 'evolucao_internacao', $id, ['internacao_id' => (int) $admissionId]);
    json_response(['ok' => true, 'mensagem' => 'Evolucao registrada.', 'data' => ['id' => $id]], 201);
} catch (Throwable $exception) {
    pet_api_exception($exception, 'Nao foi possivel salvar a evolucao.');
}
