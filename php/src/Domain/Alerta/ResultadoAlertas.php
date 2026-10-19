<?php

declare(strict_types=1);

namespace Inventario\Domain\Alerta;

use Inventario\Domain\Support\Ordenacao;

/**
 * Lista de alertas e o resumo por tipo (chips do topo da aba).
 */
final class ResultadoAlertas
{
    /** @var list<array{tipo: string, qtd: int, primeiro: int}> */
    private array $resumo;

    /** @param list<Alerta> $alertas */
    public function __construct(private array $alertas)
    {
        $this->resumo = self::resumir($alertas);
    }

    /** @return list<Alerta> */
    public function alertas(): array
    {
        return $this->alertas;
    }

    /**
     * Contagem por tipo, do mais frequente ao menos frequente, com o indice do
     * primeiro apontamento de cada tipo.
     *
     * @return list<array{tipo: string, qtd: int, primeiro: int}>
     */
    public function resumo(): array
    {
        return $this->resumo;
    }

    /**
     * Acrescenta apontamentos no fim (ex.: QUALIDADE DA BASE do diagnostico).
     * O resumo por tipo continua sendo o dos alertas da propria analise.
     *
     * @param list<Alerta> $extras
     */
    public function anexar(array $extras): void
    {
        array_push($this->alertas, ...$extras);
    }

    /** Alertas que contam no painel (sem as replicas do diagnostico). */
    public function totalOperacional(): int
    {
        return count(array_filter($this->alertas, static fn (Alerta $a): bool => !$a->ehQualidadeBase() && trim($a->tipo) !== ''));
    }

    /**
     * @param list<Alerta> $alertas
     * @return list<array{tipo: string, qtd: int, primeiro: int}>
     */
    private static function resumir(array $alertas): array
    {
        $cnt = [];
        foreach ($alertas as $i => $a) {
            $t = trim($a->tipo);
            if ($t === '') {
                continue;
            }
            if (!isset($cnt[$t])) {
                $cnt[$t] = ['tipo' => $t, 'qtd' => 0, 'primeiro' => $i];
            }
            $cnt[$t]['qtd']++;
        }

        return Ordenacao::selecaoDesc(array_values($cnt), static fn (array $r): float => $r['qtd']);
    }
}
