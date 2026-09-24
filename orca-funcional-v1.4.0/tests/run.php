<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/src/Domain/Orcamento/OrcamentoCalculator.php';
require_once $root . '/src/Domain/Cotacao/MensagemCotacao.php';
require_once $root . '/src/Domain/Obra/SvgSanitizer.php';
require_once $root . '/src/Domain/Importacao/ItemCatalogMatcher.php';
require_once $root . '/src/Domain/Importacao/PlanilhaOrcamentoParser.php';
require_once $root . '/src/Domain/Orcamento/OrcamentoStatus.php';
require_once $root . '/src/Domain/Estimativa/Classificador.php';
require_once $root . '/src/Domain/Estimativa/PlantaAnalyzer.php';
require_once $root . '/src/Domain/Estimativa/Incc.php';
require_once $root . '/src/Domain/Estimativa/EstimativaService.php';
require_once $root . '/src/Infrastructure/LegacyUploadMigrator.php';
require_once $root . '/src/Infrastructure/TablePrefixer.php';
defined('APP_NAME') || define('APP_NAME', 'Orçamentista');

use App\Domain\Cotacao\MensagemCotacao;
use App\Domain\Obra\SvgSanitizer;
use App\Domain\Importacao\ItemCatalogMatcher;
use App\Domain\Importacao\PlanilhaOrcamentoParser;
use App\Domain\Orcamento\OrcamentoStatus;
use App\Domain\Estimativa\Classificador;
use App\Domain\Estimativa\EstimativaService;
use App\Domain\Estimativa\Incc;
use App\Domain\Estimativa\PlantaAnalyzer;
use App\Domain\Orcamento\OrcamentoCalculator;
use App\Infrastructure\TablePrefixer;
use App\Infrastructure\LegacyUploadMigrator;

$failures = [];
$assertSame = static function (mixed $expected, mixed $actual, string $label) use (&$failures): void {
    if ($expected !== $actual) {
        $failures[] = $label . ': esperado ' . var_export($expected, true) . ', recebido ' . var_export($actual, true);
    }
};
$assertContains = static function (string $needle, string $actual, string $label) use (&$failures): void {
    if (!str_contains($actual, $needle)) {
        $failures[] = $label . ': trecho não encontrado: ' . $needle;
    }
};
$assertThrows = static function (callable $callback, string $label) use (&$failures): void {
    try {
        $callback();
        $failures[] = $label . ': exceção esperada não foi lançada';
    } catch (InvalidArgumentException) {
        // Resultado esperado.
    }
};

$assertSame(1234.56, OrcamentoCalculator::decimal('1.234,56'), 'decimal pt-BR');
$assertSame(1234.56, OrcamentoCalculator::decimal('1234.56'), 'decimal internacional');
$assertSame(25.0, OrcamentoCalculator::totalItem('2,5', '10,00'), 'total do item');
$assertSame(35.0, OrcamentoCalculator::total([
    ['quantidade' => '2,5', 'preco_unitario' => '10'],
    ['qtd' => 2, 'preco' => 5],
]), 'total do orçamento');

$mensagem = MensagemCotacao::montar(
    ['obra' => 'Edifício Central', 'razao_social' => 'Cliente Exemplo'],
    ['nome' => 'Fornecedor Teste'],
    [['descricao' => 'Cimento', 'unidade' => 'SC', 'quantidade' => 10, 'obs' => '001']],
    '2026-08-20',
    'Frete incluso.'
);
$assertContains('Fornecedor Teste', $mensagem, 'fornecedor na cotação');
$assertContains('Edifício Central', $mensagem, 'obra na cotação');
$assertContains('20/08/2026', $mensagem, 'prazo formatado');
$assertContains('Frete incluso.', $mensagem, 'complemento');

$prefixer = new TablePrefixer('orca12_');
$prefixedSql = $prefixer->transform('SELECT o.id FROM obras o JOIN clientes c ON c.id=o.cliente_id WHERE c.usuario_id=?');
$assertContains('FROM orca12_obras', $prefixedSql, 'prefixo em tabela principal');
$assertContains('JOIN orca12_clientes', $prefixedSql, 'prefixo em join');
$assertContains('c.usuario_id', $prefixedSql, 'não alterar coluna com nome de tabela');
$assertSame('SELECT * FROM usuarios_admin', $prefixer->transform('SELECT * FROM usuarios_admin'), 'não alterar tabela central');

