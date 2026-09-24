<?php
declare(strict_types=1);

namespace App\Domain\Consulta;

/**
 * Regras da busca textual usadas pela busca global e pelas consultas.
 *
 * Cada palavra digitada vira um termo; um registro só aparece quando contém
 * todos os termos, em qualquer ordem e em qualquer das colunas pesquisadas
 * (como a busca do GitHub ou do Google Drive). A comparação sem acentos fica
 * por conta da collation utf8mb4_unicode_ci do banco.
 */
final class TermoBusca
{
    public const MAX_TERMOS = 6;

    /** @return list<string> termos normalizados, sem repetição */
    public static function termos(string $consulta, int $max = self::MAX_TERMOS): array
    {
        $consulta = mb_strtolower(trim($consulta), 'UTF-8');
        $partes = preg_split('/[\s,;]+/u', $consulta, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $termos = [];
        foreach ($partes as $parte) {
            $parte = trim($parte, "\"'()[]{}");
            $minimo = ctype_digit($parte) ? 1 : 2;
            if (mb_strlen($parte, 'UTF-8') < $minimo || in_array($parte, $termos, true)) {
                continue;
            }
            $termos[] = mb_substr($parte, 0, 60, 'UTF-8');
            if (count($termos) >= $max) {
                break;
            }
        }
        return $termos;
    }

    /** Padrão LIKE com curingas do usuário escapados. */
    public static function like(string $termo): string
    {
        return '%' . addcslashes($termo, '\\%_') . '%';
    }

    /**
     * Condição SQL "todos os termos em alguma das colunas".
     *
     * @param list<string> $colunas expressões SQL confiáveis (nunca entrada do usuário)
     * @param list<string> $termos
     * @param list<mixed>  $params recebe os valores para o prepared statement
     */
    public static function condicao(array $colunas, array $termos, array &$params): string
    {
        if ($termos === [] || $colunas === []) {
            return '1=1';
        }
        $alvo = count($colunas) === 1 ? $colunas[0] : "CONCAT_WS(' ', " . implode(', ', $colunas) . ')';
        $partes = [];
        foreach ($termos as $termo) {
            $partes[] = $alvo . ' LIKE ?';
            $params[] = self::like($termo);
        }
        return '(' . implode(' AND ', $partes) . ')';
    }

    /** Texto escapado para HTML com os termos encontrados envoltos em <mark>. */
    public static function destacar(string $texto, array $termos): string
    {
        $chars = mb_str_split($texto, 1, 'UTF-8');
        $dobrado = array_map(static fn (string $c): string => self::dobrar($c), $chars);
        $marcado = array_fill(0, count($chars), false);
        $base = implode('', $dobrado);
        foreach ($termos as $termo) {
            $alvo = implode('', array_map(static fn (string $c): string => self::dobrar($c), mb_str_split($termo, 1, 'UTF-8')));
            $tamanho = mb_strlen($alvo, 'UTF-8');
            if ($tamanho === 0) {
                continue;
            }
            $inicio = 0;
            while (($pos = mb_strpos($base, $alvo, $inicio, 'UTF-8')) !== false) {
                for ($i = $pos; $i < $pos + $tamanho; $i++) {
                    $marcado[$i] = true;
                }
                $inicio = $pos + $tamanho;
            }
        }
        $html = '';
        $aberto = false;
        foreach ($chars as $i => $char) {
            if ($marcado[$i] !== $aberto) {
                $html .= $marcado[$i] ? '<mark>' : '</mark>';
                $aberto = $marcado[$i];
            }
            $html .= htmlspecialchars($char, ENT_QUOTES, 'UTF-8');
        }
        return $html . ($aberto ? '</mark>' : '');
    }

    /** Um caractere em minúsculas e sem acento (mantém 1:1 com o original). */
    private static function dobrar(string $char): string
    {
        static $mapa = [
            'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i', 'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c', 'ñ' => 'n',
        ];
        $lower = mb_strtolower($char, 'UTF-8');
        return $mapa[$lower] ?? $lower;
    }
}
