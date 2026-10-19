<?php

declare(strict_types=1);

namespace Inventario\Domain\Preco;

enum SituacaoFontePrecos: string
{
    case OK = 'OK';
    case NENHUMA = 'NENHUMA';
    case NAO_ABRIU = 'NAO ABRIU';
    case VAZIA = 'VAZIA';

    /** Fonte que deixa PRECO UNITARIO e os alertas de preco sem referencia. */
    public function ruim(): bool
    {
        return $this !== self::OK;
    }
}