$safeSvg = SvgSanitizer::sanitize(
    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><defs><linearGradient id="g"><stop offset="0" stop-color="#fff"/></linearGradient></defs><path fill="url(#g)" d="M0 0h10v10z"/></svg>'
);
$assertContains('<svg', $safeSvg, 'preservar raiz SVG segura');
$assertContains('url(#g)', $safeSvg, 'preservar referência SVG interna');
$assertThrows(
    static fn (): string => SvgSanitizer::sanitize('<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
    'rejeitar script em SVG'
);
$assertThrows(
    static fn (): string => SvgSanitizer::sanitize('<svg xmlns="http://www.w3.org/2000/svg"><use href="https://example.com/a.svg#x"/></svg>'),
    'rejeitar referência externa em SVG'
);
$assertThrows(
    static fn (): string => SvgSanitizer::sanitize('<svg xmlns="http://www.w3.org/2000/svg"><path onclick="alert(1)" d="M0 0"/></svg>'),
    'rejeitar evento em SVG'
);

$temporaryRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'orca-upload-test-' . bin2hex(random_bytes(5));
$legacyNew = $temporaryRoot . DIRECTORY_SEPARATOR . 'orca-funcional-v1.2.2' . DIRECTORY_SEPARATOR . 'uploads';
$legacyOld = $temporaryRoot . DIRECTORY_SEPARATOR . 'orca-funcional-v1.2.1' . DIRECTORY_SEPARATOR . 'uploads';
$targetUploads = $temporaryRoot . DIRECTORY_SEPARATOR . 'orca-funcional-v1.2.3' . DIRECTORY_SEPARATOR . 'uploads';
mkdir($legacyNew . DIRECTORY_SEPARATOR . 'plantas' . DIRECTORY_SEPARATOR . '1', 0750, true);
mkdir($legacyOld . DIRECTORY_SEPARATOR . 'plantas' . DIRECTORY_SEPARATOR . '1', 0750, true);
file_put_contents($legacyNew . DIRECTORY_SEPARATOR . 'plantas' . DIRECTORY_SEPARATOR . '1' . DIRECTORY_SEPARATOR . 'atual.svg', '<svg/>');
file_put_contents($legacyOld . DIRECTORY_SEPARATOR . 'plantas' . DIRECTORY_SEPARATOR . '1' . DIRECTORY_SEPARATOR . 'legado.svg', '<svg/>');
$migrationResult = (new LegacyUploadMigrator())->migrate($targetUploads, [$legacyNew, $legacyOld]);
$assertSame(2, $migrationResult['sources'], 'detectar fontes legadas de upload');
$assertSame(2, $migrationResult['copied'], 'copiar uploads legados');
$assertSame(true, is_file($targetUploads . DIRECTORY_SEPARATOR . 'plantas' . DIRECTORY_SEPARATOR . '1' . DIRECTORY_SEPARATOR . 'atual.svg'), 'preservar upload da versão mais recente');
$assertSame(true, is_file($targetUploads . DIRECTORY_SEPARATOR . 'plantas' . DIRECTORY_SEPARATOR . '1' . DIRECTORY_SEPARATOR . 'legado.svg'), 'recuperar upload da versão histórica');
$removeTemporaryTree = static function (string $directory) use (&$removeTemporaryTree): void {
    if (!is_dir($directory)) {
        return;
    }
    foreach (new FilesystemIterator($directory) as $item) {
        if ($item->isDir() && !$item->isLink()) {
            $removeTemporaryTree($item->getPathname());
        } else {
            unlink($item->getPathname());
        }
    }
    rmdir($directory);
};
$removeTemporaryTree($temporaryRoot);

$catalogItems = (new ItemCatalogMatcher())->analisar(
    [
        ['codigo' => 'HID-001', 'descricao' => 'Tubo soldável PVC 25 mm', 'categoria' => ''],
        ['codigo' => '', 'descricao' => 'Cabo flexível 2,5 mm', 'categoria' => ''],
    ],
    [
        ['id' => 1, 'nome' => 'Instalações Hidráulicas'],
        ['id' => 2, 'nome' => 'Instalações Elétricas'],
    ],
    [
        ['id' => 10, 'categoria_id' => 1, 'codigo' => 'HID001', 'nome' => 'Tubo PVC soldável 25mm', 'descricao' => 'Tubo hidráulico', 'unidade' => 'M'],
    ]
);
$assertSame(true, $catalogItems[0]['duplicado'], 'detectar produto já cadastrado pelo código normalizado');
$assertSame(10, $catalogItems[0]['produto_id_similar'], 'indicar produto duplicado');
$assertSame(1, $catalogItems[0]['categoria_id_sugerida'], 'herdar categoria do produto encontrado');
$assertSame(2, $catalogItems[1]['categoria_id_sugerida'], 'sugerir categoria elétrica por termos do item');
$assertSame(false, $catalogItems[1]['duplicado'], 'não marcar item novo como duplicado');


// Planilha orçamentária: cabeçalho na linha 6, sub-cabeçalho, etapas, material + mão de obra, subtotais
$planilha = [
    1 => [1 => 'CLIENTE EXEMPLO'],
    2 => [1 => 'Obra: Residência'],
    6 => [0 => 'ITEM', 1 => 'DESCRIÇÃO', 2 => 'UNID.', 3 => 'QUANT.', 4 => 'MATERIAL', 6 => 'MÃO OBRA', 8 => 'TOTAL DE SERVIÇO'],
    7 => [4 => 'Unitário', 5 => 'Total', 6 => 'Unitário', 7 => 'Total'],
    8 => [0 => '1.', 1 => 'SERVIÇOS INICIAIS'],
    9 => [0 => '1.1', 1 => 'Placa de obra', 2 => 'm²', 3 => 2, 4 => 100.0, 5 => 200.0, 6 => 50.0, 7 => 100.0, 8 => 300.0],
    10 => [0 => '1.2', 1 => 'Consumo de água', 2 => 'mês', 3 => 12, 4 => 10.0, 5 => 120.0, 8 => 120.0],
    11 => [1 => 'Total do Item', 5 => 320.0, 7 => 100.0, 8 => 420.0],
    12 => [0 => '2.', 1 => 'ALVENARIA'],
    13 => [0 => '2.1', 1 => 'Bloco cerâmico', 2 => 'm²', 3 => '1.000,5', 4 => '10,00', 8 => 10005.0],
    14 => [1 => 'TOTAL GERAL', 8 => 10425.0],
    15 => [1 => 'Não orçado: mobiliário', 8 => 99999.0],
];
$analise = (new PlanilhaOrcamentoParser())->analisar($planilha, 'Custo');
$assertSame(6, $analise['cabecalho'], 'parser: localizar cabeçalho fora da linha 1');
$assertSame(3, count($analise['itens']), 'parser: ignorar etapas, subtotais e notas após o total');
$assertSame(['Serviços Iniciais', 'Alvenaria'], $analise['etapas'], 'parser: reconhecer etapas numeradas');
$assertSame(150.0, $analise['itens'][0]['preco_unitario'], 'parser: somar material + mão de obra');
$assertSame('Alvenaria', $analise['itens'][2]['etapa'], 'parser: atribuir etapa ao item');
$assertSame(1000.5, $analise['itens'][2]['quantidade'], 'parser: número pt-BR em texto');
$assertSame(10425.0, $analise['total_importado'], 'parser: total igual ao da planilha');
$assertSame(10425.0, $analise['total_planilha'], 'parser: ler total geral informado');
$assertSame([], $analise['avisos'], 'parser: sem aviso quando o total confere');

// Preço sem/com BDI: deriva o percentual e preserva o total final
$comBdi = (new PlanilhaOrcamentoParser())->analisar([
    3 => [3 => 'BDI:', 4 => 0.25],
    6 => [5 => 'CUSTO UNITÁRIO S/BDI', 7 => 'PREÇO UNITÁRIO C/ BDI'],
    7 => [0 => 'ITEM', 1 => 'DESCRIÇÃO', 2 => 'UN', 3 => 'QUANT.', 5 => 'UNITARIO', 6 => 'TOTAL', 7 => 'UNITARIO', 8 => 'TOTAL'],
    8 => [0 => '1.', 1 => 'FUNDAÇÃO'],
    9 => [0 => '1.1', 1 => 'Concreto', 2 => 'M3', 3 => 10, 5 => 400.0, 6 => 4000.0, 7 => 500.0, 8 => 5000.0],
], 'ORÇAMENTO');
$assertSame(25.0, $comBdi['bdi_percentual'], 'parser: BDI pela razão entre preços');
$assertSame(400.0, $comBdi['itens'][0]['preco_unitario'], 'parser: usar custo sem BDI');
$assertSame([], (new PlanilhaOrcamentoParser())->analisar([1 => [0 => 'Aço CA-50', 1 => 'Peso kg/m']], 'Tabela')['itens'], 'parser: rejeitar tabela sem cabeçalho de orçamento');

$assertSame(true, OrcamentoStatus::podeTransitar('cotado', 'aprovado'), 'status: aprovar cotado');
$assertSame(false, OrcamentoStatus::podeTransitar('aprovado', 'reprovado'), 'status: aprovado não vira reprovado direto');
$assertSame(false, OrcamentoStatus::editavel('aprovado'), 'status: aprovado não é editável');
$assertThrows(static fn () => OrcamentoStatus::validarTransicao('cancelado', 'aprovado'), 'status: rejeitar transição inválida');


// Colunas de especificação completam a descrição; título de seção vira etapa
$lista = (new PlanilhaOrcamentoParser())->analisar([
    3 => [0 => 'Esgoto'],
    5 => [0 => 'Nº', 1 => 'Descrição', 2 => 'Item', 3 => 'Quantidade', 4 => 'Unidade'],
    6 => [0 => 1, 1 => 'Joelho 45', 2 => '100 mm', 3 => 8, 4 => 'pç'],
    7 => [0 => 2, 1 => 'Joelho 45', 2 => '40 mm', 3 => 19, 4 => 'pç'],
], 'Lista');
$assertSame('Joelho 45 100 mm', $lista['itens'][0]['descricao'] ?? null, 'parser: especificação na descrição');
$assertSame('Esgoto', $lista['itens'][1]['etapa'] ?? null, 'parser: seção vira etapa');

// Planta: quadro de áreas, ambientes e quadro de esquadrias
$planta = (new PlantaAnalyzer())->analisar("QUADRO DE ÁREAS\nÁREA TOTAL DO TERRENO 491,76 m²\nÁREA TOTAL CONSTRUÍDA 451,40 m²\nTÉRREO 176,14 m²\nPAVIMENTO SUPERIOR 144,09 m²\nBANHEIRO A=3,19 m² COZINHA A=12,80 m²\nP1 14 0,80 2,10 DE ABRIR 1 FL - MADEIRA 1,68 m²\nJ2 4 1,50 1,20 0,90 BASCULANTE - 2 FOLHAS 1,80 m²\nP07 MOMO_Portão Garagem Basculante 320x240cm 1 PORTÃO BASCULANTE 320 240\n");
$assertSame(451.4, $planta['area_construida'], 'planta: área total construída');
$assertSame(491.76, $planta['area_terreno'], 'planta: área do terreno');
$assertSame(3, count($planta['esquadrias']), 'planta: quadro de esquadrias');
$porCodigo = array_column($planta['esquadrias'], null, 'codigo');
$assertSame(14, $porCodigo['P1']['quantidade'] ?? null, 'planta: quantidade de portas P1');
$assertSame('portao', $porCodigo['P07']['tipo'] ?? null, 'planta: portão pelo quadro do Revit');

$assertSame('estrutura', Classificador::etapaPadrao('ESTRUTURA CONCRETO ARMADO'), 'classificador: etapa estrutura');
$assertSame('eletrica', Classificador::etapaPadrao('Pavto Garagem', 'Quadro de distribuição 24 disjuntores'), 'classificador: etapa pelo item');
$assertSame('concreto', Classificador::material('Concreto usinado 30MPa bombeado', 'm3'), 'classificador: concreto');
$assertSame(null, Classificador::material('Concreto magro para lastro', 'm³'), 'classificador: concreto magro não é estrutural');
$assertSame('multifamiliar', Classificador::tipologia('Obra: Residência Multifamiliar Rua Central'), 'classificador: tipologia pelo título, não pelo endereço');
$assertSame('urbanizacao', Classificador::tipologia('Revitalização Rua Bocaiúva'), 'classificador: revitalização de rua');
$assertSame(0.75, Classificador::similaridade(['joelho', '45', '100', 'mm'], ['joelho', 'pvc', '45', '100', 'mm', 'esgoto']) > 0.7 ? 0.75 : 0.0, 'classificador: similaridade cobre a busca');

$assertSame(1.0, Incc::fator(date('Y-m-01')), 'incc: mesmo mês não corrige');
$assertSame(true, Incc::fator('2015-07-01') > 1.9, 'incc: corrige orçamentos antigos');
$assertSame(20.0, EstimativaService::percentil([10.0, 20.0, 30.0], 0.5), 'percentil: mediana');
$assertSame(30.0, EstimativaService::percentilPonderado([10.0, 20.0, 30.0], [0.1, 0.1, 5.0], 0.5), 'percentil ponderado: peso domina');

$schema = (string) file_get_contents($root . '/database/schema.sql');
$schemaParts = preg_split('/--\s*Dados iniciais/iu', $schema, 2);
$schemaBase = is_array($schemaParts) ? (string) $schemaParts[0] : $schema;
$assertSame(false, str_contains($schemaBase, 'INSERT INTO usuarios'), 'remover seed inseguro do schema base');

if ($failures !== []) {
    fwrite(STDERR, "Falhas:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "Testes de domínio OK\n";
