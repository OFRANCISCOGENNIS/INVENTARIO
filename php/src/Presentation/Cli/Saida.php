<?php

declare(strict_types=1);

namespace Inventario\Presentation\Cli;

final class Saida
{
    /** @param resource $stream */
    public function __construct(private $stream = STDOUT, private $erro = STDERR)
    {
    }

    public function linha(string $s = ''): void
    {
        fwrite($this->stream, $s . PHP_EOL);
    }

    public function erro(string $s): void
    {
        fwrite($this->erro, $s . PHP_EOL);
    }

    /**
     * @param list<string> $cab
     * @param list<list<mixed>> $linhas
     */
    public function tabela(array $cab, array $linhas): void
    {
        $w = array_map('mb_strlen', $cab);
        foreach ($linhas as $l) {
            foreach (array_values($l) as $i => $v) {
                $w[$i] = max($w[$i] ?? 0, mb_strlen((string) $v));
            }
        }
        $fmt = static function (array $l) use ($w): string {
            $c = [];
            foreach ($w as $i => $n) {
                $v = (string) ($l[$i] ?? '');
                $c[] = $v . str_repeat(' ', max(0, $n - mb_strlen($v)));
            }

            return rtrim(implode('  ', $c));
        };
        $this->linha($fmt($cab));
        $this->linha(implode('  ', array_map(static fn (int $n): string => str_repeat('-', $n), $w)));
        foreach ($linhas as $l) {
            $this->linha($fmt(array_values($l)));
        }
    }
}
