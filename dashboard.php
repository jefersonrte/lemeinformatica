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

    $totalAlimentos = 0;
    $porCategoria = [];
    $alimentosRecentes = [];

    try {
        $totalAlimentos = (int) $conn->query('SELECT COUNT(*) AS total FROM alimentos')->fetch_assoc()['total'];
        $porCategoria = $conn->query(
            "SELECT COALESCE(NULLIF(TRIM(categoria), ''), 'Sem categoria') AS label, COUNT(*) AS total
             FROM alimentos
             GROUP BY label
             ORDER BY total DESC, label ASC"
        )->fetch_all(MYSQLI_ASSOC);
        $alimentosRecentes = $conn->query(
            'SELECT id, nome, categoria, unidade, preco FROM alimentos ORDER BY id DESC LIMIT 10'
        )->fetch_all(MYSQLI_ASSOC);
    } catch (mysqli_sql_exception $e) {
        if ((int) $e->getCode() !== 1146) {
            throw $e;
        }
    }

    json_response([
        'ok' => true,
        'data' => [
            'total_animais' => $total,
            'total_alimentos' => $totalAlimentos,
            'por_porte' => $porPorte,
            'por_raca' => $porRaca,
            'por_categoria_alimento' => $porCategoria,
            'recentes' => $recentes,
            'alimentos_recentes' => $alimentosRecentes
        ]
    ]);
} catch (Throwable $e) {
    json_response([
        'ok' => false,
        'codigo' => 'API_BANCO_INDISPONIVEL',
        'erro' => 'Nao foi possivel consultar o banco principal agora.'
    ], 500);
}
