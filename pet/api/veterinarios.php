<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$context = pet_boot_api();
pet_require_permission($context, 'ver_prontuario');

try {
    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

    if ($method === 'GET') {
        pet_veterinarios_listar($context);
    } elseif ($method === 'POST') {
        pet_require_permission($context, 'gerenciar_equipe');
        pet_veterinarios_criar($context);
    } elseif ($method === 'PUT') {
        pet_require_permission($context, 'gerenciar_equipe');
        pet_veterinarios_atualizar($context);
    } else {
        json_response(['ok' => false, 'erro' => 'Metodo nao permitido.'], 405);
    }
} catch (Throwable $exception) {
    pet_api_exception($exception, 'Nao foi possivel salvar o veterinario.');
}

function pet_veterinario_payload(array $input): array
{
    $userId = filter_var($input['usuario_id'] ?? null, FILTER_VALIDATE_INT);
    $crmv = strtoupper(pet_text($input['crmv'] ?? '', 30));
    $uf = strtoupper(pet_text($input['uf_crmv'] ?? '', 2));
    $errors = [];

    if (!$userId) {
        $errors['usuario_id'] = 'Selecione um usuario.';
    } else {
        $exists = pet_execute(
            "SELECT id FROM usuarios_admin WHERE id = ? AND ativo = 1 AND perfil IN ('admin', 'operador') LIMIT 1",
            'i',
            [(int) $userId]
        )->get_result()->fetch_assoc();
        if (!$exists) {
            $errors['usuario_id'] = 'Usuario inativo ou sem perfil compativel.';
        }
    }
    if ($crmv === '') {
        $errors['crmv'] = 'Informe o CRMV.';
    }
    if (!preg_match('/^[A-Z]{2}$/', $uf)) {
        $errors['uf_crmv'] = 'Informe a UF do CRMV.';
    }

    if ($errors) {
        pet_validation_error($errors);
    }

    return [
        'usuario_id' => (int) $userId,
        'crmv' => $crmv,
        'uf_crmv' => $uf,
        'especialidade' => pet_nullable_text($input['especialidade'] ?? null, 120),
        'telefone_profissional' => (($phone = pet_digits($input['telefone_profissional'] ?? '', 20)) === '') ? null : $phone,
        'biografia' => pet_nullable_text($input['biografia'] ?? null, 500),
        'ativo' => array_key_exists('ativo', $input) ? pet_bool($input['ativo']) : 1,
    ];
}

function pet_veterinarios_listar(array $context): void
{
    if (isset($_GET['usuarios'])) {
        pet_require_permission($context, 'gerenciar_equipe');
        $records = db()->query(
            "SELECT u.id, u.nome, u.email, u.perfil
             FROM usuarios_admin u
             LEFT JOIN pet_veterinarios v ON v.usuario_id = u.id
             WHERE u.ativo = 1
               AND u.perfil IN ('admin', 'operador')
               AND v.id IS NULL
             ORDER BY u.nome"
        )->fetch_all(MYSQLI_ASSOC);
        json_response(['ok' => true, 'data' => $records]);
    }

    $records = db()->query(
        'SELECT v.id, v.usuario_id, v.crmv, v.uf_crmv, v.especialidade,
                v.telefone_profissional, v.biografia, v.foto_caminho, v.ativo,
                u.nome, u.email
         FROM pet_veterinarios v
         INNER JOIN usuarios_admin u ON u.id = v.usuario_id
         ORDER BY v.ativo DESC, u.nome ASC'
    )->fetch_all(MYSQLI_ASSOC);
    json_response(['ok' => true, 'data' => $records]);
}

function pet_veterinarios_criar(array $context): void
{
    $data = pet_veterinario_payload(pet_json_input());
    $stmt = pet_execute(
        'INSERT INTO pet_veterinarios
            (usuario_id, crmv, uf_crmv, especialidade, telefone_profissional, biografia, ativo)
         VALUES (?, ?, ?, ?, ?, ?, ?)',
        'isssssi',
        [
            $data['usuario_id'], $data['crmv'], $data['uf_crmv'], $data['especialidade'],
            $data['telefone_profissional'], $data['biografia'], $data['ativo'],
        ]
    );
    $id = (int) $stmt->insert_id;
    pet_audit($context, 'criar', 'veterinario', $id, ['usuario_id' => $data['usuario_id']]);
    json_response(['ok' => true, 'mensagem' => 'Veterinario vinculado.', 'data' => ['id' => $id]], 201);
}

function pet_veterinarios_atualizar(array $context): void
{
    $id = pet_query_id();
    $data = pet_veterinario_payload(pet_json_input());
    pet_execute(
        'UPDATE pet_veterinarios SET
            usuario_id = ?, crmv = ?, uf_crmv = ?, especialidade = ?,
            telefone_profissional = ?, biografia = ?, ativo = ?
         WHERE id = ?',
        'isssssii',
        [
            $data['usuario_id'], $data['crmv'], $data['uf_crmv'], $data['especialidade'],
            $data['telefone_profissional'], $data['biografia'], $data['ativo'], $id,
        ]
    );
    pet_audit($context, 'atualizar', 'veterinario', $id);
    json_response(['ok' => true, 'mensagem' => 'Veterinario atualizado.', 'data' => ['id' => $id]]);
}
