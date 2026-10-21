<?php

declare(strict_types=1);

namespace Inventario\Domain\Config;

use Inventario\Domain\Support\Numero;
use Inventario\Domain\Support\Texto;

/**
 * Parametros editaveis da analise (aba CONFIG do VBA). Valor vazio ou
 * ausente cai no padrao. Imutavel: com() devolve uma copia alterada.
 */
final class Parametros
{
    /** chave => [padrao, descricao] */
    public const PADRAO = [
        'TOL_ADERENCIA_PCT' => [0.1, 'Tolerancia de aderencia SAP x PRJ (fracao; 0,1 = 10%)'],
        'TOL_SUBVAL' => [0.9, 'UC subvalorizado: alerta se PU menor que fator x referencia'],
        'MIN_DIVERG_RS' => [100, 'Materialidade minima da divergencia em R$ p/ alertar'],
        'CABO_ISOLADO_MAX_M' => [15, 'Cabo isolado abaixo deste comprimento (m) fica isento de UC'],
        'CORTE_DIVERG_QTD' => [20, 'Destaca linhas com diferenca de qtd acima deste valor'],
        'PESO_REPROV' => [40, 'Ranking: peso de obra reprovada na SAP x PRJ'],
        'PESO_ALERTA' => [4, 'Ranking: peso por alerta critico'],
        'CAP_ALERTA' => [24, 'Ranking: teto da parcela de alertas'],
        'PESO_PU' => [3, 'Ranking: peso por divergencia de preco unitario'],
        'CAP_PU' => [18, 'Ranking: teto da parcela de PU'],
        'PESO_COM' => [2, 'Ranking: peso por COM fora do previsto NT.006'],
        'CAP_COM' => [18, 'Ranking: teto da parcela de COM'],
        'PESO_DISCREP' => [2, 'Ranking: peso por discrepancia de valor de material (ODI)'],
        'CAP_DISCREP' => [12, 'Ranking: teto da parcela de discrepancia de valor'],
        'COM_CRITICO' => ['CH FUS; PARA RAIO', 'Familias COM criticas (separadas por ;) que reprovam o PEP3'],
        'DIF_ABS_COND_NU' => [5, 'COND NU: diferenca ABSOLUTA (nao %) ate este valor = ADERENTE'],
        'DIF_ABS_CABO' => [15, 'Familias de DIF_ABS_FAMILIAS: dif ABSOLUTA ate este valor = ADERENTE'],
        'DIF_ABS_FAMILIAS' => ['CABO ISOLADO; CABO COBRE; COND COBRE', 'Familias (separadas por ;) que usam a regra DIF_ABS_CABO'],
        'PU_ODI_MIN' => [0.01, 'Alerta PU BAIXO EM ODI: preco unitario minimo da faixa (R$)'],
        'PU_ODI_MAX' => [10, 'Alerta PU BAIXO EM ODI: preco unitario maximo da faixa (R$)'],
        'PU_CABO_ALERTA' => [0.01, 'Alerta PU 0,01 EM CABO/COND: preco unitario simbolico (R$)'],
        'ODD_VALOR_MAX_PCT' => [0.3, 'ODD: PU acima desta fracao do PU medio do codigo na ODI e NAO ADERENTE (reprova)'],
        'POSTE_ODI_PU_MIN' => [500, 'Alerta POSTE ODI PU BAIXO: poste em ODI com preco unitario abaixo de (R$)'],
        'PRECOS_MAX_DIAS' => [90, 'Diagnostico: arquivo da base de precos mais antigo que isto (dias) gera ATENCAO'],
        'DISCREP_VALOR_PCT' => [0.3, 'Discrepancia de valor de material (ODI): PU fora de +/- esta fracao da media do codigo'],
    ];

    /** @var array<string, true>|null */
    private ?array $comCritico = null;
    /** @var array<string, true>|null */
    private ?array $famDifAbs = null;

    /**
     * @param array<string, scalar|null> $valores chave => valor informado pelo usuario
     */
    public function __construct(private readonly array $valores = [])
    {
    }

    public static function padrao(): self
    {
        return new self();
    }

    public function com(string $chave, string|int|float $valor): self
    {
        $v = $this->valores;
        $v[$chave] = $valor;

        return new self($v);
    }

    public function texto(string $chave): string
    {
        $v = trim(Texto::str($this->valores[$chave] ?? null));
        if ($v !== '') {
            return $v;
        }

        return (string) (self::PADRAO[$chave][0] ?? '');
    }

    public function numero(string $chave): float
    {
        $v = trim(Texto::str($this->valores[$chave] ?? null));
        if ($v !== '') {
            $n = Numero::converter($v);
            if ($n !== null) {
                return $n;
            }
        }

        return (float) (self::PADRAO[$chave][0] ?? 0);
    }

    /** Valor positivo do parametro, ou o padrao quando <= 0 (fallback do VBA). */
    private function positivo(string $chave): float
    {
        $v = $this->numero($chave);

        return $v > 0 ? $v : (float) self::PADRAO[$chave][0];
    }

    public function toleranciaAderencia(): float
    {
        return $this->positivo('TOL_ADERENCIA_PCT');
    }

    public function caboIsoladoMaxM(): float
    {
        return $this->positivo('CABO_ISOLADO_MAX_M');
    }

    public function difAbsCondNu(): float
    {
        return $this->positivo('DIF_ABS_COND_NU');
    }

    public function difAbsCabo(): float
    {
        return $this->positivo('DIF_ABS_CABO');
    }

    public function oddValorMaxPct(): float
    {
        return $this->numero('ODD_VALOR_MAX_PCT');
    }

    /** @return array<string, true> tokens sem espaco ("CHFUS", "PARARAIO") */
    public function comCritico(): array
    {
        return $this->comCritico ??= Texto::tokens($this->texto('COM_CRITICO'));
    }

    /** @return array<string, true> */
    public function familiasDifAbs(): array
    {
        return $this->famDifAbs ??= Texto::tokens($this->texto('DIF_ABS_FAMILIAS'));
    }

    /**
     * Todas as chaves com o valor efetivo e a descricao (para gravar/exibir).
     *
     * @return array<string, array{valor: string|float, descricao: string}>
     */
    public function tabela(): array
    {
        $out = [];
        foreach (self::PADRAO as $k => [$padrao, $desc]) {
            $out[$k] = [
                'valor' => is_string($padrao) ? $this->texto($k) : $this->numero($k),
                'descricao' => $desc,
            ];
        }

        return $out;
    }

    /** @return array<string, scalar|null> */
    public function valoresInformados(): array
    {
        return $this->valores;
    }
}
