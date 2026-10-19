<?php

declare(strict_types=1);

namespace Inventario\Domain\Historico;

/**
 * Persistencia do historico entre execucoes (abas HISTORICO e HISTORICO KPI).
 */
interface RepositorioHistorico
{
    public function ultimoKpi(): ?KpiExecucao;

    public function registrarKpi(KpiExecucao $kpi): void;

    /** @return array<string, string> PEP3 => ultima situacao registrada */
    public function situacoesAnteriores(): array;

    /** @param list<RegistroObra> $registros */
    public function registrarObras(array $registros): void;

    /** @return list<RegistroObra> */
    public function obras(): array;
}
