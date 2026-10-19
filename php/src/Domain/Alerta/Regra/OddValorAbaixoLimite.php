<?php

declare(strict_types=1);

namespace Inventario\Domain\Alerta\Regra;

use Inventario\Domain\Alerta\Alerta;
use Inventario\Domain\Alerta\LinhaAlerta;
use Inventario\Domain\Alerta\RegraAlertaLinha;
use Inventario\Domain\Regra\RegrasOD;
use Inventario\Domain\Support\Formatador;

/** ODD VALOR ABAIXO DO LIMITE (a mesma regra que torna o item NAO ADERENTE). */
final class OddValorAbaixoLimite implements RegraAlertaLinha
{
    public function __construct(private readonly float $limite)
    {
    }

    public function avaliar(LinhaAlerta $l): ?Alerta
    {
        $mot = RegrasOD::valorBaixoODD($l->tipoOD, $l->valor, $this->limite);
        if ($mot === '' || $l->codigo === '') {
            return null;
        }

        return Alerta::deLinha('ODD VALOR ABAIXO DO LIMITE', $l, $mot . ' - item NAO ADERENTE',
            ref: Formatador::decimal2($this->limite));
    }
}
