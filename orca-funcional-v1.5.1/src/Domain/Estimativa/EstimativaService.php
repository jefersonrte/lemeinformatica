<?php
declare(strict_types=1);

namespace App\Domain\Estimativa;

use InvalidArgumentException;
use PDO;

/**
 * Prévia de custo e de materiais a partir da base de orçamentos reais (referências) e do banco SINAPI.
 *
 * Método paramétrico: custo por m² das obras comparáveis (atualizado pelo INCC), distribuição
 * mediana por etapa, coeficientes de consumo por m² dos materiais-chave e preços medianos.
 */
final class EstimativaService
{
    public const PADROES = ['economico' => 'Econômico', 'medio' => 'Médio', 'alto' => 'Alto'];
    private const MIN_PRESENCA = 0.4;

    /** @var array<int,list<array<string,mixed>>> */
    private array $itensCache = [];

    public function __construct(private readonly PDO $db)
    {
    }

    /** @return list<array<string,mixed>> referências ativas com área, da mesma tipologia (ou todas as edificações) */
    public function comparaveis(string $tipologia, int $minimo = 3): array
    {
        $refs = $this->db->query('SELECT * FROM referencias WHERE ativo = 1 AND area_construida > 0 AND total_direto > 0 ORDER BY data_base DESC')->fetchAll(PDO::FETCH_ASSOC);
        foreach ($refs as &$ref) {
            $ref['fator'] = Incc::fator($ref['data_base']);
            $ref['custo_m2'] = (float) $ref['total_direto'] * $ref['fator'] / (float) $ref['area_construida'];
        }
        unset($ref);
        $mesma = array_values(array_filter($refs, static fn (array $r): bool => $r['tipologia'] === $tipologia));
        if (count($mesma) >= $minimo) {
            return $mesma;
        }
        $grupos = [['residencial', 'multifamiliar', 'comercial', 'reforma'], ['urbanizacao', 'esportivo'], ['infraestrutura']];
        $grupo = [$tipologia];
        foreach ($grupos as $candidato) {
            if (in_array($tipologia, $candidato, true)) {
                $grupo = $candidato;
            }
        }
        $amplo = array_values(array_filter($refs, static fn (array $r): bool => in_array($r['tipologia'], $grupo, true)));
        return count($amplo) >= count($mesma) ? $amplo : $mesma;
    }

    /**
     * @param array{area:float,tipologia:string,padrao?:?string,esquadrias?:list<array<string,mixed>>} $entrada
     * @return array<string,mixed>
     */
    public function estimar(array $entrada): array
    {
        $area = (float) ($entrada['area'] ?? 0);
        if ($area <= 0) {
            throw new InvalidArgumentException('Informe a área construída (m²) para gerar a prévia.');
        }
        $tipologia = isset(Classificador::TIPOLOGIAS[$entrada['tipologia'] ?? '']) ? $entrada['tipologia'] : 'residencial';
        $padrao = isset(self::PADROES[$entrada['padrao'] ?? '']) ? $entrada['padrao'] : 'medio';
        $refs = $this->comparaveis($tipologia);
        if ($refs === []) {
            throw new InvalidArgumentException('Ainda não há orçamentos de referência com área cadastrada para esta tipologia.');
        }

        // Obras de porte e data parecidos pesam mais (economia de escala e defasagem de preços)
        $pesos = [];
        foreach ($refs as $i => $ref) {
            $anos = max(0, (time() - strtotime((string) ($ref['data_base'] ?: 'now'))) / 31557600);
            $pesos[$i] = 1 / (1 + abs(log((float) $ref['area_construida'] / $area))) / (1 + $anos / 8) * ($ref['tipologia'] === $tipologia ? 1.0 : 0.5);
        }
        $custos = array_column($refs, 'custo_m2');
        $faixa = [
            'economico' => self::percentilPonderado($custos, $pesos, 0.25),
            'medio' => self::percentilPonderado($custos, $pesos, 0.5),
            'alto' => self::percentilPonderado($custos, $pesos, 0.75),
        ];
        $custoM2 = $faixa[$padrao];
        $total = round($area * $custoM2, 2);

        $etapas = $this->etapas($refs, $total);
        $materiais = $this->materiais($refs, $area);
        $esquadrias = $this->esquadrias($refs, $entrada['esquadrias'] ?? []);
        if ($esquadrias['itens'] !== []) {
            $materiais = array_values(array_filter($materiais, static fn (array $m): bool => !in_array($m['chave'], ['janelas', 'portas'], true)));
        }

        $avisos = [];
        if (count($refs) < 4) {
            $avisos[] = 'Poucas obras comparáveis (' . count($refs) . '); use a prévia como ordem de grandeza.';
        }
        if (($refs[0]['tipologia'] ?? $tipologia) !== $tipologia || count(array_unique(array_column($refs, 'tipologia'))) > 1) {
            $avisos[] = 'Foram usadas obras de tipologias semelhantes porque há poucas referências de "' . Classificador::TIPOLOGIAS[$tipologia] . '".';
        }

        return [
            'area' => $area,
            'tipologia' => $tipologia,
            'padrao' => $padrao,
            'custo_m2' => $faixa,
            'custo_m2_usado' => $custoM2,
            'total' => $total,
            'total_faixa' => [round($area * $faixa['economico'], 2), round($area * $faixa['alto'], 2)],
            'etapas' => $etapas,
            'materiais' => $materiais,
            'esquadrias' => $esquadrias,
            'referencias' => array_map(static fn (array $r): array => [
                'codigo' => $r['codigo'], 'titulo' => $r['titulo'], 'tipologia' => $r['tipologia'],
                'area' => (float) $r['area_construida'], 'data_base' => $r['data_base'],
                'custo_m2' => round($r['custo_m2'], 2), 'fator' => $r['fator'],
            ], $refs),
            'avisos' => $avisos,
        ];
    }

