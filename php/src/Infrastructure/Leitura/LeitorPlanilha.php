<?php

declare(strict_types=1);

namespace Inventario\Infrastructure\Leitura;

use Inventario\Application\Carga\FalhaLeitura;
use Inventario\Application\Carga\Tabela;

/**
 * Le as abas de um arquivo de planilha.
 */
interface LeitorPlanilha
{
    public function suporta(string $extensao): bool;

    /**
     * @return array<string, Tabela> nome da aba => tabela
     * @throws FalhaLeitura
     */
    public function ler(string $caminho): array;
}
