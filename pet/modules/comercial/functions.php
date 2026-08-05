<?php
declare(strict_types=1);

function pet_comercial_product_for_update(mysqli $conn, int $productId): array
{
    $stmt = $conn->prepare(
        'SELECT id, sku, nome, preco_venda, estoque_atual, controla_estoque, ativo
         FROM pet_produtos WHERE id = ? FOR UPDATE'
    );
    $stmt->bind_param('i', $productId);
    $stmt->execute();
    $product = $stmt->get_result()->fetch_assoc();

    if (!$product) {
        throw new PetDomainException('Produto nao encontrado.', 'PRODUTO_NAO_ENCONTRADO', 404);
    }

    return $product;
}

function pet_comercial_move_stock(
    mysqli $conn,
    array $context,
    int $productId,
    string $type,
    float $quantity,
    string $reason,
    ?float $unitCost = null,
    ?string $referenceType = null,
    ?int $referenceId = null
): array {
    $allowed = ['entrada', 'saida', 'ajuste_positivo', 'ajuste_negativo', 'venda', 'estorno'];
    if (!in_array($type, $allowed, true) || $quantity <= 0) {
        throw new PetDomainException('Movimento de estoque invalido.', 'MOVIMENTO_INVALIDO');
    }

    $product = pet_comercial_product_for_update($conn, $productId);
    $previous = round((float) $product['estoque_atual'], 3);
    $positive = in_array($type, ['entrada', 'ajuste_positivo', 'estorno'], true);
    $next = round($previous + ($positive ? $quantity : -$quantity), 3);

    if ((int) $product['controla_estoque'] === 1 && $next < 0) {
        throw new PetDomainException(
            'Estoque insuficiente para ' . (string) $product['nome'] . '.',
            'ESTOQUE_INSUFICIENTE',
            409
        );
    }

    if ((int) $product['controla_estoque'] !== 1) {
        $next = $previous;
    }

    $update = $conn->prepare('UPDATE pet_produtos SET estoque_atual = ? WHERE id = ?');
    $update->bind_param('di', $next, $productId);
    $update->execute();

    $userId = (int) $context['id'];
    $insert = $conn->prepare(
        'INSERT INTO pet_estoque_movimentos
            (produto_id, tipo, quantidade, estoque_anterior, estoque_novo, custo_unitario,
             referencia_tipo, referencia_id, motivo, criado_por)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $insert->bind_param(
        'isddddsisi',
        $productId,
        $type,
        $quantity,
        $previous,
        $next,
        $unitCost,
        $referenceType,
        $referenceId,
        $reason,
        $userId
    );
    $insert->execute();

    return ['anterior' => $previous, 'novo' => $next, 'produto' => $product];
}

function pet_comercial_sale_number(int $saleId): string
{
    return 'V' . date('Ymd') . '-' . str_pad((string) $saleId, 6, '0', STR_PAD_LEFT);
}
