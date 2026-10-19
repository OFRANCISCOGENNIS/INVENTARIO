<?php

declare(strict_types=1);

namespace Inventario\Domain\Support;

/**
 * Formatos numericos pt-BR usados nos textos de MOTIVO/OBSERVACAO.
 * Equivalem aos Format() do VBA rodando no Excel PT-BR, sem a virgula
 * sobrando que "#,##0.##" deixa em numero inteiro ("1.234,").
 */
final class Formatador
{
    /** "#,##0.##": milhar com ponto, ate $maxDec casas, sem zeros a direita. */
    public static function numero(float $v, int $maxDec = 2): string
    {
        $s = number_format($v, $maxDec, ',', '.');
        if ($maxDec > 0) {
            $s = rtrim(rtrim($s, '0'), ',');
        }

        return $s === '-0' ? '0' : $s;
    }

    /** "#,##0.00" */
    public static function decimal2(float $v): string
    {
        $s = number_format($v, 2, ',', '.');

        return $s === '-0,00' ? '0,00' : $s;
    }

    /** "#,##0" */
    public static function inteiro(float $v): string
    {
        $s = number_format($v, 0, ',', '.');

        return $s === '-0' ? '0' : $s;
    }

    /** "0%" / "0.0%" */
    public static function pct(float $fracao, int $dec = 0): string
    {
        return number_format($fracao * 100, $dec, ',', '.') . '%';
    }

    /** CStr de um Double: sem milhar, virgula decimal, sem zeros a direita. */
    public static function geral(float $v): string
    {
        if (floor($v) === $v && abs($v) < 1e15) {
            return sprintf('%.0f', $v);
        }
        $s = rtrim(rtrim(sprintf('%.10F', $v), '0'), '.');

        return str_replace('.', ',', $s);
    }

    /** Texto de valor bruto da celula (numero formatado como CStr, texto como veio). */
    public static function bruto(mixed $v): string
    {
        if (is_int($v) || is_float($v)) {
            return self::geral((float) $v);
        }

        return Texto::str($v);
    }

    /** FmtKPI: "R$ 1,2 MM" / "R$ 350 mil" / "R$ 950". */
    public static function kpi(float $v): string
    {
        $a = abs($v);
        if ($a >= 1000000) {
            return 'R$ ' . number_format($v * 0.000001, 1, ',', '.') . ' MM';
        }
        if ($a >= 1000) {
            return 'R$ ' . self::inteiro($v * 0.001) . ' mil';
        }

        return 'R$ ' . self::inteiro($v);
    }

    /**
     * FmtDif: " | Dif=+3 (42,9% acima)". Vazio quando um dos lados nao e numerico.
     */
    public static function diferenca(mixed $lib, mixed $prj): string
    {
        if (!Numero::ehNumerico($lib) || !Numero::ehNumerico($prj)) {
            return '';
        }
        $l = Numero::val0($lib);
        $p = Numero::val0($prj);
        $d = $l - $p;
        $s = ' | Dif=' . ($d >= 0 ? '+' : '') . self::numero($d);
        if ($p != 0.0) {
            $s .= ' (' . self::pct(abs($d / $p), 1) . ($d >= 0 ? ' acima' : ' abaixo') . ')';
        }

        return $s;
    }
}
