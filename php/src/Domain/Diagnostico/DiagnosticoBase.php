<?php

declare(strict_types=1);

namespace Inventario\Domain\Diagnostico;

use DateTimeImmutable;
use Inventario\Domain\Base\BaseInventario;
use Inventario\Domain\Base\Coluna;
use Inventario\Domain\Config\Parametros;
use Inventario\Domain\Preco\SituacaoFontePrecos;
use Inventario\Domain\Preco\TabelaPrecos;
use Inventario\Domain\Regra\RegraAderencia;
use Inventario\Domain\Regra\RegrasOD;
use Inventario\Domain\Support\Formatador;
use Inventario\Domain\Support\Numero;

/**
 * Mede a qualidade dos DADOS antes de confiar na analise (garbage in,
 * garbage out) - ProcessarDiagnosticoBase do VBA.
 */
final class DiagnosticoBase
{
    public function __construct(private readonly Parametros $p)
    {
    }

    public function diagnosticar(BaseInventario $base, ?TabelaPrecos $precos, ?DateTimeImmutable $agora = null): ResultadoDiagnostico
    {
        $agora ??= new DateTimeImmutable();
        $n = count($base);
        $p3 = [];
        $p4s = [];
        $dup = [];
        $famVazia = $tipoVazio = $codVazio = $qtdInval = $nUC = $nUC0 = $nDup = $sinalInv = 0;
        $temFam = $base->temColuna(Coluna::FAMILIA);
        $temTipo = $base->temColuna(Coluna::TIPO);
        $temCod = $base->temColuna(Coluna::COD_MAT);

        foreach ($base as $l) {
            if (($s = $l->texto(Coluna::PEP3)) !== '') {
                $p3[$s] = true;
            }
            $p4 = $l->texto(Coluna::PEP4);
            if ($p4 !== '') {
                $p4s[$p4] = true;
            }
            if ($temFam && $l->texto(Coluna::FAMILIA) === '') {
                $famVazia++;
            }
            $tp = $l->norm(Coluna::TIPO);
            if ($temTipo && $tp === '') {
                $tipoVazio++;
            }
            $cod = $l->texto(Coluna::COD_MAT);
            if ($temCod && $cod === '') {
                $codVazio++;
            }
            foreach ([Coluna::MAT_LIB_SAP, Coluna::MAT_PRJ_CAD] as $c) {
                if ($base->temColuna($c) && $l->texto($c) !== '' && !Numero::ehNumerico($l->bruto($c))) {
                    $qtdInval++;
                }
            }
            if ($tp === 'UC') {
                $nUC++;
                if (RegraAderencia::materialUCZerado('UC', $l->bruto(Coluna::MAT_LIB_SAP), $l->bruto(Coluna::MAT_PRJ_CAD))) {
                    $nUC0++;
                }
            }
            if ($p4 !== '' && $temCod && $cod !== '') {
                $k = $p4 . '|' . $cod;
                $dup[$k] = ($dup[$k] ?? 0) + 1;
                if ($dup[$k] === 2) {
                    $nDup++;
                }
            }
            if ($p4 !== '' && RegrasOD::sinalInvalido($l->tipoOD(), $l->bruto(Coluna::MAT_LIB_SAP), $l->bruto(Coluna::VALOR)) !== '') {
                $sinalInv++;
            }
        }

        $v = [];
        $v[] = new Verificacao('Volume da base',
            Formatador::inteiro($n) . ' linhas | ' . Formatador::inteiro(count($p3)) . ' obras (PEP3) | '
                . Formatador::inteiro(count($p4s)) . ' PEP4',
            StatusVerificacao::OK, 'Confira se o volume bate com o esperado da extracao.');
        $v[] = new Verificacao('FAMILIA em branco',
            Formatador::inteiro($famVazia) . ' linha(s)' . self::pctDe($famVazia, $n),
            StatusVerificacao::porPercentual($famVazia, $n, 0.02, 0.1),
            'Sem FAMILIA a linha fica fora das regras de aderencia. Reclassifique na origem.');
        $v[] = new Verificacao('TIPO em branco (UC/COM)',
            Formatador::inteiro($tipoVazio) . ' linha(s)' . self::pctDe($tipoVazio, $n),
            StatusVerificacao::porPercentual($tipoVazio, $n, 0.02, 0.1),
            'Sem TIPO o item nao entra no veredito APROVADO/REPROVADO.');
        $v[] = new Verificacao('COD MAT em branco',
            Formatador::inteiro($codVazio) . ' linha(s)',
            $codVazio > 0 ? StatusVerificacao::ATENCAO : StatusVerificacao::OK,
            'Sem codigo nao ha cruzamento NT.006 nem faixa de preco.');
        $v[] = new Verificacao('Quantidade com texto invalido',
            Formatador::inteiro($qtdInval) . ' celula(s)',
            $qtdInval > 0 ? StatusVerificacao::CRITICO : StatusVerificacao::OK,
            'Texto em coluna numerica vira 0 na analise e distorce a aderencia. Corrija na base.');
        if ($nUC > 0) {
            $v[] = new Verificacao('Materiais UC com valores zerados',
                Formatador::inteiro($nUC0) . ' de ' . Formatador::inteiro($nUC) . ' UC' . self::pctDe($nUC0, $nUC),
                $nUC0 === $nUC ? StatusVerificacao::CRITICO
                    : ($nUC0 / $nUC > 0.2 ? StatusVerificacao::ATENCAO : StatusVerificacao::OK),
                'Zerado isolado e ignorado; obra 100% zerada e devolvida. Muitos zerados = extracao incompleta.');
        }
        $v[] = new Verificacao('Material duplicado na mesma obra',
            Formatador::inteiro($nDup) . ' chave(s) PEP4+material repetida(s)',
            $nDup > 0 ? StatusVerificacao::ATENCAO : StatusVerificacao::OK,
            'Duplicidade soma quantidades 2x e pode gerar falso NAO ADERENTE. Consolide os lancamentos.');
        $v[] = new Verificacao('Sinal invertido por tipo de OD',
            Formatador::inteiro($sinalInv) . ' linha(s)',
            $sinalInv > 0 ? StatusVerificacao::ATENCAO : StatusVerificacao::OK,
            'ODD exige valor e quantidade negativos; ODI/ODS, positivos. Essas linhas viram NAO ADERENTE.');

        $faltam = [];
        foreach ([Coluna::TIPO_PEP, Coluna::UND, Coluna::VALOR] as $c) {
            if (!$base->temColuna($c)) {
                $faltam[] = $c;
            }
        }
        $v[] = new Verificacao('Colunas opcionais presentes',
            $faltam === [] ? 'Todas presentes' : 'Faltam: ' . implode('; ', $faltam),
            $faltam === [] ? StatusVerificacao::OK : StatusVerificacao::ATENCAO,
            'Colunas opcionais enriquecem alertas (ODI/ODM, unidade, valores em R$).');

        $v[] = $this->verificarPrecos($precos, $agora);

        return new ResultadoDiagnostico($v);
    }

