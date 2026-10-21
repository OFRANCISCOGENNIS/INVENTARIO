<?php

declare(strict_types=1);

namespace Inventario\Infrastructure\Exportacao;

use Inventario\Application\Relatorio\Aba;
use Inventario\Application\Relatorio\Bloco;
use Inventario\Application\Relatorio\Relatorio;
use RuntimeException;
use ZipArchive;

/**
 * Relatorio em XLSX com a identidade visual do VBA: banda de titulo, cabecalho
 * navy, linhas coloridas pelo status, chip colorido na coluna de status,
 * autofiltro e painel congelado na tabela principal.
 *
 * Grava o XML de cada aba direto em arquivo, linha a linha: a memoria nao
 * cresce com o tamanho da base (montar a planilha inteira em objetos estourava
 * os 512 MB de um servidor pequeno com 30 mil linhas).
 */
final class ExportadorXlsx implements Exportador
{
    private const NAVY = '111827';
    private const NS = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
    private const NS_R = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    public function extensao(): string
    {
        return 'xlsx';
    }

    /** @var array<int|string, int> texto => indice na tabela de textos compartilhados */
    private array $textos = [];

    public function exportar(Relatorio $r, string $caminho): string
    {
        $this->textos = [];
        $estilos = new EstilosXlsx();
        $temps = [];
        $nomes = [];
        $filtros = [];
        try {
            foreach ($r->abas as $i => $a) {
                $nome = self::nomeAba($a->nome, $nomes);
                $nomes[] = $nome;
                $tmp = tempnam(sys_get_temp_dir(), 'aba');
                if ($tmp === false) {
                    throw new RuntimeException('sem espaco temporario para gravar o XLSX');
                }
                $temps[] = $tmp;
                $filtros[$i] = $this->gravarAba($a, $tmp, $estilos);
            }

            $zip = new ZipArchive();
            if ($zip->open($caminho, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException("nao foi possivel criar {$caminho}");
            }
            $zip->addFromString('[Content_Types].xml', $this->tipos(count($nomes)));
            $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
                . '</Relationships>');
            $zip->addFromString('xl/workbook.xml', $this->workbook($nomes, $filtros));
            $zip->addFromString('xl/_rels/workbook.xml.rels', $this->relacoes(count($nomes)));
            $zip->addFromString('xl/styles.xml', $estilos->xml());
            $sst = tempnam(sys_get_temp_dir(), 'sst');
            if ($sst === false) {
                throw new RuntimeException('sem espaco temporario para gravar o XLSX');
            }
            $this->gravarTextos($sst);
            $zip->addFile($sst, 'xl/sharedStrings.xml');
            $zip->setCompressionName('xl/sharedStrings.xml', ZipArchive::CM_DEFLATE, 1);
            foreach ($temps as $i => $tmp) {
                $nomeZip = 'xl/worksheets/sheet' . ($i + 1) . '.xml';
                $zip->addFile($tmp, $nomeZip);
                // o nivel padrao de compressao levava ~7 s em 30 mil linhas; o nivel 1
                // leva uma fracao disso e o arquivo cresce pouco (XML repete muito)
                $zip->setCompressionName($nomeZip, ZipArchive::CM_DEFLATE, 1);
            }
            $zip->close();
        } finally {
            foreach ($temps as $tmp) {
                @unlink($tmp);
            }
            if (isset($sst) && is_string($sst)) {
                @unlink($sst);
            }
        }

        return $caminho;
    }

