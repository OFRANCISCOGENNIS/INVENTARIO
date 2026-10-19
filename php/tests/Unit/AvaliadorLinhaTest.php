<?php

declare(strict_types=1);

namespace Inventario\Tests\Unit;

use Inventario\Domain\Base\TipoOD;
use Inventario\Domain\Config\Parametros;
use Inventario\Domain\Equivalencia\CatalogoEquivalencias;
use Inventario\Domain\Regra\AvaliadorLinha;
use PHPUnit\Framework\TestCase;

/** AvaliarLinha: fonte unica da regra (veredito e exibicao). */
final class AvaliadorLinhaTest extends TestCase
{
    private AvaliadorLinha $a;

    protected function setUp(): void
    {
        $this->a = new AvaliadorLinha(Parametros::padrao());
    }

    public function testCondCobreAderente(): void
    {
        $av = $this->a->avaliar('UC', 'COND COBRE', 2, 0, '');
        self::assertTrue($av->ehAderente);
        self::assertFalse($av->reprovaLinha);
        self::assertTrue($av->avaliavel);
    }

    public function testUcZeradoComObraTodaZerada(): void
    {
        $av = $this->a->avaliar('UC', 'PARAFUSO', 0, 0, 'NULO', true);
        self::assertTrue($av->ucZerado);
        self::assertTrue($av->reprovaLinha);
        self::assertFalse($av->ehAderente);
    }

    public function testUcZeradoIsoladoNaoReprova(): void
    {
        $av = $this->a->avaliar('UC', 'PARAFUSO', 0, 0, 'NULO', false);
        self::assertFalse($av->ucZerado);
        self::assertFalse($av->reprovaLinha);
    }

    public function testOddPositivoReprova(): void
    {
        $av = $this->a->avaliar('UC', 'PARAFUSO', 5, 5, 'ADERENTE', false, TipoOD::ODD, 100);
        self::assertTrue($av->violaOD);
        self::assertFalse($av->ehAderente);
        self::assertTrue($av->reprovaLinha);
    }

    public function testOddNegativoAderente(): void
    {
        $av = $this->a->avaliar('UC', 'PARAFUSO', -5, -5, 'ADERENTE', false, TipoOD::ODD, -100);
        self::assertFalse($av->violaOD);
        self::assertTrue($av->ehAderente);
    }

    public function testOddValorAbaixoDoLimite(): void
    {
        $av = $this->a->avaliar('UC', 'PARAFUSO', -5, -5, 'ADERENTE', false, TipoOD::ODD, -150);
        self::assertTrue($av->violaOD);
        self::assertTrue($av->reprovaLinha);
    }

    public function testSinalPelaQuantidadeDaPropriaLinha(): void
    {
        // total do grupo negativo, mas a linha tem quantidade +4 em ODD
        $av = $this->a->avaliar('UC', 'PARAFUSO', -10, -10, 'ADERENTE', false, TipoOD::ODD, -50, 4);
        self::assertTrue($av->violaOD);
    }

    public function testCelulaVaziaNaLinhaNaoCaiNoTotalDoGrupo(): void
    {
        // celula vazia (null) e zero: nao herda o +4 do total do grupo
        $av = $this->a->avaliar('UC', 'PARAFUSO', 4, 4, 'ADERENTE', false, TipoOD::ODD, 0, null, null);
        self::assertFalse($av->violaOD);
    }

    public function testSinaisOpostosNaoAderente(): void
    {
        $av = $this->a->avaliar('COM', 'PARAFUSO', 3, -3, 'ADERENTE', false);
        self::assertTrue($av->violaOD);
        self::assertFalse($av->ehAderente);
    }

    public function testComNaoCriticoNaoAvaliavel(): void
    {
        self::assertFalse($this->a->avaliar('COM', 'POSTE', 100, 0, 'NAO ADERENTE')->avaliavel);
    }

    public function testComCriticoReprova(): void
    {
        $av = $this->a->avaliar('COM', 'CH FUS', 100, 0, 'NAO ADERENTE');
        self::assertTrue($av->avaliavel);
        self::assertTrue($av->reprovaLinha);
    }

    public function testCaboIsoladoCurtoIsento(): void
    {
        self::assertTrue($this->a->avaliar('UC', 'CABO ISOLADO', 10, 0, '')->caboIsento);
    }

    public function testSitNuloNaoReprova(): void
    {
        self::assertFalse($this->a->avaliar('UC', 'PARAFUSO', 1, 5, 'NULO', false)->reprovaLinha);
    }

    public function testEquivalenteCaboExigeMesmoNumero(): void
    {
        $av = $this->a->avaliar('UC', 'COND PROT', 10, 10.5, 'ADERENTE', false);
        $av->ajustarEquivalencia(CatalogoEquivalencias::situacaoPorQuantidade(10, 10.5) === 'ADERENTE');
        self::assertFalse($av->ehAderente);
        self::assertTrue($av->reprovaLinha);

        $av = $this->a->avaliar('UC', 'COND PROT', 10, 10, 'ADERENTE', false);
        $av->ajustarEquivalencia(true);
        self::assertTrue($av->ehAderente);
        self::assertFalse($av->reprovaLinha);
    }

    public function testEquivalenteTresPorNove(): void
    {
        $av = $this->a->avaliar('UC', 'CH SU FACA', 3, 9, CatalogoEquivalencias::situacaoPorQuantidade(3, 9), false);
        self::assertFalse($av->ehAderente);
        self::assertTrue($av->reprovaLinha);
    }

    public function testEquivalenteComSinalInvertidoContinuaNaoAderente(): void
    {
        $av = $this->a->avaliar('UC', 'CH SU FACA', 3, 3, 'ADERENTE', false, TipoOD::ODD, 100);
        $av->ajustarEquivalencia(true);
        self::assertFalse($av->ehAderente);
        self::assertTrue($av->violaOD);
    }
}
