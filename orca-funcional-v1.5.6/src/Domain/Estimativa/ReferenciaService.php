<?php
declare(strict_types=1);

namespace App\Domain\Estimativa;

use InvalidArgumentException;
use PDO;
use Throwable;

/** Base de referência para a prévia: acervo anonimizado, preços SINAPI e orçamentos aprovados do sistema. */
final class ReferenciaService
{
    public function __construct(private readonly PDO $db)
    {
    }

    /** Carrega os arquivos JSON versionados em database/referencias (idempotente). @return array{referencias:int,precos:int} */
    public function carregarPasta(string $pasta): array
    {
        $resultado = ['referencias' => 0, 'precos' => 0];
        foreach (glob(rtrim($pasta, '/') . '/*.json') ?: [] as $arquivo) {
            $dados = json_decode((string) file_get_contents($arquivo), true);
            if (!is_array($dados)) {
                continue;
            }
            if (isset($dados['referencias'])) {
                $resultado['referencias'] += $this->importarReferencias($dados['referencias'], 'acervo');
            }
            if (isset($dados['precos'])) {
                $resultado['precos'] += $this->importarPrecos($dados['precos'], $dados['data_base'] ?? null, $dados['localidade'] ?? null);
            }
        }
        return $resultado;
    }

    public function importarReferencias(array $referencias, string $origem, ?int $orcamentoId = null): int
    {
        $existe = $this->db->prepare('SELECT COUNT(*) FROM referencias WHERE fonte_hash = ?');
        $insere = $this->db->prepare(
            'INSERT INTO referencias (codigo, titulo, tipologia, padrao, area_construida, data_base, total_direto, bdi_percentual, origem, orcamento_id, fonte_hash) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $novas = 0;
        foreach ($referencias as $ref) {
            $existe->execute([$ref['fonte_hash']]);
            if ((int) $existe->fetchColumn() > 0) {
                if ($origem === 'acervo') {
                    $this->sincronizarItens($ref); // descrições revisadas (ex.: anonimização) chegam às instalações existentes
                }
                continue;
            }
            $propria = !$this->db->inTransaction();
            if ($propria) {
                $this->db->beginTransaction();
            }
            try {
                $insere->execute([
                    mb_substr((string) $ref['codigo'], 0, 20), mb_substr((string) $ref['titulo'], 0, 160),
                    isset(Classificador::TIPOLOGIAS[$ref['tipologia'] ?? '']) ? $ref['tipologia'] : 'residencial',
                    $ref['padrao'] ?? null, $ref['area_construida'] ?: null, $ref['data_base'] ?? null,
                    (float) $ref['total_direto'], (float) ($ref['bdi_percentual'] ?? 0), $origem, $orcamentoId, $ref['fonte_hash'],
                ]);
                $id = (int) $this->db->lastInsertId();
                $this->inserirEmLotes('INSERT INTO referencia_itens (referencia_id, etapa, etapa_padrao, descricao, unidade, quantidade, preco_unitario) VALUES ', 7, $this->linhasItens($id, $ref['itens']));
                if ($propria) {
                    $this->db->commit();
                }
                $novas++;
            } catch (Throwable $exception) {
                if ($propria && $this->db->inTransaction()) {
                    $this->db->rollBack();
                }
                throw $exception;
            }
        }
        return $novas;
    }

    /** Regrava os itens de uma referência do acervo quando etapa/descrição mudaram no arquivo versionado. */
    private function sincronizarItens(array $ref): void
    {
        $id = $this->db->prepare("SELECT id FROM referencias WHERE fonte_hash = ? AND origem = 'acervo'");
        $id->execute([$ref['fonte_hash']]);
        $id = (int) $id->fetchColumn();
        if ($id === 0) {
            return;
        }
        $atuais = $this->db->prepare('SELECT etapa, descricao FROM referencia_itens WHERE referencia_id = ? ORDER BY id');
        $atuais->execute([$id]);
        $assinatura = static fn (array $itens): string => sha1(implode("\n", array_map(
            static fn (array $i): string => mb_substr((string) ($i['etapa'] ?? ''), 0, 150) . '|' . mb_substr((string) $i['descricao'], 0, 255),
            $itens
        )));
        if ($assinatura($atuais->fetchAll(PDO::FETCH_ASSOC)) === $assinatura($ref['itens'])) {
            return;
        }
        $propria = !$this->db->inTransaction();
        if ($propria) {
            $this->db->beginTransaction();
        }
        try {
            $this->db->prepare('DELETE FROM referencia_itens WHERE referencia_id = ?')->execute([$id]);
            $this->inserirEmLotes('INSERT INTO referencia_itens (referencia_id, etapa, etapa_padrao, descricao, unidade, quantidade, preco_unitario) VALUES ', 7, $this->linhasItens($id, $ref['itens']));
            if ($propria) {
                $this->db->commit();
            }
        } catch (Throwable $exception) {
            if ($propria && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }

    /** @return list<list<mixed>> */
    private function linhasItens(int $id, array $itens): array
    {
        return array_map(static fn (array $i): array => [
            $id, isset($i['etapa']) ? mb_substr((string) $i['etapa'], 0, 150) : null,
            $i['etapa_padrao'] ?? Classificador::etapaPadrao($i['etapa'] ?? null, (string) $i['descricao']),
            mb_substr((string) $i['descricao'], 0, 255), Classificador::unidade((string) ($i['unidade'] ?? 'UN')),
            (float) $i['quantidade'], (float) $i['preco_unitario'],
        ], $itens);
    }

    public function importarPrecos(array $precos, ?string $dataBase, ?string $localidade): int
    {
        $novos = 0;
        $propria = !$this->db->inTransaction();
        if ($propria) {
            $this->db->beginTransaction();
        }
        try {
            $linhas = array_map(static fn (array $p): array => [
                mb_substr((string) $p['fonte'], 0, 20), mb_substr((string) $p['codigo'], 0, 30), mb_substr((string) $p['descricao'], 0, 500),
                Classificador::unidade((string) $p['unidade']), (float) $p['preco'], $dataBase, $localidade,
            ], $precos);
            $novos = $this->inserirEmLotes('INSERT IGNORE INTO precos_base (fonte, codigo, descricao, unidade, preco, data_base, localidade) VALUES ', 7, $linhas);
            if ($propria) {
                $this->db->commit();
            }
        } catch (Throwable $exception) {
            if ($propria && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
        return $novos;
    }

    /** Transforma um orçamento aprovado em referência anônima (a base aprende com as obras novas). */
    public function promoverOrcamento(int $orcamentoId, string $tipologia, ?float $area): int
    {
        $orc = $this->db->prepare('SELECT o.*, ob.area_construida FROM orcamentos o JOIN obras ob ON ob.id = o.obra_id WHERE o.id = ?');
        $orc->execute([$orcamentoId]);
        $orc = $orc->fetch(PDO::FETCH_ASSOC);
        if (!$orc) {
            throw new InvalidArgumentException('Orçamento não encontrado.');
        }
        if ($orc['status'] !== 'aprovado') {
            throw new InvalidArgumentException('Somente orçamentos aprovados podem virar referência.');
        }
        $area = $area ?: ((float) $orc['area_construida'] ?: null);
        $itens = $this->db->prepare('SELECT etapa, descricao, unidade, quantidade, preco_unitario FROM orcamento_itens WHERE orcamento_id = ? ORDER BY ordem, id');
        $itens->execute([$orcamentoId]);
        $itens = $itens->fetchAll(PDO::FETCH_ASSOC);
        $numero = (int) $this->db->query('SELECT COUNT(*) FROM referencias')->fetchColumn() + 1;
        $data = substr((string) $orc['criado_em'], 0, 7) . '-01';
        $tipologia = isset(Classificador::TIPOLOGIAS[$tipologia]) ? $tipologia : 'residencial';

        $novas = $this->importarReferencias([[
            'codigo' => sprintf('ORC-%03d', $numero),
            'titulo' => Classificador::TIPOLOGIAS[$tipologia] . ($area ? ' — ' . number_format($area, 0, ',', '.') . ' m²' : '') . ' (' . substr($data, 5, 2) . '/' . substr($data, 0, 4) . ')',
            'tipologia' => $tipologia,
            'area_construida' => $area,
            'data_base' => $data,
            'total_direto' => (float) $orc['total_estimado'],
            'bdi_percentual' => (float) $orc['bdi_percentual'],
            'fonte_hash' => sha1('orcamento:' . $orcamentoId),
            'itens' => $itens,
        ]], 'orcamento', $orcamentoId);
        if ($novas === 0) {
            throw new InvalidArgumentException('Este orçamento já faz parte da base de referência.');
        }
        return (int) $this->db->query("SELECT id FROM referencias WHERE fonte_hash = " . $this->db->quote(sha1('orcamento:' . $orcamentoId)))->fetchColumn();
    }

    /** INSERT com várias linhas por comando (poucas idas ao banco remoto). @param list<list<mixed>> $linhas */
    private function inserirEmLotes(string $comando, int $colunas, array $linhas, int $lote = 400): int
    {
        $afetadas = 0;
        $marcador = '(' . implode(', ', array_fill(0, $colunas, '?')) . ')';
        foreach (array_chunk($linhas, $lote) as $bloco) {
            $statement = $this->db->prepare($comando . implode(', ', array_fill(0, count($bloco), $marcador)));
            $statement->execute(array_merge(...$bloco));
            $afetadas += $statement->rowCount();
        }
        return $afetadas;
    }

    public function atualizar(int $id, string $tipologia, ?float $area, bool $ativo): void
    {
        if (!isset(Classificador::TIPOLOGIAS[$tipologia])) {
            throw new InvalidArgumentException('Tipologia inválida.');
        }
        $this->db->prepare('UPDATE referencias SET tipologia = ?, area_construida = ?, ativo = ? WHERE id = ?')
            ->execute([$tipologia, $area && $area > 0 ? $area : null, $ativo ? 1 : 0, $id]);
    }
}
