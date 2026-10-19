<?php

declare(strict_types=1);

namespace Inventario\Domain\Diagnostico;

enum StatusVerificacao: string
{
    case OK = 'OK';
    case ATENCAO = 'ATENCAO';
    case CRITICO = 'CRITICO';

    /** CRITICO acima de $pCrit, ATENCAO acima de $pAten (fracoes do total). */
    public static function porPercentual(int $qtd, int $total, float $pAten, float $pCrit): self
    {
        if ($total <= 0 || $qtd === 0) {
            return self::OK;
        }
        $p = $qtd / $total;

        return $p > $pCrit ? self::CRITICO : ($p > $pAten ? self::ATENCAO : self::OK);
    }
}
