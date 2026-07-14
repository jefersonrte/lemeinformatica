<?php
require_once __DIR__ . '/includes/init.php';

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        json_response(['ok' => false, 'erro' => 'Metodo nao permitido.'], 405);
    }

    $conn = db();

    $total = (int) $conn->query('SELECT COUNT(*) AS total FROM animais')->fetch_assoc()['total'];

    $porPorte = $conn->query(
        "SELECT COALESCE(NULLIF(TRIM(porte), ''), 'Sem porte') AS label, COUNT(*) AS total
         FROM animais
         GROUP BY label
         ORDER BY total DESC, label ASC"
    )->fetch_all(MYSQLI_ASSOC);

    $porRaca = $conn->query(
        "SELECT COALESCE(NULLIF(TRIM(raca), ''), 'Sem raca') AS label, COUNT(*) AS total
         FROM animais
         GROUP BY label
         ORDER BY total DESC, label ASC"
    )->fetch_all(MYSQLI_ASSOC);

    $recentes = $conn->query(
        'SELECT id, nome, raca, porte FROM animais ORDER BY id DESC LIMIT 10'
    )->fetch_all(MYSQLI_ASSOC);

    json_response([
        'ok' => true,
        'data' => [
            'total_animais' => $total,
            'por_porte' => $porPorte,
            'por_raca' => $porRaca,
            'recentes' => $recentes
        ]
    ]);
} catch (Throwable $e) {
    json_response([
        'ok' => false,
        'erro' => 'Erro ao montar dashboard.',
        'detalhe' => $e->getMessage()
    ], 500);
}
