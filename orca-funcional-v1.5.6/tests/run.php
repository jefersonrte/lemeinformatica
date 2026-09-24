<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/src/Domain/Orcamento/OrcamentoCalculator.php';
require_once $root . '/src/Domain/Cotacao/MensagemCotacao.php';
require_once $root . '/src/Domain/Obra/SvgSanitizer.php';
require_once $root . '/src/Domain/Importacao/ItemCatalogMatcher.php';
require_once $root . '/src/Domain/Importacao/PlanilhaOrcamentoParser.php';
require_once $root . '/src/Domain/Importacao/ResumoEstruturaParser.php';
require_once $root . '/src/Domain/Obra/AcervoPacoteImporter.php';
require_once $root . '/helpers/orcamento_editor.php';
require_once $root . '/src/Domain/Orcamento/OrcamentoStatus.php';
require_once $root . '/src/Domain/Estimativa/Classificador.php';
require_once $root . '/src/Domain/Estimativa/PlantaAnalyzer.php';
require_once $root . '/src/Domain/Estimativa/Incc.php';
require_once $root . '/src/Domain/Estimativa/EstimativaService.php';
require_once $root . '/src/Infrastructure/LegacyUploadMigrator.php';
require_once $root . '/src/Infrastructure/TablePrefixer.php';
require_once $root . '/src/Support/Theme.php';
require_once $root . '/src/Support/Layout.php';
require_once $root . '/src/Domain/Consulta/TermoBusca.php';
require_once $root . '/src/Domain/Consulta/Estatistica.php';
require_once $root . '/src/Domain/Consulta/Unidade.php';
defined('APP_NAME') || define('APP_NAME', 'Orçamentista');

use App\Domain\Cotacao\MensagemCotacao;
use App\Domain\Obra\SvgSanitizer;
use App\Domain\Importacao\ItemCatalogMatcher;
use App\Domain\Importacao\PlanilhaOrcamentoParser;
use App\Domain\Importacao\ResumoEstruturaParser;
use App\Domain\Obra\AcervoPacoteImporter;
use App\Domain\Orcamento\OrcamentoStatus;
use App\Domain\Estimativa\Classificador;
use App\Domain\Estimativa\EstimativaService;
use App\Domain\Estimativa\Incc;
use App\Domain\Estimativa\PlantaAnalyzer;
use App\Domain\Orcamento\OrcamentoCalculator;
use App\Infrastructure\TablePrefixer;
use App\Infrastructure\LegacyUploadMigrator;
use App\Support\Layout;
use App\Domain\Consulta\Estatistica;
use App\Domain\Consulta\TermoBusca;
use App\Domain\Consulta\Unidade;
use App\Support\Theme;

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
mkdir($legacyNew . DIRECTORY_SEPARATOR . 'acervo', 0750, true);
file_put_contents($legacyNew . DIRECTORY_SEPARATOR . 'acervo' . DIRECTORY_SEPARATOR . 'pacote.zip', 'temporario');
$migrationResult = (new LegacyUploadMigrator())->migrate($targetUploads, [$legacyNew, $legacyOld]);
$assertSame(2, $migrationResult['sources'], 'detectar fontes legadas de upload');
$assertSame(2, $migrationResult['copied'], 'copiar uploads legados');
$assertSame(false, is_file($targetUploads . DIRECTORY_SEPARATOR . 'acervo' . DIRECTORY_SEPARATOR . 'pacote.zip'), 'não copiar pacote temporário do acervo entre versões');
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
$assertSame(229.93, (new PlantaAnalyzer())->analisar("QUADRO DE ÁREAS\nÁREA COMPUTÁVEL ÁREA TOTAL\nTOTAL 216,05 m² 13,87 m² 229,93 m²")['area_construida'], 'planta: total como soma das parciais');
$assertSame(491.76, $planta['area_terreno'], 'planta: área do terreno');
$assertSame(3, count($planta['esquadrias']), 'planta: quadro de esquadrias');
$porCodigo = array_column($planta['esquadrias'], null, 'codigo');
$assertSame(14, $porCodigo['P1']['quantidade'] ?? null, 'planta: quantidade de portas P1');
$assertSame('portao', $porCodigo['P07']['tipo'] ?? null, 'planta: portão pelo quadro do Revit');
$fusao = (new PlantaAnalyzer())->fundir([
    $planta,
    (new PlantaAnalyzer())->analisar("PAVIMENTO SUPERIOR\nDORMITÓRIO A=10,00 m² SUÍTE A=14,00 m²\nP1 16 0,80 2,10 DE ABRIR\nJ5 2 1,00 1,00 CORRER\n"),
]);
$assertSame(451.4, $fusao['area_construida'], 'fusão de pranchas: maior área construída');
$assertSame(4, count($fusao['esquadrias']), 'fusão de pranchas: esquadrias unidas por código');
$assertSame(16, array_column($fusao['esquadrias'], null, 'codigo')['P1']['quantidade'] ?? null, 'fusão de pranchas: maior quantidade por código');
$assertSame(true, $fusao['texto_util'], 'fusão de pranchas: texto útil');


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
$assertSame(15.0, EstimativaService::percentilSuave([10.0, 20.0], [1.0, 1.0], 0.5), 'percentil suave: interpola entre obras');