    /**
     * Preço sugerido para itens de uma lista de quantitativos, pela semelhança com itens reais e o SINAPI.
     * @param list<array{descricao:string,unidade:string,quantidade:float|int}> $itens
     * @return list<array<string,mixed>>
     */
    public function precificar(array $itens): array
    {
        $candidatos = $this->baseDePrecos();
        $indice = [];
        foreach ($candidatos as $unidade => $lista) {
            foreach ($lista as $posicao => $candidato) {
                foreach ($candidato['tokens'] as $token) {
                    $indice[$unidade][$token][] = $posicao;
                }
            }
        }
        $resultado = [];
        foreach ($itens as $item) {
            $unidade = Classificador::unidade((string) ($item['unidade'] ?? 'UN'));
            $tokens = Classificador::tokens((string) $item['descricao']);
            $posicoes = [];
            foreach ($tokens as $token) {
                foreach ($indice[$unidade][$token] ?? [] as $posicao) {
                    $posicoes[$posicao] = true;
                }
            }
            $pontuados = [];
            foreach (array_keys($posicoes) as $posicao) {
                $candidato = $candidatos[$unidade][$posicao];
                $s = Classificador::similaridade($tokens, $candidato['tokens']);
                if ($s >= 0.45) {
                    $pontuados[] = [$s, $candidato];
                }
            }
            usort($pontuados, static fn (array $a, array $b): int => $b[0] <=> $a[0]);
            // Usa os candidatos próximos do melhor escore (evita misturar itens de outra bitola/modelo)
            $melhores = array_values(array_filter(array_slice($pontuados, 0, 7), static fn (array $p): bool => $p[0] >= $pontuados[0][0] - 0.1));
            $preco = $melhores === [] ? 0.0 : self::percentil(array_map(static fn (array $p): float => $p[1]['preco'], $melhores), 0.5);
            $resultado[] = $item + [
                'preco_sugerido' => round($preco, 2),
                'similaridade' => $melhores === [] ? 0.0 : round(min(1.0, $melhores[0][0]), 2),
                'referencia' => $melhores === [] ? null : $melhores[0][1]['descricao'],
                'fonte' => $melhores === [] ? null : $melhores[0][1]['fonte'],
            ];
        }
        return $resultado;
    }

