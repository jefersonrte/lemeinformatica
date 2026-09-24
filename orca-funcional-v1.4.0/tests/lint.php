<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
$failed = [];
$count = 0;
foreach ($iterator as $file) {
    $path = $file->getPathname();
    if ($file->getExtension() !== 'php' || str_contains($path, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR)) {
        continue;
    }
    $count++;
    $command = escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($path);
    exec($command, $output, $exitCode);
    if ($exitCode !== 0) {
        $failed[] = $path . ': ' . implode(' ', $output);
    }
    $output = [];
}

if ($failed !== []) {
    fwrite(STDERR, implode("\n", $failed) . "\n");
    exit(1);
}
echo "PHP lint OK: {$count} arquivos\n";
