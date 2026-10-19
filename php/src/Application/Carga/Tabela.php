<?php

declare(strict_types=1);

namespace Inventario\Application\Carga;

/**
 * Uma aba lida de um arquivo: cabecalho (linha 1) e as linhas de dados.
 */
final class Tabela
{
    /**
     * @param list<mixed> $cabecalho
     * @param list<list<mixed>> $linhas
     */
    public function __construct(
        public readonly string $aba,
        public readonly array $cabecalho,
        public readonly array $linhas,
    ) {
    }

    /** @param list<list<mixed>> $matriz linha 1 = cabecalho */
    public static function deMatriz(string $aba, array $matriz): self
    {
        $cab = array_shift($matriz) ?? [];
        // descarta linhas vazias no fim (o UsedRange do Excel costuma sobrar)
        while ($matriz !== [] && self::vazia(end($matriz))) {
            array_pop($matriz);
        }

        return new self($aba, array_values($cab), array_values($matriz));
    }

    /** @param list<mixed> $linha */
    public static function vazia(array $linha): bool
    {
        foreach ($linha as $v) {
            if ($v !== null && $v !== '') {
                return false;
            }
        }

        return true;
    }
}
