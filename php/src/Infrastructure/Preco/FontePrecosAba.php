<?php

declare(strict_types=1);

namespace Inventario\Infrastructure\Preco;

use Inventario\Domain\Preco\FontePrecos;
use Inventario\Domain\Preco\TabelaPrecos;
use Inventario\Domain\Support\Texto;
use Inventario\Application\Carga\Tabela;

/**
 * Aba interna "BASE PRECOS" / "BASE DE PRECOS" do proprio arquivo da base.
 */
final class FontePrecosAba implements FontePrecos
{
    private const NOMES = ['BASE PRECOS', 'BASE DE PRECOS'];

    /** @param array<string, Tabela> $abas */
    public function __construct(private readonly array $abas)
    {
    }

    public function carregar(): ?TabelaPrecos
    {
        foreach ($this->abas as $nome => $t) {
            if (in_array(Texto::norm((string) $nome), self::NOMES, true)) {
                return InterpretadorFaixaPrecos::interpretar($t, "aba interna '{$nome}'");
            }
        }

        return null;
    }
}
