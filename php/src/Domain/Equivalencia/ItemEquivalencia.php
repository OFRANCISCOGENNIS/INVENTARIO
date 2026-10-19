<?php

declare(strict_types=1);

namespace Inventario\Domain\Equivalencia;

/**
 * Uma linha da tabela de equivalencias: codigos com o mesmo numero de grupo
 * sao intercambiaveis.
 */
final class ItemEquivalencia
{
    public function __construct(
        public readonly string $codigo,
        public readonly string $grupo,
        public readonly string $descricao = '',
        public readonly string $unidade = '',
        public readonly string $tipo = '',
        public readonly string $familia = '',
        public readonly int $versao = 0,
    ) {
    }

    /** @return array<string, string|int> */
    public function paraArray(): array
    {
        return [
            'codigo' => $this->codigo,
            'descricao' => $this->descricao,
            'unidade' => $this->unidade,
            'tipo' => $this->tipo,
            'familia' => $this->familia,
            'grupo' => $this->grupo,
            'versao' => $this->versao,
        ];
    }

    /** @param array<string, mixed> $a */
    public static function deArray(array $a): self
    {
        return new self(
            (string) ($a['codigo'] ?? ''),
            (string) ($a['grupo'] ?? ''),
            (string) ($a['descricao'] ?? ''),
            (string) ($a['unidade'] ?? ''),
            (string) ($a['tipo'] ?? ''),
            (string) ($a['familia'] ?? ''),
            (int) ($a['versao'] ?? 0),
        );
    }
}
