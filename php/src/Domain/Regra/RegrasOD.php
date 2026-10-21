<?php

declare(strict_types=1);

namespace Inventario\Domain\Regra;

use Inventario\Domain\Base\TipoOD;
use Inventario\Domain\Support\Formatador;
use Inventario\Domain\Support\Numero;

/**
 * Premissas de sinal e valor por tipo de OD.
 *
 *   ODD (desmonte)         -> valor e quantidade NEGATIVOS
 *   ODI / ODS (instalacao) -> valor e quantidade POSITIVOS
 *
 * Zero nao viola (nao e positivo nem negativo). ODM fica fora da premissa.
 * Cada regra devolve a descricao da violacao, ou "" quando ok.
 */
final class RegrasOD
{
    public static function sinalInvalido(TipoOD $tipo, mixed $qtd, mixed $valor): string
    {
        $q = Numero::val0($qtd);
        $v = Numero::val0($valor);
        $errado = '';
        $esperado = '';
        if ($tipo === TipoOD::ODD) {
            $esperado = 'negativo';
            if ($q > 0 && $v > 0) {
                $errado = 'valor e quantidade POSITIVOS';
            } elseif ($q > 0) {
                $errado = 'quantidade POSITIVA';
            } elseif ($v > 0) {
                $errado = 'valor POSITIVO';
            }
        } elseif ($tipo === TipoOD::ODI || $tipo === TipoOD::ODS) {
            $esperado = 'positivo';
            if ($q < 0 && $v < 0) {
                $errado = 'valor e quantidade NEGATIVOS';
            } elseif ($q < 0) {
                $errado = 'quantidade NEGATIVA';
            } elseif ($v < 0) {
                $errado = 'valor NEGATIVO';
            }
        }

        return $errado === '' ? '' : "PEP {$tipo->value} com {$errado} (esperado {$esperado})";
    }

    /**
     * ODD com valor excessivo: o PU do desmonte (|VALOR| / |QTD|) pode ser no
     * maximo $pctMax do valor do material, que e o PU medio do codigo nas
     * linhas ODI da base. Sem media ou com QTD zero, nao compara.
     */
    public static function valorExcessivoODD(TipoOD $tipo, mixed $valor, mixed $qtd, ?float $media, float $pctMax): string
    {
        if ($tipo !== TipoOD::ODD || $media === null || $media <= 0) {
            return '';
        }
        $q = abs(Numero::val0($qtd));
        if ($q == 0.0) {
            return '';
        }
        $pu = abs(Numero::val0($valor)) / $q;
        if ($pu > $pctMax * $media + 1e-9) {
            return 'PEP ODD com valor de R$ ' . Formatador::decimal2($pu) . ' por unidade, acima de '
                . Formatador::pct($pctMax) . ' do valor do material (media ODI R$ ' . Formatador::decimal2($media)
                . '; maximo R$ ' . Formatador::decimal2($pctMax * $media) . ')';
        }

        return '';
    }

    /** SAP e PRJ com sinais opostos na mesma linha (incoerente em qualquer OD). */
    public static function sinaisOpostos(mixed $lib, mixed $prj): string
    {
        $l = Numero::val0($lib);
        $p = Numero::val0($prj);
        if (($l > 0 && $p < 0) || ($l < 0 && $p > 0)) {
            return 'MAT LIB SAP (' . Formatador::geral($l) . ') e MAT PRJ CAD ('
                . Formatador::geral($p) . ') com sinais opostos';
        }

        return '';
    }

    /** Linha de PEP ODI com VALOR negativo (reprova o PEP3). Zero nao conta. */
    public static function negativoEmODI(TipoOD $tipo, mixed $valor): bool
    {
        return $tipo === TipoOD::ODI && Numero::val0($valor) < 0;
    }
}
