<?php

declare(strict_types=1);

namespace Inventario\Domain\Analise\SapPrj;

use Inventario\Domain\Base\Coluna;
use Inventario\Domain\Base\LinhaBase;
use Inventario\Domain\Support\Numero;

/**
 * Uma linha da aba ANALISE SAP x PRJ: as colunas da base mais o veredito.
 */
final class LinhaAnalise
{
    public function __construct(
        public readonly LinhaBase $origem,
        public readonly string $sitMat,
        public readonly Aprovacao $aprovacao,
        public readonly string $motivo,
        public readonly string $motivoDevolucao,
        public readonly bool $divergenciaQtd,
    ) {
    }

    public function pep3(): string
    {
        return $this->origem->texto(Coluna::PEP3);
    }

    public function pep4(): string
    {
        return $this->origem->texto(Coluna::PEP4);
    }

    public function valor(): float
    {
        return $this->origem->num(Coluna::VALOR);
    }

    public function reprovada(): bool
    {
        return $this->aprovacao === Aprovacao::REPROVADO;
    }

    /**
     * Linha na ordem das colunas de saida.
     *
     * @return array<string, mixed>
     */
    public function paraArray(): array
    {
        $out = [];
        foreach (Coluna::SAIDA_ANALISE as $c) {
            $v = $this->origem->bruto($c);
            // colunas numericas saem como numero ("3200,00" do CSV -> 3200.0)
            if (is_string($v) && in_array($c, [Coluna::VALOR, Coluna::MAT_LIB_SAP, Coluna::MAT_PRJ_CAD], true)) {
                $v = Numero::converter($v) ?? $v;
            }
            $out[$c] = $v;
        }
        $out[Coluna::SIT_MAT] = $this->sitMat;
        $out['APROVACAO'] = $this->aprovacao->value;
        $out['MOTIVO'] = $this->motivo;
        $out['MOTIVO DEVOLUCAO PEP3'] = $this->motivoDevolucao;

        return $out;
    }
}
