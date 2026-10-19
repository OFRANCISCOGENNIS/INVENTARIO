<?php

declare(strict_types=1);

namespace Inventario\Infrastructure\Preco;

use Inventario\Application\Porta\FabricaFontePrecos;
use Inventario\Application\Porta\LeitorPlanilhas;
use Inventario\Domain\Preco\FontePrecos;
use Inventario\Domain\Support\Texto;

/**
 * Cadeia de precos: aba interna do arquivo da base -> arquivo informado ->
 * "BASE DE PRECOS.xlsx" (ou .csv) na pasta de dados.
 */
final class FabricaFontePrecosPadrao implements FabricaFontePrecos
{
    public function __construct(
        private readonly LeitorPlanilhas $leitor,
        private readonly string $pastaDados,
    ) {
    }

    public function fonte(array $abas, ?string $arquivoPrecos): FontePrecos
    {
        $fontes = [new FontePrecosAba($abas)];
        if ($arquivoPrecos !== null && $arquivoPrecos !== '') {
            $fontes[] = new FontePrecosArquivo($arquivoPrecos, $this->leitor);
        }
        foreach (['BASE DE PRECOS.xlsx', 'BASE DE PREÇOS.xlsx', 'BASE DE PRECOS.csv'] as $nome) {
            $fontes[] = new FontePrecosArquivo($this->pastaDados . DIRECTORY_SEPARATOR . $nome, $this->leitor);
        }

        return new CadeiaFontesPrecos($fontes);
    }

    public function precosReferencia(array $abas): array
    {
        foreach ($abas as $nome => $t) {
            if (Texto::norm((string) $nome) === 'PRECOS') {
                return InterpretadorFaixaPrecos::precosReferencia($t);
            }
        }

        return [];
    }
}
