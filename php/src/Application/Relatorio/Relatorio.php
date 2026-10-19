<?php

declare(strict_types=1);

namespace Inventario\Application\Relatorio;

final class Relatorio
{
    /** @param list<Aba> $abas */
    public function __construct(
        public readonly string $base,
        public readonly string $geradoEm,
        public readonly string $selo,
        public readonly array $abas,
    ) {
    }

    public function aba(string $nome): ?Aba
    {
        foreach ($this->abas as $a) {
            if ($a->nome === $nome) {
                return $a;
            }
        }

        return null;
    }
}
