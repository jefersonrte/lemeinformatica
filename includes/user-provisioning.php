<?php

final class UserProvisioningException extends RuntimeException
{
}

function user_sync_url(): string
{
    if (defined('USER_SYNC_URL') && trim((string) USER_SYNC_URL) !== '') {
        return (string) USER_SYNC_URL;
    }

    return 'https://lemesolucoesemti.com.br/api/usuarios-sync.php';
}

function provision_user_in_integrated_systems(string $action, array $user): array
{
    if (!in_array($action, ['upsert', 'disable'], true)) {
        throw new InvalidArgumentException('Acao de sincronizacao invalida.');
    }

    $payload = [
        'acao' => $action,
        'usuario_central_id' => (int) ($user['id'] ?? 0),
        'nome' => (string) ($user['nome'] ?? ''),
        'email' => (string) ($user['email'] ?? ''),
        'perfil' => (string) ($user['perfil'] ?? 'visualizador'),
        'ativo' => (int) ($user['ativo'] ?? 1) === 1,
    ];

    if (array_key_exists('email_anterior', $user)) {
        $payload['email_anterior'] = (string) $user['email_anterior'];
    }

    if (array_key_exists('senha', $user) && (string) $user['senha'] !== '') {
        $payload['senha'] = (string) $user['senha'];
    }

    $ch = curl_init(user_sync_url());
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Content-Type: application/json; charset=utf-8',
            'X-API-KEY: ' . API_KEY,
        ],
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 45,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);

    $responseBody = curl_exec($ch);
    $curlError = curl_error($ch);
    $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($responseBody === false) {
        throw new UserProvisioningException('Nao foi possivel conectar ao servico de sincronizacao: ' . $curlError);
    }

    $response = json_decode($responseBody, true);
    if ($statusCode < 200 || $statusCode >= 300 || !is_array($response) || ($response['ok'] ?? false) !== true) {
        $message = is_array($response) ? trim((string) ($response['erro'] ?? '')) : '';
        throw new UserProvisioningException(
            $message !== '' ? $message : 'O servico de sincronizacao recusou a operacao.'
        );
    }

    return $response;
}
