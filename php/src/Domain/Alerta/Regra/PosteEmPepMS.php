<?php

declare(strict_types=1);

namespace Inventario\Domain\Alerta\Regra;

use Inventario\Domain\Alerta\Alerta;
use Inventario\Domain\Alerta\LinhaAlerta;
use Inventario\Domain\Alerta\RegraAlertaLinha;

/** POSTE EM PEP .M / .S: poste lancado em PEP4 de ODM ou ODS. */
final class PosteEmPepMS implements RegraAlertaLinha
{
    /** @param 'M'|'S' $sufixo */
    public function __construct(private readonly string $sufixo)
    {
    }

    public function avaliar(LinhaAlerta $l): ?Alerta
    {
        $s = '.' . $this->sufixo;
        if ((str_ends_with($l->pep4, $s) || str_contains($l->pep4, $s . '.')) && str_contains($l->familiaNorm, 'POSTE')) {
            $od = $this->sufixo === 'M' ? 'ODM' : 'ODS';

            return Alerta::deLinha("POSTE EM PEP {$s}", $l, "Poste em PEP4 com sufixo {$s} ({$od})");
        }

        return null;
    }
}
