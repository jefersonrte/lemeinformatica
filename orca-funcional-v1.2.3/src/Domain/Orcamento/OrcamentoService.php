<?php
declare(strict_types=1);

namespace App\Domain\Orcamento;

use InvalidArgumentException;
use PDO;
use Throwable;

final class OrcamentoService
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function criar(int $obraId, string $titulo, string $tipo, string $observacao, array $itens): int
    {
        $titulo = trim($titulo);
        if ($obraId <= 0 || $titulo === '') {
            throw new InvalidArgumentException('Obra e título são obrigatórios.');
        }

        $obra = $this->db->prepare('SELECT cliente_id FROM obras WHERE id = ? LIMIT 1');
        $obra->execute([$obraId]);
        $clienteId = (int) $obra->fetchColumn();
        if ($clienteId <= 0) {
            throw new InvalidArgumentException('A obra informada não existe.');
        }

        $tiposPermitidos = ['manual', 'excel', 'xml', 'pdf', 'caixa'];
        if (!in_array($tipo, $tiposPermitidos, true)) {
            $tipo = 'manual';
        }

        $ownsTransaction = !$this->db->inTransaction();
        if ($ownsTransaction) {
            $this->db->beginTransaction();
        }
        try {
            $this->db->prepare(
                'INSERT INTO orcamentos (obra_id, cliente_id, titulo, obs, tipo_origem, status) VALUES (?, ?, ?, ?, ?, ?)'
            )->execute([$obraId, $clienteId, $titulo, $observacao, $tipo, 'rascunho']);
            $orcamentoId = (int) $this->db->lastInsertId();
            $insertItem = $this->db->prepare(
                'INSERT INTO orcamento_itens (orcamento_id, descricao, unidade, quantidade, preco_unitario, categoria_id, obs) VALUES (?, ?, ?, ?, ?, ?, ?)'
            );

            $itensNormalizados = [];
            foreach ($itens as $item) {
                $descricao = trim((string) ($item['descricao'] ?? ''));
                if ($descricao === '') {
                    continue;
                }
                $quantidade = max(0, OrcamentoCalculator::decimal($item['quantidade'] ?? 1));
                $preco = max(0, OrcamentoCalculator::decimal($item['preco_unitario'] ?? 0));
                $categoriaId = (int) ($item['categoria_id'] ?? 0) ?: null;
                $unidade = mb_substr(trim((string) ($item['unidade'] ?? 'UN')), 0, 20);
                $codigo = mb_substr(trim((string) ($item['codigo'] ?? '')), 0, 255);

                $insertItem->execute([$orcamentoId, $descricao, $unidade ?: 'UN', $quantidade, $preco, $categoriaId, $codigo ?: null]);
                $itensNormalizados[] = ['quantidade' => $quantidade, 'preco_unitario' => $preco];
            }

            if ($itensNormalizados === []) {
                throw new InvalidArgumentException('Informe ao menos um item válido.');
            }

            $total = OrcamentoCalculator::total($itensNormalizados);
            $this->db->prepare('UPDATE orcamentos SET total_estimado = ? WHERE id = ?')->execute([$total, $orcamentoId]);
            if ($ownsTransaction) {
                $this->db->commit();
            }
            return $orcamentoId;
        } catch (Throwable $exception) {
            if ($ownsTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }
}
