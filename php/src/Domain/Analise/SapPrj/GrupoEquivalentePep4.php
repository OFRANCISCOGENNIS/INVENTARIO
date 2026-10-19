<?php

declare(strict_types=1);

namespace Inventario\Domain\Analise\SapPrj;

/**
 * Totais de um grupo de equivalentes dentro de um PEP4.
 */
final class GrupoEquivalentePep4
{
    public float $lib = 0.0;
    public float $prj = 0.0;
    /** @var array<string, string> codigo => descricao */
    public array $codigos = [];

    /** Ativo quando 2+ codigos diferentes do grupo aparecem no PEP4. */
    public function ativo(): bool
    {
        return count($this->codigos) >= 2;
    }
}
