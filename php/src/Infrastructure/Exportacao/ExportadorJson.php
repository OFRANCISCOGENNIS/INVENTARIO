<?php

declare(strict_types=1);

namespace Inventario\Infrastructure\Exportacao;

use Inventario\Application\Relatorio\Relatorio;

/** Relatorio em JSON: cada aba com seus blocos como lista de objetos. */
final class ExportadorJson implements Exportador
{
    public function extensao(): string
    {
        return 'json';
    }

    public function exportar(Relatorio $r, string $caminho): string
    {
        // sem JSON_PRETTY_PRINT: os recuos dobravam o arquivo (74 MB em 30 mil linhas)
        file_put_contents($caminho, json_encode(self::paraArray($r),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_INVALID_UTF8_SUBSTITUTE));

        return $caminho;
    }

    /** @return array<string, mixed> */
    public static function paraArray(Relatorio $r): array
    {
        $abas = [];
        foreach ($r->abas as $a) {
            $blocos = [];
            foreach ($a->blocos as $b) {
                $blocos[] = [
                    'titulo' => $b->titulo,
                    'linhas' => array_map(static fn (array $l): array => array_combine($b->cabecalho, array_pad($l, count($b->cabecalho), null)), $b->linhas),
                ];
            }
            $abas[$a->nome] = ['titulo' => $a->titulo, 'subtitulo' => $a->subtitulo, 'blocos' => $blocos];
        }

        return ['base' => $r->base, 'gerado_em' => $r->geradoEm, 'selo' => $r->selo, 'abas' => $abas];
    }
}