// Acervo 2026-09: coluna de descrição sem rótulo, resumo estrutural, esquadrias em cm e tipologia pela planta
$semRotulo = (new PlanilhaOrcamentoParser())->analisar([
    7 => [1 => 'ITEM', 3 => 'UNID.', 4 => 'QUANT.', 7 => 'MÃO OBRA', 9 => 'TOTAL DE SERVIÇO'],
    8 => [5 => 'Unitário', 6 => 'Total', 7 => 'Unitário', 8 => 'Total'],
    9 => [1 => '1.', 2 => 'SERVIÇOS COMUNS'],
    10 => [1 => '1.1', 2 => 'Placa de obra', 3 => 'm²', 4 => 4, 5 => 150, 6 => 600, 7 => 57.2, 8 => 228.8, 9 => 828.8],
    11 => [1 => '1.2', 2 => 'Barraco de obras', 3 => 'm²', 4 => 50, 5 => 700, 6 => 35000, 7 => 275, 8 => 13750, 9 => 48750],
], 'Custo');
$assertSame('Placa de obra', $semRotulo['itens'][0]['descricao'] ?? null, 'parser: descrição em coluna sem rótulo ao lado do item');
$assertSame(49578.8, round($semRotulo['total_importado'], 2), 'parser: material + mão de obra pelo total da linha');
$estrutura = (new ResumoEstruturaParser())->analisar([['nome' => 'RESUMO - PESO', 'linhas' => [
    10 => [0 => 'PAVIMENTO', 2 => 'ELEMENTO', 3 => 'PESO DO AÇO + 10 % (kg)', 11 => 'ÁREA DE FORMAS (m²)', 12 => 'VOLUME DE CONCRETO (m³)'],
    11 => [3 => 'ø 5,0', 4 => 'ø 10,0', 10 => 'TOTAL'],
    12 => [0 => 'TÉRREO', 2 => 'VIGAS', 3 => 50.0, 4 => 100.0, 10 => 150.0, 11 => 40.0, 12 => 4.0],
    13 => [2 => 'SAPATAS', 3 => 0.0, 4 => 20.0, 10 => 20.0, 11 => 10.0, 12 => 3.0],
    14 => [2 => 'TOTAL', 3 => 50.0, 4 => 120.0, 10 => 170.0, 11 => 50.0, 12 => 7.0],
    16 => [0 => 'fck=', 1 => '30 Mpa'],
]]]);
$porDescricao = array_column($estrutura['itens'] ?? [], 'quantidade', 'descricao');
$assertSame(3.0, $porDescricao['Concreto usinado bombeado fck 30 MPa — fundação'] ?? null, 'estrutura: concreto da fundação separado');
$assertSame(4.0, $porDescricao['Concreto usinado bombeado fck 30 MPa — estrutura'] ?? null, 'estrutura: total do pavimento não soma em dobro');
$assertSame(120.0, $porDescricao['Aço CA-50 ø 10,0 mm para armadura, corte e dobra'] ?? null, 'estrutura: aço por bitola');
$assertSame(50.0, $porDescricao['Aço CA-60 ø 5,0 mm para armadura, corte e dobra'] ?? null, 'estrutura: ø 5,0 é CA-60');
$cm = (new PlantaAnalyzer())->analisar("TABELA DE ESQUADRIAS\n J05 180X215 30 Correr, alumínio e vidro. 02\n P02 80X210 - Madeira de giro, interna. 04\n EC200       200           160           80               1     Alumínio e vidro temperado - 02 folhas de correr\n EC200       200           130           110              1     Alumínio e vidro temperado - 02 folhas de correr\nÁrea Total Construída da Edificação 9.367,66m²");
$assertSame(4, count($cm['esquadrias']), 'planta: esquadrias em centímetros (código + LxA e colunas separadas)');
$assertSame(4, array_column($cm['esquadrias'], null, 'codigo')['P02']['quantidade'] ?? null, 'planta: quantidade após a descrição');
$assertSame(9367.66, $cm['area_construida'], 'planta: área total construída da edificação');
$assertSame('multifamiliar', Classificador::tipologiaDaPlanta('PAVIMENTO TIPO APTO 101 APTO 102 residência unifamiliar'), 'planta: pavimento tipo é multifamiliar');
$assertSame('residencial', Classificador::tipologiaDaPlanta('Residência unifamiliar SUÍTE DORMITÓRIO EDIFICAÇÃO 60'), 'planta: residência unifamiliar');
$assertSame('multifamiliar', Classificador::tipologiaDaPlanta('planta baixa', 2500.0), 'planta: residência acima de 1.200 m² vira multifamiliar');
$assertSame('multifamiliar', Classificador::tipologia('ORÇAMENTO DE 3 CASAS Obra: Residência Unifamiliar'), 'classificador: três casas é geminado');
$assertSame('reforma', Classificador::tipologia('Obra: REFORMA - APARTAMENTO 1602'), 'classificador: reforma de apartamento');
$assertSame('comercial', Classificador::tipologia('Hemosc Doação'), 'classificador: hemocentro é institucional');

