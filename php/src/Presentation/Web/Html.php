<?php

declare(strict_types=1);

namespace Inventario\Presentation\Web;

final class Html
{
    public static function e(mixed $v): string
    {
        return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** Numero pt-BR para celulas; texto como veio. */
    public static function celula(mixed $v, bool $moeda): string
    {
        if (is_float($v) || is_int($v)) {
            $casas = $moeda || (is_float($v) && floor($v) !== $v) ? 2 : 0;

            return number_format((float) $v, $casas, ',', '.');
        }

        return self::e($v);
    }
}
