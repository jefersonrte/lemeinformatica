<?php
declare(strict_types=1);

namespace App\Support;

/**
 * Catálogo dos temas visuais do site.
 *
 * A escolha é apenas cosmética: fica no cookie `orca_tema` do navegador e é
 * aplicada como `data-theme` no <html>. As cores de cada tema vivem em
 * assets/css/style.css; aqui ficam nome, descrição e amostras do seletor.
 *
 * O tema "personalizado" é gerado a partir de dois matizes (0–360) e de um tom
 * claro/escuro guardados no cookie `orca_cor` no formato "h1-h2-tom".
 */
final class Theme
{
    public const COOKIE = 'orca_tema';
    public const DEFAULT = 'grafite';
    public const CUSTOM = 'personalizado';
    public const COOKIE_COR = 'orca_cor';
    public const CUSTOM_DEFAULT = ['h1' => 210, 'h2' => 170, 'tom' => 'claro'];

    /** Degradês prontos do tema personalizado: nome => [matiz principal, matiz do degradê]. */
    public const GRADIENTES = [
        'Oceano' => [205, 172],
        'Pôr do sol' => [12, 42],
        'Uva' => [268, 322],
        'Floresta' => [148, 88],
        'Ardósia' => [218, 252],
        'Coral' => [352, 24],
    ];

    /** @var array<string, array{nome: string, descricao: string, esquema: string, meta: string, amostra: array{bg: string, nav: string, primary: string, accent: string}}> */
    private const TEMAS = [
        'grafite' => [
            'nome' => 'Grafite',
            'descricao' => 'Neutro moderno, detalhe índigo',
            'esquema' => 'light',
            'meta' => '#18181b',
            'amostra' => ['bg' => '#f4f4f5', 'nav' => '#18181b', 'primary' => '#27272b', 'accent' => '#5b67f1'],
        ],
        'leme' => [
            'nome' => 'Leme',
            'descricao' => 'Claro, azul institucional',
            'esquema' => 'light',
            'meta' => '#173f6a',
            'amostra' => ['bg' => '#f3f6fa', 'nav' => '#1f5d99', 'primary' => '#1f5d99', 'accent' => '#13937a'],
        ],
        'noturno' => [
            'nome' => 'Noturno',
            'descricao' => 'Escuro, descansa a vista',
            'esquema' => 'dark',
            'meta' => '#0a1323',
            'amostra' => ['bg' => '#0b1220', 'nav' => '#10203a', 'primary' => '#5b9cf8', 'accent' => '#2dd4bf'],
        ],
        'canteiro' => [
            'nome' => 'Canteiro',
            'descricao' => 'Grafite com laranja de obra',
            'esquema' => 'light',
            'meta' => '#26231f',
            'amostra' => ['bg' => '#f3f2ef', 'nav' => '#26231f', 'primary' => '#c2410c', 'accent' => '#3d6b5f'],
        ],
        'esmeralda' => [
            'nome' => 'Esmeralda',
            'descricao' => 'Verde suave e arredondado',
            'esquema' => 'light',
            'meta' => '#0d4a37',
            'amostra' => ['bg' => '#eff5f1', 'nav' => '#0f6b4d', 'primary' => '#0f7a55', 'accent' => '#0e7490'],
        ],
        'terracota' => [
            'nome' => 'Terracota',
            'descricao' => 'Tons quentes de areia e argila',
            'esquema' => 'light',
            'meta' => '#5b2f1f',
            'amostra' => ['bg' => '#f6f0e9', 'nav' => '#7a3d26', 'primary' => '#a8482c', 'accent' => '#5f7434'],
        ],
    ];

    /** @return array<string, array{nome: string, descricao: string, esquema: string, meta: string, amostra: array{bg: string, nav: string, primary: string, accent: string}}> */
    public static function all(): array
    {
        return self::TEMAS;
    }

    public static function exists(string $key): bool
    {
        return isset(self::TEMAS[$key]) || $key === self::CUSTOM;
    }

    /** Normaliza um valor recebido (cookie, formulário) para uma chave válida. */
    public static function resolve(mixed $value): string
    {
        $key = is_string($value) ? strtolower(trim($value)) : '';
        return self::exists($key) ? $key : self::DEFAULT;
    }

    /** Tema escolhido pelo visitante atual. */
    public static function current(): string
    {
        return self::resolve($_COOKIE[self::COOKIE] ?? null);
    }

    /** @return array{nome: string, descricao: string, esquema: string, meta: string, amostra: array{bg: string, nav: string, primary: string, accent: string}} */
    public static function get(string $key): array
    {
        $key = self::resolve($key);
        if ($key !== self::CUSTOM) {
            return self::TEMAS[$key];
        }
        $cor = self::custom();
        return [
            'nome' => 'Personalizado',
            'descricao' => 'Seu degradê de cores',
            'esquema' => $cor['tom'] === 'escuro' ? 'dark' : 'light',
            'meta' => sprintf('hsl(%d, 50%%, %d%%)', $cor['h1'], $cor['tom'] === 'escuro' ? 9 : 22),
            'amostra' => ['bg' => '', 'nav' => '', 'primary' => '', 'accent' => ''],
        ];
    }

    /** @return array{h1: int, h2: int, tom: string} */
    public static function custom(): array
    {
        return self::parseCustom($_COOKIE[self::COOKIE_COR] ?? null);
    }

    /** @return array{h1: int, h2: int, tom: string} */
    public static function parseCustom(mixed $value): array
    {
        if (!is_string($value) || !preg_match('/^(\d{1,3})-(\d{1,3})-(claro|escuro)$/', trim($value), $m)) {
            return self::CUSTOM_DEFAULT;
        }
        return ['h1' => min(360, (int) $m[1]), 'h2' => min(360, (int) $m[2]), 'tom' => $m[3]];
    }
}
