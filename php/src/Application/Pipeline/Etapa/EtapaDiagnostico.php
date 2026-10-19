<?php

declare(strict_types=1);

namespace Inventario\Application\Pipeline\Etapa;

use Inventario\Application\Pipeline\ContextoAnalise;
use Inventario\Application\Pipeline\Etapa;
use Inventario\Domain\Diagnostico\DiagnosticoBase;

/** DIAGNOSTICO BASE; replica CRITICO/ATENCAO no fim do ALERTA CRITICO. */
final class EtapaDiagnostico implements Etapa
{
    public function nome(): string
    {
        return 'DIAGNOSTICO BASE';
    }

    public function executar(ContextoAnalise $ctx): void
    {
        $diag = (new DiagnosticoBase($ctx->parametros))->diagnosticar($ctx->base, $ctx->tabelaPrecos, $ctx->agora);
        $ctx->diagnostico = $diag;
        $ctx->alertas()->anexar($diag->alertasQualidade());
    }
}
