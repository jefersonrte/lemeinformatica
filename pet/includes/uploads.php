<?php
declare(strict_types=1);

function pet_upload_photo(array $file, string $category): string
{
    $allowedCategories = ['tutores', 'animais', 'veterinarios'];
    if (!in_array($category, $allowedCategories, true)) {
        json_response(['ok' => false, 'erro' => 'Categoria de foto invalida.'], 422);
    }

    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        json_response(['ok' => false, 'erro' => 'Selecione uma foto valida.'], 422);
    }

    $size = (int) ($file['size'] ?? 0);
    if ($size < 1 || $size > PET_MAX_UPLOAD_BYTES) {
        json_response(['ok' => false, 'erro' => 'A foto deve ter no maximo 5 MB.'], 422);
    }

    $temporaryPath = (string) ($file['tmp_name'] ?? '');
    if ($temporaryPath === '' || !is_uploaded_file($temporaryPath)) {
        json_response(['ok' => false, 'erro' => 'O envio da foto nao foi reconhecido.'], 422);
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string) $finfo->file($temporaryPath);
    $extensions = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    if (!isset($extensions[$mime]) || @getimagesize($temporaryPath) === false) {
        json_response(['ok' => false, 'erro' => 'Use uma imagem JPG, PNG ou WebP.'], 422);
    }

    $directory = PET_UPLOAD_ROOT . '/' . $category;
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('Nao foi possivel preparar o diretorio de fotos.');
    }

    $filename = bin2hex(random_bytes(18)) . '.' . $extensions[$mime];
    $destination = $directory . '/' . $filename;

    if (!move_uploaded_file($temporaryPath, $destination)) {
        throw new RuntimeException('Nao foi possivel armazenar a foto.');
    }

    return 'uploads/' . $category . '/' . $filename;
}

function pet_remove_photo(?string $relativePath): void
{
    if (!$relativePath || !preg_match('#^uploads/(tutores|animais|veterinarios)/[a-f0-9]{36}\.(jpg|png|webp)$#', $relativePath)) {
        return;
    }

    $absolutePath = PET_ROOT . '/' . $relativePath;
    if (is_file($absolutePath)) {
        @unlink($absolutePath);
    }
}
