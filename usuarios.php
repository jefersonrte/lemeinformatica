<?php
require_once __DIR__ . '/includes/init.php';

if (($GLOBALS['API_AUTH_MODE'] ?? '') === 'session') {
    $sessionUser = current_api_user();
    if ($sessionUser === null || $sessionUser['perfil'] !== 'admin') {
        json_response(['ok' => false, 'erro' => 'Apenas administradores podem gerenciar usuarios.'], 403);
    }
}

try {
    $method = method_override();

    switch ($method) {
        case 'GET':
            api_listar_usuarios();
            break;
        case 'POST':
            api_criar_usuario();
            break;
        case 'PUT':
            api_atualizar_usuario();
            break;
        case 'DELETE':
            api_desativar_usuario();
            break;
        default:
            json_response(['ok' => false, 'erro' => 'Metodo nao permitido.'], 405);
    }
} catch (mysqli_sql_exception $e) {
    if ((int) $e->getCode() === 1062) {
        json_response(['ok' => false, 'erro' => 'Ja existe usuario cadastrado com este e-mail.'], 409);
    }

    if ((int) $e->getCode() === 1146) {
        json_response([
            'ok' => false,
            'erro' => 'Tabela usuarios_admin nao encontrada. Execute sql/create_auth_tables.sql no banco principal.'
        ], 500);
    }

    json_response(['ok' => false, 'erro' => 'Erro ao salvar usuario.'], 500);
} catch (Throwable $e) {
    json_response(['ok' => false, 'erro' => 'Erro interno ao gerenciar usuarios.'], 500);
}

function api_usuario_clean_text(mixed $value): string
{
    return trim((string) $value);
}

function api_usuario_clean_email(mixed $value): string
{
    return strtolower(trim((string) $value));
}

function api_usuario_bool(mixed $value, bool $default = true): int
{
    if ($value === null) {
        return $default ? 1 : 0;
    }

    $bool = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    return $bool === null ? ($default ? 1 : 0) : ($bool ? 1 : 0);
}

function api_usuario_roles(): array
{
    return ['admin', 'operador', 'visualizador'];
}

function api_validar_usuario(array $data, bool $creating): array
{
    $nome = api_usuario_clean_text($data['nome'] ?? '');
    $email = api_usuario_clean_email($data['email'] ?? '');
    $perfil = api_usuario_clean_text($data['perfil'] ?? 'visualizador');
    $senha = (string) ($data['senha'] ?? '');
    $ativo = api_usuario_bool($data['ativo'] ?? true);
    $erros = [];

    if ($nome === '') {
        $erros['nome'] = 'Nome e obrigatorio.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erros['email'] = 'E-mail invalido.';
    }

    if (!in_array($perfil, api_usuario_roles(), true)) {
        $erros['perfil'] = 'Perfil invalido.';
    }

    if ($creating && $senha === '') {
        $erros['senha'] = 'Senha e obrigatoria.';
    }

    if ($senha !== '' && strlen($senha) < 8) {
        $erros['senha'] = 'A senha deve ter pelo menos 8 caracteres.';
    }

    if ($erros) {
        json_response([
            'ok' => false,
            'erro' => 'Dados invalidos.',
            'campos' => $erros
        ], 422);
    }

    return [
        'nome' => $nome,
        'email' => $email,
        'perfil' => $perfil,
        'senha' => $senha,
        'ativo' => $ativo,
    ];
}

function api_usuario_id_from_request(array $data): int
{
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);

    if (!$id) {
        json_response(['ok' => false, 'erro' => 'Informe o id do usuario.'], 422);
    }

    return (int) $id;
}

