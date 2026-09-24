<?php
declare(strict_types=1);

namespace App\Domain\Orcamento;

use InvalidArgumentException;

/** Estados do orçamento e transições permitidas pela tela. */
final class OrcamentoStatus
{
    public const TODOS = ['rascunho', 'aguardando_cotacao', 'cotado', 'aprovado', 'reprovado', 'cancelado'];

    private const TRANSICOES = [
        'rascunho' => ['aguardando_cotacao', 'aprovado', 'reprovado', 'cancelado'],
        'aguardando_cotacao' => ['rascunho', 'cotado', 'aprovado', 'reprovado', 'cancelado'],
        'cotado' => ['aprovado', 'reprovado', 'cancelado', 'rascunho'],
        'aprovado' => ['rascunho', 'cancelado'],
        'reprovado' => ['rascunho', 'cancelado'],
        'cancelado' => ['rascunho'],
    ];

    /** Itens só podem ser alterados antes da decisão final. */
    private const EDITAVEIS = ['rascunho', 'aguardando_cotacao', 'cotado', 'reprovado'];

    public static function podeTransitar(string $de, string $para): bool
    {
        return in_array($para, self::TRANSICOES[$de] ?? [], true);
    }

    public static function validarTransicao(string $de, string $para): void
    {
        if (!in_array($para, self::TODOS, true)) {
            throw new InvalidArgumentException('Status de orçamento inválido.');
        }
        if (!self::podeTransitar($de, $para)) {
            throw new InvalidArgumentException('Não é possível passar de "' . self::rotulo($de) . '" para "' . self::rotulo($para) . '".');
        }
    }

    public static function editavel(string $status): bool
    {
        return in_array($status, self::EDITAVEIS, true);
    }

    /** @return list<string> */
    public static function proximos(string $status): array
    {
        return self::TRANSICOES[$status] ?? [];
    }

    public static function rotulo(string $status): string
    {
        return [
            'rascunho' => 'Rascunho',
            'aguardando_cotacao' => 'Aguardando cotação',
            'cotado' => 'Cotado',
            'aprovado' => 'Aprovado',
            'reprovado' => 'Reprovado',
            'cancelado' => 'Cancelado',
        ][$status] ?? $status;
    }
}
