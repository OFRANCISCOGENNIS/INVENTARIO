<?php

declare(strict_types=1);

namespace Inventario\Domain\Alerta\Regra;

use Inventario\Domain\Alerta\Alerta;
use Inventario\Domain\Alerta\RegraAlertaAgregada;
use Inventario\Domain\Preco\LinhaPrecoUnitario;
use Inventario\Domain\Preco\StatusPreco;

/** PU ABAIXO MIN / PU ACIMA MAX importados da analise de PRECO UNITARIO. */
final class DivergenciaPrecoUnitario implements RegraAlertaAgregada
{
    /** @param list<LinhaPrecoUnitario> $precoUnitario */
    public function __construct(private readonly array $precoUnitario)
    {
    }

    public function gerar(array $linhas): array
    {
        $out = [];
        foreach ($this->precoUnitario as $p) {
            if (!$p->status->divergente()) {
                continue;
            }
            $abaixo = $p->status === StatusPreco::ABAIXO;
            $out[] = new Alerta(
                $abaixo ? 'PU ABAIXO MIN' : 'PU ACIMA MAX',
                $p->pep3,
                $p->pep4,
                $p->codigo,
                $p->descricao,
                $p->tipo,
                $p->valor,
                $p->qtd,
                $p->precoUnitario,
                $abaixo ? $p->faixa?->min : $p->faixa?->max,
                $p->observacao,
            );
        }

        return $out;
    }
}
