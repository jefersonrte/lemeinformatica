<?php
declare(strict_types=1);

namespace App\Domain\Estimativa;

use App\Domain\Importacao\PlanilhaOrcamentoParser;

/**
 * Vocabulário comum para comparar orçamentos diferentes: etapas padronizadas,
 * materiais/serviços-chave e tipologia da obra.
 */
final class Classificador
{
    /** Etapas padronizadas: chave => [rótulo, padrões de texto]. A ordem define a prioridade. */
    public const ETAPAS = [
        'administracao' => ['Administração e gerenciamento', '/administra|gerenciamento|acompanhamento|engenheir|mestre de obra|responsabilidade tecnica|mao de obra indireta|despesas indiretas|controle de execucao|encarregado|tecnico em seguranca|almoxarif|vigia/'],
        'preliminares' => ['Serviços preliminares', '/prelimin|iniciais|canteiro|provisori|tapume|placa de obra|projetos?\b|consumo|despesas gerais|mobilizacao|locacao da obra|container|andaime|barraco|sondagem|topograf/'],
        'terraplenagem' => ['Terraplenagem e demolições', '/movimento de terra|terraplen|terraplan|escavac|aterro|demolic|retirada|preparacao do terreno|bota.?fora|limpeza do terreno/'],
        'fundacao' => ['Fundação e contenção', '/funda[cç]|infraestrutura|estaca|sapata|baldrame|conten[cç]|radier|bloco de coroamento/'],
        'estrutura' => ['Estrutura', '/estrutur|concreto armado|superestrutura|laje|pilar|viga/'],
        'alvenaria' => ['Alvenaria e vedações', '/alvenaria|parede|vedac|fechamento|drywall/'],
        'cobertura' => ['Cobertura', '/cobertura|telhad|telha|calha/'],
        'impermeabilizacao' => ['Impermeabilização', '/impermeab/'],
        'esquadrias' => ['Esquadrias e vidros', '/esquadri|porta|janela|vidro|serralher|guarda.?corpo|corrimao/'],
        'revestimento_parede' => ['Revestimento de paredes', '/revestimento de parede|revestimentos? parede|reboco|emboco|chapisco|azulejo|revestimento interno|revestimento externo|fachada|^revestimentos?$/'],
        'pisos' => ['Pisos e pavimentação', '/piso|contrapiso|pavimenta|calcad|passeio|intertravado|asfalt/'],
        'forros' => ['Forros', '/forro|gesso/'],
        'pintura' => ['Pintura', '/pintura|textura|tinta/'],
        'eletrica' => ['Instalações elétricas', '/eletric|eletica|luminari|iluminac|spda|para.?raio|telecom|cabeamento|energia|logica|alimentador|quadro de (distrib|medi|luz)|\bquadros\b|disjuntor|tomada|interruptor/'],
        'hidrossanitaria' => ['Instalações hidrossanitárias', '/hidraul|sanitar|esgoto|agua|pluvial|drenag|\bgas\b|hidro/'],
        'incendio' => ['Prevenção de incêndio', '/preventiv|ppci|incendio/'],
        'loucas_metais' => ['Louças e metais', '/lou[cç]a|metais|bancada|tampo/'],
        'climatizacao' => ['Climatização', '/climatiz|ar condicionado|exaust|ventilac/'],
        'paisagismo' => ['Paisagismo e urbanização', '/paisag|urbaniz|vegeta|jardim|playground|mobiliario urbano|grama|area externa/'],
        'equipamentos' => ['Equipamentos e complementares', '/equipamento|piscina|elevador|mobiliario|movei|marcenaria|armario|complementar|diversos|extras|especial/'],
        'limpeza' => ['Limpeza final', '/limpeza/'],
    ];

