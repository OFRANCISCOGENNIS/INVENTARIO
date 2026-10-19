<?php

declare(strict_types=1);

namespace Inventario\Application\Carga;

use Inventario\Domain\Base\BaseInventario;
use Inventario\Domain\Base\Coluna;
use Inventario\Domain\Base\MapeadorCabecalho;
use Inventario\Application\Porta\LeitorPlanilhas;
use Inventario\Domain\Support\Texto;

/**
 * CARREGAR BASES: le os arquivos (so leitura), acha a aba da base em cada um
 * e junta ou separa as bases (CarregarBases / LerBases / GravarBase do VBA).
 */
final class CarregadorBases
{
    /** Abas geradas pela analise ou de apoio: nunca sao a base. */
    private const ABAS_IGNORADAS = [
        'PAINEL DO GESTOR', 'ANALISE SAP x PRJ', 'RESUMO SAP x PRJ', 'RACIONALIZACAO COM', 'ALERTA CRITICO',
        'PRECO UNITARIO', 'RANKING DE RISCO', 'CONFIG', 'HISTORICO', 'LOG EXECUCAO', 'TESTES',
        'DIAGNOSTICO BASE', 'DEVOLUCOES', 'EQUIVALENCIAS', 'HISTORICO KPI',
    ];

    public function __construct(private readonly LeitorPlanilhas $leitor)
    {
    }

    /**
     * @param array<string, string> $arquivos caminho => nome exibido
     */
    public function ler(array $arquivos): LeituraBases
    {
        $blocos = [];
        $erros = [];
        foreach ($arquivos as $caminho => $nome) {
            $caminho = (string) $caminho;
            try {
                $abas = $this->leitor->ler($caminho, $nome);
            } catch (FalhaLeitura $e) {
                $erros[] = "{$nome}: {$e->getMessage()}";
                continue;
            }
            $tab = self::acharBase($abas);
            if ($tab === null) {
                $erros[] = "{$nome}: sem as colunas MAT LIB SAP / ATENDIDO SAP e MAT PRJ CAD / TOMBADO PROJ";
                continue;
            }
            if ($tab->linhas === []) {
                $erros[] = "{$nome}: aba '{$tab->aba}' sem linhas de dados";
                continue;
            }
            $blocos[] = new BlocoBase($nome, $caminho, $tab, $abas);
        }

        return new LeituraBases($blocos, $erros);
    }

    /** @param array<string, Tabela> $abas */
    public static function acharBase(array $abas): ?Tabela
    {
        foreach ($abas as $nome => $t) {
            if (in_array((string) $nome, self::ABAS_IGNORADAS, true) || $t->linhas === []) {
                continue;
            }
            $m = MapeadorCabecalho::mapear($t->cabecalho);
            if (isset($m[Coluna::MAT_LIB_SAP], $m[Coluna::MAT_PRJ_CAD])) {
                return $t;
            }
        }

        return null;
    }

    /** Uma base por arquivo (modo SEPARAR). */
    public static function base(BlocoBase $b): BaseInventario
    {
        return BaseInventario::deTabela($b->arquivo, $b->tabela->cabecalho, $b->tabela->linhas);
    }

    /**
     * Junta as bases (modo JUNTAR): colunas casadas por NOME (a ordem pode
     * variar entre arquivos), cabecalho do 1o arquivo e ARQUIVO ORIGEM no fim.
     *
     * @param list<BlocoBase> $blocos
     */
    public static function juntar(array $blocos, string $nome = 'BASE INVENTARIO'): BaseInventario
    {
        $hdr = self::cabecalhoDestino($blocos);
        $linhas = [];
        foreach ($blocos as $b) {
            [$src] = self::mapearBloco($b->tabela->cabecalho, $hdr);
            $mo = MapeadorCabecalho::mapear($b->tabela->cabecalho);
            $cOrig = $mo[Coluna::ARQUIVO_ORIGEM] ?? null;
            foreach ($b->tabela->linhas as $row) {
                $out = [];
                foreach ($src as $idx) {
                    $out[] = $idx === null ? null : ($row[$idx] ?? null);
                }
                // base que ja veio de uma juncao anterior mantem a origem dela
                $orig = $cOrig !== null ? Texto::limpo($row[$cOrig] ?? null) : '';
                $out[] = $orig !== '' ? $orig : $b->arquivo;
                $linhas[] = $out;
            }
        }
        $hdr[] = Coluna::ARQUIVO_ORIGEM;

        return BaseInventario::deTabela($nome, $hdr, $linhas);
    }

    /**
     * Cabecalho do 1o bloco, sem ARQUIVO ORIGEM e sem coluna vazia.
     *
     * @param list<BlocoBase> $blocos
     * @return list<string>
     */
    public static function cabecalhoDestino(array $blocos): array
    {
        if ($blocos === []) {
            return [];
        }
        $hdr = [];
        foreach ($blocos[0]->tabela->cabecalho as $h) {
            $t = Texto::limpo($h);
            if ($t !== '' && Texto::norm($t) !== Coluna::ARQUIVO_ORIGEM) {
                $hdr[] = $t;
            }
        }

        return $hdr;
    }

    /**
     * Casa as colunas de um bloco com o cabecalho de destino, pelo nome
     * canonico. Devolve [indice por coluna de destino (null = ausente), ausentes].
     *
     * @param list<mixed> $cabecalhoBloco
     * @param list<string> $hdr
     * @return array{0: list<?int>, 1: list<string>}
     */
    public static function mapearBloco(array $cabecalhoBloco, array $hdr): array
    {
        $m = MapeadorCabecalho::mapear($cabecalhoBloco);
        $src = [];
        $faltam = [];
        foreach ($hdr as $h) {
            $c = MapeadorCabecalho::canonico(Texto::norm($h));
            $idx = $m[$c] ?? null;
            $src[] = $idx;
            if ($idx === null) {
                $faltam[] = $h;
            }
        }

        return [$src, $faltam];
    }
}
