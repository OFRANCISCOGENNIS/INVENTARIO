<?php

declare(strict_types=1);

namespace Inventario\Domain\Historico;

use DateTimeImmutable;

/** Agregado de uma rodada: base da comparacao "vs. execucao anterior". */
final class KpiExecucao
{
    public function __construct(
        public readonly DateTimeImmutable $data,
        public readonly int $peps,
        public readonly int $reprovados,
        public readonly int $alertas,
        public readonly int $divergencias,
        public readonly float $valorEmRisco,
        public readonly float $notaBase,
    ) {
    }

    /** @return array<string, mixed> */
    public function paraArray(): array
    {
        return [
            'run' => $this->data->format(DATE_ATOM),
            'peps' => $this->peps,
            'reprovados' => $this->reprovados,
            'alertas' => $this->alertas,
            'divergencias' => $this->divergencias,
            'valor_em_risco' => $this->valorEmRisco,
            'nota_base' => $this->notaBase,
        ];
    }

    /** @param array<string, mixed> $a */
    public static function deArray(array $a): self
    {
        return new self(
            new DateTimeImmutable((string) ($a['run'] ?? 'now')),
            (int) ($a['peps'] ?? 0),
            (int) ($a['reprovados'] ?? 0),
            (int) ($a['alertas'] ?? 0),
            (int) ($a['divergencias'] ?? 0),
            (float) ($a['valor_em_risco'] ?? 0),
            (float) ($a['nota_base'] ?? 0),
        );
    }
}
