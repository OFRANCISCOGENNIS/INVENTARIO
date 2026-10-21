<?php

declare(strict_types=1);

namespace Inventario\Domain\Alerta\Regra;

use Inventario\Domain\Alerta\Alerta;
use Inventario\Domain\Alerta\LinhaAlerta;
use Inventario\Domain\Alerta\RegraAlertaLinha;
use Inventario\Domain\Regra\RegrasOD;
use Inventario\Domain\Support\Formatador;
use Inventario\Domain\Support\Texto;

/**
 * ODD VALOR EXCESSIVO: PU do desmonte acima de ODD_VALOR_MAX_PCT do PU medio
 * do codigo na ODI (a mesma regra que torna o item NAO ADERENTE e reprova a obra).
 */
final class OddValorExcessivo implements RegraAlertaLinha
{
    /** @param array<string, float> $mediasOdi codigo => PU medio na ODI */
    public function __construct(private readonly array $mediasOdi, private readonly float $pctMax)
    {
    }

    public function avaliar(LinhaAlerta $l): ?Alerta
    {
        if ($l->codigo === '') {
            return null;
        }
        $media = $this->mediasOdi[Texto::codigo($l->codigo)] ?? null;
        $mot = RegrasOD::valorExcessivoODD($l->tipoOD, $l->valor, $l->qtd, $media, $this->pctMax);
        if ($mot === '') {
            return null;
        }

        return Alerta::deLinha('ODD VALOR EXCESSIVO', $l, $mot . ' - item NAO ADERENTE, obra reprovada',
            ref: Formatador::decimal2($this->pctMax * (float) $media));
    }
}
