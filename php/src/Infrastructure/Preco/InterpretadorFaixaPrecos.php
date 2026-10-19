<?php

declare(strict_types=1);

namespace Inventario\Infrastructure\Preco;

use DateTimeImmutable;
use Inventario\Domain\Preco\FaixaPreco;
use Inventario\Domain\Preco\SituacaoFontePrecos;
use Inventario\Domain\Preco\TabelaPrecos;
use Inventario\Domain\Support\Numero;
use Inventario\Domain\Support\Texto;
use Inventario\Application\Carga\Tabela;

/**
 * BASE DE PRECOS: MATERIAL | TEXTO MATERIAL | UML | MIN PU | MAX PU | ...
 * As colunas sao achadas pelo cabecalho; sem ele, valem as posicoes padrao
 * (1, 2, 4 e 5). Codigo repetido: vale a primeira linha.
 */
final class InterpretadorFaixaPrecos
{
    public static function interpretar(Tabela $t, string $fonte, ?DateTimeImmutable $data = null): TabelaPrecos
    {
        $cMat = $cTxt = $cMin = $cMax = null;
        foreach ($t->cabecalho as $i => $h) {
            match (Texto::norm($h)) {
                'MATERIAL' => $cMat ??= $i,
                'TEXTO MATERIAL' => $cTxt ??= $i,
                'MIN PU' => $cMin ??= $i,
                'MAX PU' => $cMax ??= $i,
                default => null,
            };
        }
        $cMat ??= 0;
        $cTxt ??= 1;
        $cMin ??= 3;
        $cMax ??= 4;

        $faixas = [];
        foreach ($t->linhas as $row) {
            $cod = Texto::codigo($row[$cMat] ?? null);
            if ($cod === '' || array_key_exists($cod, $faixas)) {
                continue;
            }
            $faixas[$cod] = new FaixaPreco(
                Numero::val0($row[$cMin] ?? null),
                Numero::val0($row[$cMax] ?? null),
                Texto::limpo($row[$cTxt] ?? null),
            );
        }
        if ($faixas === []) {
            return new TabelaPrecos([], $fonte . ' (vazia: nenhum material lido)', SituacaoFontePrecos::VAZIA, $data);
        }

        return new TabelaPrecos($faixas, $fonte, SituacaoFontePrecos::OK, $data);
    }

    /**
     * Aba PRECOS (codigo na coluna 1, preco na coluna 2): preco de referencia.
     *
     * @return array<string, float>
     */
    public static function precosReferencia(Tabela $t): array
    {
        $out = [];
        foreach ($t->linhas as $row) {
            $cod = Texto::codigo($row[0] ?? null);
            $preco = Numero::val0($row[1] ?? null);
            if ($cod !== '' && $preco > 0 && !array_key_exists($cod, $out)) {
                $out[$cod] = $preco;
            }
        }

        return $out;
    }
}
