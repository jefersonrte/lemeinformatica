<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/modules/comercial/functions.php';

$context = pet_boot_api();
pet_require_permission($context, 'ver_comercial');

try {
    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if ($method === 'GET') {
        pet_vendas_listar();
    } elseif ($method === 'POST') {
        pet_require_permission($context, 'registrar_venda');
        pet_vendas_criar($context);
    } elseif ($method === 'PUT') {
        pet_require_permission($context, 'cancelar_venda');
        pet_vendas_cancelar($context);
    } else {
        json_response(['ok' => false, 'erro' => 'Metodo nao permitido.'], 405);
    }
} catch (Throwable $exception) {
    pet_api_exception($exception, 'Nao foi possivel concluir a venda.');
}

function pet_vendas_listar(): void
{
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if ($id) {
        $sale = pet_execute(
            'SELECT v.*, t.nome AS tutor_nome, u.nome AS atendente_nome,
                    uc.nome AS cancelada_por_nome
             FROM pet_vendas v
             LEFT JOIN pet_tutores t ON t.id = v.tutor_id
             LEFT JOIN usuarios_admin u ON u.id = v.criado_por
             LEFT JOIN usuarios_admin uc ON uc.id = v.cancelada_por
             WHERE v.id = ? LIMIT 1',
            'i',
            [(int) $id]
        )->get_result()->fetch_assoc();
        if (!$sale) {
            json_response(['ok' => false, 'erro' => 'Venda nao encontrada.'], 404);
        }
        $sale['itens'] = pet_execute(
            'SELECT produto_id, produto_nome, sku, quantidade, preco_unitario, subtotal
             FROM pet_venda_itens WHERE venda_id = ? ORDER BY id',
            'i',
            [(int) $id]
        )->get_result()->fetch_all(MYSQLI_ASSOC);
        json_response(['ok' => true, 'data' => $sale]);
    }

    $status = pet_text($_GET['status'] ?? '', 20);
    $search = '%' . pet_text($_GET['q'] ?? '', 100) . '%';
    $records = pet_execute(
        "SELECT v.id, v.numero, v.status, v.subtotal, v.desconto, v.total,
                v.forma_pagamento, v.concluida_em, v.cancelada_em,
                t.nome AS tutor_nome, u.nome AS atendente_nome,
                (SELECT COUNT(*) FROM pet_venda_itens i WHERE i.venda_id = v.id) AS itens_total
         FROM pet_vendas v
         LEFT JOIN pet_tutores t ON t.id = v.tutor_id
         LEFT JOIN usuarios_admin u ON u.id = v.criado_por
         WHERE (? = '' OR v.status = ?)
           AND (v.numero LIKE ? OR COALESCE(t.nome, '') LIKE ? OR EXISTS (
                SELECT 1 FROM pet_venda_itens i
                WHERE i.venda_id = v.id AND (i.produto_nome LIKE ? OR i.sku LIKE ?)
           ))
         ORDER BY v.concluida_em DESC
         LIMIT 100",
        'ssssss',
        [$status, $status, $search, $search, $search, $search]
    )->get_result()->fetch_all(MYSQLI_ASSOC);

    json_response(['ok' => true, 'data' => $records]);
}

function pet_vendas_payload(array $input): array
{
    $tutorId = filter_var($input['tutor_id'] ?? null, FILTER_VALIDATE_INT);
    $payment = pet_text($input['forma_pagamento'] ?? 'pix', 20);
    $discount = pet_nullable_decimal($input['desconto'] ?? 0, 0, 99999999);
    $items = $input['itens'] ?? [];
    $errors = [];

    if ($tutorId && !pet_record_exists('pet_tutores', (int) $tutorId)) {
        $errors['tutor_id'] = 'Tutor nao encontrado.';
    }
    if (!in_array($payment, ['dinheiro', 'pix', 'debito', 'credito', 'outro'], true)) {
        $errors['forma_pagamento'] = 'Forma de pagamento invalida.';
    }
    if ($discount === null) {
        $errors['desconto'] = 'Informe um desconto valido.';
    }
    if (!is_array($items) || $items === []) {
        $errors['itens'] = 'Inclua ao menos um produto na venda.';
    }

    $normalized = [];
    if (is_array($items)) {
        foreach ($items as $index => $item) {
            if (!is_array($item)) {
                $errors['itens'] = 'Item de venda invalido.';
                break;
            }
            $productId = filter_var($item['produto_id'] ?? null, FILTER_VALIDATE_INT);
            $quantity = pet_nullable_decimal($item['quantidade'] ?? null, 0.001, 99999999, 3);
            if (!$productId || $quantity === null || $quantity <= 0) {
                $errors['itens.' . $index] = 'Selecione o produto e informe uma quantidade positiva.';
                continue;
            }
            $normalized[] = ['produto_id' => (int) $productId, 'quantidade' => round($quantity, 3)];
        }
    }

    if ($errors) {
        pet_validation_error($errors);
    }

    $byProduct = [];
    foreach ($normalized as $item) {
        $key = $item['produto_id'];
        $byProduct[$key] = ($byProduct[$key] ?? 0.0) + $item['quantidade'];
    }
    ksort($byProduct, SORT_NUMERIC);
    $normalized = [];
    foreach ($byProduct as $productId => $quantity) {
        if ($quantity > 99999999) {
            pet_validation_error(['itens' => 'A quantidade total de um produto excede o limite permitido.']);
        }
        $normalized[] = ['produto_id' => (int) $productId, 'quantidade' => round($quantity, 3)];
    }

    return [
        'tutor_id' => $tutorId ? (int) $tutorId : null,
        'forma_pagamento' => $payment,
        'desconto' => (float) $discount,
        'observacoes' => pet_nullable_text($input['observacoes'] ?? null, 1000),
        'itens' => $normalized,
    ];
}

