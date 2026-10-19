<?php

declare(strict_types=1);

namespace Inventario\Application\Porta;

use Inventario\Application\Carga\Tabela;
use Inventario\Domain\Preco\FontePrecos;

/**
 * Monta a fonte de precos de uma analise a partir das abas do arquivo da
 * base e de um arquivo de precos informado.
 */
interface FabricaFontePrecos
{
    /** @param array<string, Tabela> $abas */
    public function fonte(array $abas, ?string $arquivoPrecos): FontePrecos;

    /**
     * Precos de referencia (aba PRECOS: codigo, preco); vazio = ponto medio da faixa.
     *
     * @param array<string, Tabela> $abas
     * @return array<string, float>
     */
    public function precosReferencia(array $abas): array;
}
