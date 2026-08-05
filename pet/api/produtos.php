<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/modules/comercial/functions.php';

$context = pet_boot_api();
pet_require_permission($context, 'ver_comercial');

try {
    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if ($method === 'GET') {
        pet_produtos_listar();
    } elseif ($method === 'POST') {
        pet_require_permission($context, 'gerenciar_produtos');
        pet_produtos_criar($context);
    } elseif ($method === 'PUT') {
        pet_require_permission($context, 'gerenciar_produtos');
        pet_produtos_atualizar($context);
    } else {
        json_response(['ok' => false, 'erro' => 'Metodo nao permitido.'], 405);
    }
} catch (Throwable $exception) {
    pet_api_exception($exception, 'Nao foi possivel salvar o produto.');
}

function pet_produto_payload(array $input, bool $creating): array
{
    $sku = strtoupper(pet_text($input['sku'] ?? '', 60));
    $sku = (string) preg_replace('/[^A-Z0-9._-]/', '', $sku);
    $name = pet_text($input['nome'] ?? '', 180);
    $category = pet_text($input['categoria'] ?? 'outro', 30);
    $unit = strtolower(pet_text($input['unidade'] ?? 'un', 20));
    $cost = pet_nullable_decimal($input['preco_custo'] ?? null, 0, 99999999);
    $price = pet_nullable_decimal($input['preco_venda'] ?? null, 0, 99999999);
    $minimum = pet_nullable_decimal($input['estoque_minimo'] ?? 0, 0, 99999999, 3);
    $initial = $creating ? pet_nullable_decimal($input['estoque_inicial'] ?? 0, 0, 99999999, 3) : 0.0;
    $errors = [];

    if ($sku === '') $errors['sku'] = 'Informe um SKU unico.';
    if ($name === '') $errors['nome'] = 'Informe o nome do produto.';
    if (!in_array($category, ['racao', 'petisco', 'higiene', 'acessorio', 'medicamento', 'outro'], true)) {
        $errors['categoria'] = 'Categoria invalida.';
    }
    if ($unit === '') $errors['unidade'] = 'Informe a unidade.';
    if ($price === null) $errors['preco_venda'] = 'Informe um preco de venda valido.';
    if ($minimum === null) $errors['estoque_minimo'] = 'Informe um estoque minimo valido.';
    if ($initial === null) $errors['estoque_inicial'] = 'Informe um estoque inicial valido.';
    if ($errors) pet_validation_error($errors);

    return [
        'sku' => $sku,
        'nome' => $name,
        'categoria' => $category,
        'unidade' => $unit,
        'marca' => pet_nullable_text($input['marca'] ?? null, 100),
        'codigo_barras' => pet_nullable_text($input['codigo_barras'] ?? null, 80),
        'preco_custo' => $cost,
        'preco_venda' => $price,
        'estoque_minimo' => $minimum,
        'estoque_inicial' => $initial,
        'controla_estoque' => pet_bool($input['controla_estoque'] ?? true),
        'ativo' => pet_bool($input['ativo'] ?? true),
    ];
}

function pet_produtos_listar(): void
{
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if ($id) {
        $record = pet_execute('SELECT * FROM pet_produtos WHERE id = ? LIMIT 1', 'i', [(int) $id])
            ->get_result()->fetch_assoc();
        if (!$record) json_response(['ok' => false, 'erro' => 'Produto nao encontrado.'], 404);
        json_response(['ok' => true, 'data' => $record]);
    }

    $search = '%' . pet_text($_GET['q'] ?? '', 120) . '%';
    $category = pet_text($_GET['categoria'] ?? '', 30);
    $onlyLow = pet_bool($_GET['estoque_baixo'] ?? false);
    $includeInactive = pet_bool($_GET['inativos'] ?? false);
    $records = pet_execute(
        "SELECT id, sku, nome, categoria, unidade, marca, codigo_barras, preco_custo,
                preco_venda, estoque_atual, estoque_minimo, controla_estoque, ativo,
                CASE WHEN controla_estoque = 1 AND estoque_atual <= estoque_minimo THEN 1 ELSE 0 END AS estoque_baixo
         FROM pet_produtos
         WHERE (? = 1 OR ativo = 1)
           AND (? = '' OR categoria = ?)
           AND (? = 0 OR (controla_estoque = 1 AND estoque_atual <= estoque_minimo))
           AND (nome LIKE ? OR sku LIKE ? OR marca LIKE ? OR codigo_barras LIKE ?)
         ORDER BY estoque_baixo DESC, ativo DESC, nome
         LIMIT 300",
        'issiissss',
        [$includeInactive, $category, $category, $onlyLow, $search, $search, $search, $search]
    )->get_result()->fetch_all(MYSQLI_ASSOC);
    json_response(['ok' => true, 'data' => $records]);
}

function pet_produtos_criar(array $context): void
{
    $data = pet_produto_payload(pet_json_input(), true);
    $conn = db();
    $conn->begin_transaction();
    try {
        $userId = (int) $context['id'];
        $stmt = $conn->prepare(
            'INSERT INTO pet_produtos
                (sku, nome, categoria, unidade, marca, codigo_barras, preco_custo, preco_venda,
                 estoque_minimo, controla_estoque, ativo, criado_por)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->bind_param(
            'ssssssdddiii',
            $data['sku'], $data['nome'], $data['categoria'], $data['unidade'], $data['marca'],
            $data['codigo_barras'], $data['preco_custo'], $data['preco_venda'],
            $data['estoque_minimo'], $data['controla_estoque'], $data['ativo'], $userId
        );
        $stmt->execute();
        $id = (int) $stmt->insert_id;
        if ($data['estoque_inicial'] > 0) {
            pet_comercial_move_stock(
                $conn, $context, $id, 'entrada', (float) $data['estoque_inicial'],
                'Estoque inicial do produto', $data['preco_custo'], 'produto', $id
            );
        }
        $conn->commit();
    } catch (Throwable $exception) {
        $conn->rollback();
        throw $exception;
    }

    pet_audit($context, 'criar', 'produto', $id, ['sku' => $data['sku']]);
    json_response(['ok' => true, 'mensagem' => 'Produto criado.', 'data' => ['id' => $id]], 201);
}

function pet_produtos_atualizar(array $context): void
{
    $id = pet_query_id();
    if (!pet_record_exists('pet_produtos', $id)) {
        json_response(['ok' => false, 'erro' => 'Produto nao encontrado.'], 404);
    }
    $data = pet_produto_payload(pet_json_input(), false);
    pet_execute(
        'UPDATE pet_produtos SET sku = ?, nome = ?, categoria = ?, unidade = ?, marca = ?,
            codigo_barras = ?, preco_custo = ?, preco_venda = ?, estoque_minimo = ?,
            controla_estoque = ?, ativo = ? WHERE id = ?',
        'ssssssdddiii',
        [$data['sku'], $data['nome'], $data['categoria'], $data['unidade'], $data['marca'],
         $data['codigo_barras'], $data['preco_custo'], $data['preco_venda'],
         $data['estoque_minimo'], $data['controla_estoque'], $data['ativo'], $id]
    );
    pet_audit($context, 'atualizar', 'produto', $id, ['sku' => $data['sku']]);
    json_response(['ok' => true, 'mensagem' => 'Produto atualizado.', 'data' => ['id' => $id]]);
}
