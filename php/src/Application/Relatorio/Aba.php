<?php

declare(strict_types=1);

namespace Inventario\Application\Relatorio;

final class Aba
{
    /**
     * @param list<Bloco> $blocos
     * @param list<int> $larguras largura sugerida das colunas
     */
    public function __construct(
        public readonly string $nome,
        public readonly string $titulo,
        public readonly string $subtitulo,
        public readonly array $blocos,
        public readonly string $corGuia = '111827',
        public readonly array $larguras = [],
    ) {
    }
}
