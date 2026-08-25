<?php
declare(strict_types=1);

namespace App\Domain\Obra;

use InvalidArgumentException;
use PDO;
use Throwable;

final class ObraService
{
    private const DEFAULT_STAGES = [
        'Fundação',
        'Estrutura',
        'Alvenaria',
        'Cobertura',
        'Elétrica / Hidráulica',
        'Revestimentos',
        'Acabamento',
    ];

    public function __construct(private readonly PDO $db)
    {
    }

    public function criar(array $data): int
    {
        $this->validate($data);
        $ownsTransaction = !$this->db->inTransaction();
        if ($ownsTransaction) {
            $this->db->beginTransaction();
        }

        try {
            $statement = $this->db->prepare(
                'INSERT INTO obras '
                . '(cliente_id,nome,descricao,endereco,cidade,estado,status,data_inicio,data_prev_fim,valor_total,progresso) '
                . 'VALUES (?,?,?,?,?,?,?,?,?,?,?)'
            );
            $statement->execute($this->parameters($data));
            $workId = (int) $this->db->lastInsertId();

            $stageStatement = $this->db->prepare('INSERT INTO obra_etapas (obra_id,nome,ordem) VALUES (?,?,?)');
            foreach (self::DEFAULT_STAGES as $index => $stage) {
                $stageStatement->execute([$workId, $stage, $index + 1]);
            }

            if ($ownsTransaction) {
                $this->db->commit();
            }
            return $workId;
        } catch (Throwable $exception) {
            if ($ownsTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }

    public function atualizar(int $workId, array $data): void
    {
        if ($workId <= 0) {
            throw new InvalidArgumentException('Obra inválida.');
        }
        $this->validate($data);
        $statement = $this->db->prepare(
            'UPDATE obras SET cliente_id=?,nome=?,descricao=?,endereco=?,cidade=?,estado=?,status=?,data_inicio=?,data_prev_fim=?,valor_total=?,progresso=? WHERE id=?'
        );
        $statement->execute([...$this->parameters($data), $workId]);
    }

    private function validate(array $data): void
    {
        if ((int) ($data['cliente_id'] ?? 0) <= 0) {
            throw new InvalidArgumentException('Selecione o cliente.');
        }
        if (trim((string) ($data['nome'] ?? '')) === '') {
            throw new InvalidArgumentException('Nome da obra obrigatório.');
        }
    }

    /** @return list<mixed> */
    private function parameters(array $data): array
    {
        return [
            (int) $data['cliente_id'],
            trim((string) $data['nome']),
            (string) ($data['descricao'] ?? ''),
            (string) ($data['endereco'] ?? ''),
            (string) ($data['cidade'] ?? ''),
            (string) ($data['estado'] ?? ''),
            (string) ($data['status'] ?? 'planejamento'),
            $data['data_inicio'] ?: null,
            $data['data_prev_fim'] ?: null,
            (float) ($data['valor_total'] ?? 0),
            max(0, min(100, (int) ($data['progresso'] ?? 0))),
        ];
    }
}