// Pacote do acervo: manifesto versionado dentro do ZIP
$zipTeste = sys_get_temp_dir() . '/orca-acervo-teste-' . getmypid() . '.zip';
$zip = new ZipArchive();
$zip->open($zipTeste, ZipArchive::CREATE | ZipArchive::OVERWRITE);
$zip->addFromString('manifest.json', json_encode(['versao' => 1, 'gerado_em' => '2026-09-24T10:00:00-03:00', 'obras' => [['cliente' => [], 'obra' => [], 'orcamentos' => [], 'plantas' => []]]]));
$zip->close();
$assertSame(1, count(AcervoPacoteImporter::lerManifesto($zipTeste)['obras']), 'acervo: lê o manifesto do pacote');
$zip->open($zipTeste, ZipArchive::OVERWRITE);
$zip->addFromString('manifest.json', '{"versao":99}');
$zip->close();
$assertThrows(static fn () => AcervoPacoteImporter::lerManifesto($zipTeste), 'acervo: rejeita pacote de outra versão');
$assertThrows(static fn () => AcervoPacoteImporter::lerManifesto($zipTeste . '.inexistente'), 'acervo: rejeita arquivo ausente');
@unlink($zipTeste);

// Itens do editor chegam num único campo JSON (orçamentos grandes não esbarram no max_input_vars)
$muitos = array_map(static fn (int $i): array => ['etapa' => 'Estrutura', 'descricao' => 'Item ' . $i, 'unidade' => 'M²', 'quantidade' => '2.5', 'preco_unitario' => '10', 'id' => '', 'categoria_id' => ''], range(1, 1200));
$doPost = orcamentoItensDoPost(['itens_json' => json_encode($muitos), 'item_desc' => ['ignorado']]);
$assertSame(1200, count($doPost), 'editor: 1.200 itens pelo campo JSON');
$assertSame(null, $doPost[0]['id'], 'editor: id vazio vira item novo');
$assertSame(1200, $doPost[1199]['ordem'], 'editor: ordem preservada');
$assertSame('Item 1', orcamentoItensDoPost(['item_desc' => ['Item 1'], 'item_qtd' => [1]])[0]['descricao'], 'editor: formato antigo continua aceito');
$assertThrows(static fn () => orcamentoItensDoPost(['itens_json' => '{quebrado']), 'editor: rejeita JSON inválido');

$schema = (string) file_get_contents($root . '/database/schema.sql');
$schemaParts = preg_split('/--\s*Dados iniciais/iu', $schema, 2);
$schemaBase = is_array($schemaParts) ? (string) $schemaParts[0] : $schema;
$assertSame(false, str_contains($schemaBase, 'INSERT INTO usuarios'), 'remover seed inseguro do schema base');

