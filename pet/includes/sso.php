<?php
declare(strict_types=1);

function pet_sso_bearer_token(): string
{
    $authorization = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
    if (stripos($authorization, 'Bearer ') !== 0) {
        return '';
    }

    $token = trim(substr($authorization, 7));
    return preg_match('/^[a-f0-9]{64}$/', $token) ? $token : '';
}

function pet_sso_user_from_token(?string $token = null): ?array
{
    $rawToken = $token ?? pet_sso_bearer_token();
    if (!preg_match('/^[a-f0-9]{64}$/', $rawToken)) {
        return null;
    }

    $hash = hash('sha256', $rawToken);
    $stmt = db()->prepare(
        'SELECT u.id, u.nome, u.email, u.perfil
         FROM pet_sso_tokens s
         INNER JOIN usuarios_admin u ON u.id = s.usuario_id
         WHERE s.token_hash = ? AND s.trocado_em IS NOT NULL AND s.revogado_em IS NULL
           AND s.token_expira_em > NOW() AND u.ativo = 1
         LIMIT 1'
    );
    $stmt->bind_param('s', $hash);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    if (!$user) {
        return null;
    }

    $touch = db()->prepare('UPDATE pet_sso_tokens SET ultimo_uso_em = NOW() WHERE token_hash = ?');
    $touch->bind_param('s', $hash);
    $touch->execute();

    return [
        'id' => (int) $user['id'],
        'nome' => (string) $user['nome'],
        'email' => (string) $user['email'],
        'perfil' => (string) $user['perfil'],
    ];
}

function pet_sso_require_user(): array
{
    $user = pet_sso_user_from_token();
    if ($user === null) {
        json_response([
            'ok' => false,
            'codigo' => 'TOKEN_SSO_INVALIDO',
            'erro' => 'A sessao integrada expirou. Entre novamente.',
        ], 401);
    }
    return $user;
}
