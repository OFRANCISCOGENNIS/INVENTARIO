<?php

declare(strict_types=1);

namespace Inventario\Domain\Regra;

use Inventario\Domain\Config\Parametros;
use Inventario\Domain\Support\Numero;
use Inventario\Domain\Support\Texto;

/**
 * Aderencia SAP x PRJ de uma linha (EhAderente e auxiliares do VBA).
 * As familias recebidas ja vem normalizadas (Texto::norm).
 */
final class RegraAderencia
{
    public function __construct(private readonly Parametros $p)
    {
    }

    /** Familias com margem: COND*, CABO* e RAMAL. */
    public static function familiaComMargem(string $fam): bool
    {
        return str_starts_with($fam, 'COND') || str_starts_with($fam, 'CABO') || $fam === 'RAMAL';
    }

    public function ehAderente(string $fam, mixed $lib, mixed $prj, string $sitNorm): bool
    {
        if (!self::familiaComMargem($fam) || !Numero::ehNumerico($lib) || !Numero::ehNumerico($prj)) {
            return $sitNorm === 'ADERENTE';
        }
        $l = Numero::val0($lib);
        $pp = Numero::val0($prj);
        // COND NU: diferenca ABSOLUTA ate DIF_ABS_COND_NU e aderente
        if (self::ehCondNu($fam) && abs($l - $pp) <= $this->p->difAbsCondNu()) {
            return true;
        }
        // DIF_ABS_FAMILIAS: diferenca ABSOLUTA ate DIF_ABS_CABO, mesmo com PRJ 0
        if ($this->familiaDifAbs($fam) && abs($l - $pp) <= $this->p->difAbsCabo()) {
            return true;
        }
        if ($pp == 0.0) {
            return $l == 0.0;
        }

        return abs($l - $pp) <= $this->p->toleranciaAderencia() * abs($pp);
    }

    /** COND NU tolerante a espaco/pontuacao ("COND-NU" -> "CONDNU"). */
    public static function ehCondNu(string $fam): bool
    {
        return Texto::semEspaco($fam) === 'CONDNU';
    }

    public function familiaDifAbs(string $fam): bool
    {
        return isset($this->p->familiasDifAbs()[Texto::semEspaco($fam)]);
    }

    /** COM que, se nao aderente, reprova o PEP3 (CH FUS, PARA RAIO...). */
    public function ehComCritico(string $famNorm): bool
    {
        $f = Texto::semEspaco($famNorm);
        foreach (array_keys($this->p->comCritico()) as $tk) {
            if (str_contains($f, (string) $tk)) {
                return true;
            }
        }

        return false;
    }

    /** CABO ISOLADO abaixo de CABO_ISOLADO_MAX_M e isento de UC. */
    public function caboIsento(string $fam, mixed $lib): bool
    {
        return $fam === 'CABO ISOLADO' && Numero::ehNumerico($lib)
            && abs(Numero::val0($lib)) < $this->p->caboIsoladoMaxM();
    }

    /** Material UC com MAT LIB SAP e MAT PRJ CAD zerados/vazios. */
    public static function materialUCZerado(string $tipo, mixed $lib, mixed $prj): bool
    {
        return $tipo === 'UC' && Numero::val0($lib) == 0.0 && Numero::val0($prj) == 0.0;
    }
}
