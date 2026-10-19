<?php

declare(strict_types=1);

namespace Inventario\Infrastructure\Preco;

use Inventario\Domain\Preco\FontePrecos;
use Inventario\Domain\Preco\TabelaPrecos;

/**
 * Chain of Responsibility: a primeira fonte que se aplica responde
 * (aba interna -> arquivo informado -> arquivo padrao da pasta de dados).
 * O resultado fica em cache para as etapas seguintes.
 */
final class CadeiaFontesPrecos implements FontePrecos
{
    private ?TabelaPrecos $cache = null;
    private bool $carregado = false;

    /** @param list<FontePrecos> $fontes */
    public function __construct(private readonly array $fontes)
    {
    }

    public function carregar(): ?TabelaPrecos
    {
        if ($this->carregado) {
            return $this->cache;
        }
        $this->carregado = true;
        foreach ($this->fontes as $f) {
            $t = $f->carregar();
            if ($t !== null) {
                return $this->cache = $t;
            }
        }

        return null;
    }
}