    /** Materiais/serviços-chave: chave => [rótulo, unidade esperada, etapa, padrão, padrão de exclusão]. */
    public const MATERIAIS = [
        'concreto' => ['Concreto estrutural', 'M³', 'estrutura', '/concreto (usinado|estrutural|armado|fck|bombeado|\d{2} ?mpa)|concreto \d{2}/', '/magro|vassourado|calcada|piso/'],
        'aco' => ['Aço para armadura (CA-50/60)', 'KG', 'estrutura', '/\ba[cç]o\b.*ca|armadura|ca.?50|ca.?60/', '/inox|galvaniz|perfil/'],
        'forma' => ['Fôrmas de madeira', 'M²', 'estrutura', '/\bf[oô]rmas?\b/', '/plataforma|conformac/'],
        'laje' => ['Laje (pré-moldada/treliçada)', 'M²', 'estrutura', '/\blaje/', '/impermeab|regulariz|forro/'],
        'alvenaria' => ['Alvenaria de vedação', 'M²', 'alvenaria', '/alvenaria|bloco cer[aâ]mico|tijolo/', '/estrutural/'],
        'chapisco_reboco' => ['Chapisco, emboço e reboco', 'M²', 'revestimento_parede', '/chapisco|reboco|embo[cç]o|massa [uú]nica/', '/teto/'],
        'revestimento_ceramico' => ['Revestimento cerâmico de parede', 'M²', 'revestimento_parede', '/azulejo|revestimento cer[aâ]mic|pastilha/', '//'],
        'contrapiso' => ['Contrapiso e regularização', 'M²', 'pisos', '/contrapiso|regulariza[cç][aã]o.*piso|regulariza[cç][aã]o de laje/', '//'],
        'piso' => ['Piso cerâmico/porcelanato', 'M²', 'pisos', '/porcelanato|piso cer[aâ]mic|piso vin[ií]lic|piso laminad|assentamento de piso/', '/rodap/'],
        'forro' => ['Forro (gesso/PVC)', 'M²', 'forros', '/forro/', '//'],
        'pintura' => ['Pintura (paredes e tetos)', 'M²', 'pintura', '/pintura|textura|acr[ií]lica|l[aá]tex/', '/demarca|piso|estrutura met/'],
        'telhado' => ['Telhamento e estrutura do telhado', 'M²', 'cobertura', '/telha|telhado|madeiramento/', '/cumeeira|calha|rufo/'],
        'impermeabilizacao' => ['Impermeabilização', 'M²', 'impermeabilizacao', '/impermeabiliz|manta asf/', '//'],
        'janelas' => ['Janelas e esquadrias de alumínio/vidro', 'M²', 'esquadrias', '/janela|esquadria.*alum|caixilho|\bvidro/', '/porta/'],
        'portas' => ['Portas (kit completo)', 'UN', 'esquadrias', '/\bporta/', '/portao|porta.?corta|portaria/'],
        'eletrica' => ['Instalação elétrica (pontos/cabos/eletrodutos)', 'VB', 'eletrica', '/eletric|cabo|fio\b|eletroduto|tomada|interruptor|quadro de distrib/', '//'],
        'hidrossanitaria' => ['Instalação hidrossanitária (tubos e conexões)', 'VB', 'hidrossanitaria', '/hidraul|sanitar|tubo|esgoto|agua fria|agua quente/', '//'],
        'loucas' => ['Louças sanitárias', 'UN', 'loucas_metais', '/vaso sanit|bacia|lavat[oó]rio|cuba|tanque/', '//'],
        'metais' => ['Metais sanitários', 'UN', 'loucas_metais', '/torneira|registro|chuveiro|misturador|ducha/', '//'],
    ];

    public const TIPOLOGIAS = [
        'residencial' => 'Residência unifamiliar',
        'multifamiliar' => 'Residencial multifamiliar / geminado',
        'comercial' => 'Comercial / institucional',
        'reforma' => 'Reforma / retrofit',
        'urbanizacao' => 'Urbanização / praças / loteamento',
        'infraestrutura' => 'Infraestrutura (pontes, galerias, redes)',
        'esportivo' => 'Esportivo / lazer',
    ];

    public static function etapaPadrao(?string $etapa, string $descricao = ''): string
    {
        foreach ([$etapa ?? '', $descricao] as $texto) {
            $normalizado = PlanilhaOrcamentoParser::normalizar($texto);
            if ($normalizado === '') {
                continue;
            }
            foreach (self::ETAPAS as $chave => [, $padrao]) {
                if (preg_match($padrao, $normalizado)) {
                    return $chave;
                }
            }
        }
        return 'outros';
    }

    public static function rotuloEtapa(string $chave): string
    {
        return self::ETAPAS[$chave][0] ?? 'Outros serviços';
    }

    public static function material(string $descricao, string $unidade = ''): ?string
    {
        $normalizado = PlanilhaOrcamentoParser::normalizar($descricao);
        $un = self::unidade($unidade);
        foreach (self::MATERIAIS as $chave => [, $unidadeEsperada, , $padrao, $exclusao]) {
            if ($exclusao !== '//' && preg_match($exclusao, $normalizado)) {
                continue;
            }
            if (!preg_match($padrao, $normalizado)) {
                continue;
            }
            if ($unidadeEsperada === 'VB' || $un === $unidadeEsperada) {
                return $chave;
            }
        }
        return null;
    }

