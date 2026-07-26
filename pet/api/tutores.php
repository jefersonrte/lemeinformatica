<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$context = pet_boot_api();
pet_require_permission($context, 'ver_cadastros');

try {
    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

    if ($method === 'GET') {
        pet_tutores_listar();
    } elseif ($method === 'POST') {
        pet_require_permission($context, 'editar_cadastros');
        pet_tutores_criar($context);
    } elseif ($method === 'PUT') {
        pet_require_permission($context, 'editar_cadastros');
        pet_tutores_atualizar($context);
    } elseif ($method === 'DELETE') {
        pet_require_permission($context, 'editar_cadastros');
        pet_tutores_desativar($context);
    } else {
        json_response(['ok' => false, 'erro' => 'Metodo nao permitido.'], 405);
    }
} catch (Throwable $exception) {
    pet_api_exception($exception, 'Nao foi possivel salvar o tutor.');
}

function pet_tutor_payload(array $input): array
{
    $cpf = pet_digits($input['cpf'] ?? '', 11);
    $email = strtolower(pet_text($input['email'] ?? '', 160));
    $phone = pet_digits($input['telefone'] ?? '', 20);
    $birthDate = pet_nullable_date($input['data_nascimento'] ?? null);
    $errors = [];

    if (pet_text($input['nome'] ?? '', 160) === '') {
        $errors['nome'] = 'Informe o nome completo.';
    }
    if ($cpf !== '' && !pet_validate_cpf($cpf)) {
        $errors['cpf'] = 'CPF invalido.';
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'E-mail invalido.';
    }
    if (strlen($phone) < 10) {
        $errors['telefone'] = 'Informe telefone com DDD.';
    }
    if (!empty($input['data_nascimento']) && $birthDate === null) {
        $errors['data_nascimento'] = 'Data de nascimento invalida.';
    }

    $uf = strtoupper(pet_text($input['uf'] ?? '', 2));
    if ($uf !== '' && !preg_match('/^[A-Z]{2}$/', $uf)) {
        $errors['uf'] = 'UF invalida.';
    }

    if ($errors) {
        pet_validation_error($errors);
    }

    return [
        'nome' => pet_text($input['nome'] ?? '', 160),
        'cpf' => $cpf === '' ? null : $cpf,
        'rg' => pet_nullable_text($input['rg'] ?? null, 30),
        'data_nascimento' => $birthDate,
        'genero' => pet_nullable_text($input['genero'] ?? null, 30),
        'estado_civil' => pet_nullable_text($input['estado_civil'] ?? null, 30),
        'profissao' => pet_nullable_text($input['profissao'] ?? null, 100),
        'email' => $email === '' ? null : $email,
        'telefone' => $phone,
        'whatsapp' => (($digits = pet_digits($input['whatsapp'] ?? '', 20)) === '') ? null : $digits,
        'cep' => (($digits = pet_digits($input['cep'] ?? '', 8)) === '') ? null : $digits,
        'logradouro' => pet_nullable_text($input['logradouro'] ?? null, 180),
        'numero' => pet_nullable_text($input['numero'] ?? null, 20),
        'complemento' => pet_nullable_text($input['complemento'] ?? null, 100),
        'bairro' => pet_nullable_text($input['bairro'] ?? null, 100),
        'cidade' => pet_nullable_text($input['cidade'] ?? null, 100),
        'uf' => $uf === '' ? null : $uf,
        'contato_emergencia_nome' => pet_nullable_text($input['contato_emergencia_nome'] ?? null, 160),
        'contato_emergencia_telefone' => (($digits = pet_digits($input['contato_emergencia_telefone'] ?? '', 20)) === '') ? null : $digits,
        'observacoes' => pet_nullable_text($input['observacoes'] ?? null, 5000),
        'ativo' => array_key_exists('ativo', $input) ? pet_bool($input['ativo']) : 1,
    ];
}

