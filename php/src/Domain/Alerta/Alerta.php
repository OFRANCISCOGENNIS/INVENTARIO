<?php

declare(strict_types=1);

namespace Inventario\Domain\Alerta;

/**
 * Um apontamento da aba ALERTA CRITICO.
 */
final class Alerta
{
    public function __construct(
        public readonly string $tipo,
        public readonly string $pep3 = '',
        public readonly string $pep4 = '',
        public readonly string $codigo = '',
        public readonly string $descricao = '',
        public readonly string $familia = '',
        public readonly ?float $valor = null,
        public readonly ?float $qtd = null,
        public readonly ?float $precoUnitario = null,
        public readonly string|float|null $referencia = null,
        public readonly string $motivo = '',
    ) {
    }

    /** Monta o alerta com os dados de identificacao da linha da base. */
    public static function deLinha(
        string $tipo,
        LinhaAlerta $l,
        string $motivo,
        ?float $pu = null,
        string|float|null $ref = null,
        bool $semCodigo = false,
        bool $comQtd = true,
    ): self {
        return new self(
            $tipo,
            $l->pep3,
            $l->pep4,
            $semCodigo ? '' : $l->codigo,
            $l->descricao,
            $l->familia,
            $l->valor,
            $comQtd ? $l->qtd : null,
            $pu,
            $ref,
            $motivo,
        );
    }

    /** PRECO UNIT = VALOR / QTD quando ha quantidade (como a aba recalcula). */
    public function comPrecoRecalculado(): self
    {
        $q = $this->qtd ?? 0.0;
        if ($q == 0.0) {
            return $this;
        }

        return new self(
            $this->tipo, $this->pep3, $this->pep4, $this->codigo, $this->descricao, $this->familia,
            $this->valor, $this->qtd, ($this->valor ?? 0.0) / $q, $this->referencia, $this->motivo,
        );
    }

    public function ehQualidadeBase(): bool
    {
        return str_contains(strtoupper($this->tipo), 'QUALIDADE DA BASE');
    }

    /** @return array<string, mixed> */
    public function paraArray(): array
    {
        return [
            'TIPO ALERTA' => $this->tipo,
            'PEP3NIVEL' => $this->pep3,
            'PEP4NIVEL' => $this->pep4,
            'COD MAT' => $this->codigo,
            'DESC MAT' => $this->descricao,
            'FAMILIA' => $this->familia,
            'VALOR' => $this->valor,
            'QTD' => $this->qtd,
            'PRECO UNIT' => $this->precoUnitario,
            'REFERENCIA' => $this->referencia,
            'MOTIVO' => $this->motivo,
        ];
    }
}
