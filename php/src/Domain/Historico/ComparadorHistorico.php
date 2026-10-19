<?php

declare(strict_types=1);

namespace Inventario\Domain\Historico;

use DateTimeImmutable;
use Inventario\Domain\Analise\Resumo\ResumoObra;

/**
 * Compara a situacao de cada obra com a rodada anterior (AtualizarHistorico).
 */
final class ComparadorHistorico
{
    /**
     * @param list<ResumoObra> $resumo
     * @param array<string, string> $anteriores
     * @return list<RegistroObra>
     */
    public function comparar(array $resumo, array $anteriores, DateTimeImmutable $agora): array
    {
        $run = $agora->format('d/m/Y H:i');
        $out = [];
        foreach ($resumo as $r) {
            $si = $r->situacao();
            if (!array_key_exists($r->pep3, $anteriores)) {
                $mudou = 'NOVO';
            } elseif ($anteriores[$r->pep3] !== $si) {
                $mudou = "MUDOU ({$anteriores[$r->pep3]} -> {$si})";
            } else {
                $mudou = 'MANTEVE';
            }
            $out[] = new RegistroObra($run, $r->pep3, $si, $r->valorNaoAderente, $mudou);
        }

        return $out;
    }
}
