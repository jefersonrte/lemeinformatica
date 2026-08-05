<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

apply_page_security_headers();
start_api_session();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}
if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'GET') {
    json_response(['ok' => false, 'erro' => 'Metodo nao permitido.'], 405);
}

$ssoUser = null;
if (!has_valid_api_key() && current_api_user() === null) {
    $ssoUser = pet_sso_require_user();
}

try {
    $totals = pet_execute(
        "SELECT
            (SELECT COUNT(*) FROM pet_tutores WHERE ativo = 1) AS tutores,
            (SELECT COUNT(*) FROM pet_animais WHERE ativo = 1) AS animais,
            (SELECT COUNT(*) FROM pet_atendimentos WHERE DATE(inicio_em) = CURDATE()) AS atendimentos_hoje,
            (SELECT COUNT(*) FROM pet_internacoes WHERE status = 'ativa') AS internacoes_ativas,
            (SELECT COUNT(*) FROM pet_banho_tosa_agendamentos
             WHERE DATE(inicio_em) = CURDATE() AND status NOT IN ('cancelado', 'nao_compareceu')) AS estetica_hoje,
            (SELECT COUNT(*) FROM pet_banho_tosa_agendamentos
             WHERE inicio_em >= NOW() AND inicio_em < DATE_ADD(CURDATE(), INTERVAL 8 DAY)
               AND status NOT IN ('concluido', 'cancelado', 'nao_compareceu')) AS estetica_proximos_7_dias,
            (SELECT COUNT(*) FROM pet_produtos WHERE ativo = 1) AS produtos_ativos,
            (SELECT COUNT(*) FROM pet_produtos
             WHERE ativo = 1 AND controla_estoque = 1 AND estoque_atual <= estoque_minimo) AS estoque_baixo,
            (SELECT COALESCE(SUM(total), 0) FROM pet_vendas
             WHERE status = 'concluida' AND DATE(concluida_em) = CURDATE()) AS vendas_hoje,
            (SELECT COALESCE(SUM(total), 0) FROM pet_vendas
             WHERE status = 'concluida' AND concluida_em >= DATE_FORMAT(CURDATE(), '%Y-%m-01')) AS vendas_mes"
    )->get_result()->fetch_assoc();

    $sales = pet_execute(
        "SELECT calendario.dia,
                COALESCE(SUM(CASE WHEN v.status = 'concluida' THEN v.total ELSE 0 END), 0) AS total,
                COUNT(CASE WHEN v.status = 'concluida' THEN 1 END) AS quantidade
         FROM (
            SELECT CURDATE() - INTERVAL 6 DAY AS dia UNION ALL
            SELECT CURDATE() - INTERVAL 5 DAY UNION ALL
            SELECT CURDATE() - INTERVAL 4 DAY UNION ALL
            SELECT CURDATE() - INTERVAL 3 DAY UNION ALL
            SELECT CURDATE() - INTERVAL 2 DAY UNION ALL
            SELECT CURDATE() - INTERVAL 1 DAY UNION ALL
            SELECT CURDATE()
         ) calendario
         LEFT JOIN pet_vendas v ON DATE(v.concluida_em) = calendario.dia
         GROUP BY calendario.dia
         ORDER BY calendario.dia"
    )->get_result()->fetch_all(MYSQLI_ASSOC);

    $categories = pet_execute(
        "SELECT categoria, COUNT(*) AS produtos, COALESCE(SUM(estoque_atual), 0) AS estoque
         FROM pet_produtos WHERE ativo = 1 GROUP BY categoria ORDER BY produtos DESC, categoria"
    )->get_result()->fetch_all(MYSQLI_ASSOC);

    $grooming = pet_execute(
        "SELECT status, COUNT(*) AS quantidade
         FROM pet_banho_tosa_agendamentos
         WHERE inicio_em >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
           AND inicio_em < DATE_ADD(LAST_DAY(CURDATE()), INTERVAL 1 DAY)
         GROUP BY status ORDER BY quantidade DESC, status"
    )->get_result()->fetch_all(MYSQLI_ASSOC);

    $species = pet_execute(
        "SELECT especie, COUNT(*) AS quantidade
         FROM pet_animais WHERE ativo = 1
         GROUP BY especie ORDER BY quantidade DESC, especie LIMIT 8"
    )->get_result()->fetch_all(MYSQLI_ASSOC);

    json_response([
        'ok' => true,
        'versao' => PET_VERSION,
        'gerado_em' => date(DATE_ATOM),
        'data' => [
            'totais' => $totals,
            'vendas_7_dias' => $sales,
            'produtos_por_categoria' => $categories,
            'estetica_por_status' => $grooming,
            'animais_por_especie' => $species,
        ],
    ]);
} catch (Throwable $exception) {
    pet_api_exception($exception, 'Nao foi possivel montar os indicadores do modulo Pet.');
}
