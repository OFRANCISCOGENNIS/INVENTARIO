<?php

declare(strict_types=1);

namespace Inventario\Domain\Alerta;

use Inventario\Domain\Alerta\Regra\DivergenciaPrecoUnitario;
use Inventario\Domain\Alerta\Regra\LacreMedidor;
use Inventario\Domain\Alerta\Regra\MedidorEmOdi;
use Inventario\Domain\Alerta\Regra\OddValorExcessivo;
use Inventario\Domain\Alerta\Regra\OdiSemUcSemCom;
use Inventario\Domain\Alerta\Regra\PepSemUc;
use Inventario\Domain\Alerta\Regra\PosteEmPepMS;
use Inventario\Domain\Alerta\Regra\PosteOdiPuBaixo;
use Inventario\Domain\Alerta\Regra\PuBaixoEmOdi;
use Inventario\Domain\Alerta\Regra\PuDiscrepanteMedia;
use Inventario\Domain\Alerta\Regra\PuSimbolicoCabo;
use Inventario\Domain\Alerta\Regra\SinalPorTipoOD;
use Inventario\Domain\Alerta\Regra\UcPrecoReferencia;
use Inventario\Domain\Base\BaseInventario;
use Inventario\Domain\Config\Parametros;
use Inventario\Domain\Preco\LinhaDiscrepanciaValor;
use Inventario\Domain\Preco\LinhaPrecoUnitario;
use Inventario\Domain\Preco\PrecosReferencia;

/**
 * ALERTA CRITICO (ProcessarAlertaCritico do VBA): roda as regras na ordem
 * da aba - agregadas iniciais, regras por linha, agregadas finais.
 * Novas regras entram implementando RegraAlertaLinha/RegraAlertaAgregada.
 */
final class GeradorAlertas
{
    /**
     * @param list<RegraAlertaAgregada> $antes
     * @param list<RegraAlertaLinha> $porLinha
     * @param list<RegraAlertaAgregada> $depois
     */
    public function __construct(
        private readonly array $antes,
        private readonly array $porLinha,
        private readonly array $depois,
    ) {
    }

    /**
     * @param list<LinhaPrecoUnitario> $precoUnitario
     * @param array<string, float> $mediasOdi codigo => PU medio na ODI (ODD VALOR EXCESSIVO)
     * @param list<LinhaDiscrepanciaValor> $discrepancias linhas ODI fora da media do codigo
     */
    public static function padrao(
        Parametros $p,
        PrecosReferencia $precos,
        array $precoUnitario,
        array $mediasOdi = [],
        array $discrepancias = [],
    ): self
    {
        return new self(
            [new OdiSemUcSemCom(), new PepSemUc()],
            [
                new PosteEmPepMS('M'),
                new PosteEmPepMS('S'),
                new SinalPorTipoOD(),
                new PuBaixoEmOdi($p->numero('PU_ODI_MIN'), $p->numero('PU_ODI_MAX')),
                new OddValorExcessivo($mediasOdi, $p->oddValorMaxPct()),
                new MedidorEmOdi(),
                new PosteOdiPuBaixo($p->numero('POSTE_ODI_PU_MIN')),
                new PuSimbolicoCabo($p->numero('PU_CABO_ALERTA')),
                new UcPrecoReferencia($precos, $p->numero('TOL_SUBVAL'), $p->numero('MIN_DIVERG_RS')),
            ],
            [new LacreMedidor(), new DivergenciaPrecoUnitario($precoUnitario), new PuDiscrepanteMedia($discrepancias)],
        );
    }

    public function gerar(BaseInventario $base): ResultadoAlertas
    {
        $linhas = array_map(LinhaAlerta::de(...), $base->linhas());
        $out = [];
        foreach ($this->antes as $r) {
            array_push($out, ...$r->gerar($linhas));
        }
        foreach ($linhas as $l) {
            if ($l->pep4 === '') {
                continue;
            }
            foreach ($this->porLinha as $r) {
                $a = $r->avaliar($l);
                if ($a !== null) {
                    $out[] = $a;
                }
            }
        }
        foreach ($this->depois as $r) {
            array_push($out, ...$r->gerar($linhas));
        }

        return new ResultadoAlertas(array_map(static fn (Alerta $a): Alerta => $a->comPrecoRecalculado(), $out));
    }
}
