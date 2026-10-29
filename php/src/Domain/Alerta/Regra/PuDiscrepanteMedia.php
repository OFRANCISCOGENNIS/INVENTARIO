<?php

declare(strict_types=1);

namespace Inventario\Domain\Alerta\Regra;

use Inventario\Domain\Alerta\Alerta;
use Inventario\Domain\Alerta\RegraAlertaAgregada;
use Inventario\Domain\Preco\LinhaDiscrepanciaValor;

/**
 * PU DISCREPANTE DA MEDIA: linha ODI cujo PU foge mais de DISCREP_VALOR_PCT
 * (padrao 30%) da media do mesmo codigo na ODI (DiscrepanciaValor). So alerta:
 * nao reprova a obra e nao soma de novo no score (ja conta como discrepancia).
 */
final class PuDiscrepanteMedia implements RegraAlertaAgregada
{
    public const TIPO = 'PU DISCREPANTE DA MEDIA';

    /** @param list<LinhaDiscrepanciaValor> $discrepancias */
    public function __construct(private readonly array $discrepancias)
    {
    }

    public function gerar(array $linhas): array
    {
        $out = [];
        foreach ($this->discrepancias as $d) {
            $out[] = new Alerta(self::TIPO, $d->pep3, $d->pep4, $d->codigo, $d->descricao, 'ODI',
                $d->valor, $d->qtd, $d->precoUnitario, $d->media, $d->observacao());
        }

        return $out;
    }
}
