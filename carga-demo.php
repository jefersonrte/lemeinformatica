<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/http.php';
require_once __DIR__ . '/includes/database.php';

apply_cors();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_api_key();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    json_response(['ok' => false, 'erro' => 'Metodo nao permitido.'], 405);
}

$input = request_json();
if (($input['confirmacao'] ?? '') !== 'CRIAR_1000_REGISTROS') {
    json_response([
        'ok' => false,
        'erro' => 'Confirmacao da carga invalida.'
    ], 422);
}

function carregar_colunas(mysqli $conn, string $table): array
{
    $columns = [];
    $result = $conn->query('SHOW COLUMNS FROM ' . $table);
    while ($column = $result->fetch_assoc()) {
        $columns[] = $column['Field'];
    }
    return $columns;
}

function migrar_tabela_alimentos(mysqli $conn): void
{
    $tableExists = $conn->query("SHOW TABLES LIKE 'alimentos'")->num_rows > 0;
    if (!$tableExists) {
        return;
    }

    $columns = carregar_colunas($conn, 'alimentos');
    if (in_array('tipo', $columns, true) && !in_array('nome', $columns, true)) {
        $conn->query(
            'ALTER TABLE alimentos
             CHANGE COLUMN tipo nome VARCHAR(100) NOT NULL,
             CHANGE COLUMN porte categoria VARCHAR(80) NOT NULL,
             CHANGE COLUMN embalagem unidade VARCHAR(30) NOT NULL'
        );
        $columns = carregar_colunas($conn, 'alimentos');
    }

    if (!in_array('preco', $columns, true)) {
        $conn->query('ALTER TABLE alimentos ADD COLUMN preco DECIMAL(10,2) NOT NULL DEFAULT 0');
    }
    if (!in_array('criado_em', $columns, true)) {
        $conn->query('ALTER TABLE alimentos ADD COLUMN criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP');
    }
}

try {
    $authSql = file_get_contents(__DIR__ . '/sql/create_auth_tables.sql');
    $seedSql = file_get_contents(__DIR__ . '/sql/seed_1000_animais_alimentos.sql');
    if ($authSql === false || $seedSql === false || trim($authSql) === '' || trim($seedSql) === '') {
        throw new RuntimeException('Arquivo de carga nao encontrado.');
    }
    $sql = $authSql . "\n" . $seedSql;

    $conn = db();
    migrar_tabela_alimentos($conn);
    if (!$conn->multi_query($sql)) {
        throw new RuntimeException('Nao foi possivel iniciar a carga.', (int) $conn->errno);
    }

    do {
        $result = $conn->store_result();
        if ($result instanceof mysqli_result) {
            $result->free();
        }
    } while ($conn->more_results() && $conn->next_result());

    if ($conn->errno) {
        throw new RuntimeException('Uma etapa da carga falhou.', (int) $conn->errno);
    }

    $totalAnimais = (int) $conn->query('SELECT COUNT(*) AS total FROM animais')->fetch_assoc()['total'];
    $totalAlimentos = (int) $conn->query('SELECT COUNT(*) AS total FROM alimentos')->fetch_assoc()['total'];
    $lote = $conn->query(
        "SELECT registros, executado_em
         FROM cargas_dados_demo
         WHERE codigo = 'animais_alimentos_1000_v1'
         LIMIT 1"
    )->fetch_assoc();

    json_response([
        'ok' => true,
        'mensagem' => 'Carga de demonstracao concluida.',
        'data' => [
            'registros_do_lote' => (int) ($lote['registros'] ?? 0),
            'executado_em' => $lote['executado_em'] ?? null,
            'total_animais' => $totalAnimais,
            'total_alimentos' => $totalAlimentos
        ]
    ]);
} catch (Throwable $e) {
    json_response([
        'ok' => false,
        'codigo' => 'CARGA_DADOS_FALHOU',
        'erro' => 'Nao foi possivel criar os dados de demonstracao.',
        'erro_banco' => (int) $e->getCode()
    ], 500);
}