    private function verificarPrecos(?TabelaPrecos $precos, DateTimeImmutable $agora): Verificacao
    {
        $maxDias = $this->p->numero('PRECOS_MAX_DIAS');
        if ($precos === null) {
            [$res, $st] = ['Nao consultada nesta execucao', StatusVerificacao::OK];
        } elseif ($precos->situacao !== SituacaoFontePrecos::OK) {
            [$res, $st] = [$precos->descricao(), StatusVerificacao::ATENCAO];
        } elseif ($precos->dataArquivo === null) {
            [$res, $st] = ['Fonte interna (sem data para conferir)', StatusVerificacao::OK];
        } else {
            $dias = (int) $precos->dataArquivo->setTime(0, 0)->diff($agora->setTime(0, 0))->format('%r%a');
            $res = 'Arquivo alterado em ' . $precos->dataArquivo->format('d/m/Y') . " ({$dias} dias)";
            $st = $dias > $maxDias ? StatusVerificacao::ATENCAO : StatusVerificacao::OK;
        }

        return new Verificacao('Base de precos atualizada', $res, $st,
            'Faixas MIN/MAX antigas distorcem PRECO UNITARIO e os alertas de preco. Atualize a '
            . 'BASE DE PRECOS (limite: ' . Formatador::geral($maxDias) . ' dias - CONFIG PRECOS_MAX_DIAS).');
    }

    /** " (x,x%)" do percentual, ou vazio com total 0. */
    private static function pctDe(int $qtd, int $total): string
    {
        return $total > 0 ? ' (' . Formatador::pct($qtd / $total, 1) . ')' : '';
    }
}
