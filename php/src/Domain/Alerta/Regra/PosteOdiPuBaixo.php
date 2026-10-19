<?php

declare(strict_types=1);

namespace Inventario\Domain\Alerta\Regra;

use Inventario\Domain\Alerta\Alerta;
use Inventario\Domain\Alerta\LinhaAlerta;
use Inventario\Domain\Alerta\RegraAlertaLinha;
use Inventario\Domain\Base\TipoOD;
use Inventario\Domain\Support\Formatador;

/**
 * POSTE ODI PU BAIXO: poste em ODI com preco unitario de 0 ate abaixo do
 * limite. PU negativo fica de fora (ja e alerta de sinal).
 */
final class PosteOdiPuBaixo implements RegraAlertaLinha
{
    public function __construct(private readonly float $limite)
    {
    }

    public static function aplica(TipoOD $od, string $famN, float $valor, float $qtd, float $limite): bool
    {
        if ($od !== TipoOD::ODI || $qtd == 0.0 || !str_contains($famN, 'POSTE')) {
            return false;
        }
        $pu = $valor / $qtd;

        return $pu >= 0 && $pu < $limite;
    }

    public function avaliar(LinhaAlerta $l): ?Alerta
    {
        if ($l->codigo === '' || !self::aplica($l->tipoOD, $l->familiaNorm, $l->valor, $l->qtd, $this->limite)) {
            return null;
        }
        $pu = $l->precoUnitario();
        $lim = Formatador::decimal2($this->limite);

        return Alerta::deLinha('POSTE ODI PU BAIXO', $l,
            'Poste em PEP ODI com preco unitario R$ ' . Formatador::decimal2($pu) . " abaixo de R$ {$lim}",
            $pu, $lim);
    }
}