function pet_tutores_listar(): void
{
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if ($id) {
        $stmt = pet_execute(
            'SELECT t.*,
                    (SELECT COUNT(*) FROM pet_animais a WHERE a.tutor_id = t.id AND a.ativo = 1) AS total_animais
             FROM pet_tutores t
             WHERE t.id = ?
             LIMIT 1',
            'i',
            [(int) $id]
        );
        $record = $stmt->get_result()->fetch_assoc();
        if (!$record) {
            json_response(['ok' => false, 'erro' => 'Tutor nao encontrado.'], 404);
        }

        $animals = pet_execute(
            'SELECT id, nome, especie, raca, sexo, data_nascimento, foto_caminho
             FROM pet_animais
             WHERE tutor_id = ? AND ativo = 1
             ORDER BY nome',
            'i',
            [(int) $id]
        )->get_result()->fetch_all(MYSQLI_ASSOC);

        $record['animais'] = $animals;
        json_response(['ok' => true, 'data' => $record]);
    }

    $pagination = pet_pagination();
    $search = '%' . pet_text($_GET['q'] ?? '', 100) . '%';
    $activeOnly = isset($_GET['todos']) ? 0 : 1;

    $count = pet_execute(
        'SELECT COUNT(*) AS total
         FROM pet_tutores
         WHERE (? = 0 OR ativo = 1)
           AND (nome LIKE ? OR cpf LIKE ? OR telefone LIKE ? OR email LIKE ?)',
        'issss',
        [$activeOnly, $search, $search, $search, $search]
    )->get_result()->fetch_assoc();

    $records = pet_execute(
        'SELECT t.id, t.nome, t.cpf, t.email, t.telefone, t.whatsapp, t.cidade, t.uf,
                t.foto_caminho, t.ativo, t.criado_em,
                COUNT(a.id) AS total_animais
         FROM pet_tutores t
         LEFT JOIN pet_animais a ON a.tutor_id = t.id AND a.ativo = 1
         WHERE (? = 0 OR t.ativo = 1)
           AND (t.nome LIKE ? OR t.cpf LIKE ? OR t.telefone LIKE ? OR t.email LIKE ?)
         GROUP BY t.id
         ORDER BY t.nome ASC
         LIMIT ? OFFSET ?',
        'issssii',
        [$activeOnly, $search, $search, $search, $search, $pagination['limit'], $pagination['offset']]
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

function pet_tutores_criar(array $context): void
{
    $data = pet_tutor_payload(pet_json_input());
    $userId = (int) $context['id'];

    $stmt = pet_execute(
        'INSERT INTO pet_tutores
            (nome, cpf, rg, data_nascimento, genero, estado_civil, profissao, email,
             telefone, whatsapp, cep, logradouro, numero, complemento, bairro, cidade,
             uf, contato_emergencia_nome, contato_emergencia_telefone, observacoes, ativo, criado_por)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        'ssssssssssssssssssssii',
        [
            $data['nome'], $data['cpf'], $data['rg'], $data['data_nascimento'], $data['genero'],
            $data['estado_civil'], $data['profissao'], $data['email'], $data['telefone'],
            $data['whatsapp'], $data['cep'], $data['logradouro'], $data['numero'],
            $data['complemento'], $data['bairro'], $data['cidade'], $data['uf'],
            $data['contato_emergencia_nome'], $data['contato_emergencia_telefone'],
            $data['observacoes'], $data['ativo'], $userId,
        ]
    );

    $id = (int) $stmt->insert_id;
    pet_audit($context, 'criar', 'tutor', $id, ['nome' => $data['nome']]);
    json_response(['ok' => true, 'mensagem' => 'Tutor cadastrado.', 'data' => ['id' => $id]], 201);
}

function pet_tutores_atualizar(array $context): void
{
    $id = pet_query_id();
    if (!pet_record_exists('pet_tutores', $id)) {
        json_response(['ok' => false, 'erro' => 'Tutor nao encontrado.'], 404);
    }

    $data = pet_tutor_payload(pet_json_input());
    pet_execute(
        'UPDATE pet_tutores SET
            nome = ?, cpf = ?, rg = ?, data_nascimento = ?, genero = ?, estado_civil = ?,
            profissao = ?, email = ?, telefone = ?, whatsapp = ?, cep = ?, logradouro = ?,
            numero = ?, complemento = ?, bairro = ?, cidade = ?, uf = ?,
            contato_emergencia_nome = ?, contato_emergencia_telefone = ?,
            observacoes = ?, ativo = ?
         WHERE id = ?',
        'ssssssssssssssssssssii',
        [
            $data['nome'], $data['cpf'], $data['rg'], $data['data_nascimento'], $data['genero'],
            $data['estado_civil'], $data['profissao'], $data['email'], $data['telefone'],
            $data['whatsapp'], $data['cep'], $data['logradouro'], $data['numero'],
            $data['complemento'], $data['bairro'], $data['cidade'], $data['uf'],
            $data['contato_emergencia_nome'], $data['contato_emergencia_telefone'],
            $data['observacoes'], $data['ativo'], $id,
        ]
    );

    pet_audit($context, 'atualizar', 'tutor', $id);
    json_response(['ok' => true, 'mensagem' => 'Tutor atualizado.', 'data' => ['id' => $id]]);
}

function pet_tutores_desativar(array $context): void
{
    $id = pet_query_id();
    pet_execute('UPDATE pet_tutores SET ativo = 0 WHERE id = ?', 'i', [$id]);
    pet_audit($context, 'desativar', 'tutor', $id);
    json_response(['ok' => true, 'mensagem' => 'Tutor desativado.']);
}