    /** @return string|null faixa do autofiltro (ex.: A5:P120) */
    private function gravarAba(Aba $a, string $arquivo, EstilosXlsx $e): ?string
    {
        $nCols = 4;
        foreach ($a->blocos as $b) {
            $nCols = max($nCols, count($b->cabecalho));
        }
        $ult = self::col($nCols - 1);

        // 1a passada: onde fica a maior tabela (autofiltro + painel congelado)
        $linha = 4;
        $principal = null;
        foreach ($a->blocos as $b) {
            $hdr = $linha + ($b->titulo !== '' ? 1 : 0);
            $nLin = count($b->linhas);
            if ($principal === null || $nLin > $principal[2]) {
                $principal = [$hdr, count($b->cabecalho), $nLin];
            }
            $linha = $hdr + 1 + ($nLin === 0 ? ($b->vazio !== '' ? 1 : 0) : $nLin) + 2;
        }
        $filtro = null;
        $congela = '';
        if ($principal !== null && $principal[2] > 0) {
            $filtro = 'A' . $principal[0] . ':' . self::col($principal[1] - 1) . ($principal[0] + $principal[2]);
        }
        // congela so quando a tabela comeca no alto (no painel ela fica la embaixo)
        if ($filtro !== null && $principal[0] <= 12) {
            $topo = $principal[0] + 1;
            $congela = '<pane ySplit="' . $principal[0] . "\" topLeftCell=\"A{$topo}\" activePane=\"bottomLeft\" state=\"frozen\"/>"
                . "<selection pane=\"bottomLeft\" activeCell=\"A{$topo}\" sqref=\"A{$topo}\"/>";
        }

        $f = fopen($arquivo, 'wb');
        if ($f === false) {
            throw new RuntimeException('nao foi possivel gravar a aba ' . $a->nome);
        }
        $cols = '';
        $larg = $a->larguras !== [] ? $a->larguras : array_fill(0, $nCols, 18);
        foreach ($larg as $i => $w) {
            $n = $i + 1;
            $cols .= "<col min=\"{$n}\" max=\"{$n}\" width=\"{$w}\" customWidth=\"1\"/>";
        }
        fwrite($f, '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="' . self::NS . '" xmlns:r="' . self::NS_R . '">'
            . '<sheetPr><tabColor rgb="FF' . $a->corGuia . '"/></sheetPr>'
            . '<dimension ref="A1:' . $ult . max(2, $linha - 3) . '"/>'
            . '<sheetViews><sheetView showGridLines="0" workbookViewId="0">' . $congela . '</sheetView></sheetViews>'
            . '<sheetFormatPr defaultRowHeight="15"/>'
            . "<cols>{$cols}</cols><sheetData>");

        $merges = ["A1:{$ult}1", "A2:{$ult}2"];
        fwrite($f, self::linha(1, [$a->titulo], $e->estilo(16, true, 'FFFFFF', $a->corGuia, alinhar: 'center'), $nCols, 30));
        fwrite($f, self::linha(2, [$a->subtitulo], $e->estilo(9, false, 'C8D2E1', self::NAVY, alinhar: 'center'), $nCols));

        $r = 4;
        foreach ($a->blocos as $b) {
            $r = $this->gravarBloco($f, $b, $r, $e, $merges) + 2;
        }
        fwrite($f, '</sheetData>');
        if ($filtro !== null) {
            fwrite($f, "<autoFilter ref=\"{$filtro}\"/>");
        }
        fwrite($f, '<mergeCells count="' . count($merges) . '">');
        foreach ($merges as $m) {
            fwrite($f, "<mergeCell ref=\"{$m}\"/>");
        }
        fwrite($f, '</mergeCells><pageMargins left="0.7" right="0.7" top="0.75" bottom="0.75" header="0.3" footer="0.3"/></worksheet>');
        fclose($f);

        return $filtro;
    }

