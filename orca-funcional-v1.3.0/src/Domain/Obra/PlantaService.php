<?php
declare(strict_types=1);

namespace App\Domain\Obra;

use InvalidArgumentException;
use PDO;
use RuntimeException;

final class PlantaService
{
    private const MIME_TYPES = [
        'application/pdf' => 'pdf',
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/webp' => 'webp',
        'image/svg+xml' => 'svg',
    ];

    public function __construct(private readonly PDO $db, private readonly string $uploadRoot, private readonly int $maxMegabytes)
    {
    }

    public function listar(int $obraId): array
    {
        $statement = $this->db->prepare(
            'SELECT p.*, u.nome AS usuario_nome FROM obra_plantas p '
            . 'LEFT JOIN usuarios u ON u.id = p.usuario_id WHERE p.obra_id = ? '
            . 'ORDER BY p.titulo, p.versao DESC, p.criado_em DESC'
        );
        $statement->execute([$obraId]);
        return $statement->fetchAll();
    }

    public function armazenar(int $obraId, string $titulo, string $descricao, array $file, ?int $usuarioId): int
    {
        $titulo = trim($titulo);
        if ($obraId <= 0 || $titulo === '') {
            throw new InvalidArgumentException('Obra e título da planta são obrigatórios.');
        }
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string) ($file['tmp_name'] ?? ''))) {
            throw new InvalidArgumentException('Selecione um arquivo de planta válido.');
        }

        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0 || $size > $this->maxMegabytes * 1024 * 1024) {
            throw new InvalidArgumentException("A planta deve ter no máximo {$this->maxMegabytes} MB.");
        }

        $temporaryFile = (string) $file['tmp_name'];
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($temporaryFile);
        $extension = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        $sanitizedSvg = null;
        if ($extension === 'svg' || $mime === 'image/svg+xml') {
            $svgContent = file_get_contents($temporaryFile);
            if (!is_string($svgContent)) {
                throw new InvalidArgumentException('Não foi possível ler o arquivo SVG.');
            }
            $sanitizedSvg = SvgSanitizer::sanitize($svgContent);
            $mime = 'image/svg+xml';
            $size = strlen($sanitizedSvg);
        }
        if (!is_string($mime) || !isset(self::MIME_TYPES[$mime])) {
            throw new InvalidArgumentException('Formato não permitido. Use PDF, PNG, JPG, WEBP ou SVG.');
        }

        $versionStatement = $this->db->prepare('SELECT COALESCE(MAX(versao), 0) + 1 FROM obra_plantas WHERE obra_id = ? AND titulo = ?');
        $versionStatement->execute([$obraId, $titulo]);
        $version = (int) $versionStatement->fetchColumn();

        $relativeDirectory = 'plantas/' . $obraId;
        $directory = rtrim($this->uploadRoot, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativeDirectory);
        if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
            throw new RuntimeException('Não foi possível preparar a pasta de plantas.');
        }

        $fileName = bin2hex(random_bytes(16)) . '.' . self::MIME_TYPES[$mime];
        $relativePath = $relativeDirectory . '/' . $fileName;
        $destination = $directory . DIRECTORY_SEPARATOR . $fileName;
        $stored = $sanitizedSvg !== null
            ? file_put_contents($destination, $sanitizedSvg, LOCK_EX) !== false
            : move_uploaded_file($temporaryFile, $destination);
        if (!$stored) {
            throw new RuntimeException('Não foi possível armazenar a planta.');
        }

        try {
            $statement = $this->db->prepare(
                'INSERT INTO obra_plantas (obra_id, titulo, descricao, arquivo, nome_original, mime_type, tamanho, versao, usuario_id) '
                . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $statement->execute([
                $obraId,
                $titulo,
                trim($descricao) ?: null,
                $relativePath,
                mb_substr(basename((string) ($file['name'] ?? 'planta')), 0, 255),
                $mime,
                $size,
                $version,
                $usuarioId,
            ]);
            return (int) $this->db->lastInsertId();
        } catch (\Throwable $exception) {
            @unlink($destination);
            throw $exception;
        }
    }
}
