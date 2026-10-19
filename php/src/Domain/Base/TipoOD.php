<?php

declare(strict_types=1);

namespace Inventario\Domain\Base;

/**
 * Tipo de ordem (OD) do PEP: sufixo do PEP4 (.I/.M/.S/.D) ou, sem sufixo, a
 * coluna TIPO PEP (I/M/S/D ou ODI/ODM/ODS/ODD).
 */
enum TipoOD: string
{
    case ODI = 'ODI';
    case ODM = 'ODM';
    case ODS = 'ODS';
    case ODD = 'ODD';
    case NENHUM = '';

    /** Recebe o PEP4 CRU: a normalizacao remove o ponto e apagaria o sufixo. */
    public static function classificar(string $pep4Bruto, string $tipoPepBruto = ''): self
    {
        $suf = strtoupper(substr(trim($pep4Bruto), -2));
        $porSufixo = match ($suf) {
            '.I' => self::ODI,
            '.M' => self::ODM,
            '.S' => self::ODS,
            '.D' => self::ODD,
            default => null,
        };
        if ($porSufixo !== null) {
            return $porSufixo;
        }

        return match (strtoupper(trim($tipoPepBruto))) {
            'I', 'ODI' => self::ODI,
            'M', 'ODM' => self::ODM,
            'S', 'ODS' => self::ODS,
            'D', 'ODD' => self::ODD,
            default => self::NENHUM,
        };
    }

    public function rotulo(): string
    {
        return $this === self::NENHUM ? '-' : $this->value;
    }
}
