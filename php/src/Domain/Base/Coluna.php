<?php

declare(strict_types=1);

namespace Inventario\Domain\Base;

/**
 * Nomes CANONICOS (ja normalizados) das colunas da base SAP x PRJ.
 */
final class Coluna
{
    public const PEP3 = 'PEP3NIVEL';
    public const PEP4 = 'PEP4NIVEL';
    public const NOTA = 'NOTA';
    public const CLASSE = 'CLASSE';
    public const COD_MAT = 'COD MAT';
    public const VALOR = 'VALOR';
    public const DESC_MAT = 'DESC MAT';
    public const UND = 'UND';
    public const MAT_LIB_SAP = 'MAT LIB SAP';
    public const MAT_PRJ_CAD = 'MAT PRJ CAD';
    public const TIPO = 'TIPO';
    public const FAMILIA = 'FAMILIA';
    public const SIT_MAT = 'SIT MAT';
    public const TIPO_PEP = 'TIPO PEP';
    public const ARQUIVO_ORIGEM = 'ARQUIVO ORIGEM';

    /** Colunas sem as quais a analise nao roda. */
    public const OBRIGATORIAS = [
        self::PEP3, self::PEP4, self::COD_MAT, self::VALOR, self::FAMILIA, self::TIPO,
        self::SIT_MAT, self::MAT_LIB_SAP, self::MAT_PRJ_CAD,
    ];

    /** Colunas copiadas para a ANALISE SAP x PRJ, na ordem de saida. */
    public const SAIDA_ANALISE = [
        self::PEP3, self::PEP4, self::NOTA, self::CLASSE, self::COD_MAT, self::VALOR,
        self::DESC_MAT, self::UND, self::MAT_LIB_SAP, self::MAT_PRJ_CAD, self::TIPO,
        self::FAMILIA, self::SIT_MAT,
    ];
}
