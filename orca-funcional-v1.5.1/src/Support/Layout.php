<?php
declare(strict_types=1);

namespace App\Support;

/**
 * Disposições da navegação principal.
 *
 * Como o tema, é uma preferência cosmética do navegador (cookie `orca_layout`)
 * aplicada como `data-layout` no <html>. O mesmo HTML do menu serve a todas as
 * disposições; o CSS decide como os grupos e submenus aparecem.
 */
final class Layout
{
    public const COOKIE = 'orca_layout';
    public const DEFAULT = 'topo';

    /** @var array<string, array{nome: string, descricao: string, icone: string}> */
    private const LAYOUTS = [
        'topo' => [
            'nome' => 'Topo com submenus',
            'descricao' => 'Barra horizontal, grupos suspensos',
            'icone' => 'fa-window-maximize',
        ],
        'lateral' => [
            'nome' => 'Lateral',
            'descricao' => 'Menu à esquerda com grupos recolhíveis',
            'icone' => 'fa-table-columns',
        ],
        'trilho' => [
            'nome' => 'Trilho de ícones',
            'descricao' => 'Coluna compacta, submenu flutuante',
            'icone' => 'fa-grip-lines-vertical',
        ],
        'faixas' => [
            'nome' => 'Duas faixas',
            'descricao' => 'Grupos no topo, páginas do grupo em abas',
            'icone' => 'fa-bars-staggered',
        ],
        'dock' => [
            'nome' => 'Dock flutuante',
            'descricao' => 'Barra flutuante na base da tela',
            'icone' => 'fa-dock',
        ],
    ];

    /** @return array<string, array{nome: string, descricao: string, icone: string}> */
    public static function all(): array
    {
        return self::LAYOUTS;
    }

    public static function resolve(mixed $value): string
    {
        $key = is_string($value) ? strtolower(trim($value)) : '';
        return isset(self::LAYOUTS[$key]) ? $key : self::DEFAULT;
    }

    public static function current(): string
    {
        return self::resolve($_COOKIE[self::COOKIE] ?? null);
    }
}
