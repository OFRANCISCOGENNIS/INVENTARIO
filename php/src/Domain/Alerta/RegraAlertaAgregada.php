<?php

declare(strict_types=1);

namespace Inventario\Domain\Alerta;

/**
 * Regra que olha a base inteira (agregados por PEP3/PEP4) e emite N alertas.
 */
interface RegraAlertaAgregada
{
    /**
     * @param list<LinhaAlerta> $linhas
     * @return list<Alerta>
     */
    public function gerar(array $linhas): array;
}
