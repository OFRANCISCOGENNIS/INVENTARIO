<?php

declare(strict_types=1);

namespace Inventario\Domain\Alerta\Regra;

use Inventario\Domain\Alerta\Alerta;
use Inventario\Domain\Alerta\LinhaAlerta;
use Inventario\Domain\Alerta\RegraAlertaLinha;
use Inventario\Domain\Base\TipoOD;
use Inventario\Domain\Support\Formatador;

/**
 * PU BAIXO EM ODI: preco unitario dentro de [min, max] (limites inclusive),
 * sem condutores e cabos (PU por metro).
 */
final class PuBaixoEmOdi implements RegraAlertaLinha
{
    public function __construct(private readonly float $min, private readonly float $max)
    {
    }

    public static function aplica(TipoOD $od, string $famN, float $valor, float $qtd, float $min, float $max): bool
    {
        if ($od !== TipoOD::ODI || $qtd == 0.0) {
            return false;
        }
        if (str_starts_with($famN, 'COND') || str_starts_with($famN, 'CABO')) {
            return false;
        }
        $pu = $valor / $qtd;

        // folga de 1e-6: 0,01 em ponto flutuante pode sair 0,00999999...
        return $pu >= $min - 0.000001 && $pu <= $max + 0.000001;
    }

    public function avaliar(LinhaAlerta $l): ?Alerta
    {
        if ($l->codigo === '' || !self::aplica($l->tipoOD, $l->familiaNorm, $l->valor, $l->qtd, $this->min, $this->max)) {
            return null;
        }
        $pu = $l->precoUnitario();
        $mn = Formatador::decimal2($this->min);
        $mx = Formatador::decimal2($this->max);

        return Alerta::deLinha('PU BAIXO EM ODI', $l,
            'Preco unitario R$ ' . Formatador::decimal2($pu) . " em PEP ODI (faixa de alerta R$ {$mn} a R$ {$mx})",
            $pu, "{$mn} a {$mx}");
    }
}