    /**
     * @param resource $f
     * @param list<string> $merges
     * @return int proxima linha livre
     */
    private function gravarBloco($f, Bloco $b, int $r, EstilosXlsx $e, array &$merges): int
    {
        $n = max(1, count($b->cabecalho));
        $ult = self::col($n - 1);
        if ($b->titulo !== '') {
            $merges[] = "A{$r}:{$ult}{$r}";
            fwrite($f, self::linha($r, [$b->titulo], $e->estilo(11, true, 'FFFFFF', '1F2937', alinhar: 'center'), $n, 20));
            $r++;
        }
        fwrite($f, self::linha($r, $b->cabecalho, $e->estilo(10, true, 'EBF0F8', self::NAVY, alinhar: 'center', quebra: true), $n, 28));
        $r++;
        if ($b->linhas === []) {
            if ($b->vazio !== '') {
                fwrite($f, self::linha($r, [$b->vazio], $e->estilo(9, false, '008000', italico: true), 1));
                $r++;
            }

            return $r;
        }

        $moeda = array_flip($b->colunasMoeda);
        $cols = [];
        for ($i = 0; $i < $n; $i++) {
            $cols[$i] = '<c r="' . self::col($i);
        }
        $cache = [];
        $corPorStatus = [];
        $buf = '';
        foreach ($b->linhas as $l) {
            // a cor depende so do valor da coluna de status: calcula uma vez por valor
            $sv = $b->colunaStatus === null ? '' : (string) ($l[$b->colunaStatus] ?? '');
            $cor = array_key_exists($sv, $corPorStatus) ? $corPorStatus[$sv] : ($corPorStatus[$sv] = $b->corDaLinha($l));
            $chave = $cor === null ? '-' : $cor[0] . $cor[1];
            if (!isset($cache[$chave])) {
                $st = [];
                for ($i = 0; $i < $n; $i++) {
                    $st[$i] = $cor !== null && $i === $b->colunaStatus
                        ? $e->estilo(9, true, self::contraste($cor[1]), $cor[1], isset($moeda[$i]), 'center')
                        : $e->estilo(9, false, '2D3440', $cor[0] ?? null, isset($moeda[$i]));
                }
                // pedacos prontos por coluna: celula vazia, texto e numero
                $vazia = $txt = $num = [];
                foreach ($st as $i => $k) {
                    $sx = $k > 0 ? " s=\"{$k}\"" : '';
                    $vazia[$i] = $cor !== null && $k > 0 ? "\"{$sx}/>" : null;
                    $txt[$i] = "\"{$sx} t=\"s\"><v>";
                    $num[$i] = "\"{$sx}><v>";
                }
                $cache[$chave] = [$vazia, $txt, $num];
            }
            [$vazia, $txt, $num] = $cache[$chave];
            // pedacos prontos + textos compartilhados: cada celula e uma concatenacao
            $buf .= "<row r=\"{$r}\">";
            $vals = array_values($l);
            for ($i = 0; $i < $n; $i++) {
                $v = $vals[$i] ?? null;
                if ($v === null || $v === '') {
                    if ($vazia[$i] !== null) {
                        $buf .= $cols[$i] . $r . $vazia[$i];
                    }
                } elseif (is_string($v)) {
                    $buf .= $cols[$i] . $r . $txt[$i] . ($this->textos[$v] ??= count($this->textos)) . '</v></c>';
                } elseif ((is_int($v) || is_float($v)) && is_finite((float) $v)) {
                    $buf .= $cols[$i] . $r . $num[$i] . self::numero($v) . '</v></c>';
                } elseif (is_bool($v)) {
                    $buf .= $cols[$i] . $r . substr($num[$i], 0, -4) . ' t="b"><v>' . ($v ? 1 : 0) . '</v></c>';
                } else {
                    $buf .= $cols[$i] . $r . $txt[$i] . ($this->textos[(string) $v] ??= count($this->textos)) . '</v></c>';
                }
            }
            $buf .= '</row>';
            $r++;
            if (strlen($buf) > 1 << 20) {
                fwrite($f, $buf);
                $buf = '';
            }
        }
        fwrite($f, $buf);

        return $r;
    }

    /** Tabela de textos compartilhados: cada texto distinto gravado uma vez. */
    private function gravarTextos(string $arquivo): void
    {
        $f = fopen($arquivo, 'wb');
        if ($f === false) {
            throw new RuntimeException('nao foi possivel gravar os textos do XLSX');
        }
        $n = count($this->textos);
        fwrite($f, '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<sst xmlns="' . self::NS . "\" count=\"{$n}\" uniqueCount=\"{$n}\">");
        $buf = '';
        foreach (array_keys($this->textos) as $t) {
            $buf .= '<si><t xml:space="preserve">' . self::xml((string) $t) . '</t></si>';
            if (strlen($buf) > 1 << 20) {
                fwrite($f, $buf);
                $buf = '';
            }
        }
        fwrite($f, $buf . '</sst>');
        fclose($f);
    }

    /** @param list<mixed> $valores */
    private static function linha(int $r, array $valores, int $estilo, int $nCols, ?int $altura = null): string
    {
        $x = "<row r=\"{$r}\"" . ($altura !== null ? " ht=\"{$altura}\" customHeight=\"1\"" : '') . '>';
        for ($i = 0; $i < $nCols; $i++) {
            $x .= self::celula(self::col($i) . $r, $valores[$i] ?? null, $estilo, true);
        }

        return $x . '</row>';
    }

    private static function celula(string $ref, mixed $v, int $estilo, bool $vazioComEstilo): string
    {
        $s = $estilo > 0 ? " s=\"{$estilo}\"" : '';
        if ($v === null || $v === '') {
            return $vazioComEstilo && $estilo > 0 ? "<c r=\"{$ref}\"{$s}/>" : '';
        }
        if (is_bool($v)) {
            return "<c r=\"{$ref}\"{$s} t=\"b\"><v>" . ($v ? 1 : 0) . '</v></c>';
        }
        if ((is_int($v) || is_float($v)) && is_finite((float) $v)) {
            return "<c r=\"{$ref}\"{$s}><v>" . self::numero($v) . '</v></c>';
        }

        // texto sempre como texto: codigo nao vira numero e "=..." nao vira formula
        return "<c r=\"{$ref}\"{$s} t=\"inlineStr\"><is><t xml:space=\"preserve\">" . self::xml((string) $v) . '</t></is></c>';
    }