    /** @return list<array<string,mixed>> */
    private function etapas(array $refs, float $total): array
    {
        $participacoes = [];
        foreach ($refs as $ref) {
            $porEtapa = [];
            foreach ($this->itens((int) $ref['id']) as $item) {
                $porEtapa[$item['etapa_padrao']] = ($porEtapa[$item['etapa_padrao']] ?? 0) + $item['quantidade'] * $item['preco_unitario'];
            }
            $soma = array_sum($porEtapa);
            if ($soma > 0) {
                $participacoes[] = array_map(static fn (float $v): float => $v / $soma, $porEtapa);
            }
        }
        $chaves = array_unique(array_merge(...array_map('array_keys', $participacoes ?: [[]])));
        $medianas = [];
        foreach ($chaves as $chave) {
            $valores = array_map(static fn (array $p): float => $p[$chave] ?? 0.0, $participacoes);
            $medianas[$chave] = self::percentil($valores, 0.5);
        }
        $medianas = array_filter($medianas, static fn (float $v): bool => $v > 0.001);
        $soma = array_sum($medianas) ?: 1;
        $ordem = array_flip(array_merge(array_keys(Classificador::ETAPAS), ['outros']));
        uksort($medianas, static fn (string $a, string $b): int => ($ordem[$a] ?? 99) <=> ($ordem[$b] ?? 99));
        $etapas = [];
        foreach ($medianas as $chave => $valor) {
            $percentual = $valor / $soma;
            $etapas[] = ['chave' => $chave, 'rotulo' => Classificador::rotuloEtapa($chave), 'percentual' => round($percentual * 100, 1), 'valor' => round($total * $percentual, 2)];
        }
        return $etapas;
    }

    /** @return list<array<string,mixed>> */
    private function materiais(array $refs, float $area): array
    {
        $amostras = [];
        foreach ($refs as $ref) {
            $porMaterial = [];
            foreach ($this->itens((int) $ref['id']) as $item) {
                $chave = Classificador::material($item['descricao'], $item['unidade']);
                if ($chave === null || $item['preco_unitario'] <= 0) {
                    continue;
                }
                $porMaterial[$chave]['quantidade'] = ($porMaterial[$chave]['quantidade'] ?? 0) + $item['quantidade'];
                $porMaterial[$chave]['valor'] = ($porMaterial[$chave]['valor'] ?? 0) + $item['quantidade'] * $item['preco_unitario'] * $ref['fator'];
            }
            foreach ($porMaterial as $chave => $dados) {
                if ($dados['quantidade'] > 0 && $dados['valor'] > 0) {
                    $amostras[$chave][] = [
                        'coef' => $dados['quantidade'] / (float) $ref['area_construida'],
                        'preco' => $dados['valor'] / $dados['quantidade'],
                        'custo_m2' => $dados['valor'] / (float) $ref['area_construida'],
                    ];
                }
            }
        }

        $lista = [];
        foreach (Classificador::MATERIAIS as $chave => [$rotulo, $unidade, $etapa]) {
            $dados = $amostras[$chave] ?? [];
            if (count($dados) < 2 || count($dados) / count($refs) < self::MIN_PRESENCA) {
                continue;
            }
            if ($unidade === 'VB') {
                $custoM2 = self::percentil(array_column($dados, 'custo_m2'), 0.5);
                $quantidade = $area;
                $preco = $custoM2;
                $unidadeExibida = 'M²';
                $rotulo .= ' — por m² construído';
            } else {
                $quantidade = $area * self::percentil(array_column($dados, 'coef'), 0.5);
                $preco = self::percentil(array_column($dados, 'preco'), 0.5);
                $unidadeExibida = $unidade;
            }
            $lista[] = [
                'chave' => $chave, 'rotulo' => $rotulo, 'unidade' => $unidadeExibida, 'etapa' => $etapa,
                'quantidade' => round($quantidade, 2), 'preco_unitario' => round($preco, 2),
                'total' => round($quantidade * $preco, 2), 'amostras' => count($dados),
                'coef_m2' => $unidade === 'VB' ? null : round($quantidade / $area, 4),
            ];
        }
        return $lista;
    }

    /** Esquadrias lidas da planta, com preço mediano por unidade (portas) e por m² (janelas). */
    private function esquadrias(array $refs, array $esquadrias): array
    {
        if ($esquadrias === []) {
            return ['itens' => [], 'total' => 0.0];
        }
        $precoPorta = [];
        $precoJanelaM2 = [];
        foreach ($refs as $ref) {
            foreach ($this->itens((int) $ref['id']) as $item) {
                $material = Classificador::material($item['descricao'], $item['unidade']);
                if ($material === 'portas' && $item['preco_unitario'] > 0) {
                    $precoPorta[] = $item['preco_unitario'] * $ref['fator'];
                } elseif ($material === 'janelas' && $item['preco_unitario'] > 0) {
                    $precoJanelaM2[] = $item['preco_unitario'] * $ref['fator'];
                }
            }
        }
        $porta = $precoPorta !== [] ? self::percentil($precoPorta, 0.5) : 0.0;
        $janela = $precoJanelaM2 !== [] ? self::percentil($precoJanelaM2, 0.5) : 0.0;
        $itens = [];
        foreach ($esquadrias as $e) {
            $quantidade = (int) $e['quantidade'];
            $areaTotal = round($quantidade * (float) $e['area_unitaria'], 2);
            $porUnidade = $e['tipo'] === 'porta';
            $preco = $porUnidade ? $porta : $janela;
            $itens[] = $e + [
                'area_total' => $areaTotal,
                'unidade' => $porUnidade ? 'UN' : 'M²',
                'preco_unitario' => round($preco, 2),
                'total' => round(($porUnidade ? $quantidade : $areaTotal) * $preco, 2),
            ];
        }
        return ['itens' => $itens, 'total' => round(array_sum(array_column($itens, 'total')), 2)];
    }

