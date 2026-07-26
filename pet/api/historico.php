<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$context = pet_boot_api();
pet_require_permission($context, 'ver_prontuario');

try {
    $animalId = (int) (filter_input(INPUT_GET, 'animal_id', FILTER_VALIDATE_INT) ?: 0);
    if ($animalId < 1) {
        pet_validation_error(['animal_id' => 'Informe o animal.']);
    }

    $animal = pet_execute(
        'SELECT a.*, t.nome AS tutor_nome, t.telefone AS tutor_telefone, t.email AS tutor_email
         FROM pet_animais a
         INNER JOIN pet_tutores t ON t.id = a.tutor_id
         WHERE a.id = ?
         LIMIT 1',
        'i',
        [$animalId]
    )->get_result()->fetch_assoc();
    if (!$animal) {
        json_response(['ok' => false, 'erro' => 'Animal nao encontrado.'], 404);
    }

    $appointments = pet_execute(
        'SELECT a.*, u.nome AS veterinario_nome, v.crmv, v.uf_crmv
         FROM pet_atendimentos a
         LEFT JOIN pet_veterinarios v ON v.id = a.veterinario_id
         LEFT JOIN usuarios_admin u ON u.id = v.usuario_id
         WHERE a.animal_id = ?
         ORDER BY a.inicio_em DESC
         LIMIT 100',
        'i',
        [$animalId]
    )->get_result()->fetch_all(MYSQLI_ASSOC);

    $admissions = pet_execute(
        'SELECT i.*, u.nome AS veterinario_nome
         FROM pet_internacoes i
         LEFT JOIN pet_veterinarios v ON v.id = i.veterinario_responsavel_id
         LEFT JOIN usuarios_admin u ON u.id = v.usuario_id
         WHERE i.animal_id = ?
         ORDER BY i.entrada_em DESC
         LIMIT 50',
        'i',
        [$animalId]
    )->get_result()->fetch_all(MYSQLI_ASSOC);

    foreach ($admissions as &$admission) {
        $admission['evolucoes'] = pet_execute(
            'SELECT e.*, u.nome AS veterinario_nome
             FROM pet_internacao_evolucoes e
             LEFT JOIN pet_veterinarios v ON v.id = e.veterinario_id
             LEFT JOIN usuarios_admin u ON u.id = v.usuario_id
             WHERE e.internacao_id = ?
             ORDER BY e.registrado_em DESC',
            'i',
            [(int) $admission['id']]
        )->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    unset($admission);

    json_response([
        'ok' => true,
        'data' => [
            'animal' => $animal,
            'atendimentos' => $appointments,
            'internacoes' => $admissions,
        ],
    ]);
} catch (Throwable $exception) {
    pet_api_exception($exception, 'Nao foi possivel carregar o prontuario.');
}
