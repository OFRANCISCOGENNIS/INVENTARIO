<?php

declare(strict_types=1);

namespace Inventario\Domain\Analise\SapPrj;

use Inventario\Domain\Base\BaseInventario;
use Inventario\Domain\Base\Coluna;
use Inventario\Domain\Base\LinhaBase;
use Inventario\Domain\Config\Parametros;
use Inventario\Domain\Equivalencia\CatalogoEquivalencias;
use Inventario\Domain\Preco\DiscrepanciaValor;
use Inventario\Domain\Regra\AvaliacaoLinha;
use Inventario\Domain\Regra\AvaliadorLinha;
use Inventario\Domain\Regra\RegraAderencia;
use Inventario\Domain\Regra\RegrasOD;
use Inventario\Domain\Support\Formatador;
use Inventario\Domain\Support\Numero;
use Inventario\Domain\Support\Texto;

/**
 * ANALISE SAP x PRJ (ProcessarSAPxPRJ do VBA).
 *
 * Passo 1: avalia cada linha e monta o veredito por PEP4; qualquer PEP4
 * reprovado reprova o PEP3 inteiro. Passo 2: escreve SIT MAT, APROVACAO e
 * MOTIVO de cada linha a partir do veredito.
 */
final class AnalisadorSapPrj
{
    private readonly AvaliadorLinha $avaliador;
    /** @var array<string, float> codigo => PU medio na ODI (valor excessivo em ODD) */
    private array $mediasOdi = [];

    public function __construct(
        private readonly Parametros $p,
        private readonly CatalogoEquivalencias $equivalencias,
    ) {
        $this->avaliador = new AvaliadorLinha($p);
    }

    public function analisar(BaseInventario $base): ResultadoSapPrj
    {
        $linhas = $base->linhas();
        $this->mediasOdi = DiscrepanciaValor::mediasOdi($base);

        // ---- pre-passe: UC zerado por obra (so devolve com TODOS os UC zerados)
        $p3nUC = [];
        $p3nUC0 = [];
        foreach ($linhas as $l) {
            if ($l->norm(Coluna::TIPO) !== 'UC') {
                continue;
            }
            $p3 = $l->norm(Coluna::PEP3);
            $p3nUC[$p3] = ($p3nUC[$p3] ?? 0) + 1;
            if (RegraAderencia::materialUCZerado('UC', $l->bruto(Coluna::MAT_LIB_SAP), $l->bruto(Coluna::MAT_PRJ_CAD))) {
                $p3nUC0[$p3] = ($p3nUC0[$p3] ?? 0) + 1;
            }
        }
        $todosZerados = static function (string $p3) use ($p3nUC, $p3nUC0): bool {
            $t = $p3nUC[$p3] ?? 0;

            return $t > 0 && ($p3nUC0[$p3] ?? 0) === $t;
        };

        // ---- pre-passe: grupos de equivalentes por PEP4
        /** @var array<string, GrupoEquivalentePep4> $grupos */
        $grupos = [];
        foreach ($linhas as $l) {
            $k = $this->equivalencias->chave($l);
            if ($k === '') {
                continue;
            }
            $g = $grupos[$k] ??= new GrupoEquivalentePep4();
            $g->lib += $l->num(Coluna::MAT_LIB_SAP);
            $g->prj += $l->num(Coluna::MAT_PRJ_CAD);
            $cod = $l->codigo();
            if (!array_key_exists($cod, $g->codigos)) {
                $g->codigos[$cod] = $l->texto(Coluna::DESC_MAT);
            }
        }

        // ---- passo 1: veredito por PEP4 / PEP3
        $aprova = [];
        $p4pep3 = [];
        $allP4 = [];
        $pep3RepFam = [];
        $p3RepLinha = [];
        $sumValor = 0.0;
        $sumNaoAder = 0.0;
        $temValor = $base->temColuna(Coluna::VALOR);
        foreach ($linhas as $l) {
            $pep4 = $l->norm(Coluna::PEP4);
            $pep3 = $l->norm(Coluna::PEP3);
            $famRaw = $l->texto(Coluna::FAMILIA);
            [$av] = $this->avaliar($l, $grupos, $todosZerados($pep3));

            if ($pep3 !== '') {
                $fBase = $famRaw !== '' ? $famRaw : '(sem familia)';
                if (RegrasOD::negativoEmODI($l->tipoOD(), $l->bruto(Coluna::VALOR))) {
                    $p3RepLinha[$pep3] = true;
                    self::registrarFamilia($pep3RepFam, $pep3, $fBase . ' (valor negativo em ODI)');
                }
                if (RegrasOD::sinaisOpostos($l->bruto(Coluna::MAT_LIB_SAP), $l->bruto(Coluna::MAT_PRJ_CAD)) !== '') {
                    $p3RepLinha[$pep3] = true;
                    self::registrarFamilia($pep3RepFam, $pep3, $fBase . ' (SAP x PRJ com sinais opostos)');
                }
            }
            if ($pep4 !== '') {
                $allP4[$pep4] = true;
                if (!array_key_exists($pep4, $p4pep3)) {
                    $p4pep3[$pep4] = $pep3;
                }
            }
            if ($pep4 !== '' && $av->avaliavel && !$av->caboIsento) {
                $aprova[$pep4] ??= true;
                if ($av->reprovaLinha) {
                    $aprova[$pep4] = false;
                    $lbl = $famRaw !== '' ? $famRaw : '(sem familia)';
                    if ($av->ucZerado) {
                        $lbl .= ' (valores zerados)';
                    }
                    if ($av->violaOD) {
                        $lbl .= ' (regra de OD)';
                    }
                    self::registrarFamilia($pep3RepFam, $pep3, $lbl);
                }
            }
            if ($temValor) {
                $vv = Numero::val0($l->bruto(Coluna::VALOR));
                $sumValor += $vv;
                if ($av->reprovaLinha) {
                    $sumNaoAder += abs($vv);
                }
            }
        }

        // rollup: qualquer PEP4 reprovado reprova o PEP3
        $pep3Rep = [];
        foreach ($aprova as $p4 => $ok) {
            $p3 = $p4pep3[(string) $p4];
            $pep3Rep[$p3] = ($pep3Rep[$p3] ?? false) || !$ok;
        }
        // regras de linha (ODI negativa, sinais opostos) reprovam mesmo sem UC
        foreach (array_keys($p3RepLinha) as $p3) {
            $pep3Rep[(string) $p3] = true;
        }
        $nAprov = 0;
        $nReprov = 0;
        foreach ($pep3Rep as $rep) {
            $rep ? $nReprov++ : $nAprov++;
        }

        // ---- passo 2: SIT MAT, APROVACAO e MOTIVO por linha
        $corte = $this->p->numero('CORTE_DIVERG_QTD');
        $saida = [];
        foreach ($linhas as $l) {
            $saida[] = $this->linhaSaida($l, $grupos, $todosZerados, $pep3Rep, $pep3RepFam, $corte);
        }

        return new ResultadoSapPrj($saida, count($allP4), $nAprov, $nReprov, $sumValor, $sumNaoAder);
    }

