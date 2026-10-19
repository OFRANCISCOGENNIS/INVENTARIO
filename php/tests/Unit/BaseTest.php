<?php

declare(strict_types=1);

namespace Inventario\Tests\Unit;

use Inventario\Application\Carga\BlocoBase;
use Inventario\Application\Carga\CarregadorBases;
use Inventario\Application\Carga\Tabela;
use Inventario\Domain\Base\BaseInventario;
use Inventario\Domain\Base\Coluna;
use Inventario\Domain\Base\MapeadorCabecalho;
use Inventario\Domain\Support\Texto;
use PHPUnit\Framework\TestCase;

/** Cabecalhos, aliases e carga de bases. */
final class BaseTest extends TestCase
{
    public function testAliasesDoLayoutNovo(): void
    {
        $m = MapeadorCabecalho::aplicarAliases([
            Texto::norm('MATERIAL') => 5, Texto::norm('ATENDIDO SAP') => 9,
            Texto::norm('TOMBADO PROJ') => 10, Texto::norm('SITUACAO_DO_MATERIAL') => 13,
        ]);
        self::assertSame(5, $m['COD MAT']);
        self::assertSame(9, $m['MAT LIB SAP']);
        self::assertSame(10, $m['MAT PRJ CAD']);
        self::assertSame(13, $m['SIT MAT']);
    }

    public function testComOsDoisNomesValeOCanonico(): void
    {
        $m = MapeadorCabecalho::aplicarAliases(['MAT LIB SAP' => 3, 'ATENDIDO SAP' => 9]);
        self::assertSame(3, $m['MAT LIB SAP']);
    }

    public function testCanonico(): void
    {
        self::assertSame('MAT LIB SAP', MapeadorCabecalho::canonico(Texto::norm('ATENDIDO SAP')));
        self::assertSame('FAMILIA', MapeadorCabecalho::canonico('FAMILIA'));
        $m = MapeadorCabecalho::mapear(['MATERIAL', 'TOMBADO PROJ']);
        self::assertSame(0, $m['COD MAT']);
        self::assertSame(1, $m['MAT PRJ CAD']);
    }

    public function testFaltantes(): void
    {
        $b = BaseInventario::deTabela('t', ['PEP3NIVEL', 'ATENDIDO SAP'], [['A', 1]]);
        $f = $b->colunasFaltantes();
        self::assertContains('MAT PRJ CAD (ou TOMBADO PROJ)', $f);
        self::assertNotContains('MAT LIB SAP (ou ATENDIDO SAP)', $f);
    }

    public function testMapearBlocoCasaNomesNovosEListaAusente(): void
    {
        [$src, $faltam] = CarregadorBases::mapearBloco(['MATERIAL', 'ATENDIDO SAP'], ['COD MAT', 'MAT LIB SAP', 'FAMILIA']);
        self::assertSame([0, 1, null], $src);
        self::assertSame(['FAMILIA'], $faltam);
    }

    public function testJuntarCasaColunasPorNomeEMarcaOrigem(): void
    {
        $a = new BlocoBase('a.xlsx', 'a', new Tabela('BASE', ['COD MAT', 'MAT LIB SAP'], [['1', 3]]));
        $b = new BlocoBase('b.csv', 'b', new Tabela('b', ['ATENDIDO SAP', 'MATERIAL'], [[7, '2']]));
        $base = CarregadorBases::juntar([$a, $b]);
        [$l1, $l2] = $base->linhas();
        self::assertSame('2', $l2->texto(Coluna::COD_MAT));
        self::assertSame(7.0, $l2->num(Coluna::MAT_LIB_SAP));
        self::assertSame('a.xlsx', $l1->texto(Coluna::ARQUIVO_ORIGEM));
        self::assertSame('b.csv', $l2->texto(Coluna::ARQUIVO_ORIGEM));
    }

    public function testAcharBaseIgnoraAbasGeradas(): void
    {
        $abas = [
            'ANALISE SAP x PRJ' => new Tabela('ANALISE SAP x PRJ', ['MAT LIB SAP', 'MAT PRJ CAD'], [[1, 1]]),
            'Planilha1' => new Tabela('Planilha1', ['ATENDIDO SAP', 'TOMBADO PROJ'], [[1, 1]]),
        ];
        self::assertSame('Planilha1', CarregadorBases::acharBase($abas)?->aba);
    }

    public function testLinhasVaziasSaoIgnoradas(): void
    {
        $b = BaseInventario::deTabela('t', ['PEP3NIVEL'], [['A'], [null], [''], ['B']]);
        self::assertCount(2, $b);
    }
}
