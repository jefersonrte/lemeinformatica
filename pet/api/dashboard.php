<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$context = pet_boot_api();

try {
    $conn = db();
    $totals = [
        'tutores' => (int) $conn->query('SELECT COUNT(*) AS total FROM pet_tutores WHERE ativo = 1')->fetch_assoc()['total'],
        'animais' => (int) $conn->query('SELECT COUNT(*) AS total FROM pet_animais WHERE ativo = 1')->fetch_assoc()['total'],
        'atendimentos_hoje' => (int) $conn->query(
            "SELECT COUNT(*) AS total
             FROM pet_atendimentos
             WHERE DATE(inicio_em) = CURDATE() AND status <> 'cancelado'"
        )->fetch_assoc()['total'],
        'internados' => (int) $conn->query(
            "SELECT COUNT(*) AS total FROM pet_internacoes WHERE status = 'ativa'"
        )->fetch_assoc()['total'],
    ];

    $speciesResult = $conn->query(
        'SELECT especie AS nome, COUNT(*) AS total
         FROM pet_animais
         WHERE ativo = 1
         GROUP BY especie
         ORDER BY total DESC, especie ASC
         LIMIT 8'
    );

    $payload = [
        'ok' => true,
        'versao' => PET_VERSION,
        'data' => [
            'totais' => $totals,
            'especies' => $speciesResult->fetch_all(MYSQLI_ASSOC),
            'perfil' => [
                'nome' => $context['nome'],
                'perfil' => $context['perfil'],
                'veterinario' => $context['veterinario_id'] !== null,
                'permissoes' => $context['permissoes'],
            ],
        ],
    ];

    if (pet_can($context, 'ver_cadastros')) {
        $appointments = $conn->query(
            "SELECT a.id, a.inicio_em, a.tipo, a.status, a.motivo,
                    p.id AS animal_id, p.nome AS animal_nome, p.especie,
                    t.nome AS tutor_nome,
                    COALESCE(u.nome, 'A definir') AS veterinario_nome
             FROM pet_atendimentos a
             INNER JOIN pet_animais p ON p.id = a.animal_id
             INNER JOIN pet_tutores t ON t.id = p.tutor_id
             LEFT JOIN pet_veterinarios v ON v.id = a.veterinario_id
             LEFT JOIN usuarios_admin u ON u.id = v.usuario_id
             WHERE a.inicio_em >= DATE_SUB(NOW(), INTERVAL 1 DAY)
               AND a.status <> 'cancelado'
             ORDER BY a.inicio_em ASC
             LIMIT 10"
        );

        $admissions = $conn->query(
            "SELECT i.id, i.entrada_em, i.setor, i.leito, i.classificacao_risco,
                    p.id AS animal_id, p.nome AS animal_nome, p.especie,
                    t.nome AS tutor_nome
             FROM pet_internacoes i
             INNER JOIN pet_animais p ON p.id = i.animal_id
             INNER JOIN pet_tutores t ON t.id = p.tutor_id
             WHERE i.status = 'ativa'
             ORDER BY FIELD(i.classificacao_risco, 'critico', 'alto', 'moderado', 'baixo'),
                      i.entrada_em ASC
             LIMIT 12"
        );

        $payload['data']['proximos_atendimentos'] = $appointments->fetch_all(MYSQLI_ASSOC);
        $payload['data']['internacoes_ativas'] = $admissions->fetch_all(MYSQLI_ASSOC);
    }

    json_response($payload);
} catch (Throwable $exception) {
    pet_api_exception($exception, 'Nao foi possivel carregar o painel Pet.');
}