    /**
     * Avalia a linha, com os totais do grupo quando ha troca por equivalente.
     *
     * @param array<string, GrupoEquivalentePep4> $grupos
     * @return array{0: AvaliacaoLinha, 1: ?GrupoEquivalentePep4, 2: mixed, 3: mixed, 4: string}
     */
    private function avaliar(LinhaBase $l, array $grupos, bool $ucZeradoReprova): array
    {
        $lib = $l->bruto(Coluna::MAT_LIB_SAP);
        $prj = $l->bruto(Coluna::MAT_PRJ_CAD);
        $sit = $l->norm(Coluna::SIT_MAT);
        $k = $this->equivalencias->chave($l);
        $grupo = $k !== '' && $grupos[$k]->ativo() ? $grupos[$k] : null;
        $libAv = $lib;
        $prjAv = $prj;
        if ($grupo !== null) {
            $libAv = $grupo->lib;
            $prjAv = $grupo->prj;
            $sit = CatalogoEquivalencias::situacaoPorQuantidade($libAv, $prjAv);
        }
        $av = $this->avaliador->avaliar(
            $l->norm(Coluna::TIPO),
            $l->norm(Coluna::FAMILIA),
            $libAv,
            $prjAv,
            $sit,
            $ucZeradoReprova,
            $l->tipoOD(),
            $l->bruto(Coluna::VALOR) ?? 0,
            $lib,
            $prj,
            $this->mediasOdi[$l->codigo()] ?? null,
        );
        if ($grupo !== null) {
            $av->ajustarEquivalencia(CatalogoEquivalencias::situacaoPorQuantidade($libAv, $prjAv) === 'ADERENTE');
        }

        return [$av, $grupo, $libAv, $prjAv, $sit];
    }

