<?php

declare(strict_types=1);

namespace Inventario\Domain\Base;

use Inventario\Domain\Support\Texto;

/**
 * Casa os cabecalhos da extracao com os nomes canonicos (MapaDeCabecalho,
 * AliasesBase e AplicarAliasesBase do VBA). O layout novo da extracao
 * renomeou 7 colunas; o antigo continua aceito. Com os dois nomes, vale o
 * canonico. Cabecalho repetido: vence a primeira ocorrencia.
 */
final class MapeadorCabecalho
{
    /** canonico => aliases, escritos como aparecem na planilha */
    public const ALIASES = [
        Coluna::COD_MAT => ['MATERIAL'],
        Coluna::DESC_MAT => ['DESC_MATERIAL'],
        Coluna::UND => ['UNIDADE'],
        Coluna::CLASSE => ['CLASSE_CUSTO'],
        Coluna::MAT_LIB_SAP => ['ATENDIDO SAP'],
        Coluna::MAT_PRJ_CAD => ['TOMBADO PROJ'],
        Coluna::SIT_MAT => ['SITUACAO_DO_MATERIAL'],
    ];

    /**
     * @param list<mixed> $cabecalho linha 1 da planilha
     * @return array<string, int> nome normalizado => indice (0-based)
     */
    public static function mapear(array $cabecalho): array
    {
        $m = [];
        foreach (array_values($cabecalho) as $i => $h) {
            $n = Texto::norm($h);
            if ($n !== '' && !array_key_exists($n, $m)) {
                $m[$n] = $i;
            }
        }

        return self::aplicarAliases($m);
    }

    /**
     * @param array<string, int> $mapa
     * @return array<string, int>
     */
    public static function aplicarAliases(array $mapa): array
    {
        foreach (self::ALIASES as $canonico => $alts) {
            if (array_key_exists($canonico, $mapa)) {
                continue;
            }
            foreach ($alts as $alt) {
                $a = Texto::norm($alt);
                if (array_key_exists($a, $mapa)) {
                    $mapa[$canonico] = $mapa[$a];
                    break;
                }
            }
        }

        return $mapa;
    }

    /** "ATENDIDO SAP" -> "MAT LIB SAP"; nome sem alias volta igual. */
    public static function canonico(string $nomeNorm): string
    {
        foreach (self::ALIASES as $canonico => $alts) {
            foreach ($alts as $alt) {
                if (Texto::norm($alt) === $nomeNorm) {
                    return $canonico;
                }
            }
        }

        return $nomeNorm;
    }

    /** "MAT LIB SAP (ou ATENDIDO SAP)" para mensagens. */
    public static function nomeExibicao(string $canonico): string
    {
        if (isset(self::ALIASES[$canonico])) {
            return $canonico . ' (ou ' . implode(' / ', self::ALIASES[$canonico]) . ')';
        }

        return $canonico;
    }

    /**
     * Colunas obrigatorias ausentes, ja no formato de mensagem.
     *
     * @param array<string, int> $mapa
     * @return list<string>
     */
    public static function faltantes(array $mapa): array
    {
        $faltam = [];
        foreach (Coluna::OBRIGATORIAS as $c) {
            if (!array_key_exists($c, $mapa)) {
                $faltam[] = self::nomeExibicao($c);
            }
        }

        return $faltam;
    }
}
