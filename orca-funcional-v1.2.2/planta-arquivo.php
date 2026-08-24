<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap/app.php';
requireLogin();

$id = (int) ($_GET['id'] ?? 0);
$db = getDB();
if (isAdmin()) {
    $statement = $db->prepare('SELECT p.* FROM obra_plantas p WHERE p.id = ?');
    $statement->execute([$id]);
} else {
    $statement = $db->prepare(
        'SELECT p.* FROM obra_plantas p JOIN obras o ON o.id = p.obra_id '
        . 'JOIN clientes c ON c.id = o.cliente_id WHERE p.id = ? AND c.usuario_id = ?'
    );
    $statement->execute([$id, currentUserId()]);
}
$planta = $statement->fetch();
if (!$planta) {
    http_response_code(404);
    exit;
}

$uploadRoot = realpath(UPLOAD_DIR);
$file = $uploadRoot !== false ? realpath($uploadRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $planta['arquivo'])) : false;
if ($file === false || $uploadRoot === false || !str_starts_with($file, $uploadRoot . DIRECTORY_SEPARATOR) || !is_file($file)) {
    http_response_code(404);
    exit;
}

$thumbnailRequested = isset($_GET['thumb']) && str_starts_with((string) $planta['mime_type'], 'image/');
$imageDimensions = $thumbnailRequested ? @getimagesize($file) : false;
$safeToResize = is_array($imageDimensions)
    && isset($imageDimensions[0], $imageDimensions[1])
    && ((int) $imageDimensions[0] * (int) $imageDimensions[1]) <= 12_000_000;
if ($thumbnailRequested && $safeToResize && function_exists('imagecreatefromstring')) {
    $sourceContent = file_get_contents($file);
    $sourceImage = is_string($sourceContent) ? @imagecreatefromstring($sourceContent) : false;
    if ($sourceImage !== false) {
        $sourceWidth = imagesx($sourceImage);
        $sourceHeight = imagesy($sourceImage);
        $maxWidth = 520;
        $maxHeight = 380;
        $scale = min(1, $maxWidth / max(1, $sourceWidth), $maxHeight / max(1, $sourceHeight));
        $targetWidth = max(1, (int) round($sourceWidth * $scale));
        $targetHeight = max(1, (int) round($sourceHeight * $scale));
        $thumbnail = imagecreatetruecolor($targetWidth, $targetHeight);
        $white = imagecolorallocate($thumbnail, 255, 255, 255);
        imagefill($thumbnail, 0, 0, $white);
        imagecopyresampled($thumbnail, $sourceImage, 0, 0, 0, 0, $targetWidth, $targetHeight, $sourceWidth, $sourceHeight);

        header('Content-Type: image/jpeg');
        header('Cache-Control: private, max-age=3600');
        header('X-Content-Type-Options: nosniff');
        imagejpeg($thumbnail, null, 84);
        imagedestroy($thumbnail);
        imagedestroy($sourceImage);
        exit;
    }
}

header('Content-Type: ' . $planta['mime_type']);
header('Content-Length: ' . filesize($file));
header('Content-Disposition: inline; filename="' . rawurlencode($planta['nome_original']) . '"');
header('Cache-Control: private, max-age=300');
header('X-Content-Type-Options: nosniff');
header('Cross-Origin-Resource-Policy: same-origin');
if ($planta['mime_type'] === 'image/svg+xml') {
    header("Content-Security-Policy: sandbox; default-src 'none'; style-src 'unsafe-inline'; img-src data:");
}
readfile($file);
exit;
