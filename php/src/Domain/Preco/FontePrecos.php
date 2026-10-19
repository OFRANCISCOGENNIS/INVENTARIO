<?php

declare(strict_types=1);

namespace Inventario\Domain\Preco;

/**
 * Origem das faixas de preco (porta do dominio; as implementacoes ficam na
 * infraestrutura). Devolve null quando a fonte nao se aplica (ex.: arquivo
 * inexistente), para a proxima da cadeia ser tentada.
 */
interface FontePrecos
{
    public function carregar(): ?TabelaPrecos;
}
