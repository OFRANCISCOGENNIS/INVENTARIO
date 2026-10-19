<?php

declare(strict_types=1);

namespace Inventario\Domain\Base;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * Base de analise (extracao SAP x PRJ) ja com as colunas casadas pelo nome
 * canonico.
 *
 * @implements IteratorAggregate<int, LinhaBase>
 */
final class BaseInventario implements IteratorAggregate, Countable
{
    /**
     * @param list<LinhaBase> $linhas
     * @param array<string, int> $colunas nome canonico => indice na origem
     */
    public function __construct(
        public readonly string $nome,
        private readonly array $linhas,
        private readonly array $colunas,
    ) {
    }

    /**
     * Monta a base a partir de uma tabela crua (linha 1 = cabecalho).
     *
     * @param list<mixed> $cabecalho
     * @param iterable<list<mixed>> $dados
     */
    public static function deTabela(string $nome, array $cabecalho, iterable $dados): self
    {
        $mapa = MapeadorCabecalho::mapear($cabecalho);
        $linhas = [];
        $n = 1;
        foreach ($dados as $row) {
            $n++;
            $row = array_values($row);
            $vazia = true;
            foreach ($row as $v) {
                if ($v !== null && $v !== '') {
                    $vazia = false;
                    break;
                }
            }
            if ($vazia) {
                continue;
            }
            $cel = [];
            foreach ($mapa as $col => $idx) {
                $v = $row[$idx] ?? null;
                $cel[(string) $col] = $v === '' ? null : $v;
            }
            $linhas[] = new LinhaBase($n, $cel);
        }

        return new self($nome, $linhas, $mapa);
    }

    public function temColuna(string $coluna): bool
    {
        return array_key_exists($coluna, $this->colunas);
    }

    /** @return array<string, int> */
    public function colunas(): array
    {
        return $this->colunas;
    }

    /** @return list<string> */
    public function colunasFaltantes(): array
    {
        return MapeadorCabecalho::faltantes($this->colunas);
    }

    /** @return list<LinhaBase> */
    public function linhas(): array
    {
        return $this->linhas;
    }

    public function count(): int
    {
        return count($this->linhas);
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->linhas);
    }
}
