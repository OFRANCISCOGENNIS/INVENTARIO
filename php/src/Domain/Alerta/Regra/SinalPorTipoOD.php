<?php

declare(strict_types=1);

namespace Inventario\Domain\Alerta\Regra;

use Inventario\Domain\Alerta\Alerta;
use Inventario\Domain\Alerta\LinhaAlerta;
use Inventario\Domain\Alerta\RegraAlertaLinha;
use Inventario\Domain\Base\TipoOD;
use Inventario\Domain\Regra\RegrasOD;

/**
 * Premissa de sinal (a mesma da ANALISE SAP x PRJ): MATERIAL POSITIVO EM ODD
 * ou MATERIAL NEGATIVO em ODI/ODS. ODM negativo segue so como alerta.
 */
final class SinalPorTipoOD implements RegraAlertaLinha
{
    public function avaliar(LinhaAlerta $l): ?Alerta
    {
        if ($l->codigo === '') {
            return null;
        }
        $mot = RegrasOD::sinalInvalido($l->tipoOD, $l->qtd, $l->valor);
        if ($mot !== '') {
            $tipo = $l->tipoOD === TipoOD::ODD ? 'MATERIAL POSITIVO EM ODD' : 'MATERIAL NEGATIVO';

            return Alerta::deLinha($tipo, $l, $mot . ' - item NAO ADERENTE');
        }
        if ($l->tipoOD === TipoOD::ODM && $l->qtd < 0) {
            return Alerta::deLinha('MATERIAL NEGATIVO', $l, 'Material NEGATIVO em PEP ODM (esperado positivo)');
        }

        return null;
    }
}
