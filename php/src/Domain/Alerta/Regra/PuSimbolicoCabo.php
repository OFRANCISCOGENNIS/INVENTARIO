<?php

declare(strict_types=1);

namespace Inventario\Domain\Alerta\Regra;

use Inventario\Domain\Alerta\Alerta;
use Inventario\Domain\Alerta\LinhaAlerta;
use Inventario\Domain\Alerta\RegraAlertaLinha;
use Inventario\Domain\Support\Formatador;

/**
 * PU 0,01 EM CABO/COND: cabo ou condutor com preco unitario simbolico (o que
 * aparece como 0,01: a menos de meio centavo do alvo). Qualquer tipo de PEP.
 */
final class PuSimbolicoCabo implements RegraAlertaLinha
{
    public function __construct(private readonly float $alvo)
    {
    }

    public static function aplica(string $famN, float $valor, float $qtd, float $alvo): bool
    {
        if ($qtd == 0.0) {
            return false;
        }
        if (!str_starts_with($famN, 'COND') && !str_starts_with($famN, 'CABO')) {
            return false;
        }

        return abs($valor / $qtd - $alvo) < 0.005;
    }

    public function avaliar(LinhaAlerta $l): ?Alerta
    {
        if ($l->codigo === '' || !self::aplica($l->familiaNorm, $l->valor, $l->qtd, $this->alvo)) {
            return null;
        }
        $pu = $l->precoUnitario();
        $pep = $l->tipoOD->value !== '' ? $l->tipoOD->value : $l->pep4;

        return Alerta::deLinha('PU 0,01 EM CABO/COND', $l,
            'Cabo/condutor com preco unitario R$ ' . Formatador::decimal2($pu) . " (valor simbolico) em PEP {$pep}",
            $pu, Formatador::decimal2($this->alvo));
    }
}
