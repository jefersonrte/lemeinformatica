<?php
require_once __DIR__ . '/includes/init.php';

try {
    $method = method_override();
    require_session_roles_for_state_change(
        (string) ($GLOBALS['API_AUTH_MODE'] ?? ''),
        $method,
        ['admin', 'operador']
    );

    switch ($method) {
        case 'GET':
            listar_animais();
            break;
        case 'POST':
            criar_animal();
            break;
        case 'PUT':
            atualizar_animal();
            break;
        case 'DELETE':
            excluir_animal();
            break;
        default:
            json_response(['ok' => false, 'erro' => 'Metodo nao permitido.'], 405);
    }
} catch (Throwable $e) {
    json_response([
        'ok' => false,
        'codigo' => 'API_BANCO_INDISPONIVEL',
        'erro' => 'Nao foi possivel consultar o banco principal agora.'
    ], 500);
}

function listar_animais(): void
{
    $conn = db();
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

    if ($id) {
        $stmt = $conn->prepare('SELECT id, nome, raca, porte FROM animais WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $animal = $stmt->get_result()->fetch_assoc();

        if (!$animal) {
            json_response(['ok' => false, 'erro' => 'Animal nao encontrado.'], 404);
        }

        json_response(['ok' => true, 'data' => $animal]);
    }

    $q = clean_text($_GET['q'] ?? '');
    $limit = positive_int($_GET['limit'] ?? 100, 100, 5000);
    $offset = max(0, (int) ($_GET['offset'] ?? 0));

    if ($q !== '') {
        $like = '%' . $q . '%';

        $countStmt = $conn->prepare('SELECT COUNT(*) AS total FROM animais WHERE nome LIKE ? OR raca LIKE ? OR porte LIKE ?');
        $countStmt->bind_param('sss', $like, $like, $like);
        $countStmt->execute();
        $total = (int) $countStmt->get_result()->fetch_assoc()['total'];

        $stmt = $conn->prepare('SELECT id, nome, raca, porte FROM animais WHERE nome LIKE ? OR raca LIKE ? OR porte LIKE ? ORDER BY id DESC LIMIT ? OFFSET ?');
        $stmt->bind_param('sssii', $like, $like, $like, $limit, $offset);
    } else {
        $total = (int) $conn->query('SELECT COUNT(*) AS total FROM animais')->fetch_assoc()['total'];

        $stmt = $conn->prepare('SELECT id, nome, raca, porte FROM animais ORDER BY id DESC LIMIT ? OFFSET ?');
        $stmt->bind_param('ii', $limit, $offset);
    }

    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    json_response([
        'ok' => true,
        'data' => $rows,
        'meta' => [
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset
        ]
    ]);
}

function criar_animal(): void
{
    $data = request_json();
    $nome = clean_text($data['nome'] ?? '');
    $raca = clean_text($data['raca'] ?? '');
    $porte = clean_text($data['porte'] ?? '');

    validar_animal($nome, $raca, $porte);

    $conn = db();
    $stmt = $conn->prepare('INSERT INTO animais (nome, raca, porte) VALUES (?, ?, ?)');
    $stmt->bind_param('sss', $nome, $raca, $porte);
    $stmt->execute();

    json_response([
        'ok' => true,
        'mensagem' => 'Animal cadastrado com sucesso.',
        'data' => [
            'id' => $conn->insert_id,
            'nome' => $nome,
            'raca' => $raca,
            'porte' => $porte
        ]
    ], 201);
}

function atualizar_animal(): void
{
    $data = request_json();
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);

    if (!$id) {
        json_response(['ok' => false, 'erro' => 'Informe o id do animal.'], 422);
    }

    $nome = clean_text($data['nome'] ?? '');
    $raca = clean_text($data['raca'] ?? '');
    $porte = clean_text($data['porte'] ?? '');

    validar_animal($nome, $raca, $porte);

    $conn = db();
    $stmt = $conn->prepare('UPDATE animais SET nome = ?, raca = ?, porte = ? WHERE id = ?');
    $stmt->bind_param('sssi', $nome, $raca, $porte, $id);
    $stmt->execute();

    if ($stmt->affected_rows === 0) {
        $check = $conn->prepare('SELECT id FROM animais WHERE id = ?');
        $check->bind_param('i', $id);
        $check->execute();
        if (!$check->get_result()->fetch_assoc()) {
            json_response(['ok' => false, 'erro' => 'Animal nao encontrado.'], 404);
        }
    }

    json_response([
        'ok' => true,
        'mensagem' => 'Animal atualizado com sucesso.',
        'data' => [
            'id' => $id,
            'nome' => $nome,
            'raca' => $raca,
            'porte' => $porte
        ]
    ]);
}

function excluir_animal(): void
{
    $data = request_json();
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);

    if (!$id) {
        json_response(['ok' => false, 'erro' => 'Informe o id do animal.'], 422);
    }

    $conn = db();
    $stmt = $conn->prepare('DELETE FROM animais WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();

    if ($stmt->affected_rows === 0) {
        json_response(['ok' => false, 'erro' => 'Animal nao encontrado.'], 404);
    }

    json_response([
        'ok' => true,
        'mensagem' => 'Animal excluido com sucesso.',
        'data' => ['id' => $id]
    ]);
}

function validar_animal(string $nome, string $raca, string $porte): void
{
    $erros = [];

    if ($nome === '') {
        $erros['nome'] = 'Nome e obrigatorio.';
    }
    if ($raca === '') {
        $erros['raca'] = 'Raca e obrigatoria.';
    }
    if ($porte === '') {
        $erros['porte'] = 'Porte e obrigatorio.';
    }

    if ($erros) {
        json_response([
            'ok' => false,
            'erro' => 'Dados invalidos.',
            'campos' => $erros
        ], 422);
    }
}
