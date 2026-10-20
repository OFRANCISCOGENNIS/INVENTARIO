<?php

declare(strict_types=1);

namespace Inventario\Domain\Preco;

use Inventario\Domain\Support\Formatador;

/** Linha ODI cujo PU foge da media do mesmo codigo de material. */
final class LinhaDiscrepanciaValor
{
    public function __construct(
        public readonly string $pep3,
        public readonly string $pep4,
        public readonly string $codigo,
        public readonly string $descricao,
        public readonly float $qtd,
        public readonly float $valor,
        public readonly float $precoUnitario,
        public readonly float $media,
        public readonly float $desvio,
        public readonly int $lancamentos,
    ) {
    }

    public function acima(): bool
    {
        return $this->desvio > 0;
    }

    public function situacao(): string
    {
        return $this->acima() ? 'ACIMA DA MEDIA' : 'ABAIXO DA MEDIA';
    }

    public function observacao(): string
    {
        return 'Discrepancia de valor de material: PU R$ ' . Formatador::decimal2($this->precoUnitario)
            . ' esta ' . Formatador::pct(abs($this->desvio), 1) . ($this->acima() ? ' acima' : ' abaixo')
            . ' da media do codigo (R$ ' . Formatador::decimal2($this->media) . ' em ' . $this->lancamentos
            . ' lancamentos ODI)';
    }

    /** @return array<string, mixed> */
    public function paraArray(): array
    {
        return [
            'PEP3NIVEL' => $this->pep3,
            'PEP4NIVEL' => $this->pep4,
            'COD MAT' => $this->codigo,
            'DESC MAT' => $this->descricao,
            'QTD' => $this->qtd,
            'VALOR' => $this->valor,
            'PU (VALOR/QTD)' => $this->precoUnitario,
            'MEDIA PU DO COD' => $this->media,
            'DESVIO' => $this->desvio,
            'SITUACAO' => $this->situacao(),
            'OBSERVACAO' => $this->observacao(),
        ];
    }
}
