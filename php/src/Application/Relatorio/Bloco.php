<?php

declare(strict_types=1);

namespace Inventario\Application\Relatorio;

/**
 * Uma tabela dentro de uma aba do relatorio. Os exportadores (XLSX, HTML,
 * JSON) desenham qualquer bloco do mesmo jeito.
 */
final class Bloco
{
    /**
     * @param list<string> $cabecalho
     * @param list<list<mixed>> $linhas
     * @param int|null $colunaStatus indice da coluna cujo valor escolhe a cor da linha
     * @param array<string, array{0: string, 1: string}> $cores valor da coluna de status => [fundo, chip] (hex sem #)
     * @param list<int> $colunasMoeda indices com formato #,##0.00
     */
    public function __construct(
        public readonly string $titulo,
        public readonly array $cabecalho,
        public readonly array $linhas,
        public readonly ?int $colunaStatus = null,
        public readonly array $cores = [],
        public readonly array $colunasMoeda = [],
        public readonly string $vazio = '',
    ) {
    }

    /** @return array{0: string, 1: string}|null */
    public function corDaLinha(array $linha): ?array
    {
        if ($this->colunaStatus === null) {
            return null;
        }
        $v = (string) ($linha[$this->colunaStatus] ?? '');
        if (isset($this->cores[$v])) {
            return $this->cores[$v];
        }
        foreach ($this->cores as $chave => $cor) {
            if ($chave !== '*' && str_contains($v, (string) $chave)) {
                return $cor;
            }
        }

        return $this->cores['*'] ?? null;
    }
}
