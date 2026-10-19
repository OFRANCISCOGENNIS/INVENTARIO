<?php

declare(strict_types=1);

namespace Inventario\Domain\Preco;

use Inventario\Domain\Support\Formatador;

/**
 * Preco de REFERENCIA por material (alerta UC SUBVALORIZADO). Vem de uma
 * tabela codigo -> preco ou, sem ela, do ponto medio da faixa MIN/MAX.
 */
final class PrecosReferencia
{
    /** @param array<string, float> $precos */
    public function __construct(
        private readonly array $precos,
        public readonly string $fonte,
    ) {
    }

    /** @param array<string, float> $precos */
    public static function deTabela(array $precos): self
    {
        $ok = array_filter($precos, static fn (float $p): bool => $p > 0);

        return new self($ok, 'tabela PRECOS (' . Formatador::inteiro(count($ok)) . ' materiais)');
    }

    public static function dePontoMedio(TabelaPrecos $tabela): self
    {
        $p = [];
        foreach ($tabela->faixas() as $cod => $f) {
            $md = $f->pontoMedio();
            if ($md > 0) {
                $p[(string) $cod] = $md;
            }
        }

        return new self($p, 'ponto medio MIN/MAX da faixa de precos (' . Formatador::inteiro(count($p)) . ' materiais)');
    }

    public function preco(string $codigo): ?float
    {
        return $this->precos[$codigo] ?? null;
    }

    public function total(): int
    {
        return count($this->precos);
    }
}
