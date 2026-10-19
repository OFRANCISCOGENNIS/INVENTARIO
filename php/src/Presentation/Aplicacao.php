<?php

declare(strict_types=1);

namespace Inventario\Presentation;

use Inventario\Application\Relatorio\MontadorRelatorio;
use Inventario\Application\UseCase\AnalisarInventario;
use Inventario\Infrastructure\Exportacao\Exportador;
use Inventario\Infrastructure\Exportacao\ExportadorJson;
use Inventario\Infrastructure\Exportacao\ExportadorXlsx;
use Inventario\Infrastructure\Leitura\LeitorArquivos;
use Inventario\Infrastructure\Persistencia\RepositorioEquivalenciasJson;
use Inventario\Infrastructure\Persistencia\RepositorioHistoricoJson;
use Inventario\Infrastructure\Persistencia\RepositorioParametrosJson;
use Inventario\Infrastructure\Preco\FabricaFontePrecosPadrao;

/**
 * Raiz de composicao: monta o caso de uso com as implementacoes concretas.
 * Pasta de dados (padrao: php/storage):
 *   config.json, equivalencias.json, historico.json, BASE DE PRECOS.xlsx (opcional)
 *   saidas/  arquivos gerados
 */
final class Aplicacao
{
    public readonly string $pastaDados;
    public readonly string $pastaSaidas;
    public readonly LeitorArquivos $leitor;
    public readonly RepositorioParametrosJson $parametros;
    public readonly RepositorioEquivalenciasJson $equivalencias;
    public readonly RepositorioHistoricoJson $historico;

    public function __construct(?string $pastaDados = null)
    {
        $this->pastaDados = rtrim($pastaDados ?? (getenv('INVENTARIO_DADOS') ?: dirname(__DIR__, 2) . '/storage'), '/\\');
        $this->pastaSaidas = $this->pastaDados . '/saidas';
        foreach ([$this->pastaDados, $this->pastaSaidas] as $d) {
            if (!is_dir($d)) {
                mkdir($d, 0775, true);
            }
        }
        $this->leitor = LeitorArquivos::padrao();
        $this->parametros = new RepositorioParametrosJson($this->pastaDados . '/config.json');
        $this->equivalencias = new RepositorioEquivalenciasJson($this->pastaDados . '/equivalencias.json');
        $this->historico = new RepositorioHistoricoJson($this->pastaDados . '/historico.json');
    }

    public function analisarInventario(): AnalisarInventario
    {
        return new AnalisarInventario(
            $this->leitor,
            $this->parametros,
            $this->equivalencias,
            new FabricaFontePrecosPadrao($this->leitor, $this->pastaDados),
            $this->historico,
        );
    }

    public function montador(): MontadorRelatorio
    {
        return new MontadorRelatorio();
    }

    /** @return array<string, Exportador> extensao => exportador */
    public function exportadores(): array
    {
        return ['xlsx' => new ExportadorXlsx(), 'json' => new ExportadorJson()];
    }

    /** "ANALISE INVENTARIO - <base>" sem caracteres invalidos em nome de arquivo. */
    public static function nomeSaida(string $base): string
    {
        $b = preg_replace('/\.[A-Za-z0-9]{2,4}$/', '', $base) ?? $base;
        $b = preg_replace('/[^\pL\pN _.-]+/u', '_', $b) ?? 'base';

        return 'ANALISE INVENTARIO - ' . trim($b);
    }
}
