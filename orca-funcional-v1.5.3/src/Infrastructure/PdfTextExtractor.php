<?php
declare(strict_types=1);

namespace App\Infrastructure;

use RuntimeException;
use Smalot\PdfParser\Parser;
use Throwable;

/** Texto de PDFs de projeto: pdftotext (Poppler) quando existir, senão parser em PHP puro. */
final class PdfTextExtractor
{
    public const MAX_BYTES = 100 * 1024 * 1024;

    public function extrair(string $caminho): string
    {
        if (!is_file($caminho) || filesize($caminho) === 0) {
            throw new RuntimeException('Arquivo PDF não encontrado.');
        }
        if (filesize($caminho) > self::MAX_BYTES) {
            throw new RuntimeException('PDF muito grande para leitura automática (máximo 100 MB).');
        }

        $texto = $this->comPdftotext($caminho);
        if ($texto !== null && mb_strlen(trim($texto)) > 40) {
            return $texto;
        }

        $limite = ini_get('memory_limit');
        if ($limite !== false && $limite !== '-1' && (int) $limite > 0 && (int) $limite < 768 && str_ends_with(strtoupper($limite), 'M')) {
            @ini_set('memory_limit', '768M');
        }
        try {
            return (new Parser())->parseFile($caminho)->getText();
        } catch (Throwable $exception) {
            throw new RuntimeException('Não foi possível ler o texto do PDF: ' . $exception->getMessage());
        }
    }

    private function comPdftotext(string $caminho): ?string
    {
        if (!function_exists('shell_exec') || in_array('shell_exec', array_map('trim', explode(',', (string) ini_get('disable_functions'))), true)) {
            return null;
        }
        $binario = @shell_exec('command -v pdftotext 2>/dev/null');
        if (!is_string($binario) || trim($binario) === '') {
            return null;
        }
        $saida = @shell_exec('pdftotext -layout ' . escapeshellarg($caminho) . ' - 2>/dev/null');
        return is_string($saida) ? $saida : null;
    }
}
