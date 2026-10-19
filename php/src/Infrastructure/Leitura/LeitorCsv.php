<?php

declare(strict_types=1);

namespace Inventario\Infrastructure\Leitura;

use Inventario\Application\Carga\FalhaLeitura;
use Inventario\Application\Carga\Tabela;

/**
 * CSV com ; ou , (detectado pela 1a linha), em UTF-8 ou Windows-1252.
 * Numeros ficam como texto; a conversao (virgula decimal) e feita no dominio.
 */
final class LeitorCsv implements LeitorPlanilha
{
    public function suporta(string $extensao): bool
    {
        return in_array($extensao, ['csv', 'txt'], true);
    }

    public function ler(string $caminho): array
    {
        $conteudo = @file_get_contents($caminho);
        if ($conteudo === false) {
            throw new FalhaLeitura('nao foi possivel abrir');
        }
        if (str_starts_with($conteudo, "\xEF\xBB\xBF")) {
            $conteudo = substr($conteudo, 3);
        }
        if (!mb_check_encoding($conteudo, 'UTF-8')) {
            $conteudo = mb_convert_encoding($conteudo, 'UTF-8', 'Windows-1252');
        }
        $primeira = strtok($conteudo, "\r\n") ?: '';
        $sep = substr_count($primeira, ';') >= substr_count($primeira, ',') ? ';' : ',';

        $fh = fopen('php://temp', 'r+');
        fwrite($fh, $conteudo);
        rewind($fh);
        $matriz = [];
        while (($row = fgetcsv($fh, null, $sep, '"', '')) !== false) {
            if ($row === [null]) {
                $row = [];
            }
            $matriz[] = array_map(static fn ($v) => $v === null || trim((string) $v) === '' ? null : $v, $row);
        }
        fclose($fh);

        $aba = pathinfo($caminho, PATHINFO_FILENAME);

        return [$aba => Tabela::deMatriz($aba, $matriz)];
    }
}
