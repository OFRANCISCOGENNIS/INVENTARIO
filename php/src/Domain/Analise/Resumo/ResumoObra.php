<?php

declare(strict_types=1);

namespace Inventario\Domain\Analise\Resumo;

/**
 * Uma linha da aba RESUMO SAP x PRJ (uma por obra/PEP3).
 */
final class ResumoObra
{
    public function __construct(
        public readonly string $pep3,
        public readonly bool $reprovado,
        public readonly int $totalPep4,
        public readonly float $valorObra,
        public readonly float $valorNaoAderente,
        public readonly string $familiasReprovadas,
        public readonly string $motivoDevolucao,
    ) {
    }

    public function situacao(): string
    {
        return $this->reprovado ? 'REPROVADO' : 'APROVADO';
    }

    /** @return array<string, mixed> */
    public function paraArray(): array
    {
        return [
            'PEP3NIVEL' => $this->pep3,
            'SITUACAO' => $this->situacao(),
            'ODs (PEP4)' => $this->totalPep4,
            'VALOR OBRA' => $this->valorObra,
            'VALOR NAO ADER' => $this->valorNaoAderente,
            'FAMILIAS REPROVADAS' => $this->familiasReprovadas,
            'MOTIVO DEVOLUCAO' => $this->motivoDevolucao,
        ];
    }
}
