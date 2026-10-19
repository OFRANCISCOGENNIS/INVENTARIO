<?php

declare(strict_types=1);

namespace Inventario\Presentation\Cli;

/** Argumentos posicionais e opcoes --chave=valor / --flag. */
final class Argumentos
{
    /** @var list<string> */
    public readonly array $posicionais;
    /** @var array<string, string|true> */
    private array $opcoes = [];

    /** @param list<string> $argv sem o nome do script e do comando */
    public function __construct(array $argv)
    {
        $pos = [];
        foreach ($argv as $a) {
            if (str_starts_with($a, '--')) {
                $p = explode('=', substr($a, 2), 2);
                $this->opcoes[$p[0]] = $p[1] ?? true;
            } else {
                $pos[] = $a;
            }
        }
        $this->posicionais = $pos;
    }

    public function opcao(string $nome, ?string $padrao = null): ?string
    {
        $v = $this->opcoes[$nome] ?? null;

        return is_string($v) ? $v : $padrao;
    }

    public function flag(string $nome): bool
    {
        return isset($this->opcoes[$nome]);
    }
}
