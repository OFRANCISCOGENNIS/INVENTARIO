<?php

declare(strict_types=1);

namespace Inventario\Infrastructure\Leitura;

use Inventario\Application\Carga\FalhaLeitura;
use Inventario\Application\Carga\Tabela;
use XMLReader;
use ZipArchive;

/**
 * XLSX / XLSM lidos em fluxo (XMLReader), sem montar a planilha inteira na
 * memoria: uma base de dezenas de milhares de linhas cabe nos 512 MB de um
 * servidor pequeno. Celula com formula entra pelo valor salvo pelo Excel.
 */
final class LeitorXlsx implements LeitorPlanilha
{
    private const NS_REL = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    public function suporta(string $extensao): bool
    {
        return in_array($extensao, ['xlsx', 'xlsm'], true);
    }

    public function ler(string $caminho): array
    {
        $zip = new ZipArchive();
        if ($zip->open($caminho) !== true) {
            throw new FalhaLeitura('nao foi possivel abrir (arquivo xlsx invalido)');
        }
        try {
            $alvos = $this->relacoes($zip);
            $textos = $this->textosCompartilhados($zip);
            $abas = [];
            foreach ($this->abas($zip) as [$nome, $rid]) {
                $alvo = $alvos[$rid] ?? null;
                if ($alvo === null) {
                    continue;
                }
                $xml = $zip->getFromName($alvo);
                if ($xml === false) {
                    continue;
                }
                $abas[$nome] = Tabela::deMatriz($nome, $this->linhas($xml, $textos));
            }
        } finally {
            $zip->close();
        }

        return $abas;
    }

    /** @return list<array{0: string, 1: string}> nome da aba e id da relacao, na ordem do arquivo */
    private function abas(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/workbook.xml');
        if ($xml === false) {
            throw new FalhaLeitura('arquivo xlsx sem xl/workbook.xml');
        }
        $r = new XMLReader();
        $r->XML($xml);
        $out = [];
        while ($r->read()) {
            if ($r->nodeType === XMLReader::ELEMENT && $r->localName === 'sheet') {
                $out[] = [(string) $r->getAttribute('name'), (string) $r->getAttributeNs('id', self::NS_REL)];
            }
        }
        $r->close();

        return $out;
    }

    /** @return array<string, string> id da relacao => caminho da aba no zip */
    private function relacoes(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($xml === false) {
            return [];
        }
        $r = new XMLReader();
        $r->XML($xml);
        $out = [];
        while ($r->read()) {
            if ($r->nodeType === XMLReader::ELEMENT && $r->localName === 'Relationship') {
                $alvo = (string) $r->getAttribute('Target');
                $alvo = str_starts_with($alvo, '/') ? ltrim($alvo, '/') : 'xl/' . $alvo;
                $out[(string) $r->getAttribute('Id')] = $alvo;
            }
        }
        $r->close();

        return $out;
    }

    /** @return list<string> */
    private function textosCompartilhados(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($xml === false) {
            return [];
        }
        $r = new XMLReader();
        $r->XML($xml);
        $out = [];
        $atual = null;
        while ($r->read()) {
            if ($r->nodeType === XMLReader::ELEMENT && $r->localName === 'si') {
                $atual = '';
            } elseif ($r->nodeType === XMLReader::ELEMENT && $r->localName === 't' && $atual !== null) {
                $atual .= $r->readString();
            } elseif ($r->nodeType === XMLReader::ELEMENT && $r->localName === 'rPh') {
                $r->next(); // texto fonetico (japones) nao faz parte do valor
            } elseif ($r->nodeType === XMLReader::END_ELEMENT && $r->localName === 'si') {
                $out[] = (string) $atual;
                $atual = null;
            }
        }
        $r->close();

        return $out;
    }

    /**
     * @param list<string> $textos
     * @return list<list<mixed>>
     */
    private function linhas(string $xml, array $textos): array
    {
        $r = new XMLReader();
        $r->XML($xml);
        $linhas = [];
        $linha = null;
        $numLinha = 0;
        while ($r->read()) {
            if ($r->nodeType === XMLReader::ELEMENT && $r->localName === 'row') {
                $numLinha = (int) ($r->getAttribute('r') ?? ($numLinha + 1));
                $linha = [];
                if ($r->isEmptyElement) {
                    $linhas[$numLinha] = [];
                    $linha = null;
                }
            } elseif ($r->nodeType === XMLReader::END_ELEMENT && $r->localName === 'row' && $linha !== null) {
                $linhas[$numLinha] = $linha;
                $linha = null;
            } elseif ($r->nodeType === XMLReader::ELEMENT && $r->localName === 'c' && $linha !== null) {
                $ref = (string) $r->getAttribute('r');
                $col = $ref !== '' ? self::coluna($ref) : count($linha);
                $tipo = (string) ($r->getAttribute('t') ?? 'n');
                $valor = null;
                if (!$r->isEmptyElement) {
                    $valor = $this->valorCelula($r, $tipo, $textos);
                }
                if ($valor !== null && $valor !== '') {
                    $linha[$col] = $valor;
                }
            }
        }
        $r->close();
        if ($linhas === []) {
            return [];
        }

        // matriz densa a partir da linha 1, colunas ate a ultima usada
        $maxCol = 0;
        foreach ($linhas as $l) {
            if ($l !== []) {
                $maxCol = max($maxCol, max(array_keys($l)) + 1);
            }
        }
        $out = [];
        $ult = max(array_keys($linhas));
        for ($n = 1; $n <= $ult; $n++) {
            $l = $linhas[$n] ?? [];
            $densa = array_fill(0, $maxCol, null);
            foreach ($l as $c => $v) {
                $densa[$c] = $v;
            }
            $out[] = $densa;
        }

        return $out;
    }

    /** @param list<string> $textos */
    private function valorCelula(XMLReader $r, string $tipo, array $textos): mixed
    {
        $v = null;
        $inline = '';
        $prof = $r->depth;
        while ($r->read()) {
            if ($r->nodeType === XMLReader::END_ELEMENT && $r->localName === 'c' && $r->depth === $prof) {
                break;
            }
            if ($r->nodeType !== XMLReader::ELEMENT) {
                continue;
            }
            if ($r->localName === 'v') {
                $v = $r->readString();
            } elseif ($r->localName === 't') {
                $inline .= $r->readString();
            }
        }

        return match ($tipo) {
            's' => $v === null ? null : ($textos[(int) $v] ?? null),
            'inlineStr' => $inline,
            'str' => $v,
            'b' => $v === null ? null : $v === '1',
            'e' => null, // #N/D, #DIV/0!: celula com erro conta como vazia
            default => $v === null || $v === '' ? null : (is_numeric($v) ? (float) $v : $v),
        };
    }

    /** "AB12" -> 27 (indice 0-based da coluna). */
    public static function coluna(string $ref): int
    {
        $n = 0;
        $len = strlen($ref);
        for ($i = 0; $i < $len; $i++) {
            $ch = ord($ref[$i]);
            if ($ch < 65 || $ch > 90) {
                break;
            }
            $n = $n * 26 + ($ch - 64);
        }

        return $n - 1;
    }
}
