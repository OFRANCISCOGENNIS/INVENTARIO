<?php

declare(strict_types=1);

namespace Inventario\Application\Carga;

/**
 * Base lida de um arquivo: a aba da base e as abas de apoio (precos).
 */
final class BlocoBase
{
    /**
     * @param array<string, Tabela> $abas todas as abas do arquivo
     */
    public function __construct(
        public readonly string $arquivo,
        public readonly string $caminho,
        public readonly Tabela $tabela,
        public readonly array $abas = [],
    ) {
    }
}
