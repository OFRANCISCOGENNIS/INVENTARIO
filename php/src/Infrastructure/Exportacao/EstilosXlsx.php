<?php

declare(strict_types=1);

namespace Inventario\Infrastructure\Exportacao;

/**
 * Registro dos estilos usados ao gravar o XLSX: cada combinacao de fonte,
 * preenchimento, formato numerico e alinhamento vira um indice de cellXfs.
 */
final class EstilosXlsx
{
    public const FONTE = 'Segoe UI';

    /** @var array<string, int> */
    private array $fontes = [];
    /** @var array<string, int> */
    private array $fills = [];
    /** @var array<string, int> */
    private array $xfs = [];

    public function __construct()
    {
        $this->fonte(9, false, false, '2D3440');
        $this->xfs['padrao'] = 0;
    }

    /**
     * @param string|null $fundo hex sem # (null = sem preenchimento)
     * @param string|null $alinhar 'center' | 'left' | null
     */
    public function estilo(
        int $tamanho = 9,
        bool $negrito = false,
        string $corFonte = '2D3440',
        ?string $fundo = null,
        bool $moeda = false,
        ?string $alinhar = null,
        bool $quebra = false,
        bool $italico = false,
    ): int {
        $font = $this->fonte($tamanho, $negrito, $italico, $corFonte);
        $fill = $fundo === null ? 0 : $this->fill($fundo);
        $chave = "{$font}|{$fill}|" . ($moeda ? 4 : 0) . '|' . ($alinhar ?? '') . '|' . ($quebra ? 1 : 0);

        return $this->xfs[$chave] ??= count($this->xfs);
    }

    public function xml(): string
    {
        $fonts = '';
        foreach (array_keys($this->fontes) as $k) {
            [$tam, $neg, $ita, $cor] = explode('|', (string) $k);
            $fonts .= '<font>' . ($neg === '1' ? '<b/>' : '') . ($ita === '1' ? '<i/>' : '')
                . "<sz val=\"{$tam}\"/><color rgb=\"FF{$cor}\"/><name val=\"" . self::FONTE . '"/></font>';
        }
        $fills = '<fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>';
        foreach (array_keys($this->fills) as $cor) {
            $fills .= "<fill><patternFill patternType=\"solid\"><fgColor rgb=\"FF{$cor}\"/><bgColor indexed=\"64\"/></patternFill></fill>";
        }
        $xfs = '';
        foreach (array_keys($this->xfs) as $k) {
            if ($k === 'padrao') {
                $xfs .= '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>';
                continue;
            }
            [$font, $fill, $fmt, $al, $wrap] = explode('|', (string) $k);
            $attrs = "numFmtId=\"{$fmt}\" fontId=\"{$font}\" fillId=\"{$fill}\" borderId=\"0\" xfId=\"0\""
                . ($fmt !== '0' ? ' applyNumberFormat="1"' : '') . ($fill !== '0' ? ' applyFill="1"' : '')
                . ' applyFont="1" applyAlignment="1"';
            $align = '<alignment vertical="center"' . ($al !== '' ? " horizontal=\"{$al}\"" : '')
                . ($wrap === '1' ? ' wrapText="1"' : '') . '/>';
            $xfs .= "<xf {$attrs}>{$align}</xf>";
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="' . count($this->fontes) . "\">{$fonts}</fonts>"
            . '<fills count="' . (count($this->fills) + 2) . "\">{$fills}</fills>"
            . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="' . count($this->xfs) . "\">{$xfs}</cellXfs>"
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>';
    }

    private function fonte(int $tamanho, bool $negrito, bool $italico, string $cor): int
    {
        $k = $tamanho . '|' . ($negrito ? 1 : 0) . '|' . ($italico ? 1 : 0) . '|' . strtoupper($cor);

        return $this->fontes[$k] ??= count($this->fontes);
    }

    private function fill(string $cor): int
    {
        return $this->fills[strtoupper($cor)] ??= count($this->fills) + 2;
    }
}
