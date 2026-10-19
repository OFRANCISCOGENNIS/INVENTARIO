<?php

declare(strict_types=1);

namespace Inventario\Domain\Equivalencia;

use Inventario\Domain\Base\Coluna;
use Inventario\Domain\Base\LinhaBase;
use Inventario\Domain\Support\Numero;
use Inventario\Domain\Support\Texto;

/**
 * Grupos de materiais equivalentes (GarantirEquivalencias do VBA).
 *
 * Um codigo em dois grupos une os grupos (union-find por numero de grupo):
 * 102310194 nos grupos 2 e 11 deixa os tres codigos equivalentes entre si.
 */
final class CatalogoEquivalencias
{
    /** @var array<string, string> codigo => grupo raiz */
    private array $grupoDe = [];
    /** @var array<string, list<string>> grupo raiz => codigos */
    private array $codigos = [];
    /** @var list<string> */
    private array $log = [];

    /** @param iterable<ItemEquivalencia> $itens */
    public function __construct(iterable $itens = [])
    {
        $pai = [];
        $codG = [];
        foreach ($itens as $it) {
            $grp = trim($it->grupo);
            $cod = Texto::codigo($it->codigo);
            if ($grp === '' || $cod === '') {
                continue;
            }
            $pai[$grp] ??= $grp;
            if (isset($codG[$cod])) {
                $ga = self::raiz($pai, $codG[$cod]);
                $gb = self::raiz($pai, $grp);
                if ($ga !== $gb) {
                    $pai[$gb] = $ga;
                    $this->log[] = "EQUIVALENCIAS: grupos {$ga} e {$gb} unidos (codigo {$cod} nos dois)";
                }
            } else {
                $codG[$cod] = $grp;
            }
        }
        foreach ($codG as $cod => $grp) {
            $cod = (string) $cod;
            $r = self::raiz($pai, (string) $grp);
            $this->grupoDe[$cod] = $r;
            $this->codigos[$r][] = $cod;
        }
    }

    /**
     * Grupo representante (raiz) no union-find.
     *
     * @param array<string, string> $pai
     */
    public static function raiz(array $pai, string $g): string
    {
        while (isset($pai[$g]) && (string) $pai[$g] !== $g) {
            $g = (string) $pai[$g];
        }

        return $g;
    }

    public function grupo(string $codigo): ?string
    {
        return $this->grupoDe[$codigo] ?? null;
    }

    public function vazio(): bool
    {
        return $this->grupoDe === [];
    }

    public function totalCodigos(): int
    {
        return count($this->grupoDe);
    }

    public function totalGrupos(): int
    {
        return count($this->codigos);
    }

    /** @return array<string, list<string>> */
    public function grupos(): array
    {
        return $this->codigos;
    }

    /** @return list<string> */
    public function log(): array
    {
        return $this->log;
    }

    /**
     * Chave "PEP4|GRUPO" da linha quando o material esta num grupo; "" quando
     * nao esta (ou falta PEP4 / COD MAT).
     */
    public function chave(LinhaBase $linha): string
    {
        if ($this->vazio()) {
            return '';
        }
        $cod = $linha->codigo();
        if ($cod === '' || !isset($this->grupoDe[$cod])) {
            return '';
        }
        $p4 = $linha->norm(Coluna::PEP4);
        if ($p4 === '') {
            return '';
        }

        return $p4 . '|' . $this->grupoDe[$cod];
    }

    /**
     * SIT MAT de um grupo de equivalentes pela quantidade TOTAL: exige o MESMO
     * numero no SAP e no PRJ, sem tolerancia.
     */
    public static function situacaoPorQuantidade(mixed $lib, mixed $prj): string
    {
        return abs(Numero::val0($lib) - Numero::val0($prj)) < 0.000001 ? 'ADERENTE' : 'NAO ADERENTE';
    }

    /**
     * "105000058 CH SEC 1P = 105000029 CH SEC BYPAS": os codigos do grupo
     * presentes no PEP4, cada um com a propria descricao.
     *
     * @param array<string, string> $codDesc
     */
    public static function texto(array $codDesc): string
    {
        $partes = [];
        foreach ($codDesc as $cod => $desc) {
            $partes[] = trim($cod . ' ' . $desc);
        }

        return implode(' = ', $partes);
    }
}
