<?php

declare(strict_types=1);

namespace Inventario\Domain\Regra;

use Inventario\Domain\Base\TipoOD;
use Inventario\Domain\Config\Parametros;
use Inventario\Domain\Support\Numero;

/**
 * Avalia uma linha SAP x PRJ (AvaliarLinha do VBA).
 */
final class AvaliadorLinha
{
    private readonly RegraAderencia $aderencia;

    public function __construct(private readonly Parametros $p)
    {
        $this->aderencia = new RegraAderencia($p);
    }

    public function aderencia(): RegraAderencia
    {
        return $this->aderencia;
    }

    /**
     * @param string $tipo TIPO normalizado (UC/COM)
     * @param string $fam FAMILIA normalizada
     * @param mixed $lib MAT LIB SAP usado na comparacao (total do grupo, se equivalente)
     * @param mixed $prj MAT PRJ CAD usado na comparacao
     * @param string $sitNorm SIT MAT normalizada
     * @param bool $ucZeradoReprova true quando TODOS os UC do PEP3 estao zerados
     * @param mixed $qtdSinal quantidade da PROPRIA linha para as regras de sinal (ausente = $lib)
     * @param mixed $prjSinal PRJ da PROPRIA linha para sinais opostos (ausente = $prj)
     * @param float|null $mediaOdi PU medio do codigo na ODI (regra de valor excessivo em ODD)
     */
    public function avaliar(
        string $tipo,
        string $fam,
        mixed $lib,
        mixed $prj,
        string $sitNorm,
        bool $ucZeradoReprova = true,
        TipoOD $tipoOD = TipoOD::NENHUM,
        mixed $valor = 0,
        mixed $qtdSinal = Ausente::VALOR,
        mixed $prjSinal = Ausente::VALOR,
        ?float $mediaOdi = null,
    ): AvaliacaoLinha {
        $ucZerado = RegraAderencia::materialUCZerado($tipo, $lib, $prj) && $ucZeradoReprova;
        $ehAder = $this->aderencia->ehAderente($fam, $lib, $prj, $sitNorm) && !$ucZerado;

        $qS = $qtdSinal === Ausente::VALOR ? $lib : $qtdSinal;
        $pS = $prjSinal === Ausente::VALOR ? $prj : $prjSinal;
        $motivos = array_filter([
            RegrasOD::sinalInvalido($tipoOD, $qS, $valor),
            RegrasOD::valorBaixoODD($tipoOD, $valor, $this->p->oddValorMin()),
            RegrasOD::valorExcessivoODD($tipoOD, $valor, $qS, $mediaOdi, $this->p->oddValorMaxPct()),
            RegrasOD::sinaisOpostos($qS, $pS),
        ], static fn (string $m): bool => $m !== '');
        $motivoOD = implode('; ', $motivos);
        $violaOD = $motivoOD !== '';
        if ($violaOD) {
            $ehAder = false;
        }

        $avaliavel = $tipo === 'UC' || ($tipo === 'COM' && $this->aderencia->ehComCritico($fam));
        $reprova = (!$ehAder && $sitNorm !== 'NULO') || $ucZerado || $violaOD;
        $recalcula = RegraAderencia::familiaComMargem($fam)
            && Numero::ehNumerico($lib) && Numero::ehNumerico($prj);

        return new AvaliacaoLinha(
            $ehAder,
            $ucZerado,
            $avaliavel,
            $this->aderencia->caboIsento($fam, $lib),
            $reprova,
            $recalcula,
            $violaOD,
            $motivoOD,
        );
    }
}
