<?php
declare(strict_types=1);

function pet_estetica_quote(array $input): array
{
    $rawItems = $input['servicos'] ?? [];
    if (!is_array($rawItems) || $rawItems === []) {
        $singleId = filter_var($input['servico_id'] ?? null, FILTER_VALIDATE_INT);
        $rawItems = $singleId ? [['servico_id' => (int) $singleId, 'quantidade' => 1]] : [];
    }

    if ($rawItems === [] || count($rawItems) > 20) {
        throw new PetDomainException('Selecione ao menos um servico.', 'SERVICOS_INVALIDOS');
    }

    $items = [];
    $total = 0.0;
    $duration = 0;
    $seen = [];

    foreach ($rawItems as $rawItem) {
        if (!is_array($rawItem)) {
            throw new PetDomainException('Lista de servicos invalida.', 'SERVICOS_INVALIDOS');
        }

        $serviceId = filter_var($rawItem['servico_id'] ?? null, FILTER_VALIDATE_INT);
        $quantity = pet_nullable_decimal($rawItem['quantidade'] ?? 1, 0.01, 20);
        if (!$serviceId || $quantity === null) {
            throw new PetDomainException('Revise o servico e a quantidade.', 'SERVICOS_INVALIDOS');
        }
        if (isset($seen[(int) $serviceId])) {
            throw new PetDomainException('O mesmo servico foi informado mais de uma vez.', 'SERVICO_DUPLICADO');
        }

        $stmt = pet_execute(
            'SELECT id, nome, duracao_minutos, preco, ativo FROM pet_servicos WHERE id = ? LIMIT 1',
            'i',
            [(int) $serviceId]
        );
        $service = $stmt->get_result()->fetch_assoc();
        if (!$service || (int) $service['ativo'] !== 1) {
            throw new PetDomainException('Um dos servicos selecionados nao esta disponivel.', 'SERVICO_INDISPONIVEL', 409);
        }

        $unitPrice = round((float) $service['preco'], 2);
        $subtotal = round($unitPrice * $quantity, 2);
        $items[] = [
            'servico_id' => (int) $service['id'],
            'servico_nome' => (string) $service['nome'],
            'quantidade' => $quantity,
            'preco_unitario' => $unitPrice,
            'subtotal' => $subtotal,
            'observacoes' => pet_nullable_text($rawItem['observacoes'] ?? null, 500),
        ];
        $total += $subtotal;
        $duration += (int) round(((int) $service['duracao_minutos']) * $quantity);
        $seen[(int) $serviceId] = true;
    }

    return [
        'itens' => $items,
        'total' => round($total, 2),
        'duracao_minutos' => max(1, $duration),
    ];
}

function pet_estetica_replace_items(mysqli $conn, int $appointmentId, array $items): void
{
    $delete = $conn->prepare('DELETE FROM pet_banho_tosa_itens WHERE agendamento_id = ?');
    $delete->bind_param('i', $appointmentId);
    $delete->execute();

    $insert = $conn->prepare(
        'INSERT INTO pet_banho_tosa_itens
            (agendamento_id, servico_id, servico_nome, quantidade, preco_unitario, subtotal, observacoes)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );

    foreach ($items as $item) {
        $serviceId = (int) $item['servico_id'];
        $name = (string) $item['servico_nome'];
        $quantity = (float) $item['quantidade'];
        $unitPrice = (float) $item['preco_unitario'];
        $subtotal = (float) $item['subtotal'];
        $notes = $item['observacoes'];
        $insert->bind_param('iisddds', $appointmentId, $serviceId, $name, $quantity, $unitPrice, $subtotal, $notes);
        $insert->execute();
    }
}
