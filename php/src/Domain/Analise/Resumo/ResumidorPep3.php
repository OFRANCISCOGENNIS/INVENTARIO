<?php

declare(strict_types=1);

namespace Inventario\Domain\Analise\Resumo;

use Inventario\Domain\Analise\SapPrj\AnalisadorSapPrj;
use Inventario\Domain\Analise\SapPrj\ResultadoSapPrj;
use Inventario\Domain\Support\Ordenacao;

/**
 * RESUMO SAP x PRJ: uma linha por obra (PEP3), reprovadas primeiro e, dentro
 * de cada grupo, pelo maior valor nao aderente (ProcessarResumoPEP3).
 */
final class ResumidorPep3
{
    /** @return list<ResumoObra> */
    public function resumir(ResultadoSapPrj $analise): array
    {
        $obras = [];
        foreach ($analise->linhas as $l) {
            $p3 = $l->pep3();
            if ($p3 === '') {
                continue;
            }
            $o = &$obras[$p3];
            $o ??= ['rep' => false, 'val' => 0.0, 'na' => 0.0, 'mot' => '', 'ods' => []];
            if ($l->reprovada()) {
                $o['rep'] = true;
            }
            $v = $l->valor();
            $o['val'] += $v;
            if (str_contains(mb_strtoupper($l->sitMat, 'UTF-8'), 'NAO ADER')) {
                $o['na'] += abs($v);
            }
            if ($l->motivoDevolucao !== '' && $o['mot'] === '') {
                $o['mot'] = $l->motivoDevolucao;
            }
            if ($l->pep4() !== '') {
                $o['ods'][$l->pep4()] = true;
            }
            unset($o);
        }

        $lista = [];
        foreach ($obras as $p3 => $o) {
            $lista[] = new ResumoObra(
                (string) $p3,
                $o['rep'],
                count($o['ods']),
                $o['val'],
                $o['na'],
                $o['rep'] ? AnalisadorSapPrj::familiaDevolucao($o['mot']) : '',
                $o['mot'],
            );
        }

        return Ordenacao::selecaoDesc(
            $lista,
            static fn (ResumoObra $r): float => ($r->reprovado ? 1e12 : 0.0) + $r->valorNaoAderente,
        );
    }

    /**
     * DEVOLUCOES: so as obras reprovadas, para envio a projetista.
     *
     * @param list<ResumoObra> $resumo
     * @return list<array<string, mixed>>
     */
    public static function devolucoes(array $resumo): array
    {
        $out = [];
        foreach ($resumo as $r) {
            if ($r->reprovado) {
                $out[] = [
                    'PEP3NIVEL' => $r->pep3,
                    'SITUACAO' => $r->situacao(),
                    'VALOR NAO ADER' => $r->valorNaoAderente,
                    'FAMILIAS REPROVADAS' => $r->familiasReprovadas,
                    'MOTIVO DEVOLUCAO' => $r->motivoDevolucao,
                ];
            }
        }

        return $out;
    }
}
