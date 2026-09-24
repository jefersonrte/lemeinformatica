<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap/app.php';

requireAdmin();
$db = getDB();
$id = (int) ($_GET['id'] ?? 0);

$statement = $db->prepare('SELECT arquivo_resp FROM cotacoes WHERE id = ?');
$statement->execute([$id]);
$arquivo = (string) $statement->fetchColumn();

$root = realpath(UPLOAD_DIR);
$path = $arquivo !== '' ? realpath(rtrim(UPLOAD_DIR, '/') . '/' . $arquivo) : false;
if ($root === false || $path === false || !str_starts_with($path, $root . DIRECTORY_SEPARATOR) || !is_file($path)) {
    http_response_code(404);
    exit('Arquivo não encontrado.');
}

$tipos = [
    'pdf' => 'application/pdf',
    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'xls' => 'application/vnd.ms-excel',
    'csv' => 'text/csv',
    'xml' => 'application/xml',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
];
$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
$inline = in_array($ext, ['pdf', 'jpg', 'jpeg', 'png'], true);

header('Content-Type: ' . ($tipos[$ext] ?? 'application/octet-stream'));
header('Content-Length: ' . (string) filesize($path));
header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . basename($path) . '"');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox");
header('Cache-Control: private, no-store');
readfile($path);
exit;
