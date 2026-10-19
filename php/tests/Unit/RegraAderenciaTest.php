<?php

declare(strict_types=1);

namespace Inventario\Tests\Unit;

use Inventario\Domain\Config\Parametros;
use Inventario\Domain\Regra\RegraAderencia;
use Inventario\Domain\Support\Texto;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Testes portados de TestarLogicaInventario (VBA): aderencia. */
final class RegraAderenciaTest extends TestCase
{
    private RegraAderencia $r;

    protected function setUp(): void
    {
        $this->r = new RegraAderencia(Parametros::padrao());
    }

    public function testComCritico(): void
    {
        self::assertTrue($this->r->ehComCritico(Texto::norm('CH FUS')));
        self::assertTrue($this->r->ehComCritico(Texto::norm('PARA RAIO MT')));
        self::assertTrue($this->r->ehComCritico(Texto::norm('PARA-RAIO BT')));
        self::assertFalse($this->r->ehComCritico(Texto::norm('POSTE')));
    }

    public function testComCriticoConfiguravel(): void
    {
        $r = new RegraAderencia(Parametros::padrao()->com('COM_CRITICO', 'TRAFO'));
        self::assertTrue($r->ehComCritico('TRAFO'));
        self::assertFalse($r->ehComCritico('CH FUS'));
    }

    public function testCaboIsento(): void
    {
        self::assertTrue($this->r->caboIsento('CABO ISOLADO', 10));
        self::assertFalse($this->r->caboIsento('CABO ISOLADO', 20));
        self::assertFalse($this->r->caboIsento('CABO ISOLADO', 'abc'));
    }

    /** @return iterable<string, array{string, mixed, mixed, string, bool}> */
    public static function casosAderencia(): iterable
    {
        yield 'COND 100/100' => ['COND', 100, 100, '', true];
        yield 'COND 100/105' => ['COND', 100, 105, '', true];
        yield 'COND 100/50' => ['COND', 100, 50, '', false];
        yield 'COND NU 14/10 (dif 4) resgata fora dos 10%' => ['COND NU', 14, 10, '', true];
        yield 'COND (nao NU) 14/10' => ['COND', 14, 10, '', false];
        yield 'COND NU 15/10 (dif 5)' => ['COND NU', 15, 10, '', true];
        yield 'COND NU 16/10 (dif 6)' => ['COND NU', 16, 10, '', false];
        yield 'COND NU 3/0' => ['COND NU', 3, 0, '', true];
        yield 'COND COBRE 2/0' => ['COND COBRE', 2, 0, '', true];
        yield 'CABO COBRE 12/0' => ['CABO COBRE', 12, 0, '', true];
        yield 'CABO ISOLADO 15/0' => ['CABO ISOLADO', 15, 0, '', true];
        yield 'COND COBRE 16/0' => ['COND COBRE', 16, 0, '', false];
        yield 'COND COBRE 120/100 (dif 20)' => ['COND COBRE', 120, 100, '', false];
        yield 'CABO ACO 12/0 (fora do set)' => ['CABO ACO', 12, 0, '', false];
        yield 'PRJ 0 e SAP 0' => ['COND', 0, 0, '', true];
        yield 'RAMAL 10/10,5' => ['RAMAL', 10, '10,5', '', true];
        yield 'UC pela SIT MAT' => ['PARAFUSO', 'x', 'y', 'ADERENTE', true];
        yield 'UC SIT MAT nao aderente' => ['PARAFUSO', 1, 1, 'NAO ADERENTE', false];
        yield 'COND com texto cai na SIT MAT' => ['COND', 'abc', 1, 'ADERENTE', true];
    }

    #[DataProvider('casosAderencia')]
    public function testEhAderente(string $fam, mixed $lib, mixed $prj, string $sit, bool $esperado): void
    {
        self::assertSame($esperado, $this->r->ehAderente($fam, $lib, $prj, $sit));
    }

    public function testCondNuToleraPontuacao(): void
    {
        self::assertTrue(RegraAderencia::ehCondNu(Texto::norm('COND-NU')));
        self::assertTrue(RegraAderencia::ehCondNu(Texto::norm('COND. NU')));
        self::assertFalse(RegraAderencia::ehCondNu('COND PROT'));
    }

    public function testMaterialUcZerado(): void
    {
        self::assertTrue(RegraAderencia::materialUCZerado('UC', 0, 0));
        self::assertTrue(RegraAderencia::materialUCZerado('UC', null, null));
        self::assertTrue(RegraAderencia::materialUCZerado('UC', '', ''));
        self::assertFalse(RegraAderencia::materialUCZerado('UC', 0, 10));
        self::assertFalse(RegraAderencia::materialUCZerado('COM', 0, 0));
    }

    public function testToleranciaConfiguravel(): void
    {
        $r = new RegraAderencia(Parametros::padrao()->com('TOL_ADERENCIA_PCT', '0,2'));
        self::assertTrue($r->ehAderente('COND', 100, 85, ''));
        self::assertFalse($this->r->ehAderente('COND', 100, 85, ''));
    }
}
