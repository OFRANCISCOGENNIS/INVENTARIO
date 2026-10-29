<?php

declare(strict_types=1);

namespace Inventario\Tests\Unit;

use Inventario\Domain\Base\BaseInventario;
use Inventario\Domain\Equivalencia\CatalogoEquivalencias;
use Inventario\Domain\Equivalencia\ItemEquivalencia;
use Inventario\Domain\Equivalencia\ListaPadraoEquivalencias;
use PHPUnit\Framework\TestCase;

final class EquivalenciaTest extends TestCase
{
    public function testSituacaoPorQuantidadeSemTolerancia(): void
    {
        self::assertSame('NAO ADERENTE', CatalogoEquivalencias::situacaoPorQuantidade(0 + 3, 9 + 0));
        self::assertSame('ADERENTE', CatalogoEquivalencias::situacaoPorQuantidade(3, 3));
        self::assertSame('NAO ADERENTE', CatalogoEquivalencias::situacaoPorQuantidade(9, 10));
    }

    public function testTextoMantemOsDoisNomes(): void
    {
        self::assertSame('105000058 CH SEC 1P = 105000029 CH SEC BYPAS',
            CatalogoEquivalencias::texto(['105000058' => 'CH SEC 1P', '105000029' => 'CH SEC BYPAS']));
    }

    public function testRaizGrupo(): void
    {
        $pai = ['2' => '2', '11' => '2', '5' => '5'];
        self::assertSame('2', CatalogoEquivalencias::raiz($pai, '11'));
        self::assertSame('5', CatalogoEquivalencias::raiz($pai, '5'));
    }

    public function testCodigoEmDoisGruposUneOsGrupos(): void
    {
        $c = new CatalogoEquivalencias([
            new ItemEquivalencia('102210080', '2'),
            new ItemEquivalencia('102310194', '2'),
            new ItemEquivalencia('102310194', '11'),
            new ItemEquivalencia('102210093', '11'),
        ]);
        self::assertSame($c->grupo('102210080'), $c->grupo('102210093'));
        self::assertSame(1, $c->totalGrupos());
        self::assertNotEmpty($c->log());
    }

    public function testListaPadraoSemGrupos10e28(): void
    {
        foreach (ListaPadraoEquivalencias::itens() as $i) {
            self::assertNotContains($i->grupo, ['10', '28']);
            self::assertNotSame('', $i->codigo);
        }
        $c = new CatalogoEquivalencias(ListaPadraoEquivalencias::itens());
        self::assertSame($c->grupo('105300003'), $c->grupo('105310016'), 'grupo 31 une com o 35 via 105300005');
        self::assertSame($c->grupo('105310001'), $c->grupo('105310005'), '6 + 21 + 23');
    }

    public function testGruposDaVersao5(): void
    {
        $c = new CatalogoEquivalencias(ListaPadraoEquivalencias::itens());
        self::assertSame($c->grupo('102110178'), $c->grupo('102110181'));
        self::assertSame($c->grupo('133000127'), $c->grupo('133000041'));
        self::assertSame($c->grupo('102100064'), $c->grupo('102100373'));
        self::assertSame($c->grupo('122120033'), $c->grupo('122120006'));
        // 122120026 e 122120003 ja estavam no grupo 22: 22 + 37 + 41 formam um grupo so
        self::assertSame($c->grupo('122120026'), $c->grupo('122120028'));
        self::assertSame($c->grupo('122120003'), $c->grupo('122120028'));
        self::assertNotSame($c->grupo('122120033'), $c->grupo('122120026'));
    }

    public function testChaveDaLinha(): void
    {
        $c = new CatalogoEquivalencias([new ItemEquivalencia('105010022', '3'), new ItemEquivalencia('105010003', '3')]);
        $b = BaseInventario::deTabela('t', ['PEP4NIVEL', 'COD MAT'], [['A.01.I', 105010022.0], ['A.01.I', '999'], ['', '105010003']]);
        [$l1, $l2, $l3] = $b->linhas();
        self::assertSame('A01I|3', $c->chave($l1));
        self::assertSame('', $c->chave($l2));
        self::assertSame('', $c->chave($l3), 'sem PEP4');
    }
}
