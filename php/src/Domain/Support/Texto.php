<?php

declare(strict_types=1);

namespace Inventario\Domain\Support;

/**
 * Normalizacao de textos e codigos (NormStr / NormCod / TemPalavra do VBA).
 */
final class Texto
{
    private const ACENTOS = [
        'ç' => 'C', 'Ç' => 'C',
        'ã' => 'A', 'Ã' => 'A', 'á' => 'A', 'Á' => 'A',
        'õ' => 'O', 'Õ' => 'O', 'ó' => 'O', 'Ó' => 'O',
        'é' => 'E', 'É' => 'E',
        'í' => 'I', 'Í' => 'I',
        'ú' => 'U', 'Ú' => 'U',
    ];

    /**
     * Maiusculas, sem . / - _ e sem os acentos usados nos campos da base.
     * Espacos duplos viram um so (uma passada, como o Replace do VBA).
     */
    public static function norm(mixed $valor): string
    {
        $s = mb_strtoupper(trim(self::str($valor)), 'UTF-8');
        $s = str_replace(['.', '/', '-', '_'], '', $s);
        $s = strtr($s, self::ACENTOS);
        $s = str_replace('  ', ' ', $s);

        return trim($s);
    }

    /**
     * Codigo de material como texto: sem o ".0" de numero lido como double e
     * sem notacao cientifica.
     */
    public static function codigo(mixed $valor): string
    {
        if ($valor === null) {
            return '';
        }
        if (is_float($valor) && is_finite($valor) && floor($valor) === $valor) {
            return sprintf('%.0f', $valor);
        }
        $s = trim(self::str($valor));
        if (str_ends_with($s, '.0')) {
            $s = substr($s, 0, -2);
        }
        if (stripos($s, 'e+') !== false && is_numeric($s)) {
            $s = sprintf('%.0f', (float) $s);
        }

        return $s;
    }

    /** Texto de uma celula (CStr), com numeros inteiros sem casas decimais. */
    public static function str(mixed $valor): string
    {
        if ($valor === null) {
            return '';
        }
        if (is_bool($valor)) {
            return $valor ? 'VERDADEIRO' : 'FALSO';
        }
        if (is_float($valor)) {
            return Formatador::geral($valor);
        }

        return (string) $valor;
    }

    /** trim(CStr(v)) */
    public static function limpo(mixed $valor): string
    {
        return trim(self::str($valor));
    }

    /**
     * Casa PALAVRA inteira (evita "ELO" em PARALELO). Tudo que nao for letra
     * ou digito vira separador. $descNorm ja vem normalizado.
     */
    public static function temPalavra(string $descNorm, string $termo): bool
    {
        $s = ' ' . preg_replace('/[^A-Z0-9]/', ' ', $descNorm) . ' ';

        return str_contains($s, ' ' . $termo . ' ');
    }

    /** Remove todos os espacos (tokens de familia: "CH FUS" -> "CHFUS"). */
    public static function semEspaco(string $s): string
    {
        return str_replace(' ', '', $s);
    }

    /**
     * Lista "A; B; C" da CONFIG em tokens normalizados sem espaco.
     *
     * @return array<string, true>
     */
    public static function tokens(string $lista): array
    {
        $out = [];
        foreach (explode(';', $lista) as $parte) {
            $tk = self::semEspaco(self::norm($parte));
            if ($tk !== '') {
                $out[$tk] = true;
            }
        }

        return $out;
    }
}
