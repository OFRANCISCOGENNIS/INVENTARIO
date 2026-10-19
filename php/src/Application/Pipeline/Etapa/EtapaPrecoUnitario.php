<?php

declare(strict_types=1);

namespace Inventario\Application\Pipeline\Etapa;

use Inventario\Application\Pipeline\ContextoAnalise;
use Inventario\Application\Pipeline\Etapa;
use Inventario\Domain\Preco\AnalisadorPrecoUnitario;
use Inventario\Domain\Preco\SituacaoFontePrecos;
use Inventario\Domain\Preco\TabelaPrecos;

final class EtapaPrecoUnitario implements Etapa
{
    public function nome(): string
    {
        return 'PRECO UNITARIO';
    }

    public function executar(ContextoAnalise $ctx): void
    {
        $tabela = $ctx->fontePrecos->carregar()
            ?? TabelaPrecos::semFonte('NENHUMA (nenhuma base de precos informada ou encontrada)');
        $ctx->tabelaPrecos = $tabela;
        $ctx->saude->definirFontePrecos($tabela);
        $aviso = match ($tabela->situacao) {
            SituacaoFontePrecos::NENHUMA => 'PRECOS: nenhuma base de precos encontrada - PRECO UNITARIO e alertas de preco ficam sem referencia',
            SituacaoFontePrecos::NAO_ABRIU => 'PRECOS: ' . $tabela->fonte . ' - PRECO UNITARIO e alertas de preco ficam sem referencia',
            SituacaoFontePrecos::VAZIA => 'PRECOS: a base de precos usada nao tem materiais legiveis',
            SituacaoFontePrecos::OK => null,
        };
        if ($aviso !== null) {
            $ctx->saude->aviso($aviso);
        }
        $ctx->precoUnitario = (new AnalisadorPrecoUnitario())->analisar($ctx->base, $tabela);
    }
}
