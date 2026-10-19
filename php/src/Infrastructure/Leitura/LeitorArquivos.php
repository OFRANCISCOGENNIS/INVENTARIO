<?php

declare(strict_types=1);

namespace Inventario\Infrastructure\Leitura;

use Inventario\Application\Carga\FalhaLeitura;
use Inventario\Application\Porta\LeitorPlanilhas;

/**
 * Escolhe o leitor pela extensao do arquivo (Strategy).
 */
final class LeitorArquivos implements LeitorPlanilhas
{
    /** @param list<LeitorPlanilha> $leitores */
    public function __construct(private readonly array $leitores)
    {
    }

    public static function padrao(): self
    {
        return new self([new LeitorExcel(), new LeitorCsv()]);
    }

    /**
     * @return array<string, Tabela>
     * @throws FalhaLeitura
     */
    public function ler(string $caminho, ?string $nomeOriginal = null): array
    {
        if (!is_file($caminho) || !is_readable($caminho)) {
            throw new FalhaLeitura('arquivo nao encontrado');
        }
        $ext = strtolower(pathinfo($nomeOriginal ?? $caminho, PATHINFO_EXTENSION));
        foreach ($this->leitores as $l) {
            if ($l->suporta($ext)) {
                return $l->ler($caminho);
            }
        }
        throw new FalhaLeitura("formato .{$ext} nao suportado (use xlsx, xlsm, xls, ods ou csv)");
    }
}