$assertSame(7, count(Theme::all()), 'temas: sete opções prontas');
$assertSame('grafite-escuro', Theme::resolve(null), 'temas: padrão sem cookie é o grafite escuro');
$assertSame('noturno', Theme::resolve(' NOTURNO '), 'temas: normaliza caixa e espaços');
$assertSame('grafite-escuro', Theme::resolve('"><script>'), 'temas: rejeita valor desconhecido');
$assertSame('grafite-escuro', Theme::resolve(['canteiro']), 'temas: rejeita array');
$css = (string) file_get_contents($root . '/assets/css/style.css');
foreach (array_keys(Theme::all()) as $tema) {
    $assertContains('[data-theme="' . $tema . '"]', $css, 'temas: CSS define ' . $tema);
}
$assertSame(['topo', 'lateral', 'trilho', 'faixas', 'dock'], array_keys(Layout::all()), 'layout: cinco disposições');
$assertSame('personalizado', Theme::resolve('personalizado'), 'temas: aceita personalizado');
$assertSame(['h1' => 12, 'h2' => 42, 'tom' => 'escuro'], Theme::parseCustom('12-42-escuro'), 'temas: lê degradê do cookie');
$assertSame(['h1' => 360, 'h2' => 0, 'tom' => 'claro'], Theme::parseCustom('999-0-claro'), 'temas: limita matiz a 360');
$assertSame(Theme::CUSTOM_DEFAULT, Theme::parseCustom('1;--x:red-2-claro'), 'temas: rejeita cookie malformado');
$assertContains('[data-theme="personalizado"][data-tone="escuro"]', $css, 'temas: CSS do personalizado escuro');
$assertSame('topo', Layout::resolve('lateral"'), 'layout: rejeita valor desconhecido');
$assertSame('trilho', Layout::resolve('Trilho'), 'layout: normaliza caixa');
foreach (['lateral', 'trilho', 'faixas', 'dock'] as $layout) {
    $assertContains('[data-layout="' . $layout . '"]', $css, 'layout: CSS define ' . $layout);
}

// Busca e consultas
$assertSame(['porcelanato', '60x60', 'cinza'], TermoBusca::termos('  Porcelanato 60x60, cinza porcelanato '), 'busca: termos sem repetição e em minúsculas');
$assertSame(['casa', '2'], TermoBusca::termos('a casa 2 "'), 'busca: ignora palavras de 1 letra, mantém números');
$assertSame(6, count(TermoBusca::termos('a1 b2 c3 d4 e5 f6 g7 h8')), 'busca: limita a 6 termos');
$assertSame('%10\\%\\_x%', TermoBusca::like('10%_x'), 'busca: escapa curingas do LIKE');
$params = [];
$assertSame("(CONCAT_WS(' ', a, b) LIKE ? AND CONCAT_WS(' ', a, b) LIKE ?)", TermoBusca::condicao(['a', 'b'], ['x', 'y'], $params), 'busca: todos os termos em qualquer coluna');
$assertSame(['%x%', '%y%'], $params, 'busca: parâmetros preparados');
$params = [];
$assertSame('1=1', TermoBusca::condicao(['a'], [], $params), 'busca: sem termos não filtra');
$assertSame('<mark>Cerâmica</mark> 45x45 &lt;b&gt;', TermoBusca::destacar('Cerâmica 45x45 <b>', ['ceramica']), 'busca: destaca sem acento e escapa HTML');
$assertSame('Tinta <mark>acr</mark>ílica', TermoBusca::destacar('Tinta acrílica', ['acr']), 'busca: destaque parcial');
$resumo = Estatistica::resumo([10, 0, 30, 20, 40]);
$assertSame(4, $resumo['n'], 'preços: ignora zeros');
$assertSame(25.0, $resumo['mediana'], 'preços: mediana par');
$assertSame(17.5, $resumo['p25'], 'preços: quartil inferior');
$assertSame(null, Estatistica::resumo([0, 0]), 'preços: sem valores válidos');
$assertSame('M²', Unidade::normalizar('m2'), 'unidade: m2');
$assertSame('PC', Unidade::normalizar('pç'), 'unidade: pç');
$assertSame('UN', Unidade::normalizar('Unid.'), 'unidade: Unid.');
$assertSame('M?', Unidade::normalizar("m\u{FFFD}"), 'unidade: codificação quebrada');
$assertSame('https://wa.me/5548999990000?text=Ol%C3%A1', MensagemCotacao::linkWhatsapp('(48) 99999-0000', 'Olá'), 'cotação: link WhatsApp com DDI');
$assertSame(null, MensagemCotacao::linkWhatsapp('123', 'x'), 'cotação: WhatsApp inválido');

if ($failures !== []) {
    fwrite(STDERR, "Falhas:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "Testes de domínio OK\n";
