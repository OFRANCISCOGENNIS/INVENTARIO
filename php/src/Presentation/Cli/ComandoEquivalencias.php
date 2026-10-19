<?php

declare(strict_types=1);

namespace Inventario\Presentation\Cli;

use Inventario\Presentation\Aplicacao;

/** inventario equivalencias - grupos ja unidos (codigo em dois grupos une os grupos). */
final class ComandoEquivalencias implements Comando
{
    public function __construct(private readonly Aplicacao $app)
    {
    }

    public function nome(): string
    {
        return 'equivalencias';
    }

    public function descricao(): string
    {
        return 'Lista os grupos de materiais equivalentes (edite storage/equivalencias.json)';
    }

    public function executar(Argumentos $args, Saida $out): int
    {
        $cat = $this->app->equivalencias->carregar();
        $linhas = [];
        foreach ($cat->grupos() as $g => $cods) {
            $linhas[] = [(string) $g, implode(' = ', $cods)];
        }
        $out->tabela(['GRUPO', 'CODIGOS'], $linhas);
        $out->linha('');
        $out->linha("{$cat->totalCodigos()} codigo(s) em {$cat->totalGrupos()} grupo(s) - arquivo: {$this->app->pastaDados}/equivalencias.json");

        return 0;
    }
}
