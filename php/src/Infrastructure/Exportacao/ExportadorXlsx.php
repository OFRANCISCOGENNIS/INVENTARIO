<?php

declare(strict_types=1);

namespace Inventario\Infrastructure\Exportacao;

use Inventario\Application\Relatorio\Aba;
use Inventario\Application\Relatorio\Bloco;
use Inventario\Application\Relatorio\Relatorio;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Relatorio em XLSX com a identidade visual do VBA: banda de titulo, cabecalho
 * navy, linhas coloridas pelo status, chip colorido na coluna de status,
 * autofiltro e painel congelado na tabela principal.
 */
final class ExportadorXlsx implements Exportador
{
    private const NAVY = '111827';
    private const FONTE = 'Segoe UI';

    public function extensao(): string
    {
        return 'xlsx';
    }

    public function exportar(Relatorio $r, string $caminho): string
    {
        $book = new Spreadsheet();
        $book->removeSheetByIndex(0);
        foreach ($r->abas as $a) {
            $this->aba($book->createSheet(), $a);
        }
        $book->setActiveSheetIndex(0);
        $book->getProperties()->setTitle('Analise de Inventario - ' . $r->base)->setCreator('Inventario');
        (new Xlsx($book))->save($caminho);
        $book->disconnectWorksheets();

        return $caminho;
    }

    private function aba(Worksheet $ws, Aba $a): void
    {
        $ws->setTitle(mb_substr($a->nome, 0, 31));
        $ws->getTabColor()->setRGB($a->corGuia);
        $ws->setShowGridlines(false);
        $nCols = max(1, ...array_map(static fn (Bloco $b): int => count($b->cabecalho), $a->blocos ?: [new Bloco('', ['-'], [])]));
        $ult = Coordinate::stringFromColumnIndex(max($nCols, 4));

        $ws->mergeCells("A1:{$ult}1");
        $ws->setCellValue('A1', $a->titulo);
        $this->estilo($ws, "A1:{$ult}1", 'FFFFFF', $a->corGuia, 16, true);
        $ws->getRowDimension(1)->setRowHeight(30);
        $ws->mergeCells("A2:{$ult}2");
        $ws->setCellValue('A2', $a->subtitulo);
        $this->estilo($ws, "A2:{$ult}2", 'C8D2E1', self::NAVY, 9, false);

        $linha = 4;
        $principal = null;
        foreach ($a->blocos as $b) {
            [$linha, $hdr] = $this->bloco($ws, $b, $linha);
            // a maior tabela da aba ganha autofiltro e painel congelado
            if ($principal === null || count($b->linhas) > $principal[2]) {
                $principal = [$hdr, count($b->cabecalho), count($b->linhas)];
            }
            $linha += 2;
        }
        if ($principal !== null && $principal[2] > 0) {
            [$hdr, $n] = $principal;
            $ws->setAutoFilter('A' . $hdr . ':' . Coordinate::stringFromColumnIndex($n) . ($hdr + $principal[2]));
            $ws->freezePane('A' . ($hdr + 1));
        }
        foreach ($a->larguras as $i => $w) {
            $ws->getColumnDimension(Coordinate::stringFromColumnIndex($i + 1))->setWidth($w);
        }
        if ($a->larguras === []) {
            for ($c = 1; $c <= $nCols; $c++) {
                $ws->getColumnDimension(Coordinate::stringFromColumnIndex($c))->setWidth(18);
            }
        }
    }

    /** @return array{0: int, 1: int} proxima linha livre e linha do cabecalho */
    private function bloco(Worksheet $ws, Bloco $b, int $r): array
    {
        $n = max(1, count($b->cabecalho));
        $ult = Coordinate::stringFromColumnIndex($n);
        if ($b->titulo !== '') {
            $ws->mergeCells("A{$r}:{$ult}{$r}");
            $ws->setCellValue("A{$r}", $b->titulo);
            $this->estilo($ws, "A{$r}:{$ult}{$r}", 'FFFFFF', '1F2937', 11, true);
            $r++;
        }
        $hdr = $r;
        $ws->fromArray($b->cabecalho, null, "A{$r}");
        $this->estilo($ws, "A{$r}:{$ult}{$r}", 'EBF0F8', self::NAVY, 10, true);
        $ws->getStyle("A{$r}:{$ult}{$r}")->getAlignment()->setWrapText(true);
        $ws->getRowDimension($r)->setRowHeight(28);
        $r++;

        if ($b->linhas === []) {
            if ($b->vazio !== '') {
                $ws->setCellValue("A{$r}", $b->vazio);
                $ws->getStyle("A{$r}")->getFont()->setItalic(true)->getColor()->setRGB('008000');
                $r++;
            }

            return [$r, $hdr];
        }

        $r0 = $r;
        foreach ($b->linhas as $l) {
            foreach (array_values($l) as $i => $v) {
                $cel = Coordinate::stringFromColumnIndex($i + 1) . $r;
                if (is_string($v) && $v !== '' && (ctype_digit($v) || str_starts_with($v, '='))) {
                    // codigos numericos ficam como texto (sem notacao cientifica nem formula)
                    $ws->setCellValueExplicit($cel, $v, DataType::TYPE_STRING);
                } else {
                    $ws->setCellValue($cel, $v);
                }
            }
            $cor = $b->corDaLinha($l);
            if ($cor !== null) {
                $ws->getStyle("A{$r}:{$ult}{$r}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($cor[0]);
                if ($b->colunaStatus !== null) {
                    $c = Coordinate::stringFromColumnIndex($b->colunaStatus + 1) . $r;
                    $ws->getStyle($c)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($cor[1]);
                    $ws->getStyle($c)->getFont()->setBold(true)->getColor()->setRGB(self::contraste($cor[1]));
                    $ws->getStyle($c)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }
            }
            $r++;
        }
        $faixa = "A{$r0}:{$ult}" . ($r - 1);
        $ws->getStyle($faixa)->getFont()->setName(self::FONTE)->setSize(9);
        $ws->getStyle($faixa)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        foreach ($b->colunasMoeda as $c) {
            $col = Coordinate::stringFromColumnIndex($c + 1);
            $ws->getStyle("{$col}{$r0}:{$col}" . ($r - 1))->getNumberFormat()->setFormatCode('#,##0.00');
        }

        return [$r, $hdr];
    }

    private function estilo(Worksheet $ws, string $faixa, string $fonte, string $fundo, int $tam, bool $negrito): void
    {
        $s = $ws->getStyle($faixa);
        $s->getFont()->setName(self::FONTE)->setSize($tam)->setBold($negrito)->getColor()->setRGB($fonte);
        $s->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($fundo);
        $s->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setHorizontal(Alignment::HORIZONTAL_CENTER);
    }

    /** Branco sobre chip escuro, preto sobre chip claro. */
    private static function contraste(string $hex): string
    {
        [$r, $g, $b] = sscanf($hex, '%02x%02x%02x');

        return ($r * 299 + $g * 587 + $b * 114) / 1000 > 160 ? '000000' : 'FFFFFF';
    }
}
