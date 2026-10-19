<?php

declare(strict_types=1);

namespace Inventario\Domain\Alerta\Regra;

use Inventario\Domain\Alerta\Alerta;
use Inventario\Domain\Alerta\RegraAlertaAgregada;
use Inventario\Domain\Base\TipoOD;
use Inventario\Domain\Support\Formatador;

/**
 * LACRE x MEDIDOR (ODM): 1 medidor = 2 lacres por PEP4; lacre sem medidor
 * tambem alerta.
 */
final class LacreMedidor implements RegraAlertaAgregada
{
    public function gerar(array $linhas): array
    {
        $med = [];
        $lac = [];
        $pep3 = [];
        foreach ($linhas as $l) {
            if ($l->pep4 === '' || $l->tipoOD !== TipoOD::ODM) {
                continue;
            }
            $d = $l->descricaoNorm . ' ' . $l->familiaNorm;
            $pep3[$l->pep4] ??= $l->pep3;
            // sem LACRE: "LACRE P/ MEDIDOR" nao e medidor (no VBA era contado como medidor)
            if (str_contains($d, 'MEDIDOR') && !str_contains($d, 'CAIXA') && !str_contains($d, 'LACRE')) {
                $med[$l->pep4] = ($med[$l->pep4] ?? 0.0) + $l->qtd;
            } elseif (str_contains($d, 'LACRE')) {
                $lac[$l->pep4] = ($lac[$l->pep4] ?? 0.0) + $l->qtd;
            }
        }

        $out = [];
        foreach ($med as $p4 => $qMed) {
            $p4 = (string) $p4;
            $qLac = $lac[$p4] ?? 0.0;
            $esp = 2 * $qMed;
            if ($qMed > 0 && $qLac != $esp) {
                $falta = $qLac < $esp
                    ? ' (FALTAM ' . Formatador::inteiro($esp - $qLac) . ')'
                    : ' (SOBRAM ' . Formatador::inteiro($qLac - $esp) . ')';
                $out[] = new Alerta('LACRE x MEDIDOR', $pep3[$p4], $p4, familia: 'MEDIDOR / LACRE', qtd: $qLac,
                    referencia: $esp,
                    motivo: Formatador::inteiro($qMed) . ' medidor(es) -> esperado ' . Formatador::inteiro($esp)
                        . ' lacres, veio ' . Formatador::inteiro($qLac) . $falta);
            }
        }
        foreach ($lac as $p4 => $qLac) {
            $p4 = (string) $p4;
            if (!array_key_exists($p4, $med) && $qLac != 0.0) {
                $out[] = new Alerta('LACRE x MEDIDOR', $pep3[$p4], $p4, familia: 'MEDIDOR / LACRE', qtd: $qLac,
                    referencia: 0.0,
                    motivo: 'Lacre sem medidor no PEP ODM (veio ' . Formatador::inteiro($qLac) . ')');
            }
        }

        return $out;
    }
}
