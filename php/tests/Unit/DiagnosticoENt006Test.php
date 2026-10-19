<?php

declare(strict_types=1);

namespace Inventario\Tests\Unit;

use Inventario\Domain\Analise\Racionalizacao\RacionalizadorCom;
use Inventario\Domain\Diagnostico\ResultadoDiagnostico;
use Inventario\Domain\Diagnostico\StatusVerificacao;
use Inventario\Domain\Nt006\ClassificadorDescricao;
use Inventario\Domain\Nt006\MapaNT006;
use Inventario\Domain\Ranking\ObraRisco;
use Inventario\Domain\Support\Texto;
use PHPUnit\Framework\TestCase;

final class DiagnosticoENt006Test extends TestCase
{
    public function testNotaSaude(): void
    {
        self::assertSame(100.0, ResultadoDiagnostico::notaSaude(0, 0));
        self::assertSame(55.0, ResultadoDiagnostico::notaSaude(1, 2));
        self::assertSame(0.0, ResultadoDiagnostico::notaSaude(5, 0));
    }

    public function testStatusPorPercentual(): void
    {
        self::assertSame(StatusVerificacao::OK, StatusVerificacao::porPercentual(0, 100, 0.02, 0.1));
        self::assertSame(StatusVerificacao::ATENCAO, StatusVerificacao::porPercentual(5, 100, 0.02, 0.1));
        self::assertSame(StatusVerificacao::CRITICO, StatusVerificacao::porPercentual(15, 100, 0.02, 0.1));
    }

    public function testUnidadeInteira(): void
    {
        self::assertFalse(RacionalizadorCom::unidadeInteira('M'));
        self::assertFalse(RacionalizadorCom::unidadeInteira('kg'));
        self::assertTrue(RacionalizadorCom::unidadeInteira('UN'));
        self::assertTrue(RacionalizadorCom::unidadeInteira(''));
    }

    public function testMapaNT006(): void
    {
        self::assertTrue(MapaNT006::buscar('133100007')?->ancora);
        self::assertSame(2.0, MapaNT006::buscar('123140003')?->razaoMin);
        self::assertSame('CRUZETA', MapaNT006::buscar('123140003')?->ancoraDependente);
        self::assertNull(MapaNT006::buscar('999'));
    }

    public function testClassificacaoPorDescricao(): void
    {
        $c = static fn (string $d): ?string => ClassificadorDescricao::classificar(Texto::norm($d))?->familia;
        self::assertSame('PINO', $c('PINO,ISOL,CRUZETA MAD'), 'PINO antes de CRUZETA');
        self::assertSame('PLACA IDENTIFICACAO', $c('PLACA IDENT POSTE'), 'PLACA antes de POSTE');
        self::assertSame('CHAVE FUSIVEL', $c('CHAVE FUSIVEL 15KV'));
        self::assertSame('ELO FUSIVEL', $c('ELO FUSIVEL 10K'));
        self::assertSame('CABO/CONDUTOR', $c('CABO ALUMINIO 4AWG'));
        self::assertSame('ALCA PREFORMADA', $c('ALCA PREF CABO'), 'ALCA nao e cabo');
        self::assertSame('LACRE', $c('LACRE P/ MEDIDOR'), 'lacre nao e medidor');
        self::assertSame('MEDIDOR', $c('MEDIDOR ELETRONICO'));
        self::assertTrue(ClassificadorDescricao::classificar('SELA CRUZETA')?->ancora === true, 'contem CRUZETA: ancora');
        self::assertTrue(ClassificadorDescricao::classificar(Texto::norm('MAO FRANCESA PLANA'))?->porDescricao());
        self::assertNull(ClassificadorDescricao::classificar('XYZ'));
    }

    public function testNivelDeRisco(): void
    {
        self::assertSame('ALTO', ObraRisco::nivel(60));
        self::assertSame('MEDIO', ObraRisco::nivel(30));
        self::assertSame('BAIXO', ObraRisco::nivel(0.5));
        self::assertSame('OK', ObraRisco::nivel(0));
    }
}
