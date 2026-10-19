<?php

declare(strict_types=1);

namespace Inventario\Presentation\Cli;

use Inventario\Domain\Support\Formatador;
use Inventario\Presentation\Aplicacao;

/** inventario historico - um agregado por execucao (HISTORICO KPI). */
final class ComandoHistorico implements Comando
{
    public function __construct(private readonly Aplicacao $app)
    {
    }

    public function nome(): string
    {
        return 'historico';
    }

    public function descricao(): string
    {
        return 'Mostra o historico de KPIs por execucao';
    }

    public function executar(Argumentos $args, Saida $out): int
    {
        $linhas = [];
        foreach ($this->app->historico->kpis() as $k) {
            $linhas[] = [$k->data->format('d/m/Y H:i'), $k->peps, $k->reprovados, $k->alertas, $k->divergencias,
                Formatador::kpi($k->valorEmRisco), Formatador::geral($k->notaBase)];
        }
        if ($linhas === []) {
            $out->linha('Nenhuma execucao registrada ainda.');

            return 0;
        }
        $out->tabela(['RUN', 'PEPS', 'REPROVADOS', 'ALERTAS', 'DIVERGENCIAS', 'VALOR EM RISCO', 'NOTA BASE'], $linhas);

        return 0;
    }
}
