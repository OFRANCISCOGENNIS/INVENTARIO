<?php

declare(strict_types=1);

namespace Inventario\Domain\Preco;

use Inventario\Domain\Base\TipoOD;

final class LinhaPrecoUnitario
{
    public function __construct(
        public readonly string $pep3,
        public readonly string $pep4,
        public readonly string $tipo,
        public readonly string $codigo,
        public readonly string $descricao,
        public readonly string $unidade,
        public readonly float $qtd,
        public readonly float $valor,
        public readonly float $precoUnitario,
        public readonly ?FaixaPreco $faixa,
        public readonly StatusPreco $status,
        public readonly string $observacao,
        public readonly TipoOD $tipoOD,
    ) {
    }

    /** Sobrepreco potencial: (PU - MAX) x QTD quando acima do maximo. */
    public function sobrepreco(): float
    {
        if ($this->status !== StatusPreco::ACIMA || $this->faixa === null) {
            return 0.0;
        }
        if ($this->precoUnitario > $this->faixa->max && $this->qtd > 0) {
            return ($this->precoUnitario - $this->faixa->max) * $this->qtd;
        }

        return 0.0;
    }

    /** @return array<string, mixed> */
    public function paraArray(): array
    {
        return [
            'PEP3NIVEL' => $this->pep3,
            'PEP4NIVEL' => $this->pep4,
            'TIPO' => $this->tipo,
            'COD MAT' => $this->codigo,
            'DESC MAT' => $this->descricao,
            'UND' => $this->unidade,
            'QTD' => $this->qtd,
            'VALOR' => $this->valor,
            'PU (VALOR/QTD)' => $this->precoUnitario,
            'MIN PU' => $this->faixa?->min,
            'MAX PU' => $this->faixa?->max,
            'STATUS' => $this->status->value,
            'OBSERVACAO' => $this->observacao,
            'TIPO OD' => $this->tipoOD->rotulo(),
        ];
    }
}
