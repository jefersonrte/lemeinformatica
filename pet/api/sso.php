<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

apply_page_security_headers();
header('Cache-Control: no-store');

try {
    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if ($method === 'POST' && pet_sso_bearer_token() === '') {
        pet_sso_exchange_code();
    }

    $user = pet_sso_require_user();
    if ($method === 'GET') {
        json_response(['ok' => true, 'data' => ['usuario' => $user]]);
    }
    if ($method === 'POST') {
        pet_sso_revoke();
    }
    json_response(['ok' => false, 'erro' => 'Metodo nao permitido.'], 405);
} catch (Throwable $exception) {
    pet_api_exception($exception, 'Nao foi possivel validar o acesso integrado.');
}

function pet_sso_exchange_code(): void
{
    $input = pet_json_input();
    $code = pet_text($input['codigo'] ?? '', 64);
    if (!preg_match('/^[a-f0-9]{64}$/', $code)) {
        json_response(['ok' => false, 'codigo' => 'CODIGO_SSO_INVALIDO', 'erro' => 'Codigo de acesso invalido.'], 401);
    }

    $codeHash = hash('sha256', $code);
    $conn = db();
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare(
            'SELECT s.id, s.usuario_id, u.nome, u.email, u.perfil
             FROM pet_sso_tokens s
             INNER JOIN usuarios_admin u ON u.id = s.usuario_id
             WHERE s.codigo_hash = ? AND s.trocado_em IS NULL AND s.revogado_em IS NULL
               AND s.codigo_expira_em > NOW() AND u.ativo = 1
             LIMIT 1 FOR UPDATE'
        );
        $stmt->bind_param('s', $codeHash);
        $stmt->execute();
        $record = $stmt->get_result()->fetch_assoc();
        if (!$record) {
            throw new PetDomainException('O codigo de acesso expirou ou ja foi usado.', 'CODIGO_SSO_EXPIRADO', 401);
        }

        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $tokenExpires = date('Y-m-d H:i:s', time() + 28800);
        $id = (int) $record['id'];
        $update = $conn->prepare(
            'UPDATE pet_sso_tokens SET token_hash = ?, token_expira_em = ?, trocado_em = NOW(),
                ultimo_uso_em = NOW() WHERE id = ?'
        );
        $update->bind_param('ssi', $tokenHash, $tokenExpires, $id);
        $update->execute();
        $conn->commit();
    } catch (Throwable $exception) {
        $conn->rollback();
        throw $exception;
    }

    json_response([
        'ok' => true,
        'data' => [
            'token' => $token,
            'expira_em' => $tokenExpires,
            'expira_em_epoch' => time() + 28800,
            'usuario' => [
                'id' => (int) $record['usuario_id'],
                'nome' => (string) $record['nome'],
                'email' => (string) $record['email'],
                'perfil' => (string) $record['perfil'],
            ],
        ],
    ]);
}

function pet_sso_revoke(): void
{
    $hash = hash('sha256', pet_sso_bearer_token());
    $stmt = db()->prepare('UPDATE pet_sso_tokens SET revogado_em = NOW() WHERE token_hash = ?');
    $stmt->bind_param('s', $hash);
    $stmt->execute();
    json_response(['ok' => true, 'mensagem' => 'Sessao integrada encerrada.']);
}
