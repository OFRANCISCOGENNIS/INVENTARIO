<?php

declare(strict_types=1);

namespace Inventario\Domain\Analise\Racionalizacao;

use Inventario\Domain\Base\BaseInventario;
use Inventario\Domain\Base\Coluna;
use Inventario\Domain\Nt006\ClassificadorDescricao;
use Inventario\Domain\Nt006\MapaNT006;
use Inventario\Domain\Nt006\MaterialNT006;
use Inventario\Domain\Support\Formatador;
use Inventario\Domain\Support\Numero;
use Inventario\Domain\Support\Texto;

/**
 * RACIONALIZACAO COM: o que veio na obra x o previsto pela NT.006
 * (ProcessarCOMInventario do VBA).
 *
 * Os COM sao somados por PEP4 + codigo. Ancoras (cruzeta, haste, gancho...)
 * sao somadas por PEP4 + familia; cada dependente tem a faixa prevista =
 * quantidade da ancora x razao min/max. Sem ancora no PEP4, usa o agregado
 * do PEP3 (estruturas dividem materiais entre PEP4 irmaos).
 */
final class RacionalizadorCom
{
    /** Unidades continuas: a faixa prevista mantem decimais. */
    private const UNIDADES_CONTINUAS = ['M', 'MT', 'MTS', 'KM', 'KG', 'G', 'GR', 'T', 'TON', 'L', 'LT', 'M2', 'M3'];

    public static function unidadeInteira(string $und): bool
    {
        return !in_array(Texto::norm($und), self::UNIDADES_CONTINUAS, true);
    }

    public static function referencia(string $codigo, string $descricao): ?MaterialNT006
    {
        return MapaNT006::buscar($codigo) ?? ClassificadorDescricao::classificar(Texto::norm($descricao));
    }

    /** @return list<LinhaRacionalizacao> */
    public function racionalizar(BaseInventario $base): array
    {
        $qtd = [];
        $info = [];
        foreach ($base as $l) {
            if ($l->norm(Coluna::TIPO) !== 'COM') {
                continue;
            }
            $pep4 = $l->texto(Coluna::PEP4);
            $cod = $l->codigo();
            $k = $pep4 . '|' . $cod;
            $q = Numero::converter($l->bruto(Coluna::MAT_LIB_SAP)) ?? 0.0;
            if (array_key_exists($k, $qtd)) {
                $qtd[$k] += $q;
            } else {
                $qtd[$k] = $q;
                $info[$k] = [
                    'pep4' => $pep4,
                    'pep3' => $l->texto(Coluna::PEP3),
                    'cod' => $cod,
                    'desc' => $l->texto(Coluna::DESC_MAT),
                    'und' => $l->texto(Coluna::UND),
                ];
            }
        }

        // ancoras por PEP4 e por PEP3
        $ancoras = [];
        $ancorasP3 = [];
        $ancoraInfo = [];
        $refs = [];
        foreach ($qtd as $k => $q) {
            $k = (string) $k;
            $i = $info[$k];
            $ref = $refs[$k] = self::referencia($i['cod'], $i['desc']);
            if ($ref === null || !$ref->ancora) {
                continue;
            }
            $aKey = $i['pep4'] . '|' . $ref->familia;
            if (array_key_exists($aKey, $ancoras)) {
                $ancoras[$aKey] += $q;
            } else {
                $ancoras[$aKey] = $q;
                $ancoraInfo[$aKey] = $i['cod'] . ' - ' . $i['desc'];
            }
            $aKey3 = $i['pep3'] . '|' . $ref->familia;
            $ancorasP3[$aKey3] = ($ancorasP3[$aKey3] ?? 0.0) + $q;
        }

        $chaves = array_map('strval', array_keys($qtd));
        sort($chaves, SORT_STRING);

        $out = [];
        foreach ($chaves as $k) {
            $out[] = $this->linha($info[$k], $qtd[$k], $refs[$k], $ancoras, $ancorasP3, $ancoraInfo);
        }

        return $out;
    }

