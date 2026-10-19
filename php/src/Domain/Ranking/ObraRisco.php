<?php

declare(strict_types=1);

namespace Inventario\Domain\Ranking;

final class ObraRisco
{
    public function __construct(
        public readonly int $posicao,
        public readonly string $pep3,
        public readonly float $valorObra,
        public readonly bool $reprovado,
        public readonly int $alertas,
        public readonly int $divergenciasPreco,
        public readonly float $sobrepreco,
        public readonly int $comFora,
        public readonly float $score,
        public readonly string $risco,
        public readonly string $diagnostico,
    ) {
    }

    /** ALTO >= 60, MEDIO >= 30, BAIXO > 0, OK = 0. */
    public static function nivel(float $score): string
    {
        return match (true) {
            $score >= 60 => 'ALTO',
            $score >= 30 => 'MEDIO',
            $score > 0 => 'BAIXO',
            default => 'OK',
        };
    }

    /** @return array<string, mixed> */
    public function paraArray(): array
    {
        return [
            '#' => $this->posicao,
            'PEP3NIVEL' => $this->pep3,
            'VALOR OBRA' => $this->valorObra,
            'SITUACAO' => $this->reprovado ? 'REPROVADO' : 'APROVADO',
            'ALERTAS' => $this->alertas,
            'DIVERG PRECO' => $this->divergenciasPreco,
            'SOBREPRECO R$' => $this->sobrepreco,
            'COM FORA NT.006' => $this->comFora,
            'SCORE' => $this->score,
            'RISCO' => $this->risco,
            'DIAGNOSTICO' => $this->diagnostico,
        ];
    }
}
