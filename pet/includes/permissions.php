<?php
declare(strict_types=1);

function pet_user_context(array $user): array
{
    $context = [
        'id' => (int) $user['id'],
        'nome' => (string) $user['nome'],
        'email' => (string) $user['email'],
        'perfil' => (string) $user['perfil'],
        'veterinario_id' => null,
        'crmv' => null,
        'especialidade' => null,
        'permissoes' => [],
    ];

    try {
        $userId = (int) $user['id'];
        $stmt = db()->prepare(
            'SELECT id, crmv, uf_crmv, especialidade
             FROM pet_veterinarios
             WHERE usuario_id = ? AND ativo = 1
             LIMIT 1'
        );
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $vet = $stmt->get_result()->fetch_assoc();

        if ($vet) {
            $context['veterinario_id'] = (int) $vet['id'];
            $context['crmv'] = trim((string) $vet['crmv'] . '/' . (string) $vet['uf_crmv']);
            $context['especialidade'] = (string) $vet['especialidade'];
        }
    } catch (mysqli_sql_exception $exception) {
        if ((int) $exception->getCode() !== 1146) {
            throw $exception;
        }
    }

    $isAdmin = $context['perfil'] === 'admin';
    $isOperator = $context['perfil'] === 'operador';
    $isViewer = $context['perfil'] === 'visualizador';
    $isVet = $context['veterinario_id'] !== null;

    $context['permissoes'] = [
        'dashboard' => true,
        'ver_cadastros' => !$isViewer,
        'editar_cadastros' => $isAdmin || $isOperator,
        'ver_prontuario' => $isAdmin || $isOperator || $isVet,
        'editar_prontuario' => $isAdmin || $isVet,
        'gerenciar_internacao' => $isAdmin || $isOperator || $isVet,
        'gerenciar_equipe' => $isAdmin,
    ];

    return $context;
}

function pet_can(array $context, string $permission): bool
{
    return !empty($context['permissoes'][$permission]);
}

function pet_require_permission(array $context, string $permission): void
{
    if (pet_can($context, $permission)) {
        return;
    }

    json_response([
        'ok' => false,
        'codigo' => 'ACESSO_NEGADO',
        'erro' => 'Seu perfil nao permite esta operacao.'
    ], 403);
}

function pet_require_veterinarian(array $context): int
{
    if ($context['perfil'] === 'admin' && $context['veterinario_id'] === null) {
        return 0;
    }

    if ($context['veterinario_id'] === null) {
        json_response([
            'ok' => false,
            'codigo' => 'VETERINARIO_NAO_VINCULADO',
            'erro' => 'Vincule este usuario a um cadastro veterinario antes de registrar o prontuario.'
        ], 422);
    }

    return (int) $context['veterinario_id'];
}
