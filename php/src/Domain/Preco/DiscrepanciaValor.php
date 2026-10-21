<?php

declare(strict_types=1);

namespace Inventario\Domain\Preco;

use Inventario\Domain\Base\BaseInventario;
use Inventario\Domain\Base\Coluna;
use Inventario\Domain\Base\TipoOD;

/**
 * DISCREPANCIA DE VALOR DE MATERIAL (ODI): o PU (VALOR / MAT LIB SAP) de cada
 * linha de PEP ODI e comparado com a MEDIA do mesmo codigo em todas as linhas
 * ODI da base. Fora de +/- DISCREP_VALOR_PCT (padrao 30%) = discrepancia.
 * Codigo com um unico lancamento ODI fica de fora (DiscrepValorODI do VBA).
 */
final class DiscrepanciaValor
{
    public function __construct(private readonly float $tolerancia)
    {
    }

    /** Desvio do PU em relacao a media, em fracao (+0,45 = 45% acima). */
    public static function desvio(float $pu, float $media): float
    {
        return $media == 0.0 ? 0.0 : ($pu - $media) / abs($media);
    }

    /** O limite exato (30,0%) nao e discrepancia; folga de 1e-9 contra arredondamento. */
    public static function discrepante(float $pu, float $media, float $tol): bool
    {
        return $media != 0.0 && abs(self::desvio($pu, $media)) > $tol + 1e-9;
    }

    /**
     * PU medio de cada codigo nas linhas ODI da base: o "valor do material"
     * (tambem a referencia da regra de valor excessivo em ODD).
     *
     * @return array<string, float> codigo => PU medio
     */
    public static function mediasOdi(BaseInventario $base): array
    {
        [, $soma, $n] = self::acumularOdi($base);
        $out = [];
        foreach ($soma as $cod => $s) {
            $out[(string) $cod] = $s / $n[$cod];
        }

        return $out;
    }

    /** @return list<LinhaDiscrepanciaValor> da maior para a menor diferenca (em modulo) */
    public function analisar(BaseInventario $base): array
    {
        // 1a passada: PU de cada linha ODI valida e soma/contagem por codigo
        [$odi, $soma, $n] = self::acumularOdi($base);

        // 2a passada: compara cada linha com a media do codigo
        $out = [];
        foreach ($odi as [$l, $cod, $pu, $q]) {
            $nC = $n[$cod];
            $media = $soma[$cod] / $nC;
            if ($nC < 2 || !self::discrepante($pu, $media, $this->tolerancia)) {
                continue;
            }
            $out[] = new LinhaDiscrepanciaValor(
                $l->texto(Coluna::PEP3), $l->texto(Coluna::PEP4), (string) $cod, $l->texto(Coluna::DESC_MAT),
                $q, $l->num(Coluna::VALOR), $pu, $media, self::desvio($pu, $media), $nC,
            );
        }
        usort($out, static fn (LinhaDiscrepanciaValor $a, LinhaDiscrepanciaValor $b): int => abs($b->desvio) <=> abs($a->desvio));

        return $out;
    }

    /**
     * Linhas ODI validas (PEP4, codigo e quantidade) com o PU, e soma/contagem
     * do PU por codigo.
     *
     * @return array{0: list<array{0: \Inventario\Domain\Base\LinhaBase, 1: string, 2: float, 3: float}>, 1: array<string, float>, 2: array<string, int>}
     */
    private static function acumularOdi(BaseInventario $base): array
    {
        if (!$base->temColuna(Coluna::PEP4) || !$base->temColuna(Coluna::COD_MAT)
            || !$base->temColuna(Coluna::VALOR) || !$base->temColuna(Coluna::MAT_LIB_SAP)) {
            return [[], [], []];
        }
        $odi = $soma = $n = [];
        foreach ($base as $l) {
            $p4 = $l->texto(Coluna::PEP4);
            $cod = $l->codigo();
            $q = $l->num(Coluna::MAT_LIB_SAP);
            if ($p4 === '' || $cod === '' || $q == 0.0 || $l->tipoOD() !== TipoOD::ODI) {
                continue;
            }
            $pu = $l->num(Coluna::VALOR) / $q;
            $odi[] = [$l, $cod, $pu, $q];
            $soma[$cod] = ($soma[$cod] ?? 0.0) + $pu;
            $n[$cod] = ($n[$cod] ?? 0) + 1;
        }

        return [$odi, $soma, $n];
    }
}