    public static function tipologia(string $texto): string
    {
        $t = PlanilhaOrcamentoParser::normalizar($texto);
        return match (true) {
            (bool) preg_match('/residencia unifamiliar|casa unifamiliar/', $t) => 'residencial',
            (bool) preg_match('/multifamiliar|geminad|sobrado|edificio|\bed\. |predio|condominio|\d unidades|apartament|\bhis\b/', $t) => 'multifamiliar',
            (bool) preg_match('/retrofit|refrofit|reforma|ampliac/', $t) => 'reforma',
            (bool) preg_match('/\bponte\b|galeria|rede subterr|saneamento/', $t) => 'infraestrutura',
            (bool) preg_match('/loteamento|praca|parque|passeio|urbaniz|paisag|beira mar|calcad|revitalizacao (d[aeo] )?(rua|praca|av)|area de lazer/', $t) => 'urbanizacao',
            (bool) preg_match('/arena|quadra|clube|tenis|esport|ginasio/', $t) => 'esportivo',
            (bool) preg_match('/clinica|hotel|\bupa\b|bistro|comercial|escritorio|loja|salao|galpao|escola|institucional|guarita|zeladoria|centro cultural|igreja|biblioteca/', $t) => 'comercial',
            default => 'residencial',
        };
    }

    /**
     * Ajusta a tipologia pelo conteúdo: obras dominadas por pavimentação, paisagismo e
     * terraplenagem, sem estrutura relevante, são urbanização.
     * @param array<string,float> $participacao etapa padronizada => fração do total
     */
    public static function tipologiaPorConteudo(string $tipologia, array $participacao): string
    {
        $urbano = ($participacao['pisos'] ?? 0) + ($participacao['paisagismo'] ?? 0) + ($participacao['terraplenagem'] ?? 0);
        $edificacao = ($participacao['estrutura'] ?? 0) + ($participacao['alvenaria'] ?? 0) + ($participacao['cobertura'] ?? 0) + ($participacao['fundacao'] ?? 0);
        if (in_array($tipologia, ['residencial', 'multifamiliar', 'comercial'], true) && $urbano >= 0.45 && $edificacao < 0.15) {
            return 'urbanizacao';
        }
        return $tipologia;
    }

    /** @param list<array{etapa:?string,descricao:string,quantidade:float|int,preco_unitario:float|int}> $itens @return array<string,float> */
    public static function participacaoEtapas(array $itens): array
    {
        $totais = [];
        foreach ($itens as $item) {
            $chave = self::etapaPadrao($item['etapa'] ?? null, (string) $item['descricao']);
            $totais[$chave] = ($totais[$chave] ?? 0) + (float) $item['quantidade'] * (float) $item['preco_unitario'];
        }
        $soma = array_sum($totais);
        return $soma > 0 ? array_map(static fn (float $v): float => $v / $soma, $totais) : [];
    }

    public static function unidade(string $unidade): string
    {
        $u = rtrim(mb_strtoupper(trim($unidade)), '.');
        return match ($u) {
            'M2', 'M²' => 'M²',
            'M3', 'M³' => 'M³',
            'UND', 'UNID', 'PÇ', 'PC', 'PCS', 'PEÇA' => 'UN',
            'KG', 'KGF' => 'KG',
            'ML', 'M' => 'M',
            default => $u !== '' ? $u : 'UN',
        };
    }

    /** Tokens significativos para comparar descrições (plural reduzido, medidas preservadas). @return list<string> */
    public static function tokens(string $texto): array
    {
        $t = PlanilhaOrcamentoParser::normalizar($texto);
        $t = str_replace(['p/', 'c/', 's/', 'ø', 'Ø', '"', "''"], [' ', ' ', ' ', ' ', ' ', ' pol ', ' pol '], $t);
        $t = preg_replace('/(\d),(\d)/', '$1.$2', $t) ?? $t;
        $t = preg_replace('/[^a-z0-9\.\/x ]+/', ' ', $t) ?? $t;
        $parar = ['de', 'da', 'do', 'das', 'dos', 'e', 'em', 'com', 'para', 'a', 'o', 'as', 'os', 'por', 'inclusive', 'incluso', 'conforme', 'projeto', 'fornecimento', 'instalacao', 'execucao', 'servico', 'tipo', 'ref', 'af', 'un', 'pc', 'und', 'marca', 'similar', 'ou', 'no', 'na'];
        $tokens = [];
        foreach (explode(' ', $t) as $p) {
            $p = trim($p, '.');
            if (mb_strlen($p) < 2 || in_array($p, $parar, true)) {
                continue;
            }
            if (mb_strlen($p) > 4 && preg_match('/[a-z]s$/', $p)) {
                $p = preg_replace('/(oe|ae)s$/', 'ao', $p) ?? $p; // conexões → conexao
                $p = preg_replace('/([^s])s$/', '$1', $p) ?? $p;
            }
            $tokens[] = $p;
        }
        return array_values(array_unique($tokens));
    }

    /** Similaridade entre a descrição buscada ($a) e a candidata ($b): cobre a busca e penaliza candidatas longas. */
    public static function similaridade(array $a, array $b): float
    {
        if ($a === [] || $b === []) {
            return 0.0;
        }
        $comuns = count(array_intersect($a, $b));
        if ($comuns === 0 || ($comuns < 2 && count($a) > 1)) {
            return 0.0;
        }
        return 0.65 * $comuns / count($a) + 0.35 * $comuns / count($b);
    }
}
