<?php

declare(strict_types=1);

namespace Inventario\Presentation\Cli;

use Inventario\Presentation\Aplicacao;
use Throwable;

final class Console
{
    /** @var array<string, Comando> */
    private array $comandos = [];

    public function __construct(Aplicacao $app, private readonly Saida $out = new Saida())
    {
        foreach ([new ComandoAnalisar($app), new ComandoConfig($app), new ComandoEquivalencias($app), new ComandoHistorico($app)] as $c) {
            $this->comandos[$c->nome()] = $c;
        }
    }

    /** @param list<string> $argv */
    public function rodar(array $argv): int
    {
        $nome = $argv[1] ?? 'ajuda';
        $cmd = $this->comandos[$nome] ?? null;
        if ($cmd === null) {
            $this->ajuda();

            return in_array($nome, ['ajuda', 'help', '--help', '-h'], true) ? 0 : 2;
        }
        try {
            return $cmd->executar(new Argumentos(array_slice($argv, 2)), $this->out);
        } catch (Throwable $e) {
            $this->out->erro('Erro: ' . $e->getMessage());

            return 1;
        }
    }

    private function ajuda(): void
    {
        $this->out->linha('Analise de Inventario SAP x PRJ');
        $this->out->linha('');
        $this->out->linha('Uso: inventario <comando> [argumentos]');
        $this->out->linha('');
        foreach ($this->comandos as $c) {
            $this->out->linha(sprintf('  %-14s %s', $c->nome(), $c->descricao()));
        }
        $this->out->linha('');
        $this->out->linha('analisar: --modo=juntar|separar  --precos=ARQ  --saida=PASTA  --formato=xlsx,json  --previa');
    }
}
