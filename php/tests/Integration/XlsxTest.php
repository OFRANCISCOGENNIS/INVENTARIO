<?php

declare(strict_types=1);

namespace Inventario\Tests\Integration;

use Inventario\Application\Relatorio\Aba;
use Inventario\Application\Relatorio\Bloco;
use Inventario\Application\Relatorio\Relatorio;
use Inventario\Infrastructure\Exportacao\ExportadorXlsx;
use Inventario\Infrastructure\Leitura\LeitorXlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\TestCase;

/** Leitor e gravador XLSX em fluxo. */
final class XlsxTest extends TestCase
{
    private string $arq;

    protected function setUp(): void
    {
        $this->arq = tempnam(sys_get_temp_dir(), 'xlsx') . '.xlsx';
    }

    protected function tearDown(): void
    {
        @unlink($this->arq);
    }

    public function testLeitorLeValoresComoOExcelSalvou(): void
    {
        $b = new Spreadsheet();
        $ws = $b->getActiveSheet()->setTitle('BASE');
        $ws->fromArray([
            ['PEP3NIVEL', 'MATERIAL', 'VALOR', 'DOBRO', 'TEXTO'],
            ['OBRA-1', 105010022, 10.5, '=C2*2', 'ação'],
            [null, null, null, null, null],
            ['OBRA-2', '000123', -3, '=C4*2', 'x'],
        ]);
        $b->createSheet()->setTitle('PRECOS')->fromArray([['COD', 'PRECO'], [1, 2]]);
        (new Xlsx($b))->save($this->arq);

        $abas = (new LeitorXlsx())->ler($this->arq);
        self::assertSame(['BASE', 'PRECOS'], array_keys($abas));
        $t = $abas['BASE'];
        self::assertSame(['PEP3NIVEL', 'MATERIAL', 'VALOR', 'DOBRO', 'TEXTO'], $t->cabecalho);
        self::assertSame(['OBRA-1', 105010022.0, 10.5, 21.0, 'ação'], $t->linhas[0]);
        self::assertSame([null, null, null, null, null], $t->linhas[1], 'linha vazia no meio continua');
        self::assertSame('000123', $t->linhas[2][1], 'codigo texto mantem os zeros');
        self::assertSame(-6.0, $t->linhas[2][3]);
    }

    public function testColuna(): void
    {
        self::assertSame(0, LeitorXlsx::coluna('A1'));
        self::assertSame(27, LeitorXlsx::coluna('AB12'));
        self::assertSame('AB', ExportadorXlsx::col(27));
        self::assertSame('A', ExportadorXlsx::col(0));
    }

    public function testGravadorProduzArquivoValido(): void
    {
        $linhas = [];
        for ($i = 0; $i < 300; $i++) {
            $linhas[] = ['10' . $i, $i % 2 ? 'APROVADO' : 'REPROVADO', $i * 1.5, '=SOMA(A1)', null];
        }
        $rel = new Relatorio('base.xlsx', '01/01/2026 10:00', 'EXECUCAO OK', [
            new Aba('ANALISE', 'Titulo', 'Sub <&>', [
                new Bloco('', ['COD', 'SITUACAO', 'VALOR', 'TEXTO', 'VAZIO'], $linhas, 1,
                    ['APROVADO' => ['E7F4EA', '218246'], 'REPROVADO' => ['FCE2E4', 'C00000']], [2]),
            ]),
            new Aba('ANALISE', 'Mesmo nome', '', [new Bloco('X', ['A'], [], vazio: 'nada')]),
        ]);
        (new ExportadorXlsx())->exportar($rel, $this->arq);

        $b = IOFactory::load($this->arq);
        self::assertSame(['ANALISE', 'ANALISE 2'], $b->getSheetNames());
        $ws = $b->getSheet(0);
        $txt = static fn (mixed $v): mixed => $v instanceof RichText ? $v->getPlainText() : $v;
        self::assertSame('Sub <&>', $txt($ws->getCell('A2')->getValue()));
        self::assertSame('100', $txt($ws->getCell('A5')->getValue()), 'codigo continua texto');
        self::assertSame('=SOMA(A1)', $txt($ws->getCell('D5')->getValue()), 'texto com = nao vira formula');
        self::assertFalse($ws->getCell('D5')->isFormula());
        self::assertEquals(1.5, $ws->getCell('C6')->getValue());
        self::assertSame('#,##0.00', $ws->getStyle('C6')->getNumberFormat()->getFormatCode());
        self::assertSame('FFC00000', $ws->getStyle('B5')->getFill()->getStartColor()->getARGB());
        self::assertSame('A4:E304', $ws->getAutoFilter()->getRange());
        self::assertSame('A5', $ws->getFreezePane());

        // uma entrada por aba + tabela de textos compartilhados (sem aba sobrando)
        $zip = new \ZipArchive();
        $zip->open($this->arq);
        $partes = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $partes[] = $zip->getNameIndex($i);
        }
        $zip->close();
        self::assertSame(2, count(preg_grep('#^xl/worksheets/sheet\d+\.xml$#', $partes)));
        self::assertContains('xl/sharedStrings.xml', $partes);

        // o leitor em fluxo le de volta o que o gravador escreveu
        $t = (new LeitorXlsx())->ler($this->arq)['ANALISE'];
        self::assertSame(['COD', 'SITUACAO', 'VALOR', 'TEXTO', 'VAZIO'], array_slice($t->linhas[2], 0, 5));
        self::assertNull($t->linhas[3][4], 'celula vazia continua vazia');
        self::assertSame('100', $t->linhas[3][0]);
    }
}
