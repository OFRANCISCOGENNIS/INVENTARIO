<?php

declare(strict_types=1);

namespace Inventario\Domain\Preco;

use Inventario\Domain\Base\BaseInventario;
use Inventario\Domain\Base\Coluna;
use Inventario\Domain\Support\Formatador;

/**
 * PRECO UNITARIO: PU = VALOR / QTD comparado com a faixa MIN/MAX
 * (ProcessarPrecoUnitario do VBA). Linhas sem codigo, quantidade ou valor
 * ficam de fora.
 */
final class AnalisadorPrecoUnitario
{
    /** @return list<LinhaPrecoUnitario> */
    public function analisar(BaseInventario $base, TabelaPrecos $precos): array
    {
        $out = [];
        foreach ($base as $l) {
            $cod = $l->codigo();
            $qtd = $l->num(Coluna::MAT_LIB_SAP);
            $valor = $l->num(Coluna::VALOR);
            if ($cod === '' || $qtd == 0.0 || $valor == 0.0) {
                continue;
            }
            $pu = $valor / $qtd;
            $fx = $precos->faixa($cod);
            if ($fx === null) {
                $st = StatusPreco::SEM_REFERENCIA;
                $obs = 'Material sem faixa na BASE PRECOS';
            } elseif ($pu < $fx->min) {
                $st = StatusPreco::ABAIXO;
                $obs = 'PU ' . Formatador::decimal2($pu) . ' < min ' . Formatador::decimal2($fx->min);
            } elseif ($pu > $fx->max) {
                $st = StatusPreco::ACIMA;
                $obs = 'PU ' . Formatador::decimal2($pu) . ' > max ' . Formatador::decimal2($fx->max);
            } else {
                $st = StatusPreco::DENTRO;
                $obs = 'Dentro da faixa (' . Formatador::decimal2($fx->min) . ' a ' . Formatador::decimal2($fx->max) . ')';
            }
            $out[] = new LinhaPrecoUnitario(
                $l->texto(Coluna::PEP3),
                $l->texto(Coluna::PEP4),
                $l->texto(Coluna::TIPO),
                $cod,
                $l->texto(Coluna::DESC_MAT),
                $l->texto(Coluna::UND),
                $qtd,
                $valor,
                $pu,
                $fx,
                $st,
                $obs,
                $l->tipoOD(),
            );
        }

        return $out;
    }
}
