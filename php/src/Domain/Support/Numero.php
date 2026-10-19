<?php

declare(strict_types=1);

namespace Inventario\Domain\Support;

/**
 * Conversao numerica tolerante (Val0 / IsNumeric do VBA).
 *
 * Celula vazia (null ou "") conta como numero 0, como o Empty do Excel.
 * Textos aceitam virgula decimal ("10,5") e milhar com ponto ("1.234,5").
 */
final class Numero
{
    public static function ehNumerico(mixed $valor): bool
    {
        return self::converter($valor) !== null;
    }

    /** Valor numerico, ou 0 quando nao numerico. */
    public static function val0(mixed $valor): float
    {
        return self::converter($valor) ?? 0.0;
    }

    public static function converter(mixed $valor): ?float
    {
        if ($valor === null) {
            return 0.0;
        }
        if (is_int($valor) || is_float($valor)) {
            return (float) $valor;
        }
        if (is_bool($valor)) {
            return $valor ? -1.0 : 0.0;
        }
        $s = trim((string) $valor);
        if ($s === '') {
            return 0.0;
        }
        $s = str_replace(["\u{00A0}", ' ', 'R$'], '', $s);
        if ($s === '') {
            return null;
        }
        $temVirgula = str_contains($s, ',');
        $temPonto = str_contains($s, '.');
        if ($temVirgula && $temPonto) {
            // o separador que vem por ultimo e o decimal
            if (strrpos($s, ',') > strrpos($s, '.')) {
                $s = str_replace(['.', ','], ['', '.'], $s);
            } else {
                $s = str_replace(',', '', $s);
            }
        } elseif ($temVirgula) {
            if (substr_count($s, ',') > 1) {
                return null;
            }
            $s = str_replace(',', '.', $s);
        } elseif ($temPonto && substr_count($s, '.') > 1) {
            // "1.234.567": pontos de milhar
            if (!preg_match('/^[+-]?\d{1,3}(\.\d{3})+$/', $s)) {
                return null;
            }
            $s = str_replace('.', '', $s);
        }

        return is_numeric($s) ? (float) $s : null;
    }

    /** Inteiro de VBA: Int() arredonda para baixo. */
    public static function int(float $v): float
    {
        return floor($v);
    }

    /** Teto: -Int(-x). */
    public static function teto(float $v): float
    {
        return ceil($v);
    }
}
