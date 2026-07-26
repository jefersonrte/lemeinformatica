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
            listar_alimentos();
            break;
        case 'POST':
            criar_alimento();
            break;
        case 'PUT':
            atualizar_alimento();
            break;
        case 'DELETE':
            excluir_alimento();
            break;
        default:
            json_response(['ok' => false, 'erro' => 'Metodo nao permitido.'], 405);
    }
} catch (Throwable $e) {
    json_response([
        'ok' => false,
        'codigo' => 'API_BANCO_INDISPONIVEL',
        'erro' => 'Nao foi possivel consultar os alimentos agora.'
    ], 500);
}

function listar_alimentos(): void
{
    $conn = db();
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

    if ($id) {
        $stmt = $conn->prepare('SELECT id, nome, categoria, unidade, preco FROM alimentos WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $alimento = $stmt->get_result()->fetch_assoc();

        if (!$alimento) {
            json_response(['ok' => false, 'erro' => 'Alimento nao encontrado.'], 404);
        }

        json_response(['ok' => true, 'data' => $alimento]);
    }

    $q = clean_text($_GET['q'] ?? '');
    $limit = positive_int($_GET['limit'] ?? 100, 100, 5000);
    $offset = max(0, (int) ($_GET['offset'] ?? 0));

    if ($q !== '') {
        $like = '%' . $q . '%';
        $count = $conn->prepare('SELECT COUNT(*) AS total FROM alimentos WHERE nome LIKE ? OR categoria LIKE ? OR unidade LIKE ?');
        $count->bind_param('sss', $like, $like, $like);
        $count->execute();
        $total = (int) $count->get_result()->fetch_assoc()['total'];

        $stmt = $conn->prepare(
            'SELECT id, nome, categoria, unidade, preco
             FROM alimentos
             WHERE nome LIKE ? OR categoria LIKE ? OR unidade LIKE ?
             ORDER BY id DESC LIMIT ? OFFSET ?'
        );
        $stmt->bind_param('sssii', $like, $like, $like, $limit, $offset);
    } else {
        $total = (int) $conn->query('SELECT COUNT(*) AS total FROM alimentos')->fetch_assoc()['total'];
        $stmt = $conn->prepare(
            'SELECT id, nome, categoria, unidade, preco
             FROM alimentos ORDER BY id DESC LIMIT ? OFFSET ?'
        );
        $stmt->bind_param('ii', $limit, $offset);
    }

    $stmt->execute();
    json_response([
        'ok' => true,
        'data' => $stmt->get_result()->fetch_all(MYSQLI_ASSOC),
        'meta' => ['total' => $total, 'limit' => $limit, 'offset' => $offset]
    ]);
}

function validar_alimento(array $data): array
{
    $nome = clean_text($data['nome'] ?? '');
    $categoria = clean_text($data['categoria'] ?? '');
    $unidade = clean_text($data['unidade'] ?? '');
    $preco = filter_var($data['preco'] ?? null, FILTER_VALIDATE_FLOAT);
    $erros = [];

    if ($nome === '') {
        $erros['nome'] = 'Nome e obrigatorio.';
    }
    if ($categoria === '') {
        $erros['categoria'] = 'Categoria e obrigatoria.';
    }
    if ($unidade === '') {
        $erros['unidade'] = 'Unidade e obrigatoria.';
    }
    if ($preco === false || $preco < 0) {
        $erros['preco'] = 'Preco invalido.';
    }

    if ($erros) {
        json_response(['ok' => false, 'erro' => 'Dados invalidos.', 'campos' => $erros], 422);
    }

    return [
        'nome' => $nome,
        'categoria' => $categoria,
        'unidade' => $unidade,
        'preco' => (float) $preco,
    ];
}

function criar_alimento(): void
{
    $data = validar_alimento(request_json());
    $conn = db();
    $stmt = $conn->prepare('INSERT INTO alimentos (nome, categoria, unidade, preco) VALUES (?, ?, ?, ?)');
    $stmt->bind_param('sssd', $data['nome'], $data['categoria'], $data['unidade'], $data['preco']);
    $stmt->execute();

    json_response([
        'ok' => true,
        'mensagem' => 'Alimento cadastrado com sucesso.',
        'data' => ['id' => $conn->insert_id] + $data
    ], 201);
}

function atualizar_alimento(): void
{
    $input = request_json();
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT)
        ?: filter_var($input['id'] ?? null, FILTER_VALIDATE_INT);

    if (!$id) {
        json_response(['ok' => false, 'erro' => 'Informe o id do alimento.'], 422);
    }

    $data = validar_alimento($input);
    $conn = db();
    $stmt = $conn->prepare('UPDATE alimentos SET nome = ?, categoria = ?, unidade = ?, preco = ? WHERE id = ?');
    $stmt->bind_param('sssdi', $data['nome'], $data['categoria'], $data['unidade'], $data['preco'], $id);
    $stmt->execute();

    if ($stmt->affected_rows === 0) {
        $check = $conn->prepare('SELECT id FROM alimentos WHERE id = ?');
        $check->bind_param('i', $id);
        $check->execute();
        if (!$check->get_result()->fetch_assoc()) {
            json_response(['ok' => false, 'erro' => 'Alimento nao encontrado.'], 404);
        }
    }

    json_response([
        'ok' => true,
        'mensagem' => 'Alimento atualizado com sucesso.',
        'data' => ['id' => (int) $id] + $data
    ]);
}

function excluir_alimento(): void
{
    $input = request_json();
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT)
        ?: filter_var($input['id'] ?? null, FILTER_VALIDATE_INT);

    if (!$id) {
        json_response(['ok' => false, 'erro' => 'Informe o id do alimento.'], 422);
    }

    $stmt = db()->prepare('DELETE FROM alimentos WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();

    if ($stmt->affected_rows === 0) {
        json_response(['ok' => false, 'erro' => 'Alimento nao encontrado.'], 404);
    }

    json_response([
        'ok' => true,
        'mensagem' => 'Alimento excluido com sucesso.',
        'data' => ['id' => (int) $id]
    ]);
}