    private static function numero(int|float $v): string
    {
        if (is_int($v)) {
            return (string) $v;
        }
        if ($v == floor($v) && abs($v) < 1e15) {
            return (string) (int) $v;
        }
        $s = rtrim(rtrim(sprintf('%.10F', $v), '0'), '.');

        return $s === '-0' ? '0' : $s;
    }

    private static function xml(string $s): string
    {
        if (strlen($s) > 32767) {
            $s = mb_substr($s, 0, 32767);
        }
        // caracteres de controle sao invalidos em XML; checagem barata antes de substituir
        if (strpbrk($s, "\x00\x01\x02\x03\x04\x05\x06\x07\x08\x0B\x0C\x0E\x0F\x10\x11\x12\x13\x14\x15\x16\x17\x18\x19\x1A\x1B\x1C\x1D\x1E\x1F") !== false) {
            $s = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $s);
        }

        // ENT_SUBSTITUTE troca sequencias UTF-8 invalidas em vez de devolver vazio
        return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** 0 -> A, 26 -> AA */
    public static function col(int $i): string
    {
        $s = '';
        for ($i++; $i > 0; $i = intdiv($i - 1, 26)) {
            $s = chr(65 + ($i - 1) % 26) . $s;
        }

        return $s;
    }

    /** @param list<string> $usados */
    private static function nomeAba(string $nome, array $usados): string
    {
        $base = mb_substr(str_replace(['\\', '/', '?', '*', '[', ']', ':'], '_', $nome), 0, 31);
        $n = $base;
        for ($k = 2; in_array($n, $usados, true); $k++) {
            $n = mb_substr($base, 0, 28) . " {$k}";
        }

        return $n;
    }

    /**
     * @param list<string> $nomes
     * @param array<int, ?string> $filtros
     */
    private function workbook(array $nomes, array $filtros): string
    {
        $sheets = '';
        $def = '';
        foreach ($nomes as $i => $nome) {
            $id = $i + 1;
            $nx = self::xml($nome);
            $sheets .= "<sheet name=\"{$nx}\" sheetId=\"{$id}\" r:id=\"rId{$id}\"/>";
            if (($filtros[$i] ?? null) !== null) {
                $faixa = preg_replace('/([A-Z]+)(\d+)/', '\$$1\$$2', $filtros[$i]);
                $def .= "<definedName name=\"_xlnm._FilterDatabase\" localSheetId=\"{$i}\" hidden=\"1\">'"
                    . self::xml(str_replace("'", "''", $nome)) . "'!{$faixa}</definedName>";
            }
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="' . self::NS . '" xmlns:r="' . self::NS_R . '">'
            . '<bookViews><workbookView activeTab="0"/></bookViews>'
            . "<sheets>{$sheets}</sheets>"
            . ($def !== '' ? "<definedNames>{$def}</definedNames>" : '')
            . '</workbook>';
    }

    private function relacoes(int $n): string
    {
        $x = '';
        for ($i = 1; $i <= $n; $i++) {
            $x .= "<Relationship Id=\"rId{$i}\" Type=\"http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet\" Target=\"worksheets/sheet{$i}.xml\"/>";
        }
        $s = $n + 1;
        $t = $n + 2;

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . $x
            . "<Relationship Id=\"rId{$s}\" Type=\"http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles\" Target=\"styles.xml\"/>"
            . "<Relationship Id=\"rId{$t}\" Type=\"http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings\" Target=\"sharedStrings.xml\"/>"
            . '</Relationships>';
    }

    private function tipos(int $n): string
    {
        $x = '';
        for ($i = 1; $i <= $n; $i++) {
            $x .= "<Override PartName=\"/xl/worksheets/sheet{$i}.xml\" ContentType=\"application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml\"/>";
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>'
            . $x . '</Types>';
    }

    /** Branco sobre chip escuro, preto sobre chip claro. */
    private static function contraste(string $hex): string
    {
        [$r, $g, $b] = sscanf($hex, '%02x%02x%02x');

        return ($r * 299 + $g * 587 + $b * 114) / 1000 > 160 ? '000000' : 'FFFFFF';
    }
}
