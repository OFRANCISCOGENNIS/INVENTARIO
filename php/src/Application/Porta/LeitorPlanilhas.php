<?php

declare(strict_types=1);

namespace Inventario\Application\Porta;

use Inventario\Application\Carga\FalhaLeitura;
use Inventario\Application\Carga\Tabela;

/**
 * Le as abas de um arquivo de planilha (xlsx, csv...).
 */
interface LeitorPlanilhas
{
    /**
     * @param string|null $nomeOriginal nome do arquivo enviado (define o formato quando o caminho e temporario)
     * @return array<string, Tabela> nome da aba => tabela
     * @throws FalhaLeitura
     */
    public function ler(string $caminho, ?string $nomeOriginal = null): array;
}
