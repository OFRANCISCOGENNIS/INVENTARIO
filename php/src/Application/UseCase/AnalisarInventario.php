<?php

declare(strict_types=1);

namespace Inventario\Application\UseCase;

use DateTimeImmutable;
use Inventario\Application\Carga\CarregadorBases;
use Inventario\Application\Carga\LeituraBases;
use Inventario\Application\Carga\Tabela;
use Inventario\Application\Pipeline\ContextoAnalise;
use Inventario\Application\Pipeline\Pipeline;
use Inventario\Application\Porta\FabricaFontePrecos;
use Inventario\Application\Porta\LeitorPlanilhas;
use Inventario\Application\Porta\RepositorioEquivalencias;
use Inventario\Application\Porta\RepositorioParametros;
use Inventario\Domain\Base\BaseInventario;
use Inventario\Domain\Historico\RepositorioHistorico;

/**
 * Caso de uso principal: CarregarBases + GerarInventario do VBA.
 *
 * Fluxo: LER (so leitura) -> PREVIA (opcional, nada alterado) -> ANALISAR.
 */
final class AnalisarInventario
{
    private readonly CarregadorBases $carregador;

    public function __construct(
        LeitorPlanilhas $leitor,
        private readonly RepositorioParametros $parametros,
        private readonly RepositorioEquivalencias $equivalencias,
        private readonly FabricaFontePrecos $precos,
        private readonly ?RepositorioHistorico $historico = null,
        private readonly ?Pipeline $pipeline = null,
    ) {
        $this->carregador = new CarregadorBases($leitor);
    }

    /** Le os arquivos sem analisar (para a previa). */
    public function ler(SolicitacaoAnalise $s): LeituraBases
    {
        return $this->carregador->ler($s->arquivos);
    }

    public function executar(SolicitacaoAnalise $s, ?LeituraBases $leitura = null, ?DateTimeImmutable $agora = null): ResultadoExecucao
    {
        $leitura ??= $this->ler($s);
        if ($leitura->vazia()) {
            return new ResultadoExecucao($leitura, [], ['Nenhuma base foi lida.']);
        }

        $analises = [];
        $erros = [];
        if ($s->modo === ModoCarga::JUNTAR || count($leitura->blocos) === 1) {
            $blocos = $leitura->blocos;
            $base = count($blocos) === 1
                ? CarregadorBases::base($blocos[0])
                : CarregadorBases::juntar($blocos);
            $abas = $blocos[0]->abas;
            $analise = $this->analisar($base, $abas, $s->arquivoPrecos, false, $agora);
            if ($analise instanceof ContextoAnalise) {
                $analises[] = $analise;
            } else {
                $erros[] = $analise;
            }
        } else {
            foreach ($leitura->blocos as $b) {
                $analise = $this->analisar(CarregadorBases::base($b), $b->abas, $s->arquivoPrecos, true, $agora);
                if ($analise instanceof ContextoAnalise) {
                    $analises[] = $analise;
                } else {
                    $erros[] = "{$b->arquivo}: {$analise}";
                }
            }
        }

        return new ResultadoExecucao($leitura, $analises, $erros);
    }

    /** Analisa uma base ja montada (uso direto, testes, API). */
    public function analisarBase(BaseInventario $base, ?string $arquivoPrecos = null, bool $modoLote = false, ?DateTimeImmutable $agora = null): ContextoAnalise|string
    {
        return $this->analisar($base, [], $arquivoPrecos, $modoLote, $agora);
    }

    /**
     * @param array<string, Tabela> $abas
     * @return ContextoAnalise|string contexto analisado ou o motivo da recusa
     */
    private function analisar(BaseInventario $base, array $abas, ?string $arquivoPrecos, bool $modoLote, ?DateTimeImmutable $agora): ContextoAnalise|string
    {
        $faltam = $base->colunasFaltantes();
        if ($faltam !== []) {
            return "a base '{$base->nome}' esta sem colunas obrigatorias: " . implode(', ', $faltam);
        }
        $ctx = new ContextoAnalise(
            $base,
            $this->parametros->carregar(),
            $this->equivalencias->carregar(),
            $this->precos->fonte($abas, $arquivoPrecos),
            $this->historico,
            $this->precos->precosReferencia($abas),
            $modoLote,
            $agora,
        );

        return ($this->pipeline ?? Pipeline::padrao())->executar($ctx);
    }
}
