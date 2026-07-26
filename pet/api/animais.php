<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$context = pet_boot_api();
pet_require_permission($context, 'ver_cadastros');

try {
    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

    if ($method === 'GET') {
        pet_animais_listar();
    } elseif ($method === 'POST') {
        pet_require_permission($context, 'editar_cadastros');
        pet_animais_criar($context);
    } elseif ($method === 'PUT') {
        pet_require_permission($context, 'editar_cadastros');
        pet_animais_atualizar($context);
    } elseif ($method === 'DELETE') {
        pet_require_permission($context, 'editar_cadastros');
        pet_animais_desativar($context);
    } else {
        json_response(['ok' => false, 'erro' => 'Metodo nao permitido.'], 405);
    }
} catch (Throwable $exception) {
    pet_api_exception($exception, 'Nao foi possivel salvar o animal.');
}

function pet_animal_payload(array $input): array
{
    $tutorId = filter_var($input['tutor_id'] ?? null, FILTER_VALIDATE_INT);
    $name = pet_text($input['nome'] ?? '', 120);
    $species = pet_text($input['especie'] ?? '', 60);
    $sex = pet_text($input['sexo'] ?? 'indefinido', 20);
    $size = pet_text($input['porte'] ?? 'nao_aplicavel', 20);
    $birthDate = pet_nullable_date($input['data_nascimento'] ?? null);
    $weight = pet_nullable_decimal($input['peso_kg'] ?? null, 0, 9999);
    $allowedSex = ['macho', 'femea', 'indefinido'];
    $allowedSizes = ['mini', 'pequeno', 'medio', 'grande', 'gigante', 'nao_aplicavel'];
    $errors = [];

    if (!$tutorId || !pet_record_exists('pet_tutores', (int) $tutorId)) {
        $errors['tutor_id'] = 'Selecione um tutor valido.';
    }
    if ($name === '') {
        $errors['nome'] = 'Informe o nome do animal.';
    }
    if ($species === '') {
        $errors['especie'] = 'Informe a especie.';
    }
    if (!in_array($sex, $allowedSex, true)) {
        $errors['sexo'] = 'Sexo invalido.';
    }
    if (!in_array($size, $allowedSizes, true)) {
        $errors['porte'] = 'Porte invalido.';
    }
    if (!empty($input['data_nascimento']) && $birthDate === null) {
        $errors['data_nascimento'] = 'Data de nascimento invalida.';
    }
    if (($input['peso_kg'] ?? '') !== '' && $weight === null) {
        $errors['peso_kg'] = 'Peso invalido.';
    }

    if ($errors) {
        pet_validation_error($errors);
    }

    return [
        'tutor_id' => (int) $tutorId,
        'nome' => $name,
        'especie' => $species,
        'raca' => pet_nullable_text($input['raca'] ?? null, 120),
        'sexo' => $sex,
        'data_nascimento' => $birthDate,
        'cor' => pet_nullable_text($input['cor'] ?? null, 100),
        'peso_kg' => $weight,
        'porte' => $size,
        'microchip' => pet_nullable_text($input['microchip'] ?? null, 80),
        'castrado' => pet_bool($input['castrado'] ?? false),
        'tipo_sanguineo' => pet_nullable_text($input['tipo_sanguineo'] ?? null, 30),
        'alergias' => pet_nullable_text($input['alergias'] ?? null, 5000),
        'condicoes_preexistentes' => pet_nullable_text($input['condicoes_preexistentes'] ?? null, 5000),
        'observacoes' => pet_nullable_text($input['observacoes'] ?? null, 5000),
        'ativo' => array_key_exists('ativo', $input) ? pet_bool($input['ativo']) : 1,
    ];
}

