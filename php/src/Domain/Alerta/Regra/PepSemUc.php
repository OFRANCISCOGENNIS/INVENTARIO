<?php

declare(strict_types=1);

namespace Inventario\Domain\Alerta\Regra;

use Inventario\Domain\Alerta\Alerta;
use Inventario\Domain\Alerta\RegraAlertaAgregada;

/** PEP SEM UC: PEP3 sem nenhum item TIPO=UC. */
final class PepSemUc implements RegraAlertaAgregada
{
    public function gerar(array $linhas): array
    {
        $p3 = [];
        $temUc = [];
        foreach ($linhas as $l) {
            if ($l->pep3 === '') {
                continue;
            }
            if (!array_key_exists($l->pep3, $p3)) {
                $p3[$l->pep3] = $l->pep4;
            }
            if ($l->tipo === 'UC') {
                $temUc[$l->pep3] = true;
            }
        }
        $out = [];
        foreach ($p3 as $pep3 => $pep4) {
            if (!isset($temUc[$pep3])) {
                $out[] = new Alerta('PEP SEM UC', (string) $pep3, $pep4, motivo: 'PEP3 sem nenhum item TIPO=UC cadastrado');
            }
        }

        return $out;
    }
}
