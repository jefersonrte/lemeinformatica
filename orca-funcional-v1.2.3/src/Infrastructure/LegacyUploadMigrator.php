<?php
declare(strict_types=1);

namespace App\Infrastructure;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

final class LegacyUploadMigrator
{
    /**
     * @param list<string> $sourceDirectories
     * @return array{sources:int,copied:int,existing:int}
     */
    public function migrate(string $targetDirectory, array $sourceDirectories): array
    {
        $targetDirectory = rtrim($targetDirectory, '/\\');
        if ($targetDirectory === '') {
            throw new RuntimeException('Diretório de uploads de destino inválido.');
        }
        if (!is_dir($targetDirectory) && !mkdir($targetDirectory, 0750, true) && !is_dir($targetDirectory)) {
            throw new RuntimeException('Não foi possível preparar o diretório de uploads.');
        }

        $targetRoot = realpath($targetDirectory);
        if ($targetRoot === false) {
            throw new RuntimeException('Não foi possível resolver o diretório de uploads.');
        }

        $result = ['sources' => 0, 'copied' => 0, 'existing' => 0];
        foreach (array_values(array_unique($sourceDirectories)) as $sourceDirectory) {
            $sourceRoot = realpath($sourceDirectory);
            if ($sourceRoot === false || !is_dir($sourceRoot) || $sourceRoot === $targetRoot) {
                continue;
            }

            $result['sources']++;
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($sourceRoot, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::LEAVES_ONLY
            );
            foreach ($iterator as $file) {
                if (!$file->isFile() || $file->isLink()) {
                    continue;
                }

                $sourceFile = $file->getPathname();
                $relative = substr($sourceFile, strlen($sourceRoot) + 1);
                if (!is_string($relative) || $relative === '' || str_contains($relative, '..')) {
                    continue;
                }

                $targetFile = $targetRoot . DIRECTORY_SEPARATOR . $relative;
                if (is_file($targetFile)) {
                    $result['existing']++;
                    continue;
                }

                $targetParent = dirname($targetFile);
                if (!is_dir($targetParent) && !mkdir($targetParent, 0750, true) && !is_dir($targetParent)) {
                    throw new RuntimeException('Não foi possível preparar uma pasta do acervo legado.');
                }
                if (!copy($sourceFile, $targetFile)) {
                    throw new RuntimeException('Não foi possível migrar um arquivo do acervo legado.');
                }
                $result['copied']++;
            }
        }

        return $result;
    }
}
