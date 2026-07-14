<?php
require_once __DIR__ . '/includes/init.php';

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        json_response(['ok' => false, 'erro' => 'Metodo nao permitido.'], 405);
    }

    $conn = db();
    $rows = $conn->query('SELECT id, nome, raca, porte FROM animais ORDER BY id ASC')->fetch_all(MYSQLI_ASSOC);

    json_response([
        'ok' => true,
        'data' => $rows,
        'meta' => [
            'origem' => 'lemeinformatica.com.br',
            'tabela' => 'animais',
            'total' => count($rows),
            'gerado_em' => date('c')
        ]
    ]);
} catch (Throwable $e) {
    json_response([
        'ok' => false,
        'erro' => 'Erro ao gerar dados para Power BI.',
        'detalhe' => $e->getMessage()
    ], 500);
}
