<?php

declare(strict_types=1);

namespace Inventario\Application\Carga;

use Inventario\Domain\Base\MapeadorCabecalho;

/**
 * Resultado da leitura dos arquivos e a PREVIA (dry-run) do que sera carregado.
 */
final class LeituraBases
{
    /**
     * @param list<BlocoBase> $blocos
     * @param list<string> $erros arquivos que nao serao carregados e o motivo
     */
    public function __construct(
        public readonly array $blocos,
        public readonly array $erros,
    ) {
    }

    public function vazia(): bool
    {
        return $this->blocos === [];
    }

    /**
     * Previa por arquivo: linhas, aba, colunas ausentes no modo juntar e
     * colunas obrigatorias faltando.
     *
     * @return list<array{arquivo: string, aba: string, linhas: int, ausentes: list<string>, obrigatoriasFaltando: list<string>}>
     */
    public function previa(bool $juntar): array
    {
        $hdr = CarregadorBases::cabecalhoDestino($this->blocos);
        $out = [];
        foreach ($this->blocos as $i => $b) {
            $ausentes = [];
            if ($juntar && $i > 0) {
                [, $ausentes] = CarregadorBases::mapearBloco($b->tabela->cabecalho, $hdr);
            }
            $out[] = [
                'arquivo' => $b->arquivo,
                'aba' => $b->tabela->aba,
                'linhas' => count($b->tabela->linhas),
                'ausentes' => $ausentes,
                'obrigatoriasFaltando' => MapeadorCabecalho::faltantes(MapeadorCabecalho::mapear($b->tabela->cabecalho)),
            ];
        }

        return $out;
    }
}
