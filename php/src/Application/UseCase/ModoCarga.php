<?php

declare(strict_types=1);

namespace Inventario\Application\UseCase;

enum ModoCarga: string
{
    /** Uma analise com todas as bases (coluna ARQUIVO ORIGEM). */
    case JUNTAR = 'juntar';
    /** Uma analise por base; sem historico (compararia bases diferentes). */
    case SEPARAR = 'separar';
}
