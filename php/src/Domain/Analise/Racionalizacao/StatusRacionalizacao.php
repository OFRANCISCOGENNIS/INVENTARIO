<?php

declare(strict_types=1);

namespace Inventario\Domain\Analise\Racionalizacao;

enum StatusRacionalizacao: string
{
    case ANCORA = 'ANCORA';
    case OK = 'OK';
    case INSUFICIENTE = 'INSUFICIENTE';
    case EXCESSO = 'EXCESSO';
    case EXCESSO_EXAGERADO = 'EXCESSO EXAGERADO';
    case SEM_ANCORA = 'SEM ANCORA';
    case QTD_ZERO = 'QTD ZERO';
    case ESTORNO = 'ESTORNO SEM ENTRADA';
    case SEM_REFERENCIA = 'SEM REFERENCIA';

    /** Rotulo exibido na coluna STATUS (e lido pelo ranking). */
    public function rotulo(): string
    {
        return match ($this) {
            self::OK => 'OK',
            self::ANCORA => 'REFERENCIA',
            self::EXCESSO => 'EXCESSO',
            self::EXCESSO_EXAGERADO => 'EXAGERADO',
            self::INSUFICIENTE => 'FALTANDO',
            self::SEM_ANCORA => 'SEM ANCORA',
            self::QTD_ZERO => 'ZERO',
            self::ESTORNO => 'ESTORNO',
            self::SEM_REFERENCIA => 'SEM REFERENCIA',
        };
    }

    /** COM fora do previsto pela NT.006 (conta no ranking de risco). */
    public function foraDoPrevisto(): bool
    {
        return in_array($this, [self::INSUFICIENTE, self::EXCESSO, self::EXCESSO_EXAGERADO, self::QTD_ZERO], true);
    }
}
