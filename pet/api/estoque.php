<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/modules/comercial/functions.php';

$context = pet_boot_api();
pet_require_permission($context, 'ver_comercial');

try {
    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if ($method === 'GET') {
        pet_estoque_listar();
    } elseif ($method === 'POST') {
        pet_require_permission($context, 'gerenciar_estoque');
        pet_estoque_movimentar($context);
    } else {
        json_response(['ok' => false, 'erro' => 'Metodo nao permitido.'], 405);
    }
} catch (Throwable $exception) {
    pet_api_exception($exception, 'Nao foi possivel movimentar o estoque.');
}

function pet_estoque_listar(): void
{
    $productId = (int) (filter_input(INPUT_GET, 'produto_id', FILTER_VALIDATE_INT) ?: 0);
    $records = pet_execute(
        "SELECT m.id, m.produto_id, m.tipo, m.quantidade, m.estoque_anterior, m.estoque_novo,
                m.custo_unitario, m.referencia_tipo, m.referencia_id, m.motivo, m.criado_em,
                p.nome AS produto_nome, p.sku, p.unidade, u.nome AS usuario_nome
         FROM pet_estoque_movimentos m
         INNER JOIN pet_produtos p ON p.id = m.produto_id
         LEFT JOIN usuarios_admin u ON u.id = m.criado_por
         WHERE (? = 0 OR m.produto_id = ?)
         ORDER BY m.criado_em DESC, m.id DESC
         LIMIT 200",
        'ii',
        [$productId, $productId]
    )->get_result()->fetch_all(MYSQLI_ASSOC);
    json_response(['ok' => true, 'data' => $records]);
}

function pet_estoque_movimentar(array $context): void
{
    $input = pet_json_input();
    $productId = filter_var($input['produto_id'] ?? null, FILTER_VALIDATE_INT);
    $type = pet_text($input['tipo'] ?? '', 30);
    $quantity = pet_nullable_decimal($input['quantidade'] ?? null, 0.001, 99999999, 3);
    $cost = pet_nullable_decimal($input['custo_unitario'] ?? null, 0, 99999999);
    $reason = pet_text($input['motivo'] ?? '', 500);
    $errors = [];

    if (!$productId || !pet_record_exists('pet_produtos', (int) $productId)) {
        $errors['produto_id'] = 'Selecione um produto.';
    }
    if (!in_array($type, ['entrada', 'saida', 'ajuste_positivo', 'ajuste_negativo'], true)) {
        $errors['tipo'] = 'Tipo de movimento invalido.';
    }
    if ($quantity === null) $errors['quantidade'] = 'Informe uma quantidade valida.';
    if ($reason === '') $errors['motivo'] = 'Informe o motivo do movimento.';
    if ($errors) pet_validation_error($errors);

    $conn = db();
    $conn->begin_transaction();
    try {
        $result = pet_comercial_move_stock(
            $conn, $context, (int) $productId, $type, (float) $quantity,
            $reason, $cost, 'movimento_manual', null
        );
        $conn->commit();
    } catch (Throwable $exception) {
        $conn->rollback();
        throw $exception;
    }

    pet_audit($context, 'movimentar', 'estoque', (int) $productId, [
        'tipo' => $type,
        'quantidade' => $quantity,
        'estoque_novo' => $result['novo'],
    ]);
    json_response(['ok' => true, 'mensagem' => 'Estoque atualizado.', 'data' => $result]);
}
