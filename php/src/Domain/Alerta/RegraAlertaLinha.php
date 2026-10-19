<?php

declare(strict_types=1);

namespace Inventario\Domain\Alerta;

/**
 * Regra avaliada em cada linha da base (Strategy). Devolve o alerta ou null.
 * Linhas sem PEP4 nao chegam as regras.
 */
interface RegraAlertaLinha
{
    public function avaliar(LinhaAlerta $linha): ?Alerta;
}
