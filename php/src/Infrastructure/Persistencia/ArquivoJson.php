<?php

declare(strict_types=1);

namespace Inventario\Infrastructure\Persistencia;

use RuntimeException;

/** Leitura/gravacao atomica de um arquivo JSON. */
final class ArquivoJson
{
    public function __construct(private readonly string $caminho)
    {
    }

    public function existe(): bool
    {
        return is_file($this->caminho);
    }

    /** @return array<mixed> */
    public function ler(): array
    {
        if (!$this->existe()) {
            return [];
        }
        $d = json_decode((string) file_get_contents($this->caminho), true);

        return is_array($d) ? $d : [];
    }

    /** @param array<mixed> $dados */
    public function gravar(array $dados): void
    {
        $dir = dirname($this->caminho);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException("nao foi possivel criar a pasta {$dir}");
        }
        $tmp = $this->caminho . '.tmp';
        file_put_contents($tmp, json_encode($dados, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        rename($tmp, $this->caminho);
    }

    public function caminho(): string
    {
        return $this->caminho;
    }
}
