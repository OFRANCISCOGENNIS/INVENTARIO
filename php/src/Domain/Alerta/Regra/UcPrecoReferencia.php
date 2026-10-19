<?php

declare(strict_types=1);

namespace Inventario\Domain\Alerta\Regra;

use Inventario\Domain\Alerta\Alerta;
use Inventario\Domain\Alerta\LinhaAlerta;
use Inventario\Domain\Alerta\RegraAlertaLinha;
use Inventario\Domain\Preco\PrecosReferencia;
use Inventario\Domain\Support\Formatador;
use Inventario\Domain\Support\Texto;

/**
 * UC (fora cabos/condutores) contra o preco de referencia:
 *  - UC SUBVALORIZADO: PU abaixo de TOL_SUBVAL x referencia, com diferenca
 *    total de pelo menos MIN_DIVERG_RS;
 *  - UC - PRECO NAO ENCONTRADO: material sem preco de referencia;
 *  - UC - COD MATERIAL VAZIO.
 */
final class UcPrecoReferencia implements RegraAlertaLinha
{
    public function __construct(
        private readonly PrecosReferencia $precos,
        private readonly float $tolerancia,
        private readonly float $minDivergencia,
    ) {
    }

    public function avaliar(LinhaAlerta $l): ?Alerta
    {
        if ($l->tipo !== 'UC' || $l->caboOuCondutor()) {
            return null;
        }
        if ($l->codigo === '') {
            return Alerta::deLinha('UC - COD MATERIAL VAZIO', $l, 'Codigo de material nao preenchido',
                semCodigo: true, comQtd: false);
        }
        $cod = Texto::codigo($l->codigo);
        $ref = $this->precos->preco($cod);
        if ($l->qtd <= 0 || $l->valor <= 0) {
            return null;
        }
        if ($ref === null) {
            return $cod === '' ? null
                : Alerta::deLinha('UC - PRECO NAO ENCONTRADO', $l, 'Material nao tem preco referencia cadastrado na aba PRECOS');
        }
        $pu = $l->precoUnitario();
        if ($ref > 0 && $pu < $ref * $this->tolerancia && ($ref - $pu) * $l->qtd >= $this->minDivergencia) {
            return Alerta::deLinha('UC SUBVALORIZADO', $l,
                'PU ' . Formatador::decimal2($pu) . ' abaixo de ' . Formatador::pct($this->tolerancia)
                . ' da referencia (' . Formatador::decimal2($ref) . ')',
                $pu, $ref);
        }

        return null;
    }
}
