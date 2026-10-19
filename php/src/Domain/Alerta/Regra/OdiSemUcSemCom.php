<?php

declare(strict_types=1);

namespace Inventario\Domain\Alerta\Regra;

use Inventario\Domain\Alerta\Alerta;
use Inventario\Domain\Alerta\RegraAlertaAgregada;

/**
 * ODI SEM UC (nenhuma UC com MAT LIB SAP diferente de zero) e ODI SEM COM,
 * por PEP4. ODI aqui = TIPO PEP "I" ou PEP4 terminado em ".I".
 */
final class OdiSemUcSemCom implements RegraAlertaAgregada
{
    public function gerar(array $linhas): array
    {
        $p4 = [];
        foreach ($linhas as $l) {
            if ($l->pep4 === '') {
                continue;
            }
            $d = &$p4[$l->pep4];
            $d ??= ['pep3' => $l->pep3, 'odi' => false, 'uc' => false, 'ucNz' => false, 'com' => false];
            if ($l->tipoPepNorm === 'I' || str_ends_with($l->pep4, '.I')) {
                $d['odi'] = true;
            }
            if ($l->tipo === 'UC') {
                $d['uc'] = true;
                if ($l->qtd != 0.0) {
                    $d['ucNz'] = true;
                }
            }
            if ($l->tipo === 'COM') {
                $d['com'] = true;
            }
            unset($d);
        }

        $out = [];
        foreach ($p4 as $pep4 => $d) {
            if (!$d['odi']) {
                continue;
            }
            $pep4 = (string) $pep4;
            if (!$d['ucNz']) {
                $out[] = new Alerta('ODI SEM UC', $d['pep3'], $pep4, motivo: $d['uc']
                    ? 'ODI (PEP .I) com TODAS as UC zeradas (MAT LIB SAP = 0)'
                    : 'ODI (PEP .I) sem nenhum item TIPO=UC');
            }
            if (!$d['com']) {
                $out[] = new Alerta('ODI SEM COM', $d['pep3'], $pep4, motivo: 'ODI (PEP .I) sem nenhum material TIPO=COM');
            }
        }

        return $out;
    }
}
