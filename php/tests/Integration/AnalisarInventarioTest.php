<?php

declare(strict_types=1);

namespace Inventario\Tests\Integration;

use DateTimeImmutable;
use Inventario\Application\Pipeline\ContextoAnalise;
use Inventario\Application\UseCase\ModoCarga;
use Inventario\Application\UseCase\SolicitacaoAnalise;
use Inventario\Domain\Analise\SapPrj\Aprovacao;
use Inventario\Domain\Analise\SapPrj\LinhaAnalise;
use Inventario\Domain\Preco\StatusPreco;
use Inventario\Presentation\Aplicacao;
use PHPUnit\Framework\TestCase;

/** Pipeline completo sobre exemplos/base_exemplo.csv. */
final class AnalisarInventarioTest extends TestCase
{
    private const BASE = __DIR__ . '/../../exemplos/base_exemplo.csv';
    private string $dir;
    private Aplicacao $app;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/inventario_teste_' . bin2hex(random_bytes(4));
        $this->app = new Aplicacao($this->dir);
    }

    protected function tearDown(): void
    {
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $f) {
            $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
        }
        rmdir($this->dir);
    }

    private function analisar(?string $precos = null, ?DateTimeImmutable $agora = null): ContextoAnalise
    {
        $r = $this->app->analisarInventario()->executar(
            new SolicitacaoAnalise([self::BASE => 'base_exemplo.csv'], ModoCarga::JUNTAR, $precos),
            agora: $agora,
        );
        self::assertSame([], $r->erros);
        self::assertCount(1, $r->analises);

        return $r->analises[0];
    }

    /** @return list<LinhaAnalise> */
    private static function linhas(ContextoAnalise $c, string $pep4, string $cod = ''): array
    {
        return array_values(array_filter($c->sapPrj()->linhas,
            static fn (LinhaAnalise $l): bool => $l->pep4() === $pep4 && ($cod === '' || $l->origem->codigo() === $cod)));
    }

    public function testTodasAsEtapasRodam(): void
    {
        $c = $this->analisar();
        foreach ($c->saude->etapas() as $e) {
            self::assertTrue($e->ok, "{$e->etapa}: {$e->erro}");
        }
        self::assertSame('ANALISE COM AVISOS', $c->saude->selo(), 'sem base de precos');
    }

    public function testVeredito(): void
    {
        $c = $this->analisar();
        $r = $c->sapPrj();
        self::assertSame(7, $r->totalPep4);
        self::assertSame(3, $r->pep3Aprovados);
        self::assertSame(4, $r->pep3Reprovados);

        // ARRUELA (COM nao critico) nao aderente nao reprova a obra
        $arruela = self::linhas($c, 'OBRA-001.01.I', '134830013')[0];
        self::assertSame('NAO ADERENTE', $arruela->sitMat);
        self::assertSame(Aprovacao::APROVADO, $arruela->aprovacao);

        // equivalentes: SAP 3 num codigo e PRJ 9 no outro = NAO ADERENTE
        foreach (self::linhas($c, 'OBRA-002.01.I') as $l) {
            self::assertSame(Aprovacao::REPROVADO, $l->aprovacao);
        }
        $eq = self::linhas($c, 'OBRA-002.01.I', '105010022')[0];
        self::assertStringContainsString('Material equivalente: 105010022', $eq->motivo);
        self::assertStringContainsString('total do grupo no PEP4 SAP=3 / PRJ=9', $eq->motivo);
        self::assertSame('Devolvido por divergencia de CH SU FACA', $eq->motivoDevolucao);
        $arrastado = self::linhas($c, 'OBRA-002.01.I', '105300003')[0];
        self::assertStringStartsWith('Arrastado pela reprovacao do PEP3', $arrastado->motivo);

        // ODD: sinal e valor abaixo do limite
        $odd = self::linhas($c, 'OBRA-003.01.D', '102100160')[0];
        self::assertStringContainsString('abaixo do limite de R$ -100,00', $odd->motivo);

        // cabo isolado de 100 m nao e isento (so abaixo de 15 m)
        self::assertSame(Aprovacao::APROVADO, self::linhas($c, 'OBRA-005.01.I', '122000001')[0]->aprovacao);

        // obra com todos os UC zerados e devolvida
        self::assertStringStartsWith('Obra com TODOS os materiais UC zerados', self::linhas($c, 'OBRA-007.01.I')[0]->motivo);
    }

    public function testResumoEDevolucoes(): void
    {
        $c = $this->analisar();
        self::assertSame(['OBRA-002', 'OBRA-006', 'OBRA-003', 'OBRA-007'],
            array_slice(array_map(static fn ($o) => $o->pep3, $c->resumo), 0, 4), 'reprovadas primeiro, pelo valor nao aderente');
        self::assertSame('CH SU FACA', $c->resumo[0]->familiasReprovadas);
    }

    public function testRacionalizacao(): void
    {
        $c = $this->analisar();
        $porCod = [];
        foreach ($c->racionalizacao as $l) {
            $porCod[$l->codigo] = $l;
        }
        self::assertSame('OK', $porCod['123140003']->status->rotulo());
        self::assertSame('4 a 7', $porCod['123140003']->previsto);
        self::assertSame('EXAGERADO', $porCod['134830013']->status->rotulo());
        self::assertSame('SEM ANCORA', $porCod['134700040']->status->rotulo());
        self::assertSame('LACRE', $porCod['301000099']->familia);
    }

    public function testAlertas(): void
    {
        $c = $this->analisar();
        $tipos = array_map(static fn ($a) => $a->tipo, $c->alertas()->alertas());
        foreach (['ODI SEM UC', 'ODI SEM COM', 'PU BAIXO EM ODI', 'PU 0,01 EM CABO/COND', 'MATERIAL POSITIVO EM ODD',
            'ODD VALOR ABAIXO DO LIMITE', 'POSTE EM PEP .M', 'POSTE EM PEP .S', 'POSTE ODI PU BAIXO', 'LACRE x MEDIDOR',
            'QUALIDADE DA BASE'] as $t) {
            self::assertContains($t, $tipos);
        }
        self::assertSame(['ODI SEM UC', 'ODI SEM COM'], array_slice($tipos, 0, 2), 'agregadas primeiro, como no VBA');
        self::assertSame('QUALIDADE DA BASE', end($tipos), 'diagnostico no fim');
        self::assertNotContains('QUALIDADE DA BASE', array_column($c->alertas()->resumo(), 'tipo'));
    }

    public function testRankingEPepsSemBaixa(): void
    {
        $c = $this->analisar();
        $rk = $c->ranking;
        self::assertNotNull($rk);
        self::assertSame('OBRA-003', $rk->obras[0]->pep3);
        self::assertSame(52.0, $rk->obras[0]->score);
        self::assertSame(['OBRA-006', 'OBRA-007'], array_map(static fn ($p) => $p->pep, $rk->semBaixa));
        self::assertStringContainsString('SAP liberou 1 - material sem projeto', $rk->semBaixa[0]->observacao);
    }

    public function testComBaseDePrecos(): void
    {
        $precos = $this->dir . '/precos.csv';
        file_put_contents($precos, "MATERIAL;TEXTO MATERIAL;UML;MIN PU;MAX PU\n"
            . "133000038;POSTE;UN;1500;2500\n102100066;TRAFO;UN;9000;10000\n123140003;ISOLADOR;UN;20;40\n");
        $c = $this->analisar($precos);
        self::assertSame('EXECUCAO OK', $c->saude->selo());
        $st = [];
        foreach ($c->precoUnitario as $p) {
            $st[$p->pep4 . '|' . $p->codigo] = $p->status;
        }
        self::assertSame(StatusPreco::DENTRO, $st['OBRA-001.01.I|133000038']);
        self::assertSame(StatusPreco::ABAIXO, $st['OBRA-001.01.I|123140003']);
        self::assertSame(StatusPreco::ACIMA, $st['OBRA-004.01.M|133000038']);
        self::assertGreaterThan(0, $c->painel?->sobrepreco);
        $tipos = array_map(static fn ($a) => $a->tipo, $c->alertas()->alertas());
        self::assertContains('PU ACIMA MAX', $tipos);
        self::assertContains('UC SUBVALORIZADO', $tipos, 'poste a R$ 400 x referencia 2.000');
    }

    public function testHistoricoComparaComARodadaAnterior(): void
    {
        $this->analisar(agora: new DateTimeImmutable('2026-01-01 10:00'));
        $c = $this->analisar(agora: new DateTimeImmutable('2026-01-02 10:00'));
        self::assertSame('MANTEVE', $c->historico[0]->mudou);
        self::assertSame("\u{2022} sem variacao", $c->painel?->variacao('reprovados')?->texto);
        self::assertCount(2, $this->app->historico->kpis());
    }

    public function testModoSepararNaoGravaHistorico(): void
    {
        $r = $this->app->analisarInventario()->executar(new SolicitacaoAnalise(
            [self::BASE => 'a.csv', realpath(self::BASE) => 'b.csv'], ModoCarga::SEPARAR));
        self::assertCount(2, $r->analises);
        self::assertSame([], $this->app->historico->kpis());
    }

    public function testJuntarDuasBasesMarcaOrigem(): void
    {
        $r = $this->app->analisarInventario()->executar(new SolicitacaoAnalise(
            [self::BASE => 'a.csv', realpath(self::BASE) => 'b.csv'], ModoCarga::JUNTAR));
        $c = $r->analises[0];
        self::assertCount(38, $c->base);
        self::assertSame('b.csv', $c->base->linhas()[37]->texto('ARQUIVO ORIGEM'));
    }

    public function testBaseSemColunasObrigatoriasERecusada(): void
    {
        $arq = $this->dir . '/ruim.csv';
        file_put_contents($arq, "PEP3NIVEL;ATENDIDO SAP;TOMBADO PROJ\nA;1;1\n");
        $r = $this->app->analisarInventario()->executar(new SolicitacaoAnalise([$arq => 'ruim.csv']));
        self::assertSame([], $r->analises);
        self::assertStringContainsString('sem colunas obrigatorias', $r->erros[0]);
    }

    public function testExportacaoXlsxEJson(): void
    {
        $c = $this->analisar();
        $rel = $this->app->montador()->montar($c);
        foreach ($this->app->exportadores() as $ext => $exp) {
            $arq = $exp->exportar($rel, $this->dir . '/saida.' . $ext);
            self::assertFileExists($arq);
        }
        $book = \PhpOffice\PhpSpreadsheet\IOFactory::load($this->dir . '/saida.xlsx');
        self::assertSame(['PAINEL DO GESTOR', 'ANALISE SAP x PRJ', 'RESUMO SAP x PRJ', 'RACIONALIZACAO COM', 'PRECO UNITARIO',
            'ALERTA CRITICO', 'RANKING DE RISCO', 'DIAGNOSTICO BASE', 'DEVOLUCOES', 'HISTORICO', 'LOG EXECUCAO'], $book->getSheetNames());
        $json = json_decode((string) file_get_contents($this->dir . '/saida.json'), true);
        self::assertSame('base_exemplo.csv', $json['base']);
        self::assertArrayHasKey('ALERTA CRITICO', $json['abas']);
    }
}
