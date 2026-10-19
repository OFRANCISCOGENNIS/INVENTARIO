<?php

declare(strict_types=1);

namespace Inventario\Domain\Painel;

/**
 * KPIs do PAINEL DO GESTOR.
 */
final class PainelGestor
{
    /**
     * @param list<array{tipo: string, qtd: int, participacao: float}> $alertasPorTipo
     * @param array<string, ?Variacao> $variacoes chave do KPI => variacao
     */
    public function __construct(
        public readonly float $valorTotal,
        public readonly int $peps,
        public readonly int $aprovados,
        public readonly int $reprovados,
        public readonly int $alertas,
        public readonly int $divergenciasPreco,
        public readonly float $sobrepreco,
        public readonly float $valorEmRisco,
        public readonly array $alertasPorTipo,
        public readonly array $variacoes,
    ) {
    }

    public function pctAprovados(): float
    {
        return $this->peps > 0 ? $this->aprovados / $this->peps : 0.0;
    }

    public function variacao(string $kpi): ?Variacao
    {
        return $this->variacoes[$kpi] ?? null;
    }
}
