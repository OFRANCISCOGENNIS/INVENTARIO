<?php

declare(strict_types=1);

namespace Inventario\Domain\Painel;

use Inventario\Domain\Support\Formatador;

/**
 * Rodape de variacao de um card: "▲ 3 vs. anterior" (TxtDelta do VBA).
 */
final class Variacao
{
    public function __construct(
        public readonly string $texto,
        /** 'bom' | 'ruim' | 'neutro' */
        public readonly string $tendencia,
    ) {
    }

    /** $bomSeCai: true quando DIMINUIR e o desejado (alertas, reprovados...). */
    public static function calcular(float $atual, ?float $anterior, bool $bomSeCai, bool $moeda = false): ?self
    {
        if ($anterior === null) {
            return null;
        }
        $dif = $atual - $anterior;
        if (abs($dif) < 0.000001) {
            return new self("\u{2022} sem variacao", 'neutro');
        }
        $bom = $dif < 0 ? $bomSeCai : !$bomSeCai;
        $v = $moeda ? Formatador::kpi(abs($dif)) : Formatador::inteiro(abs($dif));

        return new self(($dif > 0 ? "\u{25B2}" : "\u{25BC}") . " {$v} vs. anterior", $bom ? 'bom' : 'ruim');
    }
}