function api_buscar_usuario(int $id): array
{
    $conn = db();
    $stmt = $conn->prepare('SELECT id, nome, email, perfil, ativo FROM usuarios_admin WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $usuario = $stmt->get_result()->fetch_assoc();

    if (!$usuario) {
        json_response(['ok' => false, 'erro' => 'Usuario nao encontrado.'], 404);
    }

    return $usuario;
}

function api_active_admins_except(int $userId): int
{
    $conn = db();
    $roleAdmin = 'admin';
    $stmt = $conn->prepare('SELECT COUNT(*) AS total FROM usuarios_admin WHERE ativo = 1 AND perfil = ? AND id <> ?');
    $stmt->bind_param('si', $roleAdmin, $userId);
    $stmt->execute();
    return (int) $stmt->get_result()->fetch_assoc()['total'];
}

function api_ensure_active_admin_remains(int $targetId, string $newPerfil, int $newAtivo): void
{
    if ($newPerfil === 'admin' && $newAtivo === 1) {
        return;
    }

    if (api_active_admins_except($targetId) < 1) {
        json_response(['ok' => false, 'erro' => 'Mantenha pelo menos um administrador ativo.'], 422);
    }
}

function api_listar_usuarios(): void
{
    $conn = db();
    $result = $conn->query(
        'SELECT id, nome, email, perfil, ativo, criado_em, atualizado_em, ultimo_login_em
         FROM usuarios_admin
         ORDER BY ativo DESC, nome ASC, id ASC'
    );

    json_response([
        'ok' => true,
        'data' => $result->fetch_all(MYSQLI_ASSOC)
    ]);
}

function api_criar_usuario(): void
{
    $data = api_validar_usuario(request_json(), true);
    $hash = password_hash($data['senha'], PASSWORD_DEFAULT);
    $conn = db();

    $stmt = $conn->prepare('INSERT INTO usuarios_admin (nome, email, senha_hash, perfil, ativo) VALUES (?, ?, ?, ?, ?)');
    $stmt->bind_param('ssssi', $data['nome'], $data['email'], $hash, $data['perfil'], $data['ativo']);
    $stmt->execute();

    json_response([
        'ok' => true,
        'mensagem' => 'Usuario criado com sucesso.',
        'data' => [
            'id' => $conn->insert_id,
            'nome' => $data['nome'],
            'email' => $data['email'],
            'perfil' => $data['perfil'],
            'ativo' => $data['ativo'],
        ]
    ], 201);
}

function api_atualizar_usuario(): void
{
    $input = request_json();
    $id = api_usuario_id_from_request($input);
    api_buscar_usuario($id);
    $data = api_validar_usuario($input, false);

    api_ensure_active_admin_remains($id, $data['perfil'], $data['ativo']);

    $conn = db();

    if ($data['senha'] !== '') {
        $hash = password_hash($data['senha'], PASSWORD_DEFAULT);
        $stmt = $conn->prepare('UPDATE usuarios_admin SET nome = ?, email = ?, perfil = ?, ativo = ?, senha_hash = ? WHERE id = ?');
        $stmt->bind_param('sssisi', $data['nome'], $data['email'], $data['perfil'], $data['ativo'], $hash, $id);
    } else {
        $stmt = $conn->prepare('UPDATE usuarios_admin SET nome = ?, email = ?, perfil = ?, ativo = ? WHERE id = ?');
        $stmt->bind_param('sssii', $data['nome'], $data['email'], $data['perfil'], $data['ativo'], $id);
    }

    $stmt->execute();

    json_response([
        'ok' => true,
        'mensagem' => 'Usuario atualizado com sucesso.',
        'data' => [
            'id' => $id,
            'nome' => $data['nome'],
            'email' => $data['email'],
            'perfil' => $data['perfil'],
            'ativo' => $data['ativo'],
        ]
    ]);
}

function api_desativar_usuario(): void
{
    $input = request_json();
    $id = api_usuario_id_from_request($input);
    $current = api_buscar_usuario($id);

    api_ensure_active_admin_remains($id, (string) $current['perfil'], 0);

    $conn = db();
    $stmt = $conn->prepare('UPDATE usuarios_admin SET ativo = 0 WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();

    json_response([
        'ok' => true,
        'mensagem' => 'Usuario desativado com sucesso.',
        'data' => ['id' => $id]
    ]);
}
