<?php

declare(strict_types=1);

namespace Inventario\Application\Pipeline;

use Inventario\Domain\Preco\TabelaPrecos;

/**
 * SAUDE DA EXECUCAO: status e tempo de cada etapa, fonte de precos e avisos
 * nao fatais (a analise seguiu, mas sem algo).
 */
final class SaudeExecucao
{
    /** @var list<RegistroEtapa> */
    private array $etapas = [];
    /** @var list<string> */
    private array $avisos = [];
    /** @var list<string> */
    private array $log = [];
    private ?TabelaPrecos $fontePrecos = null;
    private string $fonteReferencia = '';

    public function registrar(RegistroEtapa $r): void
    {
        $this->etapas[] = $r;
        $this->log($r->ok
            ? sprintf('%s OK (%.1fs)', $r->etapa, $r->segundos)
            : "FALHA [{$r->etapa}]: {$r->erro}");
    }

    public function aviso(string $msg): void
    {
        $this->avisos[] = $msg;
        $this->log('AVISO: ' . $msg);
    }

    public function log(string $msg): void
    {
        $this->log[] = date('H:i:s') . '  ' . $msg;
    }

    public function definirFontePrecos(TabelaPrecos $t): void
    {
        $this->fontePrecos = $t;
    }

    public function definirFonteReferencia(string $descricao): void
    {
        $this->fonteReferencia = $descricao;
    }

    /** @return list<RegistroEtapa> */
    public function etapas(): array
    {
        return $this->etapas;
    }

    /** @return list<string> */
    public function avisos(): array
    {
        return $this->avisos;
    }

    /** @return list<string> */
    public function linhasLog(): array
    {
        return $this->log;
    }

    public function fontePrecos(): ?TabelaPrecos
    {
        return $this->fontePrecos;
    }

    public function fonteReferencia(): string
    {
        return $this->fonteReferencia;
    }

    public function falhas(): int
    {
        return count(array_filter($this->etapas, static fn (RegistroEtapa $e): bool => !$e->ok));
    }

    public function precoRuim(): bool
    {
        return $this->fontePrecos !== null && $this->fontePrecos->situacao->ruim();
    }

    /** "EXECUCAO OK", "ANALISE COM AVISOS" ou "ANALISE INCOMPLETA: n etapa(s) com falha". */
    public function selo(): string
    {
        if ($this->falhas() > 0) {
            return 'ANALISE INCOMPLETA: ' . $this->falhas() . ' etapa(s) com falha';
        }
        if ($this->avisos !== [] || $this->precoRuim()) {
            return 'ANALISE COM AVISOS';
        }

        return 'EXECUCAO OK';
    }

    public function ok(): bool
    {
        return $this->selo() === 'EXECUCAO OK';
    }

    /** Texto da fonte de precos (cadeia: aba interna -> arquivo externo). */
    public function descricaoFontePrecos(): string
    {
        if ($this->fontePrecos === null) {
            return 'nao consultada';
        }
        $t = $this->fontePrecos->descricao();
        if ($this->fontePrecos->dataArquivo !== null) {
            $t .= ' - alterado em ' . $this->fontePrecos->dataArquivo->format('d/m/Y');
        }

        return $t;
    }
}
