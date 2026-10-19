<?php

declare(strict_types=1);

namespace Inventario\Infrastructure\Persistencia;

use Inventario\Domain\Historico\KpiExecucao;
use Inventario\Domain\Historico\RegistroObra;
use Inventario\Domain\Historico\RepositorioHistorico;

/**
 * historico.json: abas HISTORICO (uma linha por obra por execucao) e
 * HISTORICO KPI (um agregado por execucao). Apagar o arquivo nao quebra
 * nada: os cards so ficam sem variacao ate haver duas rodadas.
 */
final class RepositorioHistoricoJson implements RepositorioHistorico
{
    private readonly ArquivoJson $arquivo;

    public function __construct(string $caminho)
    {
        $this->arquivo = new ArquivoJson($caminho);
    }

    public function ultimoKpi(): ?KpiExecucao
    {
        $k = $this->arquivo->ler()['kpi'] ?? [];

        return $k === [] ? null : KpiExecucao::deArray(end($k));
    }

    public function registrarKpi(KpiExecucao $kpi): void
    {
        $d = $this->arquivo->ler();
        $d['kpi'][] = $kpi->paraArray();
        $this->arquivo->gravar($d);
    }

    public function situacoesAnteriores(): array
    {
        $out = [];
        foreach ($this->arquivo->ler()['obras'] ?? [] as $r) {
            $p3 = trim((string) ($r['PEP3NIVEL'] ?? ''));
            if ($p3 !== '') {
                $out[$p3] = strtoupper(trim((string) ($r['SITUACAO'] ?? '')));
            }
        }

        return $out;
    }

    public function registrarObras(array $registros): void
    {
        $d = $this->arquivo->ler();
        foreach ($registros as $r) {
            $d['obras'][] = $r->paraArray();
        }
        $this->arquivo->gravar($d);
    }

    public function obras(): array
    {
        return array_map(RegistroObra::deArray(...), $this->arquivo->ler()['obras'] ?? []);
    }

    /** @return list<KpiExecucao> */
    public function kpis(): array
    {
        return array_map(KpiExecucao::deArray(...), $this->arquivo->ler()['kpi'] ?? []);
    }
}
