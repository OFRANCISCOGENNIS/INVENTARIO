<?php

declare(strict_types=1);

namespace Inventario\Tests\Unit;

use Inventario\Domain\Alerta\ResultadoAlertas;
use Inventario\Domain\Analise\SapPrj\ResultadoSapPrj;
use Inventario\Domain\Base\BaseInventario;
use Inventario\Domain\Config\Parametros;
use Inventario\Domain\Preco\DiscrepanciaValor;
use Inventario\Domain\Ranking\CalculadoraRisco;
use PHPUnit\Framework\TestCase;

final class DiscrepanciaValorTest extends TestCase
{
    public function testLimiteDeTrintaPorCento(): void
    {
        self::assertTrue(DiscrepanciaValor::discrepante(131, 100, 0.3));
        self::assertTrue(DiscrepanciaValor::discrepante(69, 100, 0.3));
        self::assertFalse(DiscrepanciaValor::discrepante(130, 100, 0.3), 'limite exato nao e discrepancia');
        self::assertFalse(DiscrepanciaValor::discrepante(70, 100, 0.3), 'limite exato nao e discrepancia');
        self::assertFalse(DiscrepanciaValor::discrepante(115, 100, 0.3));
        self::assertFalse(DiscrepanciaValor::discrepante(50, 0, 0.3), 'media zero: sem comparacao');
        self::assertEqualsWithDelta(0.45, DiscrepanciaValor::desvio(145, 100), 1e-12);
        self::assertEqualsWithDelta(-0.2, DiscrepanciaValor::desvio(80, 100), 1e-12);
    }

    public function testSoOdiComDoisOuMaisLancamentos(): void
    {
        $base = self::base([
            // codigo 1 em ODI: PU 100, 100, 160 -> media 120; 160 = +33,3%
            ['OB-1', 'OB-1.01.I', '1', 'PARAFUSO', 1000, 10],
            ['OB-1', 'OB-1.02.I', '1', 'PARAFUSO', 500, 5],
            ['OB-2', 'OB-2.01.I', '1', 'PARAFUSO', 1600, 10],
            // mesmo codigo em ODM nao entra na media
            ['OB-2', 'OB-2.01.M', '1', 'PARAFUSO', 99999, 1],
            // codigo 2 com um unico lancamento ODI: sem comparacao
            ['OB-3', 'OB-3.01.I', '2', 'ARRUELA', 1, 1],
            // codigo 3: PU 10 e 30 -> media 20, os dois a 50%
            ['OB-3', 'OB-3.02.I', '3', 'PORCA', 30, 1],
            ['OB-1', 'OB-1.03.I', '3', 'PORCA', 10, 1],
            // quantidade zero fica de fora
            ['OB-3', 'OB-3.03.I', '3', 'PORCA', 500, 0],
        ]);
        $r = (new DiscrepanciaValor(0.3))->analisar($base);

        self::assertCount(3, $r);
        self::assertSame(['OB-3.02.I', 'OB-1.03.I', 'OB-2.01.I'], array_map(static fn ($d) => $d->pep4, $r), 'maior desvio primeiro');
        self::assertSame('ACIMA DA MEDIA', $r[0]->situacao());
        self::assertSame('ABAIXO DA MEDIA', $r[1]->situacao());
        self::assertSame(3, $r[2]->lancamentos);
        self::assertEqualsWithDelta(120.0, $r[2]->media, 1e-9);
        self::assertSame('Discrepancia de valor de material: PU R$ 160,00 esta 33,3% acima da media do codigo (R$ 120,00 em 3 lancamentos ODI)',
            $r[2]->observacao());
    }

    public function testMediasOdiPorCodigo(): void
    {
        $base = self::base([
            ['OB-1', 'OB-1.01.I', '1', 'PARAFUSO', 1000, 10],
            ['OB-1', 'OB-1.02.I', '1', 'PARAFUSO', 1600, 10],
            ['OB-2', 'OB-2.01.D', '1', 'PARAFUSO', -99999, -1],
            ['OB-3', 'OB-3.01.M', '2', 'ARRUELA', 50, 1],
        ]);
        self::assertSame(['1' => 130.0], DiscrepanciaValor::mediasOdi($base), 'so ODI; ODD e ODM ficam fora da media');
    }

    public function testEntraNoScoreDoRanking(): void
    {
        $base = self::base([
            ['OB-1', 'OB-1.01.I', '3', 'PORCA', 30, 1],
            ['OB-1', 'OB-1.02.I', '3', 'PORCA', 10, 1],
        ]);
        $dv = (new DiscrepanciaValor(0.3))->analisar($base);
        $rk = (new CalculadoraRisco(new Parametros()))->calcular(
            new ResultadoSapPrj([], 0, 0, 0, 0.0, 0.0), new ResultadoAlertas([]), [], [], $dv);

        self::assertCount(1, $rk->obras, 'obra so com discrepancia aparece no ranking');
        self::assertSame(4.0, $rk->obras[0]->score, '2 discrepancias x peso 2');
        self::assertSame('BAIXO', $rk->obras[0]->risco);
        self::assertSame(2, $rk->obras[0]->discrepanciasValor);
        self::assertSame('2 discrepancia(s) de valor de material', $rk->obras[0]->diagnostico);

        $muitas = array_fill(0, 10, $dv[0]);
        $rk = (new CalculadoraRisco(new Parametros()))->calcular(
            new ResultadoSapPrj([], 0, 0, 0, 0.0, 0.0), new ResultadoAlertas([]), [], [], $muitas);
        self::assertSame(12.0, $rk->obras[0]->score, 'teto CAP_DISCREP');
    }

    /** @param list<array{0: string, 1: string, 2: string, 3: string, 4: float|int, 5: float|int}> $linhas */
    private static function base(array $linhas): BaseInventario
    {
        return BaseInventario::deTabela('teste', ['PEP3NIVEL', 'PEP4NIVEL', 'COD MAT', 'DESC MAT', 'VALOR', 'MAT LIB SAP'], $linhas);
    }
}
