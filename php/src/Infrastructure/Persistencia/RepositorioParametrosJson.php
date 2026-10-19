<?php

declare(strict_types=1);

namespace Inventario\Infrastructure\Persistencia;

use Inventario\Application\Porta\RepositorioParametros;
use Inventario\Domain\Config\Parametros;

/**
 * config.json (aba CONFIG do VBA): CHAVE => {valor, descricao}. Criado com os
 * padroes na 1a execucao; chaves novas do modulo sao acrescentadas sem mexer
 * no que o usuario editou.
 */
final class RepositorioParametrosJson implements RepositorioParametros
{
    private readonly ArquivoJson $arquivo;

    public function __construct(string $caminho)
    {
        $this->arquivo = new ArquivoJson($caminho);
    }

    public function carregar(): Parametros
    {
        $dados = $this->arquivo->ler();
        $valores = [];
        $mudou = !$this->arquivo->existe();
        foreach ($dados as $k => $v) {
            $valores[(string) $k] = is_array($v) ? ($v['valor'] ?? null) : $v;
        }
        foreach (Parametros::PADRAO as $k => [$padrao]) {
            if (!array_key_exists($k, $valores)) {
                $valores[$k] = $padrao;
                $mudou = true;
            }
        }
        $p = new Parametros($valores);
        if ($mudou) {
            $this->salvar($p);
        }

        return $p;
    }

    public function salvar(Parametros $p): void
    {
        $out = [];
        $informados = $p->valoresInformados();
        foreach ($p->tabela() as $k => $linha) {
            $out[$k] = ['valor' => $informados[$k] ?? $linha['valor'], 'descricao' => $linha['descricao']];
        }
        $this->arquivo->gravar($out);
    }
}
