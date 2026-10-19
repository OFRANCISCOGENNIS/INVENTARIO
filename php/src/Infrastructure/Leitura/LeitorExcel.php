<?php

declare(strict_types=1);

namespace Inventario\Infrastructure\Leitura;

use Inventario\Application\Carga\FalhaLeitura;
use Inventario\Application\Carga\Tabela;

use PhpOffice\PhpSpreadsheet\IOFactory;
use Throwable;

/**
 * XLSX / XLSM / XLS / ODS via PhpSpreadsheet, so leitura de valores
 * (celulas com formula entram pelo valor calculado salvo no arquivo).
 */
final class LeitorExcel implements LeitorPlanilha
{
    public function suporta(string $extensao): bool
    {
        return in_array($extensao, ['xlsx', 'xlsm', 'xls', 'ods'], true);
    }

    public function ler(string $caminho): array
    {
        try {
            $reader = IOFactory::createReaderForFile($caminho);
            $reader->setReadDataOnly(true);
            $book = $reader->load($caminho);
        } catch (Throwable $e) {
            throw new FalhaLeitura('nao foi possivel abrir (' . $e->getMessage() . ')', 0, $e);
        }
        $abas = [];
        foreach ($book->getWorksheetIterator() as $ws) {
            $matriz = $ws->toArray(null, true, false, false);
            $abas[$ws->getTitle()] = Tabela::deMatriz($ws->getTitle(), array_values(array_map('array_values', $matriz)));
        }
        $book->disconnectWorksheets();

        return $abas;
    }
}
