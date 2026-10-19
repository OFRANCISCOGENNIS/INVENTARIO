<?php

declare(strict_types=1);

namespace Inventario\Application\Pipeline;

use DateTimeImmutable;
use Inventario\Domain\Alerta\ResultadoAlertas;
use Inventario\Domain\Analise\Racionalizacao\LinhaRacionalizacao;
use Inventario\Domain\Analise\Resumo\ResumoObra;
use Inventario\Domain\Analise\SapPrj\ResultadoSapPrj;
use Inventario\Domain\Base\BaseInventario;
use Inventario\Domain\Config\Parametros;
use Inventario\Domain\Diagnostico\ResultadoDiagnostico;
use Inventario\Domain\Equivalencia\CatalogoEquivalencias;
use Inventario\Domain\Historico\RegistroObra;
use Inventario\Domain\Historico\RepositorioHistorico;
use Inventario\Domain\Painel\PainelGestor;
use Inventario\Domain\Preco\FontePrecos;
use Inventario\Domain\Preco\LinhaPrecoUnitario;
use Inventario\Domain\Preco\TabelaPrecos;
use Inventario\Domain\Ranking\ResultadoRanking;

/**
 * Entradas da analise e os resultados de cada etapa. Os getters devolvem um
 * resultado vazio quando a etapa que o produz falhou, para as seguintes
 * rodarem com o que existe (como as abas vazias no VBA).
 */
final class ContextoAnalise
{
    public readonly SaudeExecucao $saude;
    public readonly DateTimeImmutable $agora;

    public ?ResultadoSapPrj $sapPrj = null;
    /** @var list<ResumoObra> */
    public array $resumo = [];
    /** @var list<LinhaRacionalizacao> */
    public array $racionalizacao = [];
    public ?TabelaPrecos $tabelaPrecos = null;
    /** @var list<LinhaPrecoUnitario> */
    public array $precoUnitario = [];
    public ?ResultadoAlertas $alertas = null;
    public ?ResultadoDiagnostico $diagnostico = null;
    public ?ResultadoRanking $ranking = null;
    public ?PainelGestor $painel = null;
    /** @var list<RegistroObra> */
    public array $historico = [];

    /**
     * @param array<string, float> $tabelaPrecosReferencia codigo => preco (aba PRECOS); vazio = ponto medio da faixa
     * @param bool $modoLote analise de varias bases separadas: nao grava nem compara historico
     */
    public function __construct(
        public readonly BaseInventario $base,
        public readonly Parametros $parametros,
        public readonly CatalogoEquivalencias $equivalencias,
        public readonly FontePrecos $fontePrecos,
        public readonly ?RepositorioHistorico $repositorioHistorico = null,
        public readonly array $tabelaPrecosReferencia = [],
        public readonly bool $modoLote = false,
        ?DateTimeImmutable $agora = null,
    ) {
        $this->saude = new SaudeExecucao();
        $this->agora = $agora ?? new DateTimeImmutable();
    }

    public function sapPrj(): ResultadoSapPrj
    {
        return $this->sapPrj ?? new ResultadoSapPrj([], 0, 0, 0, 0.0, 0.0);
    }

    public function alertas(): ResultadoAlertas
    {
        return $this->alertas ??= new ResultadoAlertas([]);
    }

    public function usaHistorico(): bool
    {
        return !$this->modoLote && $this->repositorioHistorico !== null;
    }
}
