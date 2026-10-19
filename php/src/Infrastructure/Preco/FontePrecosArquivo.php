<?php

declare(strict_types=1);

namespace Inventario\Infrastructure\Preco;

use DateTimeImmutable;
use Inventario\Domain\Preco\FontePrecos;
use Inventario\Domain\Preco\SituacaoFontePrecos;
use Inventario\Domain\Preco\TabelaPrecos;
use Inventario\Application\Carga\FalhaLeitura;
use Inventario\Application\Porta\LeitorPlanilhas;

/**
 * Arquivo externo "BASE DE PRECOS.xlsx" (1a aba). Sem arquivo devolve null
 * para a cadeia tentar a proxima fonte; arquivo que nao abre vira NAO ABRIU.
 */
final class FontePrecosArquivo implements FontePrecos
{
    public function __construct(
        private readonly string $caminho,
        private readonly LeitorPlanilhas $leitor,
    ) {
    }

    public function carregar(): ?TabelaPrecos
    {
        if (!is_file($this->caminho)) {
            return null;
        }
        $fonte = "arquivo externo '" . basename($this->caminho) . "'";
        $mtime = @filemtime($this->caminho);
        $data = $mtime === false ? null : (new DateTimeImmutable())->setTimestamp($mtime);
        try {
            $abas = $this->leitor->ler($this->caminho);
        } catch (FalhaLeitura) {
            return new TabelaPrecos([], $fonte . ' NAO ABRIU', SituacaoFontePrecos::NAO_ABRIU, $data);
        }
        $primeira = reset($abas);
        if ($primeira === false) {
            return new TabelaPrecos([], $fonte . ' (vazia: nenhum material lido)', SituacaoFontePrecos::VAZIA, $data);
        }

        return InterpretadorFaixaPrecos::interpretar($primeira, $fonte, $data);
    }
}
