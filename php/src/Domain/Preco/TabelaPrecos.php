<?php

declare(strict_types=1);

namespace Inventario\Domain\Preco;

use DateTimeImmutable;

/**
 * Faixas MIN/MAX por codigo de material e de onde elas vieram.
 */
final class TabelaPrecos
{
    /** @param array<string, FaixaPreco> $faixas */
    public function __construct(
        private readonly array $faixas,
        public readonly string $fonte,
        public readonly SituacaoFontePrecos $situacao,
        public readonly ?DateTimeImmutable $dataArquivo = null,
    ) {
    }

    public static function semFonte(string $motivo, SituacaoFontePrecos $situacao = SituacaoFontePrecos::NENHUMA): self
    {
        return new self([], $motivo, $situacao);
    }

    public function faixa(string $codigo): ?FaixaPreco
    {
        return $this->faixas[$codigo] ?? null;
    }

    /** @return array<int|string, FaixaPreco> */
    public function faixas(): array
    {
        return $this->faixas;
    }

    public function total(): int
    {
        return count($this->faixas);
    }

    /** Texto da fonte para o bloco SAUDE DA EXECUCAO. */
    public function descricao(): string
    {
        $t = $this->fonte;
        if ($this->situacao === SituacaoFontePrecos::OK) {
            $t .= ' - ' . number_format($this->total(), 0, ',', '.') . ' materiais';
        }

        return $t;
    }
}
