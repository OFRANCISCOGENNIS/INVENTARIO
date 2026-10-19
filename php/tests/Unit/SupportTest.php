<?php

declare(strict_types=1);

namespace Inventario\Tests\Unit;

use Inventario\Domain\Support\Formatador;
use Inventario\Domain\Support\Numero;
use Inventario\Domain\Support\Ordenacao;
use Inventario\Domain\Support\Texto;
use PHPUnit\Framework\TestCase;

final class SupportTest extends TestCase
{
    public function testNorm(): void
    {
        self::assertSame('CONDNU', Texto::norm(' cond-nu '));
        self::assertSame('SITUACAODOMATERIAL', Texto::norm('situação_do_material'));
        self::assertSame('PEP3NIVEL', Texto::norm('PEP3NIVEL'));
        self::assertSame('PARA RAIO', Texto::norm('Pára  Raio'));
    }

    public function testCodigo(): void
    {
        self::assertSame('105010022', Texto::codigo(105010022.0));
        self::assertSame('105010022', Texto::codigo('105010022.0'));
        self::assertSame('105010022', Texto::codigo('1.05010022E+8'));
        self::assertSame('ABC-1', Texto::codigo(' ABC-1 '));
        self::assertSame('', Texto::codigo(null));
    }

    public function testTemPalavra(): void
    {
        self::assertTrue(Texto::temPalavra('ELO FUSIVEL 10K', 'ELO'));
        self::assertFalse(Texto::temPalavra('CABO PARALELO', 'ELO'));
        self::assertTrue(Texto::temPalavra('PINO,ISOL,CRUZETA', 'PINO'));
    }

    public function testNumero(): void
    {
        self::assertSame(10.5, Numero::val0('10,5'));
        self::assertSame(1234.5, Numero::val0('1.234,5'));
        self::assertSame(1234.5, Numero::val0('1,234.5'));
        self::assertSame(1234567.0, Numero::val0('1.234.567'));
        self::assertSame(-3.0, Numero::val0('-3'));
        self::assertSame(0.0, Numero::val0(null));
        self::assertTrue(Numero::ehNumerico(null), 'celula vazia conta como 0');
        self::assertFalse(Numero::ehNumerico('abc'));
        self::assertSame(0.0, Numero::val0('abc'));
    }

    public function testFormatador(): void
    {
        self::assertSame('1.234', Formatador::numero(1234), 'sem a virgula sobrando do VBA');
        self::assertSame('1.234,5', Formatador::numero(1234.5));
        self::assertSame('-2.500,00', Formatador::decimal2(-2500));
        self::assertSame('42,9%', Formatador::pct(3 / 7, 1));
        self::assertSame('10,5', Formatador::geral(10.5));
        self::assertSame('3', Formatador::geral(3.0));
        self::assertSame('R$ 1,2 MM', Formatador::kpi(1234567));
        self::assertSame('R$ 350 mil', Formatador::kpi(350000));
        self::assertSame('R$ 950', Formatador::kpi(950));
        self::assertSame(' | Dif=+3 (42,9% acima)', Formatador::diferenca(10, 7));
        self::assertSame(' | Dif=-2 (25,0% abaixo)', Formatador::diferenca(6, 8));
        self::assertSame(' | Dif=+1', Formatador::diferenca(1, 0));
        self::assertSame('', Formatador::diferenca('x', 1));
    }

    public function testSelecaoReproduzOrdemDoVba(): void
    {
        $itens = [['k' => 'a', 'v' => 1], ['k' => 'b', 'v' => 3], ['k' => 'c', 'v' => 3], ['k' => 'd', 'v' => 2]];
        $out = Ordenacao::selecaoDesc($itens, static fn (array $i): float => $i['v']);
        self::assertSame(['b', 'c', 'd', 'a'], array_column($out, 'k'));
    }
}
