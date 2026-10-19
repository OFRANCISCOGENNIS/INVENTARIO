<?php

declare(strict_types=1);

namespace Inventario\Application\Pipeline;

use Inventario\Application\Pipeline\Etapa\EtapaAlertas;
use Inventario\Application\Pipeline\Etapa\EtapaAnaliseSapPrj;
use Inventario\Application\Pipeline\Etapa\EtapaDiagnostico;
use Inventario\Application\Pipeline\Etapa\EtapaHistorico;
use Inventario\Application\Pipeline\Etapa\EtapaPainel;
use Inventario\Application\Pipeline\Etapa\EtapaPrecoUnitario;
use Inventario\Application\Pipeline\Etapa\EtapaRacionalizacao;
use Inventario\Application\Pipeline\Etapa\EtapaRanking;
use Inventario\Application\Pipeline\Etapa\EtapaResumo;
use Throwable;

/**
 * Executa as etapas em ordem, medindo o tempo de cada uma (FimEtapa do VBA).
 */
final class Pipeline
{
    /** @param list<Etapa> $etapas */
    public function __construct(private readonly array $etapas)
    {
    }

    /** A sequencia do GerarInventario. */
    public static function padrao(): self
    {
        return new self([
            new EtapaAnaliseSapPrj(),
            new EtapaResumo(),
            new EtapaRacionalizacao(),
            new EtapaPrecoUnitario(),
            new EtapaAlertas(),
            new EtapaDiagnostico(),
            new EtapaRanking(),
            new EtapaPainel(),
            new EtapaHistorico(),
        ]);
    }

    public function executar(ContextoAnalise $ctx): ContextoAnalise
    {
        $ctx->saude->log('Inicio da execucao: ' . $ctx->base->nome);
        foreach ($this->etapas as $e) {
            $t0 = microtime(true);
            try {
                $e->executar($ctx);
                $ctx->saude->registrar(new RegistroEtapa($e->nome(), true, microtime(true) - $t0));
            } catch (Throwable $ex) {
                $ctx->saude->registrar(new RegistroEtapa($e->nome(), false, microtime(true) - $t0, $ex->getMessage()));
            }
        }
        $ctx->saude->log('Fim da geracao');

        return $ctx;
    }
}