function pet_animais_listar(): void
{
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if ($id) {
        $record = pet_execute(
            'SELECT a.*, t.nome AS tutor_nome, t.telefone AS tutor_telefone,
                    t.email AS tutor_email, t.foto_caminho AS tutor_foto,
                    (SELECT COUNT(*) FROM pet_atendimentos x WHERE x.animal_id = a.id) AS total_atendimentos,
                    (SELECT COUNT(*) FROM pet_internacoes i WHERE i.animal_id = a.id) AS total_internacoes
             FROM pet_animais a
             INNER JOIN pet_tutores t ON t.id = a.tutor_id
             WHERE a.id = ?
             LIMIT 1',
            'i',
            [(int) $id]
        )->get_result()->fetch_assoc();

        if (!$record) {
            json_response(['ok' => false, 'erro' => 'Animal nao encontrado.'], 404);
        }
        json_response(['ok' => true, 'data' => $record]);
    }

    $pagination = pet_pagination();
    $search = '%' . pet_text($_GET['q'] ?? '', 100) . '%';
    $tutorId = (int) (filter_input(INPUT_GET, 'tutor_id', FILTER_VALIDATE_INT) ?: 0);
    $activeOnly = isset($_GET['todos']) ? 0 : 1;

    $count = pet_execute(
        'SELECT COUNT(*) AS total
         FROM pet_animais a
         INNER JOIN pet_tutores t ON t.id = a.tutor_id
         WHERE (? = 0 OR a.ativo = 1)
           AND (? = 0 OR a.tutor_id = ?)
           AND (a.nome LIKE ? OR a.especie LIKE ? OR a.raca LIKE ? OR t.nome LIKE ? OR a.microchip LIKE ?)',
        'iiisssss',
        [$activeOnly, $tutorId, $tutorId, $search, $search, $search, $search, $search]
    )->get_result()->fetch_assoc();

    $records = pet_execute(
        'SELECT a.id, a.tutor_id, a.nome, a.especie, a.raca, a.sexo, a.data_nascimento,
                a.peso_kg, a.porte, a.microchip, a.foto_caminho, a.ativo,
                t.nome AS tutor_nome, t.telefone AS tutor_telefone,
                EXISTS(SELECT 1 FROM pet_internacoes i WHERE i.animal_id = a.id AND i.status = "ativa") AS internado
         FROM pet_animais a
         INNER JOIN pet_tutores t ON t.id = a.tutor_id
         WHERE (? = 0 OR a.ativo = 1)
           AND (? = 0 OR a.tutor_id = ?)
           AND (a.nome LIKE ? OR a.especie LIKE ? OR a.raca LIKE ? OR t.nome LIKE ? OR a.microchip LIKE ?)
         ORDER BY a.nome ASC
         LIMIT ? OFFSET ?',
        'iiisssssii',
        [
            $activeOnly, $tutorId, $tutorId, $search, $search, $search, $search, $search,
            $pagination['limit'], $pagination['offset'],
        ]
    )->get_result()->fetch_all(MYSQLI_ASSOC);

    json_response([
        'ok' => true,
        'data' => $records,
        'meta' => [
            'page' => $pagination['page'],
            'limit' => $pagination['limit'],
            'total' => (int) ($count['total'] ?? 0),
        ],
    ]);
}

function pet_animais_criar(array $context): void
{
    $data = pet_animal_payload(pet_json_input());
    $userId = (int) $context['id'];

    $stmt = pet_execute(
        'INSERT INTO pet_animais
            (tutor_id, nome, especie, raca, sexo, data_nascimento, cor, peso_kg, porte,
             microchip, castrado, tipo_sanguineo, alergias, condicoes_preexistentes,
             observacoes, ativo, criado_por)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        'issssssdssissssii',
        [
            $data['tutor_id'], $data['nome'], $data['especie'], $data['raca'], $data['sexo'],
            $data['data_nascimento'], $data['cor'], $data['peso_kg'], $data['porte'],
            $data['microchip'], $data['castrado'], $data['tipo_sanguineo'], $data['alergias'],
            $data['condicoes_preexistentes'], $data['observacoes'], $data['ativo'], $userId,
        ]
    );

    $id = (int) $stmt->insert_id;
    pet_audit($context, 'criar', 'animal', $id, ['nome' => $data['nome'], 'tutor_id' => $data['tutor_id']]);
    json_response(['ok' => true, 'mensagem' => 'Animal cadastrado.', 'data' => ['id' => $id]], 201);
}

function pet_animais_atualizar(array $context): void
{
    $id = pet_query_id();
    if (!pet_record_exists('pet_animais', $id)) {
        json_response(['ok' => false, 'erro' => 'Animal nao encontrado.'], 404);
    }

    $data = pet_animal_payload(pet_json_input());
    pet_execute(
        'UPDATE pet_animais SET
            tutor_id = ?, nome = ?, especie = ?, raca = ?, sexo = ?, data_nascimento = ?,
            cor = ?, peso_kg = ?, porte = ?, microchip = ?, castrado = ?, tipo_sanguineo = ?,
            alergias = ?, condicoes_preexistentes = ?, observacoes = ?, ativo = ?
         WHERE id = ?',
        'issssssdssissssii',
        [
            $data['tutor_id'], $data['nome'], $data['especie'], $data['raca'], $data['sexo'],
            $data['data_nascimento'], $data['cor'], $data['peso_kg'], $data['porte'],
            $data['microchip'], $data['castrado'], $data['tipo_sanguineo'], $data['alergias'],
            $data['condicoes_preexistentes'], $data['observacoes'], $data['ativo'], $id,
        ]
    );

    pet_audit($context, 'atualizar', 'animal', $id);
    json_response(['ok' => true, 'mensagem' => 'Animal atualizado.', 'data' => ['id' => $id]]);
}

function pet_animais_desativar(array $context): void
{
    $id = pet_query_id();
    pet_execute('UPDATE pet_animais SET ativo = 0 WHERE id = ?', 'i', [$id]);
    pet_audit($context, 'desativar', 'animal', $id);
    json_response(['ok' => true, 'mensagem' => 'Animal desativado.']);
}