    /** @return list<array{descricao:string,unidade:string,quantidade:float,preco_unitario:float,etapa_padrao:string}> */
    private function itens(int $referenciaId): array
    {
        if (!isset($this->itensCache[$referenciaId])) {
            $statement = $this->db->prepare('SELECT descricao, unidade, quantidade, preco_unitario, etapa_padrao FROM referencia_itens WHERE referencia_id = ?');
            $statement->execute([$referenciaId]);
            $this->itensCache[$referenciaId] = array_map(static fn (array $i): array => [
                'descricao' => (string) $i['descricao'], 'unidade' => (string) $i['unidade'],
                'quantidade' => (float) $i['quantidade'], 'preco_unitario' => (float) $i['preco_unitario'],
                'etapa_padrao' => (string) $i['etapa_padrao'],
            ], $statement->fetchAll(PDO::FETCH_ASSOC));
        }
        return $this->itensCache[$referenciaId];
    }

    /** @return array<string,list<array{descricao:string,tokens:list<string>,preco:float,fonte:string}>> por unidade */
    private function baseDePrecos(): array
    {
        $base = [];
        $refs = $this->db->query(
            'SELECT ri.descricao, ri.unidade, ri.preco_unitario, r.data_base, r.codigo FROM referencia_itens ri '
            . 'JOIN referencias r ON r.id = ri.referencia_id WHERE r.ativo = 1 AND ri.preco_unitario > 0'
        );
        foreach ($refs as $linha) {
            $base[Classificador::unidade((string) $linha['unidade'])][] = [
                'descricao' => (string) $linha['descricao'],
                'tokens' => Classificador::tokens((string) $linha['descricao']),
                'preco' => (float) $linha['preco_unitario'] * Incc::fator($linha['data_base']),
                'fonte' => 'Orçamento real ' . $linha['codigo'],
            ];
        }
        foreach ($this->db->query('SELECT fonte, codigo, descricao, unidade, preco, data_base FROM precos_base') as $linha) {
            $base[Classificador::unidade((string) $linha['unidade'])][] = [
                'descricao' => (string) $linha['descricao'],
                'tokens' => Classificador::tokens((string) $linha['descricao']),
                'preco' => (float) $linha['preco'] * Incc::fator($linha['data_base']),
                'fonte' => $linha['fonte'] . ' ' . $linha['codigo'],
            ];
        }
        return $base;
    }

    /** @param list<float> $valores @param list<float> $pesos */
    public static function percentilPonderado(array $valores, array $pesos, float $p): float
    {
        $pares = array_map(null, array_values($valores), array_values($pesos));
        usort($pares, static fn (array $a, array $b): int => $a[0] <=> $b[0]);
        $total = array_sum(array_column($pares, 1));
        if ($total <= 0) {
            return self::percentil($valores, $p);
        }
        $acumulado = 0.0;
        foreach ($pares as [$valor, $peso]) {
            $acumulado += $peso;
            if ($acumulado / $total >= $p) {
                return (float) $valor;
            }
        }
        return (float) end($pares)[0];
    }

    /** @param list<float> $valores */
    public static function percentil(array $valores, float $p): float
    {
        $valores = array_values(array_filter($valores, static fn ($v): bool => is_numeric($v)));
        if ($valores === []) {
            return 0.0;
        }
        sort($valores);
        $posicao = ($p * (count($valores) - 1));
        $inferior = (int) floor($posicao);
        $superior = (int) ceil($posicao);
        return $valores[$inferior] + ($valores[$superior] - $valores[$inferior]) * ($posicao - $inferior);
    }
}
