<?php
declare(strict_types=1);

namespace App\Domain\Orcamento;

use InvalidArgumentException;
use PDO;
use Throwable;

final class OrcamentoService
{
    private const TIPOS = ['manual', 'excel', 'xml', 'pdf', 'caixa'];

    public function __construct(private readonly PDO $db)
    {
    }

    public function criar(int $obraId, string $titulo, string $tipo, string $observacao, array $itens, float $bdi = 0.0, ?int $revisaoDe = null): int
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

        if (!in_array($tipo, self::TIPOS, true)) {
            $tipo = 'manual';
        }

        return $this->transacao(function () use ($obraId, $clienteId, $titulo, $observacao, $tipo, $itens, $bdi, $revisaoDe): int {
            $this->db->prepare(
                'INSERT INTO orcamentos (obra_id, cliente_id, titulo, obs, tipo_origem, status, bdi_percentual, revisao_de) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([$obraId, $clienteId, $titulo, $observacao, $tipo, 'rascunho', self::bdi($bdi), $revisaoDe]);
            $orcamentoId = (int) $this->db->lastInsertId();

            $insert = $this->db->prepare(
                'INSERT INTO orcamento_itens (orcamento_id, etapa, ordem, descricao, unidade, quantidade, preco_unitario, categoria_id, obs) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $validos = 0;
            foreach ($itens as $indice => $item) {
                $normalizado = self::normalizarItem($item, $indice);
                if ($normalizado === null) {
                    continue;
                }
                $insert->execute([$orcamentoId, ...array_values($normalizado)]);
                $validos++;
            }
            if ($validos === 0) {
                throw new InvalidArgumentException('Informe ao menos um item válido.');
            }

            $this->recalcularTotais($orcamentoId);
            return $orcamentoId;
        });
    }

    /**
     * Atualiza cabeçalho e itens. Itens com "id" são alterados, sem "id" são incluídos
     * e os que não vierem mais na lista são removidos. Preços cotados são preservados.
     */
    public function atualizar(int $orcamentoId, string $titulo, string $observacao, float $bdi, array $itens): void
    {
        $atual = $this->buscar($orcamentoId);
        if (!OrcamentoStatus::editavel((string) $atual['status'])) {
            throw new InvalidArgumentException('Reabra o orçamento como rascunho antes de editar os itens.');
        }
        $titulo = trim($titulo);
        if ($titulo === '') {
            throw new InvalidArgumentException('Título é obrigatório.');
        }

        $this->transacao(function () use ($orcamentoId, $titulo, $observacao, $bdi, $itens): int {
            $this->db->prepare('UPDATE orcamentos SET titulo = ?, obs = ?, bdi_percentual = ? WHERE id = ?')
                ->execute([$titulo, $observacao, self::bdi($bdi), $orcamentoId]);

            $existentes = $this->db->prepare('SELECT id FROM orcamento_itens WHERE orcamento_id = ?');
            $existentes->execute([$orcamentoId]);
            $idsExistentes = array_map('intval', $existentes->fetchAll(PDO::FETCH_COLUMN));

            $update = $this->db->prepare(
                'UPDATE orcamento_itens SET etapa = ?, ordem = ?, descricao = ?, unidade = ?, quantidade = ?, preco_unitario = ?, categoria_id = ?, obs = ? WHERE id = ? AND orcamento_id = ?'
            );
            $insert = $this->db->prepare(
                'INSERT INTO orcamento_itens (orcamento_id, etapa, ordem, descricao, unidade, quantidade, preco_unitario, categoria_id, obs) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );

            $mantidos = [];
            foreach (array_values($itens) as $indice => $item) {
                $normalizado = self::normalizarItem($item, $indice);
                if ($normalizado === null) {
                    continue;
                }
                $id = (int) ($item['id'] ?? 0);
                if ($id > 0 && in_array($id, $idsExistentes, true)) {
                    $update->execute([...array_values($normalizado), $id, $orcamentoId]);
                    $mantidos[] = $id;
                } else {
                    $insert->execute([$orcamentoId, ...array_values($normalizado)]);
                    $mantidos[] = (int) $this->db->lastInsertId();
                }
            }
            if ($mantidos === []) {
                throw new InvalidArgumentException('O orçamento precisa de ao menos um item válido.');
            }

            $remover = array_diff($idsExistentes, $mantidos);
            if ($remover !== []) {
                $marcadores = implode(',', array_fill(0, count($remover), '?'));
                $this->db->prepare("DELETE FROM orcamento_itens WHERE orcamento_id = ? AND id IN ($marcadores)")
                    ->execute([$orcamentoId, ...array_values($remover)]);
            }

            $this->recalcularTotais($orcamentoId);
            return $orcamentoId;
        });
    }

    public function alterarStatus(int $orcamentoId, string $novoStatus): void
    {
        $atual = $this->buscar($orcamentoId);
        OrcamentoStatus::validarTransicao((string) $atual['status'], $novoStatus);
        $this->db->prepare('UPDATE orcamentos SET status = ? WHERE id = ?')->execute([$novoStatus, $orcamentoId]);
    }

    /** Cria uma nova revisão em rascunho com os mesmos itens, sem preços cotados. */
    public function duplicar(int $orcamentoId): int
    {
        $origem = $this->buscar($orcamentoId);
        $itens = $this->db->prepare(
            'SELECT etapa, ordem, descricao, unidade, quantidade, preco_unitario, categoria_id, obs AS codigo FROM orcamento_itens WHERE orcamento_id = ? ORDER BY ordem, id'
        );
        $itens->execute([$orcamentoId]);

        $titulo = preg_replace('/\s*\(revisão \d+\)$/u', '', (string) $origem['titulo']) ?? (string) $origem['titulo'];
        $revisoes = $this->db->prepare('SELECT COUNT(*) FROM orcamentos WHERE obra_id = ? AND (id = ? OR revisao_de = ?)');
        $raiz = (int) ($origem['revisao_de'] ?: $orcamentoId);
        $revisoes->execute([$origem['obra_id'], $raiz, $raiz]);
        $numero = (int) $revisoes->fetchColumn();

        return $this->criar(
            (int) $origem['obra_id'],
            mb_substr($titulo . ' (revisão ' . $numero . ')', 0, 200),
            (string) $origem['tipo_origem'],
            (string) ($origem['obs'] ?? ''),
            $itens->fetchAll(PDO::FETCH_ASSOC),
            (float) $origem['bdi_percentual'],
            $raiz
        );
    }

    public function excluir(int $orcamentoId): void
    {
        $this->buscar($orcamentoId);
        $compras = $this->db->prepare(
            'SELECT COUNT(*) FROM compras cp JOIN cotacoes co ON co.id = cp.cotacao_id WHERE co.orcamento_id = ?'
        );
        $compras->execute([$orcamentoId]);
        if ((int) $compras->fetchColumn() > 0) {
            throw new InvalidArgumentException('Há compras vinculadas às cotações deste orçamento. Cancele o orçamento em vez de excluir.');
        }
        $this->db->prepare('DELETE FROM orcamentos WHERE id = ?')->execute([$orcamentoId]);
    }

    public function recalcularTotais(int $orcamentoId): void
    {
        $this->db->prepare(
            'UPDATE orcamentos SET '
            . 'total_estimado = (SELECT COALESCE(SUM(preco_total), 0) FROM orcamento_itens WHERE orcamento_id = ?), '
            . 'total_cotado = (SELECT COALESCE(SUM(total_cotado), 0) FROM orcamento_itens WHERE orcamento_id = ?) '
            . 'WHERE id = ?'
        )->execute([$orcamentoId, $orcamentoId, $orcamentoId]);
    }

    /** Total com BDI aplicado sobre o custo direto. */
    public static function totalComBdi(float $custoDireto, float $bdiPercentual): float
    {
        return round($custoDireto * (1 + self::bdi($bdiPercentual) / 100), 2);
    }

    private function buscar(int $orcamentoId): array
    {
        $statement = $this->db->prepare('SELECT * FROM orcamentos WHERE id = ?');
        $statement->execute([$orcamentoId]);
        $orcamento = $statement->fetch(PDO::FETCH_ASSOC);
        if (!$orcamento) {
            throw new InvalidArgumentException('Orçamento não encontrado.');
        }
        return $orcamento;
    }

    /** @return array{etapa: ?string, ordem: int, descricao: string, unidade: string, quantidade: float, preco: float, categoria: ?int, codigo: ?string}|null */
    private static function normalizarItem(array $item, int $indice): ?array
    {
        $descricao = mb_substr(trim((string) ($item['descricao'] ?? '')), 0, 255);
        if ($descricao === '') {
            return null;
        }
        $etapa = mb_substr(trim((string) ($item['etapa'] ?? '')), 0, 150);
        $unidade = mb_substr(trim((string) ($item['unidade'] ?? 'UN')), 0, 20);
        $codigo = mb_substr(trim((string) ($item['codigo'] ?? '')), 0, 255);

        return [
            'etapa' => $etapa !== '' ? $etapa : null,
            'ordem' => max(0, (int) ($item['ordem'] ?? $indice + 1)),
            'descricao' => $descricao,
            'unidade' => $unidade !== '' ? $unidade : 'UN',
            'quantidade' => max(0, OrcamentoCalculator::decimal($item['quantidade'] ?? 1)),
            'preco' => max(0, OrcamentoCalculator::decimal($item['preco_unitario'] ?? 0)),
            'categoria' => (int) ($item['categoria_id'] ?? 0) ?: null,
            'codigo' => $codigo !== '' ? $codigo : null,
        ];
    }

    private static function bdi(float $bdi): float
    {
        return round(max(0.0, min(200.0, $bdi)), 2);
    }

    /** @param callable(): int $callback */
    private function transacao(callable $callback): int
    {
        $ownsTransaction = !$this->db->inTransaction();
        if ($ownsTransaction) {
            $this->db->beginTransaction();
        }
        try {
            $resultado = $callback();
            if ($ownsTransaction) {
                $this->db->commit();
            }
            return $resultado;
        } catch (Throwable $exception) {
            if ($ownsTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }
}
