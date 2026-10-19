<?php

declare(strict_types=1);

namespace Inventario\Tests\Unit;

use Inventario\Domain\Base\TipoOD;
use Inventario\Domain\Regra\RegrasOD;
use PHPUnit\Framework\TestCase;

/** Premissas de sinal e valor por tipo de OD. */
final class RegrasODTest extends TestCase
{
    public function testClassificarOD(): void
    {
        self::assertSame(TipoOD::ODD, TipoOD::classificar('1234.D'));
        self::assertSame(TipoOD::ODI, TipoOD::classificar(' x.i '));
        self::assertSame(TipoOD::ODS, TipoOD::classificar('1234', 'S'));
        self::assertSame(TipoOD::ODM, TipoOD::classificar('1234', 'odm'));
        self::assertSame(TipoOD::NENHUM, TipoOD::classificar('1234', 'X'));
        self::assertSame(TipoOD::ODD, TipoOD::classificar('1234.D', 'I'), 'o sufixo vence a coluna TIPO PEP');
    }

    public function testSinalInvalido(): void
    {
        self::assertSame('', RegrasOD::sinalInvalido(TipoOD::ODD, -5, -100));
        self::assertNotSame('', RegrasOD::sinalInvalido(TipoOD::ODD, 5, -100));
        self::assertNotSame('', RegrasOD::sinalInvalido(TipoOD::ODD, -5, 100));
        self::assertSame('', RegrasOD::sinalInvalido(TipoOD::ODI, 5, 100));
        self::assertNotSame('', RegrasOD::sinalInvalido(TipoOD::ODS, 5, -100));
        self::assertSame('', RegrasOD::sinalInvalido(TipoOD::ODI, 0, 0), 'zero nao e negativo');
        self::assertSame('', RegrasOD::sinalInvalido(TipoOD::ODM, -5, -100), 'ODM fica fora');
    }

    public function testMensagensDeSinal(): void
    {
        self::assertSame('PEP ODD com valor e quantidade POSITIVOS (esperado negativo)', RegrasOD::sinalInvalido(TipoOD::ODD, 1, 1));
        self::assertSame('PEP ODD com quantidade POSITIVA (esperado negativo)', RegrasOD::sinalInvalido(TipoOD::ODD, 1, -1));
        self::assertSame('PEP ODI com valor NEGATIVO (esperado positivo)', RegrasOD::sinalInvalido(TipoOD::ODI, 1, -1));
        self::assertSame('PEP ODS com valor e quantidade NEGATIVOS (esperado positivo)', RegrasOD::sinalInvalido(TipoOD::ODS, -1, -1));
    }

    public function testValorBaixoODD(): void
    {
        self::assertSame('PEP ODD com valor R$ -150,00 abaixo do limite de R$ -100,00', RegrasOD::valorBaixoODD(TipoOD::ODD, -150, -100));
        self::assertSame('', RegrasOD::valorBaixoODD(TipoOD::ODD, -100, -100));
        self::assertSame('', RegrasOD::valorBaixoODD(TipoOD::ODD, -50, -100));
        self::assertSame('', RegrasOD::valorBaixoODD(TipoOD::ODI, -150, -100));
    }

    public function testNegativoEmODI(): void
    {
        self::assertTrue(RegrasOD::negativoEmODI(TipoOD::ODI, -10));
        self::assertFalse(RegrasOD::negativoEmODI(TipoOD::ODI, 0));
        self::assertFalse(RegrasOD::negativoEmODI(TipoOD::ODI, 150));
        self::assertFalse(RegrasOD::negativoEmODI(TipoOD::ODD, -10));
    }

    public function testSinaisOpostos(): void
    {
        self::assertSame('MAT LIB SAP (3) e MAT PRJ CAD (-3) com sinais opostos', RegrasOD::sinaisOpostos(3, -3));
        self::assertNotSame('', RegrasOD::sinaisOpostos(-3, 3));
        self::assertSame('', RegrasOD::sinaisOpostos(3, 3));
        self::assertSame('', RegrasOD::sinaisOpostos(0, -3), 'zero nao tem sinal');
    }
}
