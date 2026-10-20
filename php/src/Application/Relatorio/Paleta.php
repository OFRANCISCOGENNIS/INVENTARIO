<?php

declare(strict_types=1);

namespace Inventario\Application\Relatorio;

/**
 * Cores por status/tipo ([fundo da linha, chip]), as mesmas do VBA.
 */
final class Paleta
{
    public const APROVACAO = [
        'REPROVADO' => ['FCE2E4', 'C00000'],
        'APROVADO' => ['E7F4EA', '218246'],
        '*' => ['F2F2F2', '6E6E6E'],
    ];

    public const ALERTA = [
        'ODI SEM UC' => ['FFF4D6', 'D69E00'],
        'PEP SEM UC' => ['FFEEE6', 'C45911'],
        'ODI SEM COM' => ['E6F0F8', '2E75B6'],
        'PU ABAIXO MIN' => ['FFF4D6', 'B07C00'],
        'PU ACIMA MAX' => ['FCE2E4', 'C00000'],
        'MATERIAL NEGATIVO' => ['FCE2E4', 'C00000'],
        'MATERIAL POSITIVO EM ODD' => ['FCE9DB', 'D66300'],
        'LACRE x MEDIDOR' => ['E1DFF5', '6040C4'],
        'POSTE EM PEP .M' => ['DEECFA', '1F6BB7'],
        'POSTE EM PEP .S' => ['DCF5F0', '0F8273'],
        'UC SUBVALORIZADO' => ['FCE2E4', 'C00000'],
        'UC - PRECO NAO ENCONTRADO' => ['FFF4D6', 'B07C00'],
        'UC - COD MATERIAL VAZIO' => ['EEEEEE', '6E6E6E'],
        'PU BAIXO EM ODI' => ['FCE7F3', 'BE185D'],
        'PU 0,01 EM CABO/COND' => ['FFEDD5', 'C2410C'],
        'ODD VALOR ABAIXO DO LIMITE' => ['FCE2E4', '991B1B'],
        'POSTE ODI PU BAIXO' => ['E0E7FF', '3730A3'],
        'MEDIDOR EM PEP ODI' => ['EDE9FE', '6D28D9'],
        'QUALIDADE DA BASE - CRITICO' => ['FCE2E4', 'C00000'],
        'QUALIDADE DA BASE' => ['FFF4D6', 'B07C00'],
        '*' => ['F5F5F5', '787878'],
    ];

    public const RACIONALIZACAO = [
        'OK' => ['C6EFCE', '006100'],
        'REFERENCIA' => ['DDEBF7', '1F4E79'],
        'EXCESSO' => ['FFEB9C', '9C5700'],
        'EXAGERADO' => ['C00000', 'FFFFFF'],
        'FALTANDO' => ['FFC7CE', '9C0006'],
        'SEM ANCORA' => ['FFF2CC', '776B00'],
        'ZERO' => ['E6E6E6', '404040'],
        'ESTORNO' => ['E6E6E6', '404040'],
        '*' => ['F2F2F2', '595959'],
    ];

    public const PRECO = [
        'DENTRO' => ['E7F4EA', '218246'],
        'ABAIXO DO MINIMO' => ['FFF4D6', 'B07C00'],
        'ACIMA DO MAXIMO' => ['FCE2E4', 'C00000'],
        '*' => ['EEEEEE', '6E6E6E'],
    ];

    public const RISCO = [
        'ALTO' => ['FCE2E4', 'C00000'],
        'MEDIO' => ['FFF4D6', 'B07C00'],
        'BAIXO' => ['EAF2FA', '1F4E79'],
        '*' => ['E7F4EA', '218246'],
    ];

    public const VERIFICACAO = [
        'CRITICO' => ['FCE2E4', 'C00000'],
        'ATENCAO' => ['FFF4D6', 'B07C00'],
        'OK' => ['E7F4EA', '218246'],
        'FALHA' => ['FCE2E4', 'C00000'],
        '*' => ['F4F7FA', '2D3440'],
    ];

    public const DISCREP_VALOR = [
        'ACIMA DA MEDIA' => ['FCE2E4', 'C00000'],
        'ABAIXO DA MEDIA' => ['FFF4D6', 'B07C00'],
    ];

    public const SEM_BAIXA = [
        'PEP3' => ['FCE2E4', 'C00000'],
        'PEP4' => ['FFF4D6', 'B07C00'],
    ];
}