function pet_vendas_criar(array $context): void
{
    $data = pet_vendas_payload(pet_json_input());
    $conn = db();
    $conn->begin_transaction();

    try {
        $items = [];
        $subtotal = 0.0;
        foreach ($data['itens'] as $requested) {
            $product = pet_comercial_product_for_update($conn, $requested['produto_id']);
            if ((int) $product['ativo'] !== 1) {
                throw new PetDomainException(
                    'O produto ' . (string) $product['nome'] . ' esta inativo.',
                    'PRODUTO_INATIVO',
                    409
                );
            }
            $lineTotal = round((float) $product['preco_venda'] * $requested['quantidade'], 2);
            $items[] = [
                'produto' => $product,
                'quantidade' => $requested['quantidade'],
                'preco_unitario' => round((float) $product['preco_venda'], 2),
                'subtotal' => $lineTotal,
            ];
            $subtotal = round($subtotal + $lineTotal, 2);
        }

        if ($data['desconto'] > $subtotal) {
            throw new PetDomainException('O desconto nao pode superar o subtotal.', 'DESCONTO_INVALIDO');
        }
        $total = round($subtotal - $data['desconto'], 2);
        $userId = (int) $context['id'];
        $completedAt = date('Y-m-d H:i:s');

        $insert = $conn->prepare(
            'INSERT INTO pet_vendas
                (tutor_id, subtotal, desconto, total, forma_pagamento, observacoes,
                 concluida_em, criado_por)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $insert->bind_param(
            'idddsssi',
            $data['tutor_id'], $subtotal, $data['desconto'], $total,
            $data['forma_pagamento'], $data['observacoes'], $completedAt, $userId
        );
        $insert->execute();
        $saleId = (int) $insert->insert_id;
        $number = pet_comercial_sale_number($saleId);
        $setNumber = $conn->prepare('UPDATE pet_vendas SET numero = ? WHERE id = ?');
        $setNumber->bind_param('si', $number, $saleId);
        $setNumber->execute();

        $insertItem = $conn->prepare(
            'INSERT INTO pet_venda_itens
                (venda_id, produto_id, produto_nome, sku, quantidade, preco_unitario, subtotal)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        foreach ($items as $item) {
            $productId = (int) $item['produto']['id'];
            $productName = (string) $item['produto']['nome'];
            $sku = (string) $item['produto']['sku'];
            $quantity = (float) $item['quantidade'];
            $unitPrice = (float) $item['preco_unitario'];
            $lineTotal = (float) $item['subtotal'];
            $insertItem->bind_param(
                'iissddd',
                $saleId, $productId, $productName, $sku, $quantity, $unitPrice, $lineTotal
            );
            $insertItem->execute();
            pet_comercial_move_stock(
                $conn,
                $context,
                $productId,
                'venda',
                $quantity,
                'Venda ' . $number,
                null,
                'venda',
                $saleId
            );
        }

        $conn->commit();
    } catch (Throwable $exception) {
        $conn->rollback();
        throw $exception;
    }

    pet_audit($context, 'criar', 'venda', $saleId, ['numero' => $number, 'total' => $total]);
    json_response([
        'ok' => true,
        'mensagem' => 'Venda concluida e estoque atualizado.',
        'data' => ['id' => $saleId, 'numero' => $number, 'total' => $total],
    ], 201);
}

function pet_vendas_cancelar(array $context): void
{
    $id = pet_query_id();
    $input = pet_json_input();
    $reason = pet_text($input['motivo'] ?? '', 500);
    if ($reason === '') {
        pet_validation_error(['motivo' => 'Informe o motivo do cancelamento.']);
    }

    $conn = db();
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare('SELECT id, numero, status FROM pet_vendas WHERE id = ? FOR UPDATE');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $sale = $stmt->get_result()->fetch_assoc();
        if (!$sale) {
            throw new PetDomainException('Venda nao encontrada.', 'VENDA_NAO_ENCONTRADA', 404);
        }
        if ((string) $sale['status'] === 'cancelada') {
            throw new PetDomainException('A venda ja esta cancelada.', 'VENDA_JA_CANCELADA', 409);
        }

        $itemsStmt = $conn->prepare('SELECT produto_id, quantidade FROM pet_venda_itens WHERE venda_id = ? ORDER BY id');
        $itemsStmt->bind_param('i', $id);
        $itemsStmt->execute();
        $items = $itemsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        foreach ($items as $item) {
            pet_comercial_move_stock(
                $conn,
                $context,
                (int) $item['produto_id'],
                'estorno',
                (float) $item['quantidade'],
                'Cancelamento ' . (string) $sale['numero'] . ': ' . $reason,
                null,
                'venda',
                $id
            );
        }

        $cancelledAt = date('Y-m-d H:i:s');
        $userId = (int) $context['id'];
        $update = $conn->prepare(
            "UPDATE pet_vendas SET status = 'cancelada', cancelada_em = ?, cancelada_por = ?,
                observacoes = CONCAT_WS('\n', observacoes, ?) WHERE id = ?"
        );
        $note = 'Cancelamento: ' . $reason;
        $update->bind_param('sisi', $cancelledAt, $userId, $note, $id);
        $update->execute();
        $conn->commit();
    } catch (Throwable $exception) {
        $conn->rollback();
        throw $exception;
    }

    pet_audit($context, 'cancelar', 'venda', $id, ['motivo' => $reason]);
    json_response(['ok' => true, 'mensagem' => 'Venda cancelada e estoque estornado.']);
}
