<?php

declare(strict_types=1);

namespace Inventario\Domain\Diagnostico;

use Inventario\Domain\Alerta\Alerta;

/**
 * DIAGNOSTICO DA BASE: nota de saude 0-100 dos DADOS (nao das obras).
 */
final class ResultadoDiagnostico
{
    /** @param list<Verificacao> $verificacoes */
    public function __construct(public readonly array $verificacoes)
    {
    }

    public function criticos(): int
    {
        return $this->contar(StatusVerificacao::CRITICO);
    }

    public function atencoes(): int
    {
        return $this->contar(StatusVerificacao::ATENCAO);
    }

    /** 100 - 25 por CRITICO - 10 por ATENCAO (minimo 0). */
    public static function notaSaude(int $criticos, int $atencoes): float
    {
        return (float) max(0, 100 - 25 * $criticos - 10 * $atencoes);
    }

    public function nota(): float
    {
        return self::notaSaude($this->criticos(), $this->atencoes());
    }

    public function rotulo(): string
    {
        $n = $this->nota();

        return match (true) {
            $n >= 90 => 'EXCELENTE',
            $n >= 70 => 'BOA',
            $n >= 50 => 'REQUER ATENCAO',
            default => 'CRITICA',
        };
    }

    /**
     * Verificacoes CRITICO e ATENCAO como apontamentos da aba ALERTA CRITICO
     * (os CRITICO primeiro).
     *
     * @return list<Alerta>
     */
    public function alertasQualidade(): array
    {
        $out = [];
        foreach ([StatusVerificacao::CRITICO, StatusVerificacao::ATENCAO] as $alvo) {
            foreach ($this->verificacoes as $v) {
                if ($v->status === $alvo) {
                    $out[] = new Alerta(
                        $alvo === StatusVerificacao::CRITICO ? 'QUALIDADE DA BASE - CRITICO' : 'QUALIDADE DA BASE',
                        descricao: $v->nome,
                        referencia: $v->resultado,
                        motivo: $v->recomendacao,
                    );
                }
            }
        }

        return $out;
    }

    private function contar(StatusVerificacao $s): int
    {
        return count(array_filter($this->verificacoes, static fn (Verificacao $v): bool => $v->status === $s));
    }
}
