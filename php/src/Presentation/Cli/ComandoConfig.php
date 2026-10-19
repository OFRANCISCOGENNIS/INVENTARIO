<?php

declare(strict_types=1);

namespace Inventario\Presentation\Cli;

use Inventario\Domain\Config\Parametros;
use Inventario\Presentation\Aplicacao;

/** inventario config [CHAVE VALOR] - lista ou altera um parametro. */
final class ComandoConfig implements Comando
{
    public function __construct(private readonly Aplicacao $app)
    {
    }

    public function nome(): string
    {
        return 'config';
    }

    public function descricao(): string
    {
        return 'Lista os parametros (CONFIG) ou altera um: config CHAVE VALOR';
    }

    public function executar(Argumentos $args, Saida $out): int
    {
        $p = $this->app->parametros->carregar();
        [$chave, $valor] = $args->posicionais + [null, null];
        if ($chave !== null) {
            $chave = strtoupper($chave);
            if (!isset(Parametros::PADRAO[$chave])) {
                $out->erro("parametro desconhecido: {$chave}");

                return 2;
            }
            if ($valor === null) {
                $out->linha($chave . ' = ' . $p->texto($chave));

                return 0;
            }
            $this->app->parametros->salvar($p->com($chave, $valor));
            $out->linha("{$chave} = {$valor} (gravado)");

            return 0;
        }
        $linhas = [];
        foreach ($p->tabela() as $k => $l) {
            $linhas[] = [$k, (string) $l['valor'], $l['descricao']];
        }
        $out->tabela(['CHAVE', 'VALOR', 'DESCRICAO'], $linhas);

        return 0;
    }
}
