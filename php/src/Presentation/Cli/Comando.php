<?php

declare(strict_types=1);

namespace Inventario\Presentation\Cli;

interface Comando
{
    public function nome(): string;

    public function descricao(): string;

    /** @param list<string> $args */
    public function executar(Argumentos $args, Saida $out): int;
}
