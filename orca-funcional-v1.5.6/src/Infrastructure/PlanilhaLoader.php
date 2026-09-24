<?php
declare(strict_types=1);

namespace App\Infrastructure;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use RuntimeException;
use Throwable;

/**
 * Lê as abas de uma planilha como matrizes de valores já calculados.
 * Fórmulas usam o último valor salvo pelo Excel (não dependem de recálculo).
 */
final class PlanilhaLoader
{
    public const MAX_LINHAS = 3000;
    public const MAX_COLUNAS = 40;

    public function __construct(private readonly int $maxLinhas = self::MAX_LINHAS)
    {
    }

    /** @return list<array{nome:string,oculta:bool,linhas:array<int,array<int,mixed>>}> */
    public function carregar(string $caminho, string $extensao = ''): array
    {
        $extensao = strtolower($extensao !== '' ? $extensao : pathinfo($caminho, PATHINFO_EXTENSION));
        if ($extensao === 'xlsb') {
            throw new RuntimeException('Arquivos .xlsb não são suportados. Abra no Excel e salve como .xlsx.');
        }

        try {
            $reader = IOFactory::createReaderForFile($caminho);
        } catch (Throwable) {
            throw new RuntimeException('Formato de planilha não reconhecido.');
        }
        $reader->setReadDataOnly(true);
        if (method_exists($reader, 'setReadEmptyCells')) {
            $reader->setReadEmptyCells(false);
        }
        $reader->setReadFilter(new class ($this->maxLinhas) implements IReadFilter {
            public function __construct(private readonly int $maxLinhas)
            {
            }

            public function readCell(string $columnAddress, int $row, string $worksheetName = ''): bool
            {
                return $row <= $this->maxLinhas
                    && Coordinate::columnIndexFromString($columnAddress) <= PlanilhaLoader::MAX_COLUNAS;
            }
        });

        $limiteAnterior = ini_get('memory_limit');
        if ($limiteAnterior !== false && $limiteAnterior !== '-1' && self::bytes($limiteAnterior) < 512 * 1024 * 1024) {
            @ini_set('memory_limit', '512M');
        }

        try {
            $planilha = $reader->load($caminho);
        } catch (Throwable $exception) {
            throw new RuntimeException('Não foi possível ler a planilha: ' . $exception->getMessage());
        }

        $abas = [];
        foreach ($planilha->getAllSheets() as $aba) {
            $linhas = [];
            foreach ($aba->getCellCollection()->getCoordinates() as $coordenada) {
                $celula = $aba->getCell($coordenada);
                $valor = $celula->getValue();
                if ($celula->isFormula()) {
                    $valor = $celula->getOldCalculatedValue();
                    if ($valor === null) {
                        try {
                            $valor = $celula->getCalculatedValue();
                        } catch (Throwable) {
                            $valor = null;
                        }
                    }
                }
                if ($valor instanceof \PhpOffice\PhpSpreadsheet\RichText\RichText) {
                    $valor = $valor->getPlainText();
                }
                if ($valor === null || $valor === '' || (is_string($valor) && str_starts_with($valor, '#'))) {
                    continue;
                }
                [$coluna, $linha] = Coordinate::coordinateFromString($coordenada);
                $linhas[(int) $linha][Coordinate::columnIndexFromString($coluna) - 1] = $valor;
            }
            ksort($linhas);
            foreach ($linhas as &$celulas) {
                ksort($celulas);
            }
            unset($celulas);
            $abas[] = [
                'nome' => $aba->getTitle(),
                'oculta' => $aba->getSheetState() !== \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::SHEETSTATE_VISIBLE,
                'linhas' => $linhas,
            ];
        }
        $planilha->disconnectWorksheets();
        return $abas;
    }

    private static function bytes(string $valor): int
    {
        $numero = (int) $valor;
        return match (strtolower(substr(trim($valor), -1))) {
            'g' => $numero * 1024 ** 3,
            'm' => $numero * 1024 ** 2,
            'k' => $numero * 1024,
            default => $numero,
        };
    }
}
