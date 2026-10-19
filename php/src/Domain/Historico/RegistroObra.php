<?php

declare(strict_types=1);

namespace Inventario\Domain\Historico;

/** Uma linha do HISTORICO: situacao da obra numa rodada e se mudou. */
final class RegistroObra
{
    public function __construct(
        public readonly string $run,
        public readonly string $pep3,
        public readonly string $situacao,
        public readonly float $valorNaoAderente,
        public readonly string $mudou,
    ) {
    }

    /** @return array<string, mixed> */
    public function paraArray(): array
    {
        return [
            'RUN' => $this->run,
            'PEP3NIVEL' => $this->pep3,
            'SITUACAO' => $this->situacao,
            'VALOR NAO ADER' => $this->valorNaoAderente,
            'MUDOU' => $this->mudou,
        ];
    }

    /** @param array<string, mixed> $a */
    public static function deArray(array $a): self
    {
        return new self(
            (string) ($a['RUN'] ?? ''),
            (string) ($a['PEP3NIVEL'] ?? ''),
            (string) ($a['SITUACAO'] ?? ''),
            (float) ($a['VALOR NAO ADER'] ?? 0),
            (string) ($a['MUDOU'] ?? ''),
        );
    }
}
