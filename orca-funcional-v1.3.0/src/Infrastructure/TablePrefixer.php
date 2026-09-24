<?php
declare(strict_types=1);

namespace App\Infrastructure;

use InvalidArgumentException;

final class TablePrefixer
{
    private const TABLES = [
        'schema_migrations', 'fornecedor_categorias', 'orcamento_itens', 'cotacao_itens',
        'obra_plantas', 'obra_etapas', 'fornecedores', 'orcamentos', 'categorias',
        'clientes', 'cotacoes', 'produtos', 'usuarios', 'compras', 'logs', 'obras',
    ];

    public function __construct(private readonly string $prefix)
    {
        if ($prefix !== '' && preg_match('/^[a-z][a-z0-9_]{0,23}$/', $prefix) !== 1) {
            throw new InvalidArgumentException('Prefixo de tabelas inválido.');
        }
    }

    public function transform(string $sql): string
    {
        if ($this->prefix === '') {
            return $sql;
        }
        foreach (self::TABLES as $table) {
            $sql = preg_replace('/(?<![a-zA-Z0-9_])' . preg_quote($table, '/') . '(?![a-zA-Z0-9_])/i', $this->prefix . $table, $sql) ?? $sql;
        }
        return $sql;
    }
}