    /**
     * @param array<string, GrupoEquivalentePep4> $grupos
     * @param array<string, bool> $pep3Rep
     * @param array<string, string> $pep3RepFam
     */
    private function linhaSaida(
        LinhaBase $l,
        array $grupos,
        callable $todosZerados,
        array $pep3Rep,
        array $pep3RepFam,
        float $corte,
    ): LinhaAnalise {
        $tipo = $l->norm(Coluna::TIPO);
        $fam = $l->norm(Coluna::FAMILIA);
        $p3 = $l->norm(Coluna::PEP3);
        $rawSit = $l->texto(Coluna::SIT_MAT);
        [$av, $grupo, $lib, $prj, $sitR] = $this->avaliar($l, $grupos, $todosZerados($p3));

        if ($av->ucZerado || $av->violaOD) {
            $sitTexto = 'NAO ADERENTE';
        } elseif ($grupo !== null || $av->recalculaSit) {
            $sitTexto = $av->ehAderente ? 'ADERENTE' : 'NAO ADERENTE';
        } else {
            $sitTexto = $rawSit;
        }

        $familiasRep = $pep3RepFam[$p3] ?? '';
        $p3Reprovado = array_key_exists($p3, $pep3Rep) && $pep3Rep[$p3];
        $libTxt = Formatador::bruto($lib);
        $prjTxt = Formatador::bruto($prj);
        if ($p3Reprovado) {
            $aprovacao = Aprovacao::REPROVADO;
            if ($av->ucZerado) {
                $motivo = 'Obra com TODOS os materiais UC zerados (MAT LIB SAP=0 e MAT PRJ CAD=0) - devolvida';
            } elseif ($av->violaOD) {
                $motivo = 'Este item nao aderente: ' . $av->motivoOD;
            } elseif ($tipo === 'UC' && !$av->ehAderente && $sitR !== 'NULO') {
                $motivo = "Este item (UC) nao aderente: SAP={$libTxt} / PRJ={$prjTxt}" . Formatador::diferenca($lib, $prj);
            } elseif ($tipo === 'COM' && $this->avaliador->aderencia()->ehComCritico($fam) && !$av->ehAderente && $sitR !== 'NULO') {
                $motivo = "Este item (COM critico - {$fam}) nao aderente: SAP={$libTxt} / PRJ={$prjTxt}" . Formatador::diferenca($lib, $prj);
            } elseif ($av->caboIsento) {
                $motivo = 'Cabo isolado arrastado | Familia reprovada: ' . $familiasRep;
            } else {
                $motivo = 'Arrastado pela reprovacao do PEP3 | Familia reprovada: ' . $familiasRep;
            }
        } elseif ($av->caboIsento) {
            $aprovacao = Aprovacao::APROVADO;
            $motivo = 'Cabo isolado < ' . Formatador::numero($this->p->caboIsoladoMaxM(), 1) . 'm - isento de UC';
        } elseif (!array_key_exists($p3, $pep3Rep)) {
            $aprovacao = Aprovacao::SEM_UC;
            $motivo = 'PEP3 sem UC nem COM critico (CH FUS/PARA RAIO) p/ avaliar';
        } else {
            $aprovacao = Aprovacao::APROVADO;
            $motivo = RegraAderencia::materialUCZerado($tipo, $lib, $prj)
                ? 'Item UC zerado ignorado (obra nao esta toda zerada) | Demais itens aderentes'
                : 'Todos os itens avaliados do PEP3 aderentes';
        }
        if ($av->violaOD && !$p3Reprovado) {
            $motivo .= ' | Item NAO ADERENTE: ' . $av->motivoOD;
        }
        if ($grupo !== null) {
            $motivo .= ' | Material equivalente: ' . CatalogoEquivalencias::texto($grupo->codigos)
                . " | total do grupo no PEP4 SAP={$libTxt} / PRJ={$prjTxt}"
                . ($av->ehAderente ? '' : Formatador::diferenca($lib, $prj));
        }

        $libLinha = $l->num(Coluna::MAT_LIB_SAP);
        $prjLinha = $l->num(Coluna::MAT_PRJ_CAD);

        return new LinhaAnalise(
            $l,
            $sitTexto,
            $aprovacao,
            $motivo,
            $p3Reprovado ? 'Devolvido por divergencia de ' . $familiasRep : '',
            $prjLinha > $corte || $libLinha < -$corte,
        );
    }

    /** Acrescenta a familia culpada a lista de devolucao do PEP3 (sem repetir). */
    private static function registrarFamilia(array &$d, string $p3, string $lbl): void
    {
        if (!array_key_exists($p3, $d)) {
            $d[$p3] = $lbl;
        } elseif (!str_contains($d[$p3], $lbl)) {
            $d[$p3] .= ', ' . $lbl;
        }
    }

    /** Texto auxiliar para testes/diagnostico. */
    public static function familiaDevolucao(string $motivoDevolucao): string
    {
        $pre = 'Devolvido por divergencia de ';

        return str_starts_with($motivoDevolucao, $pre) ? substr($motivoDevolucao, strlen($pre)) : $motivoDevolucao;
    }
}