    /**
     * @param array{pep4: string, pep3: string, cod: string, desc: string, und: string} $i
     * @param array<string, float> $ancoras
     * @param array<string, float> $ancorasP3
     * @param array<string, string> $ancoraInfo
     */
    private function linha(array $i, float $q, ?MaterialNT006 $ref, array $ancoras, array $ancorasP3, array $ancoraInfo): LinhaRacionalizacao
    {
        $status = StatusRacionalizacao::SEM_REFERENCIA;
        $familia = 'SEM REFERENCIA';
        $ancoraFam = '';
        $qtdAnc = 0.0;
        $min = 0.0;
        $max = 0.0;
        $nivel3 = false;

        if ($ref !== null) {
            $familia = $ref->familia;
            $ancoraFam = $ref->ancoraDependente;
            if ($ref->ancora) {
                $status = $q < 0 ? StatusRacionalizacao::ESTORNO
                    : ($q == 0.0 ? StatusRacionalizacao::QTD_ZERO : StatusRacionalizacao::ANCORA);
            } else {
                $qtdAnc = $ancoras[$i['pep4'] . '|' . $ancoraFam] ?? 0.0;
                if ($qtdAnc <= 0) {
                    $p3 = $ancorasP3[$i['pep3'] . '|' . $ancoraFam] ?? 0.0;
                    if ($p3 > 0) {
                        $qtdAnc = $p3;
                        $nivel3 = true;
                    }
                }
                if ($qtdAnc > 0) {
                    $min = $qtdAnc * $ref->razaoMin;
                    $max = $qtdAnc * $ref->razaoMax;
                    if (self::unidadeInteira($i['und'])) {
                        $min = Numero::int($min);
                        $max = Numero::teto($max);
                    }
                    $status = match (true) {
                        $q < 0 => StatusRacionalizacao::ESTORNO,
                        $q < $min => StatusRacionalizacao::INSUFICIENTE,
                        $max > 0 && $q >= $max * 2 => StatusRacionalizacao::EXCESSO_EXAGERADO,
                        $q > $max => StatusRacionalizacao::EXCESSO,
                        default => StatusRacionalizacao::OK,
                    };
                } else {
                    $status = StatusRacionalizacao::SEM_ANCORA;
                }
            }
        }

        $previsto = $max > 0 ? Formatador::numero($min) . ' a ' . Formatador::numero($max) : '-';
        $obs = match ($status) {
            StatusRacionalizacao::ANCORA => 'Material de referencia (ancora)',
            StatusRacionalizacao::OK => 'Dentro do previsto',
            StatusRacionalizacao::INSUFICIENTE => 'Veio MENOS que o previsto (faltaram ' . Formatador::numero($min - $q) . ')',
            StatusRacionalizacao::EXCESSO => 'Veio MAIS que o previsto (excedeu ' . Formatador::numero($q - $max) . ')',
            StatusRacionalizacao::EXCESSO_EXAGERADO => 'Veio MUITO acima do previsto (' . number_format($q / $max, 1, ',', '.') . 'x o maximo)',
            StatusRacionalizacao::SEM_ANCORA => 'Sem ' . $ancoraFam . ' no PEP para comparar',
            StatusRacionalizacao::QTD_ZERO => 'Quantidade zero na obra',
            StatusRacionalizacao::ESTORNO => 'Estorno sem entrada correspondente',
            StatusRacionalizacao::SEM_REFERENCIA => 'Material fora da NT.006',
        };
        if ($nivel3) {
            $obs .= ' [ancora agregada no PEP3]';
        }
        if ($ref !== null && $ref->porDescricao()) {
            $obs .= ' [classif. por descricao]';
        }

        $ligado = '-';
        if ($ref !== null) {
            if ($ref->ancora) {
                $ligado = '(e a propria referencia)';
            } elseif ($ancoraFam !== '') {
                $aK = $i['pep4'] . '|' . $ancoraFam;
                if (array_key_exists($aK, $ancoras)) {
                    $ligado = ($ancoraInfo[$aK] ?? $ancoraFam) . " ({$ancoraFam}, qtd " . Formatador::numero($ancoras[$aK]) . ')';
                } elseif ($nivel3) {
                    $ligado = "{$ancoraFam} (agregada no PEP3, qtd " . Formatador::numero($qtdAnc) . ')';
                } else {
                    $ligado = "{$ancoraFam} (AUSENTE no PEP)";
                }
            }
        }

        return new LinhaRacionalizacao(
            $i['pep4'],
            $i['pep3'],
            $i['cod'],
            $i['desc'],
            $familia,
            $ligado,
            $q,
            $previsto,
            $status,
            $obs,
        );
    }
}
