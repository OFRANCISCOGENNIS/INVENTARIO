<?php

declare(strict_types=1);

namespace Inventario\Application\UseCase;

final class SolicitacaoAnalise
{
    /**
     * @param array<string, string> $arquivos caminho => nome exibido
     */
    public function __construct(
        public readonly array $arquivos,
        public readonly ModoCarga $modo = ModoCarga::JUNTAR,
        public readonly ?string $arquivoPrecos = null,
    ) {
    }
}
