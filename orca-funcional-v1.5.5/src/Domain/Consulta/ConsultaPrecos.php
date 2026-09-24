<?php
declare(strict_types=1);

namespace App\Domain\Consulta;

use PDO;

/**
 * Consulta de preços praticados nos orçamentos (banco de preços interno).
 *
 * Procura itens pela descrição, agrupa por unidade normalizada e devolve a
 * faixa de preço de cada unidade, além das facetas (unidades e etapas) para
 * refinar a busca. Cliente vê apenas os próprios orçamentos.
 */
final class ConsultaPrecos
{
    public const LIMITE_BUSCA = 3000;
    public const ORDENS = ['relevancia', 'preco_asc', 'preco_desc', 'recente', 'descricao'];

    public function __construct(private PDO $db, private ?int $clienteId)
    {
    }

    /**
     * @param array{q?: string, unidade?: string, etapa?: string, status?: string, ordem?: string} $filtros
     * @return array{termos: list<string>, itens: list<array<string, mixed>>, total: int, truncado: bool,
     *               resumos: array<string, array<string, float|int>>, unidades: array<string, int>, etapas: array<string, int>}
     */
    public function buscar(array $filtros): array
    {
        $termos = TermoBusca::termos((string) ($filtros['q'] ?? ''));
        $vazio = ['termos' => $termos, 'itens' => [], 'total' => 0, 'truncado' => false, 'resumos' => [], 'unidades' => [], 'etapas' => []];
        if ($termos === []) {
            return $vazio;
        }

        $params = [];
        $wheres = [TermoBusca::condicao(['i.descricao'], $termos, $params), 'i.preco_unitario > 0'];
        if ($this->clienteId !== null) {
            $wheres[] = 'o.cliente_id = ?';
            $params[] = $this->clienteId;
        }
        $status = (string) ($filtros['status'] ?? '');
        if ($status !== '') {
            $wheres[] = 'o.status = ?';
            $params[] = $status;
        }
        $ordem = match ((string) ($filtros['ordem'] ?? '')) {
            'preco_asc' => 'i.preco_unitario ASC',
            'preco_desc' => 'i.preco_unitario DESC',
            'descricao' => 'i.descricao ASC',
            'recente' => 'o.criado_em DESC, i.id DESC',
            default => '(i.descricao LIKE ?) DESC, o.criado_em DESC, i.id DESC',
        };
        if (str_starts_with($ordem, '(')) {
            $params[] = addcslashes($termos[0], '\\%_') . '%';
        }
        $limite = self::LIMITE_BUSCA + 1;
        $st = $this->db->prepare(
            'SELECT i.id, i.descricao, i.unidade, i.etapa, i.quantidade, i.preco_unitario, i.preco_cotado,
                    o.id AS orcamento_id, o.titulo AS orcamento, o.status, o.criado_em, o.bdi_percentual,
                    ob.nome AS obra, ob.cidade, ob.estado, ob.tipologia, fo.nome AS fornecedor
             FROM orcamento_itens i
             JOIN orcamentos o ON o.id = i.orcamento_id
             JOIN obras ob ON ob.id = o.obra_id
             LEFT JOIN fornecedores fo ON fo.id = i.fornecedor_id
             WHERE ' . implode(' AND ', $wheres) . " ORDER BY $ordem LIMIT $limite"
        );
        $st->execute($params);
        $linhas = $st->fetchAll(PDO::FETCH_ASSOC);
        $truncado = count($linhas) > self::LIMITE_BUSCA;
        $linhas = array_slice($linhas, 0, self::LIMITE_BUSCA);

        $unidadeFiltro = trim((string) ($filtros['unidade'] ?? ''));
        $etapaFiltro = trim((string) ($filtros['etapa'] ?? ''));
        $unidades = [];
        $etapas = [];
        $itens = [];
        foreach ($linhas as $linha) {
            $linha['unidade_norm'] = Unidade::normalizar($linha['unidade']);
            $linha['etapa'] = trim((string) ($linha['etapa'] ?? ''));
            $etapa = $linha['etapa'] !== '' ? $linha['etapa'] : 'Sem etapa';
            // Facetas contam considerando o outro filtro (como nos e-commerces).
            if ($etapaFiltro === '' || $etapa === $etapaFiltro) {
                $unidades[$linha['unidade_norm']] = ($unidades[$linha['unidade_norm']] ?? 0) + 1;
            }
            if ($unidadeFiltro === '' || $linha['unidade_norm'] === $unidadeFiltro) {
                $etapas[$etapa] = ($etapas[$etapa] ?? 0) + 1;
            }
            if (($unidadeFiltro === '' || $linha['unidade_norm'] === $unidadeFiltro) && ($etapaFiltro === '' || $etapa === $etapaFiltro)) {
                $itens[] = $linha;
            }
        }
        arsort($unidades);
        arsort($etapas);

        $porUnidade = [];
        foreach ($itens as $item) {
            $porUnidade[$item['unidade_norm']][] = (float) $item['preco_unitario'];
        }
        $resumos = [];
        foreach ($porUnidade as $unidade => $precos) {
            $resumo = Estatistica::resumo($precos);
            if ($resumo !== null) {
                $resumos[$unidade] = $resumo;
            }
        }
        uasort($resumos, static fn (array $a, array $b): int => $b['n'] <=> $a['n']);

        return ['termos' => $termos, 'itens' => $itens, 'total' => count($itens), 'truncado' => $truncado,
            'resumos' => $resumos, 'unidades' => $unidades, 'etapas' => $etapas];
    }
}
