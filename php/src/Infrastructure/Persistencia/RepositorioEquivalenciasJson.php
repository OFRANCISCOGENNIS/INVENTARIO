<?php

declare(strict_types=1);

namespace Inventario\Infrastructure\Persistencia;

use Inventario\Application\Porta\RepositorioEquivalencias;
use Inventario\Domain\Equivalencia\CatalogoEquivalencias;
use Inventario\Domain\Equivalencia\ItemEquivalencia;
use Inventario\Domain\Equivalencia\ListaPadraoEquivalencias;

/**
 * equivalencias.json (aba EQUIVALENCIAS do VBA). Nasce com a lista padrao.
 * A lista e do usuario: a cada versao nova da lista padrao so sao
 * acrescentados os grupos novos - grupo com todos os codigos ja cadastrados
 * e pulado; numero de grupo ja usado vira o proximo livre.
 */
final class RepositorioEquivalenciasJson implements RepositorioEquivalencias
{
    private readonly ArquivoJson $arquivo;
    /** @var list<string> */
    private array $log = [];

    public function __construct(string $caminho)
    {
        $this->arquivo = new ArquivoJson($caminho);
    }

    public function carregar(): CatalogoEquivalencias
    {
        if (!$this->arquivo->existe()) {
            $this->salvar(ListaPadraoEquivalencias::itens(), ListaPadraoEquivalencias::VERSAO_ATUAL);
        }
        $dados = $this->arquivo->ler();
        $itens = array_map(ItemEquivalencia::deArray(...), $dados['itens'] ?? []);
        $versao = (int) ($dados['versao'] ?? 1);
        if ($versao < ListaPadraoEquivalencias::VERSAO_ATUAL) {
            $itens = $this->atualizar($itens, $versao);
            $this->salvar($itens, ListaPadraoEquivalencias::VERSAO_ATUAL);
        }

        return new CatalogoEquivalencias($itens);
    }

    /** @return list<ItemEquivalencia> */
    public function itens(): array
    {
        $this->carregar();

        return array_map(ItemEquivalencia::deArray(...), $this->arquivo->ler()['itens'] ?? []);
    }

    /** @param list<ItemEquivalencia> $itens */
    public function salvar(array $itens, int $versao = ListaPadraoEquivalencias::VERSAO_ATUAL): void
    {
        $this->arquivo->gravar([
            'versao' => $versao,
            'observacao' => 'Codigos com o mesmo numero de grupo sao equivalentes: na ANALISE SAP x PRJ, quando 2+ '
                . 'codigos do grupo aparecem no mesmo PEP4, so sao ADERENTE com o mesmo total no SAP e no PRJ.',
            'itens' => array_map(static fn (ItemEquivalencia $i): array => $i->paraArray(), $itens),
        ]);
    }

    /** @return list<string> */
    public function log(): array
    {
        return $this->log;
    }

    /**
     * @param list<ItemEquivalencia> $itens
     * @return list<ItemEquivalencia>
     */
    private function atualizar(array $itens, int $versaoAtual): array
    {
        $temCod = [];
        $grupos = [];
        $maxG = 0;
        foreach ($itens as $i) {
            $temCod[$i->codigo] = true;
            $grupos[$i->grupo] = true;
            $maxG = max($maxG, (int) $i->grupo);
        }
        $novos = [];
        foreach (ListaPadraoEquivalencias::itens() as $i) {
            if ($i->versao > $versaoAtual) {
                $novos[$i->grupo][] = $i;
            }
        }
        $n = 0;
        foreach ($novos as $g => $lista) {
            $g = (string) $g;
            $todos = array_reduce($lista, static fn (bool $c, ItemEquivalencia $i): bool => $c && isset($temCod[$i->codigo]), true);
            if ($todos) {
                $this->log[] = "EQUIVALENCIAS: grupo padrao {$g} ja cadastrado - pulado";
                continue;
            }
            $dest = $g;
            if (isset($grupos[$dest])) {
                $dest = (string) ++$maxG;
                $this->log[] = "EQUIVALENCIAS: grupo padrao {$g} ja usado - gravado como {$dest}";
            }
            $maxG = max($maxG, (int) $dest);
            $grupos[$dest] = true;
            foreach ($lista as $i) {
                $itens[] = new ItemEquivalencia($i->codigo, $dest, $i->descricao, $i->unidade, $i->tipo, $i->familia, $i->versao);
                $temCod[$i->codigo] = true;
                $n++;
            }
        }
        $this->log[] = "EQUIVALENCIAS: lista padrao v{$versaoAtual} -> v" . ListaPadraoEquivalencias::VERSAO_ATUAL . ", {$n} codigo(s) acrescentado(s)";

        return $itens;
    }
}
