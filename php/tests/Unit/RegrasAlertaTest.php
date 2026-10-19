<?php

declare(strict_types=1);

namespace Inventario\Tests\Unit;

use Inventario\Domain\Alerta\LinhaAlerta;
use Inventario\Domain\Alerta\Regra\LacreMedidor;
use Inventario\Domain\Alerta\Regra\MedidorEmOdi;
use Inventario\Domain\Alerta\Regra\PosteOdiPuBaixo;
use Inventario\Domain\Alerta\Regra\PuBaixoEmOdi;
use Inventario\Domain\Alerta\Regra\PuSimbolicoCabo;
use Inventario\Domain\Alerta\Regra\UcPrecoReferencia;
use Inventario\Domain\Base\TipoOD;
use Inventario\Domain\Preco\PrecosReferencia;
use Inventario\Domain\Support\Texto;
use PHPUnit\Framework\TestCase;

final class RegrasAlertaTest extends TestCase
{
    public function testPuBaixoEmOdi(): void
    {
        self::assertTrue(PuBaixoEmOdi::aplica(TipoOD::ODI, 'PARAFUSO', 5, 1, 0.01, 10));
        self::assertTrue(PuBaixoEmOdi::aplica(TipoOD::ODI, 'PARAFUSO', 0.01, 1, 0.01, 10), 'limite inferior');
        self::assertTrue(PuBaixoEmOdi::aplica(TipoOD::ODI, 'PARAFUSO', 30, 3, 0.01, 10), 'limite superior');
        self::assertFalse(PuBaixoEmOdi::aplica(TipoOD::ODI, 'PARAFUSO', 10.5, 1, 0.01, 10));
        self::assertFalse(PuBaixoEmOdi::aplica(TipoOD::ODI, 'PARAFUSO', 0.005, 1, 0.01, 10));
        self::assertFalse(PuBaixoEmOdi::aplica(TipoOD::ODI, 'CABO ISOLADO', 5, 1, 0.01, 10));
        self::assertFalse(PuBaixoEmOdi::aplica(TipoOD::ODI, 'COND NU', 5, 1, 0.01, 10));
        self::assertFalse(PuBaixoEmOdi::aplica(TipoOD::ODS, 'PARAFUSO', 5, 1, 0.01, 10));
        self::assertFalse(PuBaixoEmOdi::aplica(TipoOD::ODI, 'PARAFUSO', 5, 0, 0.01, 10));
    }

    public function testPuSimbolicoCabo(): void
    {
        self::assertTrue(PuSimbolicoCabo::aplica('CABO ISOLADO', 1, 100, 0.01));
        self::assertTrue(PuSimbolicoCabo::aplica('COND NU', 1.2, 100, 0.01), '0,012 aparece como 0,01');
        self::assertTrue(PuSimbolicoCabo::aplica('CABO COBRE', -1, -100, 0.01), 'ODD com valor e qtd negativos');
        self::assertFalse(PuSimbolicoCabo::aplica('CABO ISOLADO', 2, 100, 0.01));
        self::assertFalse(PuSimbolicoCabo::aplica('PARAFUSO', 1, 100, 0.01));
        self::assertFalse(PuSimbolicoCabo::aplica('CABO ISOLADO', 1, 0, 0.01));
    }

    public function testPosteOdiPuBaixo(): void
    {
        self::assertTrue(PosteOdiPuBaixo::aplica(TipoOD::ODI, 'POSTE RD', 400, 1, 500));
        self::assertTrue(PosteOdiPuBaixo::aplica(TipoOD::ODI, 'POSTE RD', 800, 2, 500));
        self::assertFalse(PosteOdiPuBaixo::aplica(TipoOD::ODI, 'POSTE RD', 500, 1, 500));
        self::assertFalse(PosteOdiPuBaixo::aplica(TipoOD::ODI, 'POSTE RD', 1200, 1, 500));
        self::assertFalse(PosteOdiPuBaixo::aplica(TipoOD::ODD, 'POSTE RD', 400, 1, 500));
        self::assertFalse(PosteOdiPuBaixo::aplica(TipoOD::ODI, 'TRAFO', 400, 1, 500));
        self::assertFalse(PosteOdiPuBaixo::aplica(TipoOD::ODI, 'POSTE RD', -400, 1, 500));
    }

    public function testMedidorEmOdi(): void
    {
        self::assertTrue(MedidorEmOdi::aplica(TipoOD::ODI, Texto::norm('MEDIDOR ELETRONICO MONOFASICO'), 'MEDIDOR'));
        self::assertTrue(MedidorEmOdi::aplica(TipoOD::ODI, Texto::norm('ELETRONICO 1F 120V'), 'MEDIDOR'));
        self::assertFalse(MedidorEmOdi::aplica(TipoOD::ODM, Texto::norm('MEDIDOR ELETRONICO'), 'MEDIDOR'));
        self::assertFalse(MedidorEmOdi::aplica(TipoOD::ODI, Texto::norm('CAIXA PARA MEDIDOR'), 'CAIXA MEDICAO'));
        self::assertFalse(MedidorEmOdi::aplica(TipoOD::ODI, Texto::norm('LACRE P/ MEDIDOR'), 'LACRE'));
        self::assertFalse(MedidorEmOdi::aplica(TipoOD::ODI, Texto::norm('TRAFO DT 1F 13,8KV'), 'TRAFO'));
    }

    public function testLacreMedidorDoisPorMedidor(): void
    {
        $l = static fn (string $desc, float $q): LinhaAlerta => new LinhaAlerta('P3', 'P3.01.M', 'COM', '1', $desc, '', 0, $q, TipoOD::ODM);
        $r = new LacreMedidor();
        self::assertSame([], $r->gerar([$l('MEDIDOR ELETRONICO', 2), $l('LACRE P/ MEDIDOR', 4)]));
        $a = $r->gerar([$l('MEDIDOR ELETRONICO', 2), $l('LACRE P/ MEDIDOR', 3)]);
        self::assertCount(1, $a);
        self::assertSame('2 medidor(es) -> esperado 4 lacres, veio 3 (FALTAM 1)', $a[0]->motivo);
        $a = $r->gerar([$l('LACRE P/ MEDIDOR', 2)]);
        self::assertSame('Lacre sem medidor no PEP ODM (veio 2)', $a[0]->motivo);
    }

    public function testUcSubvalorizado(): void
    {
        $regra = new UcPrecoReferencia(new PrecosReferencia(['100' => 1000.0], 'teste'), 0.9, 100);
        $uc = static fn (string $cod, float $valor, float $q): LinhaAlerta => new LinhaAlerta('P3', 'P4', 'UC', $cod, 'TRAFO', 'TRAFO', $valor, $q, TipoOD::ODI);
        self::assertSame('UC SUBVALORIZADO', $regra->avaliar($uc('100', 800, 1))?->tipo);
        self::assertNull($regra->avaliar($uc('100', 950, 1)), 'acima de 90% da referencia');
        self::assertSame('UC - PRECO NAO ENCONTRADO', $regra->avaliar($uc('200', 800, 1))?->tipo);
        self::assertSame('UC - COD MATERIAL VAZIO', $regra->avaliar($uc('', 800, 1))?->tipo);
        self::assertNull($regra->avaliar(new LinhaAlerta('P3', 'P4', 'UC', '100', 'CABO', 'CABO ISOLADO', 1, 100, TipoOD::ODI)));
    }
}
