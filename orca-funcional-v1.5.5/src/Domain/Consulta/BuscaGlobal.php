<?php
declare(strict_types=1);

namespace App\Domain\Consulta;

use PDO;

/**
 * Busca unificada (paleta Ctrl+K e página de resultados).
 *
 * Com $clienteId preenchido, só enxerga obras e orçamentos daquele cliente;
 * nulo significa administrador (todas as entidades).
 */
final class BuscaGlobal
{
    public function __construct(private PDO $db, private ?int $clienteId, private string $baseUrl = '')
    {
    }

    /**
     * @return array{termos: list<string>, grupos: list<array{chave: string, titulo: string, icone: string, total: int, itens: list<array<string, mixed>>}>}
     */
    public function buscar(string $consulta, int $porGrupo = 5): array
    {
        $termos = TermoBusca::termos($consulta);
        if ($termos === []) {
            return ['termos' => [], 'grupos' => []];
        }
        $admin = $this->clienteId === null;
        $grupos = [
            $this->orcamentos($termos, $porGrupo),
            $this->obras($termos, $porGrupo),
        ];
        if ($admin) {
            $grupos[] = $this->clientes($termos, $porGrupo);
            $grupos[] = $this->fornecedores($termos, $porGrupo);
            $grupos[] = $this->produtos($termos, $porGrupo);
        }
        $grupos[] = $this->itens($termos, $consulta);
        return ['termos' => $termos, 'grupos' => array_values(array_filter($grupos, static fn (array $g): bool => $g['total'] > 0))];
    }

    private function orcamentos(array $termos, int $limite): array
    {
        $params = [];
        $where = TermoBusca::condicao(['o.titulo', 'ob.nome', 'c.razao_social', 'COALESCE(c.nome_fantasia, \'\')', 'CAST(o.id AS CHAR)'], $termos, $params);
        [$escopo, $params] = $this->escopo('o.cliente_id', $params);
        $rows = $this->consultar(
            "SELECT o.id, o.titulo, o.status, o.total_estimado, ob.nome AS obra, c.razao_social AS cliente, COUNT(*) OVER () AS total
             FROM orcamentos o JOIN obras ob ON ob.id = o.obra_id JOIN clientes c ON c.id = o.cliente_id
             WHERE $where $escopo ORDER BY (o.titulo LIKE ?) DESC, o.criado_em DESC LIMIT $limite",
            [...$params, addcslashes($termos[0], '\\%_') . '%']
        );
        $caminho = $this->clienteId === null ? '/admin/orcamento_detalhe.php?id=' : '/cliente/orcamento_ver.php?id=';
        return $this->grupo('orcamentos', 'Orçamentos', 'fa-solid fa-file-invoice-dollar', $rows, fn (array $r): array => [
            'titulo' => $r['titulo'],
            'detalhe' => $r['obra'] . ($this->clienteId === null ? ' · ' . $r['cliente'] : ''),
            'valor' => (float) $r['total_estimado'],
            'status' => $r['status'],
            'url' => $this->baseUrl . $caminho . (int) $r['id'],
        ], $this->listaUrl('orcamentos', $termos));
    }

    private function obras(array $termos, int $limite): array
    {
        $params = [];
        $where = TermoBusca::condicao(['ob.nome', "COALESCE(ob.cidade, '')", "COALESCE(ob.endereco, '')", 'c.razao_social'], $termos, $params);
        [$escopo, $params] = $this->escopo('ob.cliente_id', $params);
        $rows = $this->consultar(
            "SELECT ob.id, ob.nome, ob.status, ob.cidade, ob.estado, c.razao_social AS cliente, COUNT(*) OVER () AS total
             FROM obras ob JOIN clientes c ON c.id = ob.cliente_id
             WHERE $where $escopo ORDER BY ob.criado_em DESC LIMIT $limite",
            $params
        );
        $caminho = $this->clienteId === null ? '/admin/obra_detalhe.php?id=' : '/cliente/obra_detalhe.php?id=';
        return $this->grupo('obras', 'Obras', 'fa-solid fa-building', $rows, fn (array $r): array => [
            'titulo' => $r['nome'],
            'detalhe' => trim(($r['cidade'] ? $r['cidade'] . ($r['estado'] ? '/' . $r['estado'] : '') : '') . ($this->clienteId === null ? ' · ' . $r['cliente'] : ''), ' ·'),
            'status' => $r['status'],
            'url' => $this->baseUrl . $caminho . (int) $r['id'],
        ], $this->listaUrl('obras', $termos));
    }

