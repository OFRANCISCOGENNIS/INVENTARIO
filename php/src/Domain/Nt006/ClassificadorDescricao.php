<?php

declare(strict_types=1);

namespace Inventario\Domain\Nt006;

use Inventario\Domain\Support\Texto;

/**
 * Classificacao por DESCRICAO quando o codigo SAP nao esta no mapa NT.006
 * (ClassificarDesc do VBA). Padronizacao de estruturas de distribuicao 15kV
 * (NT.006 / NT.018). A ORDEM dos testes importa: PINO antes de CRUZETA,
 * PLACA antes de POSTE, CHAVE FUSIVEL antes de CHAVE...
 */
final class ClassificadorDescricao
{
    /** @param string $d descricao ja normalizada (Texto::norm) */
    public static function classificar(string $d): ?MaterialNT006
    {
        $tem = static fn (string ...$termos): bool => self::algum($d, $termos);
        $palavra = static fn (string $t): bool => Texto::temPalavra($d, $t);

        if ($palavra('PINO') && !$tem('CONECTOR') && !$tem('TERM')) {
            return self::dep('PINO', 'CRUZETA', 1, 3.5, 'F-36', '1-3 pinos por cruzeta (1 por isolador pilar)');
        }
        if ($tem('PLACA')) {
            return self::anc('PLACA IDENTIFICACAO', 'PL', 'Placa de identificacao/sinalizacao (neutro)');
        }
        if ($tem('CRUZETA')) {
            return self::anc('CRUZETA', 'R-02');
        }
        if ($tem('ISOLADOR') && $tem('DISCO', 'SUSPENS')) {
            return self::anc('ISOL SUSPENSAO', 'I-06');
        }
        if ($tem('HASTE') && $tem('TERRA', 'ATERR', 'COBRE')) {
            return self::anc('HASTE TERRA', 'F-17');
        }
        if ($tem('CHAVE') && $tem('FUS')) {
            return self::anc('CHAVE FUSIVEL', 'E-09');
        }
        if ($tem('CHAVE')) {
            return self::anc('CHAVE FACA/SECC', 'E-10', 'Chave faca/seccionadora (ancora)');
        }
        if ($tem('TRAFO', 'TRANSFORMADOR')) {
            return self::anc('TRAFO', 'E-45');
        }
        if ($tem('GANCHO') && $tem('OLHAL')) {
            return self::anc('GANCHO OLHAL', 'F-13');
        }
        if ($tem('SUPORTE') && $tem('PARA RAIO', 'PARARAIO')) {
            return self::anc('SUP PARA-RAIO', 'F-47');
        }
        // dependentes da cruzeta
        if ($tem('ISOLADOR') && $tem('PILAR')) {
            return self::dep('ISOLADOR PILAR', 'CRUZETA', 2, 3.5, 'I-05', '2-3 isolador pilar por cruzeta (1/fase, N1=3)');
        }
        if ($tem('MAO FRANCESA')) {
            return self::dep('MAO FRANCESA', 'CRUZETA', 0.5, 2.5, '', '1-2 maos-francesas por cruzeta');
        }
        if ($palavra('SELA')) {
            return self::dep('SELA CRUZETA', 'CRUZETA', 2, 4, '', '2-4 selas por cruzeta');
        }
        if ($tem('ARRUELA')) {
            return self::dep('ARRUELA', 'CRUZETA', 2, 9, 'A-02', '2-8 arruelas por cruzeta (N1=4; N2=8)');
        }
        if ($tem('PORCA')) {
            return self::dep('PORCA', 'CRUZETA', 2, 6.5, 'A-21', '2-6 porcas por cruzeta');
        }
        if ($tem('PARAFUSO') && $tem('OLHAL')) {
            return self::dep('PARAFUSO OLHAL', 'GANCHO OLHAL', 0.8, 1.2, 'F-34', '1 parafuso olhal por ponto de suspensao');
        }
        if ($tem('PARAFUSO')) {
            return self::dep('PARAFUSO', 'CRUZETA', 1, 8, 'F-30', '1-8 parafusos por cruzeta');
        }
        // dependentes de outras ancoras
        if ($tem('PARA RAIO', 'PARARAIO')) {
            return self::dep('PARA-RAIO', 'SUP PARA-RAIO', 0.8, 1.2, 'E-29', '1 para-raios por suporte (1:1)');
        }
        if ($tem('MANILHA')) {
            return self::dep('MANILHA', 'GANCHO OLHAL', 0.8, 1.2, 'F-22', '1 manilha por ponto de suspensao');
        }
        if ($tem('OLHAL')) {
            return self::dep('OLHAL', 'GANCHO OLHAL', 0.8, 1.2, '', '1 olhal por ponto de suspensao');
        }
        if ($tem('CONECTOR') && $tem('HASTE', 'ATERR', 'CUNHA')) {
            return self::dep('CONEC HASTE', 'HASTE TERRA', 0.8, 1.2, 'M-10', '1 conector por haste de aterramento');
        }
        if ($tem('CORDOALHA', 'ESTICADOR', 'SAPATA') || ($tem('HASTE') && $tem('ANCORA'))) {
            return self::anc('ESTAI', 'ES', 'Conjunto de estai (ancoragem de poste)');
        }
        if ($palavra('DPS') || ($tem('PROTETOR') && $tem('SURTO'))) {
            return self::anc('DPS', 'DPS', 'Protetor de surto BT (neutro)');
        }
        // condutores (medidos em metros); ALCA e acessorio, nao o cabo
        if (($tem('CABO', 'CONDUTOR') || $palavra('FIO') || $palavra('CAA') || $palavra('CAZ')) && !$tem('ALCA')) {
            return self::anc('CABO/CONDUTOR', 'COND', 'Condutor - medido em metros (referencia por vao)');
        }
        if ($tem('POSTE')) {
            return self::anc('POSTE', 'POSTE', 'Poste - estrutura de suporte');
        }
        if ($tem('CAIXA') && $tem('MEDI', 'POLICARB', 'MONOF', 'POLIF')) {
            return self::dep('CAIXA MEDICAO', 'MEDIDOR', 0.8, 1.2, 'CX-M', '1 caixa de medicao por medidor');
        }
        if ($tem('CAIXA')) {
            return self::anc('CAIXA', 'CX', 'Caixa (neutro)');
        }
        // LACRE antes de MEDIDOR: "LACRE P/ MEDIDOR" e lacre (no VBA caia em MEDIDOR)
        if ($tem('LACRE')) {
            return self::dep('LACRE', 'MEDIDOR', 1.8, 2.2, 'LACRE', '2 lacres por medidor instalado');
        }
        if ($tem('MEDIDOR')) {
            return self::anc('MEDIDOR', 'MED', 'Medidor de energia');
        }
        if ($palavra('ELO') || ($tem('FUSIVEL') && !$tem('CHAVE'))) {
            return self::dep('ELO FUSIVEL', 'CHAVE FUSIVEL', 0.8, 3.5, 'E-12', '1-3 elos por chave fusivel (1/fase)');
        }
        if ($palavra('CINTA')) {
            return self::dep('CINTA POSTE', 'POSTE', 1, 4, '', '1-4 cintas por poste');
        }
        if ($tem('ARMACAO')) {
            return self::anc('ARMACAO SEC', 'F-01', 'Armacao secundaria BT (ancora)');
        }
        if ($tem('ROLDANA')) {
            return self::dep('ROLDANA', 'ARMACAO SEC', 0.8, 1.2, '', '1 roldana por armacao');
        }
        if ($tem('GRAMPO')) {
            return self::anc('GRAMPO', 'F-20', 'Grampo de ancoragem/suspensao');
        }
        if ($tem('CONECTOR', 'CONEX', 'CONEC', 'CON PER')) {
            return self::anc('CONECTOR', 'M-01', 'Conector eletrico (neutro)');
        }
        if ($palavra('LUVA')) {
            return self::anc('LUVA EMENDA', 'M-05', 'Luva de emenda de condutor');
        }
        if ($palavra('FITA') || $tem('FECHO', 'FIVELA')) {
            return self::anc('FITA/FECHO', 'F-50', 'Fita/fecho de aco inox (fixacao)');
        }
        if ($tem('TERMINAL', 'CABECOTE', 'MUFLA')) {
            return self::anc('TERMINAL/MUFLA', 'M-30', 'Terminacao de cabo (neutro)');
        }
        if ($tem('ESTRIBO')) {
            return self::anc('ESTRIBO', 'M-40', 'Estribo (conexao de derivacao)');
        }
        if ($tem('RELIGADOR', 'SECCIONA', 'DISJUNTOR', 'REGULADOR')) {
            return self::anc('EQUIP MANOBRA', 'EQ', 'Equipamento de manobra/protecao (ancora)');
        }
        if ($palavra('BUCHA')) {
            return self::dep('BUCHA', 'CRUZETA', 1, 8, '', 'Bucha/fixacao por cruzeta');
        }
        if ($tem('ESPACADOR', 'BRACO', 'LOSANGULAR')) {
            return self::anc('REDE COMPACTA', 'NT018', 'Rede compacta - referencia por vao (3-5 espacadores/vao)');
        }
        if ($tem('ALCA') && $tem('PREFORM', 'PREF')) {
            return self::anc('ALCA PREFORMADA', 'F-04', 'Alca preformada (amarracao de condutor; PREF = abreviacao de preformada)');
        }
        if ($palavra('ANEL') || $palavra('LACO') || $palavra('ALCA')) {
            return self::anc('REDE COMPACTA', 'NT018', 'Acessorio rede compacta (anel/laco/alca)');
        }
        if ($tem('ELETRODUTO')) {
            return self::anc('ELETRODUTO', 'ED', 'Eletroduto/duto de descida');
        }
        if ($tem('ABRACADEIRA', 'CANTONEIRA', 'PERFIL', 'CHAPA')) {
            return self::anc('FERRAGEM', 'FE', 'Ferragem de fixacao (neutro)');
        }
        if ($palavra('ARAME')) {
            return self::anc('ARAME', 'AR', 'Arame de amarracao');
        }
        if ($tem('SUPORTE')) {
            return self::anc('SUPORTE', 'SUP', 'Suporte generico (neutro)');
        }
        if ($palavra('SUP') && $tem('CH FACA', 'CHAVE FACA', 'BYPASS', 'BY PASS')) {
            return self::anc('SUPORTE', 'SUP', 'Suporte p/ equipamento (chave faca/by-pass) - abreviacao SUP');
        }

        return null;
    }

    /** @param list<string> $termos */
    private static function algum(string $d, array $termos): bool
    {
        foreach ($termos as $t) {
            if (str_contains($d, $t)) {
                return true;
            }
        }

        return false;
    }

    /** Ancora (referencia para os dependentes). */
    private static function anc(string $familia, string $cod, string $regra = ''): MaterialNT006
    {
        return new MaterialNT006($familia, $cod, 'Classif. por descricao (NT.006/018)', true, '', 0.0, 0.0, $regra);
    }

    /** Dependente; sem codigo NT.006 proprio fica marcado como "por descricao". */
    private static function dep(string $familia, string $ancora, float $min, float $max, string $cod, string $regra): MaterialNT006
    {
        return new MaterialNT006(
            $familia,
            $cod !== '' ? $cod : MaterialNT006::COD_POR_DESCRICAO,
            'Classif. por descricao (NT.006/018)',
            false,
            $ancora,
            $min,
            $max,
            $regra,
        );
    }
}
