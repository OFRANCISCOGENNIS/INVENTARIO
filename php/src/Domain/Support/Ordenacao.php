<?php

declare(strict_types=1);

namespace Inventario\Domain\Support;

/**
 * Ordenacoes que reproduzem a ordem de saida do VBA. A selecao direta
 * (selection sort) nao e estavel: empates saem na mesma ordem que a macro.
 */
final class Ordenacao
{
    /**
     * Selection sort decrescente por $chave, no intervalo [$lo, $hi].
     *
     * @template T
     * @param list<T> $itens
     * @param callable(T): float $chave
     * @return list<T>
     */
    public static function selecaoDesc(array $itens, callable $chave, int $lo = 0, ?int $hi = null): array
    {
        $hi ??= count($itens) - 1;
        for ($a = $lo; $a < $hi; $a++) {
            $m = $a;
            for ($b = $a + 1; $b <= $hi; $b++) {
                if ($chave($itens[$b]) > $chave($itens[$m])) {
                    $m = $b;
                }
            }
            if ($m !== $a) {
                [$itens[$a], $itens[$m]] = [$itens[$m], $itens[$a]];
            }
        }

        return $itens;
    }

    /**
     * Ordenacao estavel (bubble sort do VBA = estavel; usort do PHP 8 tambem).
     *
     * @template T
     * @param list<T> $itens
     * @param callable(T, T): int $cmp
     * @return list<T>
     */
    public static function estavel(array $itens, callable $cmp): array
    {
        usort($itens, $cmp);

        return $itens;
    }
}