    private function clientes(array $termos, int $limite): array
    {
        $params = [];
        $where = TermoBusca::condicao(['razao_social', "COALESCE(nome_fantasia, '')", "COALESCE(cnpj_cpf, '')", "COALESCE(cidade, '')", "COALESCE(email, '')"], $termos, $params);
        $rows = $this->consultar("SELECT id, razao_social, nome_fantasia, cidade, estado, COUNT(*) OVER () AS total FROM clientes WHERE $where ORDER BY razao_social LIMIT $limite", $params);
        return $this->grupo('clientes', 'Clientes', 'fa-solid fa-users', $rows, fn (array $r): array => [
            'titulo' => $r['razao_social'],
            'detalhe' => trim(($r['nome_fantasia'] ?? '') . ' ' . ($r['cidade'] ? '· ' . $r['cidade'] . ($r['estado'] ? '/' . $r['estado'] : '') : '')),
            'url' => $this->baseUrl . '/admin/obras.php?cliente_id=' . (int) $r['id'],
        ], $this->baseUrl . '/admin/clientes.php');
    }

    private function fornecedores(array $termos, int $limite): array
    {
        $params = [];
        $where = TermoBusca::condicao(['nome', "COALESCE(cnpj_cpf, '')", "COALESCE(contato, '')", "COALESCE(cidade, '')", "COALESCE(email, '')"], $termos, $params);
        $rows = $this->consultar("SELECT id, nome, contato, cidade, COUNT(*) OVER () AS total FROM fornecedores WHERE $where ORDER BY nome LIMIT $limite", $params);
        return $this->grupo('fornecedores', 'Fornecedores', 'fa-solid fa-truck-field', $rows, fn (array $r): array => [
            'titulo' => $r['nome'],
            'detalhe' => trim(($r['contato'] ?? '') . ($r['cidade'] ? ' · ' . $r['cidade'] : ''), ' ·'),
            'url' => $this->baseUrl . '/admin/fornecedores.php?q=' . rawurlencode($r['nome']),
        ], $this->baseUrl . '/admin/fornecedores.php');
    }

    private function produtos(array $termos, int $limite): array
    {
        $params = [];
        $where = TermoBusca::condicao(['p.nome', "COALESCE(p.codigo, '')"], $termos, $params);
        $rows = $this->consultar("SELECT p.id, p.nome, p.codigo, p.unidade, c.nome AS categoria, COUNT(*) OVER () AS total FROM produtos p JOIN categorias c ON c.id = p.categoria_id WHERE $where ORDER BY p.nome LIMIT $limite", $params);
        return $this->grupo('produtos', 'Produtos', 'fa-solid fa-boxes-stacked', $rows, fn (array $r): array => [
            'titulo' => $r['nome'],
            'detalhe' => trim(($r['codigo'] ? $r['codigo'] . ' · ' : '') . $r['categoria'] . ' · ' . $r['unidade']),
            'url' => $this->baseUrl . '/admin/produtos.php?q=' . rawurlencode($r['nome']),
        ], $this->baseUrl . '/admin/produtos.php?q=' . rawurlencode(implode(' ', $termos)));
    }

    /** Itens de orçamento: um atalho para a consulta de preços com a contagem encontrada. */
    private function itens(array $termos, string $consulta): array
    {
        $params = [];
        $where = TermoBusca::condicao(['i.descricao'], $termos, $params);
        [$escopo, $params] = $this->escopo('o.cliente_id', $params);
        $st = $this->db->prepare("SELECT COUNT(*) AS n, COUNT(DISTINCT i.orcamento_id) AS orcs FROM orcamento_itens i JOIN orcamentos o ON o.id = i.orcamento_id WHERE $where $escopo");
        $st->execute($params);
        $r = $st->fetch(PDO::FETCH_ASSOC) ?: ['n' => 0, 'orcs' => 0];
        $n = (int) $r['n'];
        $url = $this->baseUrl . '/consulta_precos.php?q=' . rawurlencode(trim($consulta));
        return [
            'chave' => 'itens', 'titulo' => 'Preços em orçamentos', 'icone' => 'fa-solid fa-magnifying-glass-dollar', 'total' => $n, 'mais' => $url,
            'itens' => $n === 0 ? [] : [[
                'titulo' => 'Consultar preços de “' . trim($consulta) . '”',
                'detalhe' => $n . ' ' . ($n === 1 ? 'item' : 'itens') . ' em ' . (int) $r['orcs'] . ' ' . ((int) $r['orcs'] === 1 ? 'orçamento' : 'orçamentos'),
                'url' => $url,
            ]],
        ];
    }

    /** @return array{0: string, 1: list<mixed>} */
    private function escopo(string $coluna, array $params): array
    {
        if ($this->clienteId === null) {
            return ['', $params];
        }
        $params[] = $this->clienteId;
        return ["AND $coluna = ?", $params];
    }

    private function consultar(string $sql, array $params): array
    {
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    private function grupo(string $chave, string $titulo, string $icone, array $rows, callable $mapa, string $mais): array
    {
        return [
            'chave' => $chave,
            'titulo' => $titulo,
            'icone' => $icone,
            'total' => (int) ($rows[0]['total'] ?? 0),
            'mais' => $mais,
            'itens' => array_map($mapa, $rows),
        ];
    }

    private function listaUrl(string $modulo, array $termos): string
    {
        $area = $this->clienteId === null ? '/admin/' : '/cliente/';
        return $this->baseUrl . $area . $modulo . '.php?q=' . rawurlencode(implode(' ', $termos));
    }
}
