<?php

declare(strict_types=1);

namespace Inventario\Domain\Alerta\Regra;

use Inventario\Domain\Alerta\Alerta;
use Inventario\Domain\Alerta\LinhaAlerta;
use Inventario\Domain\Alerta\RegraAlertaLinha;
use Inventario\Domain\Base\TipoOD;

/**
 * MEDIDOR EM PEP ODI: medidor e material de ODM. Sem CAIXA ("CAIXA PARA
 * MEDIDOR") e sem LACRE ("LACRE P/ MEDIDOR"), que nao sao o medidor.
 */
final class MedidorEmOdi implements RegraAlertaLinha
{
    public static function aplica(TipoOD $od, string $descNorm, string $famN): bool
    {
        if ($od !== TipoOD::ODI) {
            return false;
        }
        $d = $descNorm . ' ' . $famN;

        return str_contains($d, 'MEDIDOR') && !str_contains($d, 'CAIXA') && !str_contains($d, 'LACRE');
    }

    public function avaliar(LinhaAlerta $l): ?Alerta
    {
        if ($l->codigo === '' || !self::aplica($l->tipoOD, $l->descricaoNorm, $l->familiaNorm)) {
            return null;
        }

        return Alerta::deLinha('MEDIDOR EM PEP ODI', $l, 'Medidor em PEP ODI (medidor e material de ODM)');
    }
}
