<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/src/Domain/Orcamento/OrcamentoCalculator.php';
require_once $root . '/src/Domain/Cotacao/MensagemCotacao.php';
require_once $root . '/src/Domain/Obra/SvgSanitizer.php';
require_once $root . '/src/Domain/Importacao/ItemCatalogMatcher.php';
require_once $root . '/src/Infrastructure/LegacyUploadMigrator.php';
require_once $root . '/src/Infrastructure/TablePrefixer.php';
defined('APP_NAME') || define('APP_NAME', 'Orçamentista');

use App\Domain\Cotacao\MensagemCotacao;
use App\Domain\Obra\SvgSanitizer;
use App\Domain\Importacao\ItemCatalogMatcher;
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

$schema = (string) file_get_contents($root . '/database/schema.sql');
$schemaParts = preg_split('/--\s*Dados iniciais/iu', $schema, 2);
$schemaBase = is_array($schemaParts) ? (string) $schemaParts[0] : $schema;
$assertSame(false, str_contains($schemaBase, 'INSERT INTO usuarios'), 'remover seed inseguro do schema base');

if ($failures !== []) {
    fwrite(STDERR, "Falhas:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "Testes de domínio OK\n";
