<?php

declare(strict_types=1);

namespace Inventario\Domain\Nt006;

/**
 * Mapa NT.006: codigo SAP -> referencia (CriarMapaNT006 do VBA).
 */
final class MapaNT006
{
    /** @var array<string, MaterialNT006>|null */
    private static ?array $mapa = null;

    public static function buscar(string $codigo): ?MaterialNT006
    {
        return self::todos()[$codigo] ?? null;
    }

    /** @return array<int|string, MaterialNT006> */
    public static function todos(): array
    {
        return self::$mapa ??= [
            // CRUZETAS (ancora)
            '133100007' => new MaterialNT006('CRUZETA', 'R-02', 'Cruzeta concreto T 1900mm', true, '', 0.0, 0.0, ''),
            '133100001' => new MaterialNT006('CRUZETA', 'R-02', 'Cruzeta concreto L 1700mm', true, '', 0.0, 0.0, ''),
            '133100006' => new MaterialNT006('CRUZETA', 'R-02', 'Cruzeta concreto T 2200mm', true, '', 0.0, 0.0, ''),
            '133400012' => new MaterialNT006('CRUZETA', 'R-02', 'Cruzeta PRFV 90x112,5 2,4m', true, '', 0.0, 0.0, ''),
            '133400003' => new MaterialNT006('CRUZETA', 'R-02', 'Cruzeta PRFV', true, '', 0.0, 0.0, ''),
            '133400004' => new MaterialNT006('CRUZETA', 'R-02', 'Cruzeta PRFV', true, '', 0.0, 0.0, ''),
            // ISOLADOR PILAR: 2-3 por cruzeta (N1 trifasico=3; monofasico=1)
            '123140003' => new MaterialNT006('ISOLADOR PILAR', 'I-05', 'Isolador pilar 15kV M16', false, 'CRUZETA', 2.0, 3.5, '2-3 iso. pilar por cruzeta (N1=3)'),
            '123140016' => new MaterialNT006('ISOLADOR PILAR', 'I-05', 'Isolador pilar 24,2kV M16', false, 'CRUZETA', 2.0, 3.5, '2-3 iso. pilar por cruzeta'),
            '123140015' => new MaterialNT006('ISOLADOR PILAR', 'I-05', 'Isolador pilar polim. 25kV', false, 'CRUZETA', 2.0, 3.5, '2-3 iso. pilar por cruzeta'),
            '123140014' => new MaterialNT006('ISOLADOR PILAR', 'I-05', 'Isolador pilar', false, 'CRUZETA', 2.0, 3.5, '2-3 iso. pilar por cruzeta'),
            // ISOLADOR SUSPENSAO (ancora para estruturas U/B)
            '123230001' => new MaterialNT006('ISOL SUSPENSAO', 'I-06', 'Isolador suspensao polim. 15kV', true, '', 0.0, 0.0, ''),
            '123230002' => new MaterialNT006('ISOL SUSPENSAO', 'I-06', 'Isolador suspensao', true, '', 0.0, 0.0, ''),
            // ARRUELA: 2-8 por cruzeta (N1=4; N2/T2/T3/T4=8)
            '134830013' => new MaterialNT006('ARRUELA', 'A-02', 'Arruela quad. 38x38x3mm F18', false, 'CRUZETA', 2.0, 9.0, '2-8 arruelas por cruzeta (N1=4; N2=8)'),
            '134830014' => new MaterialNT006('ARRUELA', 'A-02', 'Arruela quad. lis 18x50x3mm', false, 'CRUZETA', 2.0, 9.0, '2-8 arruelas por cruzeta'),
            '134830051' => new MaterialNT006('ARRUELA', 'A-02', 'Arruela red pres M18', false, 'CRUZETA', 2.0, 9.0, '2-8 arruelas por cruzeta'),
            // PARAFUSO: 1-8 por cruzeta (varia por estrutura)
            '134700040' => new MaterialNT006('PARAFUSO', 'F-30', 'Parafuso cab qd 125mm M16x2', false, 'CRUZETA', 1.0, 8.0, '1-8 parafusos por cruzeta'),
            '134700043' => new MaterialNT006('PARAFUSO', 'F-30', 'Parafuso cab qd 200mm M16x2', false, 'CRUZETA', 1.0, 8.0, '1-8 parafusos por cruzeta'),
            '134700046' => new MaterialNT006('PARAFUSO', 'F-30', 'Parafuso cab qd 250mm M16x2', false, 'CRUZETA', 1.0, 8.0, '1-8 parafusos por cruzeta'),
            '134700047' => new MaterialNT006('PARAFUSO', 'F-30', 'Parafuso cab qd 300mm M16x2', false, 'CRUZETA', 1.0, 8.0, '1-8 parafusos por cruzeta'),
            '134700049' => new MaterialNT006('PARAFUSO', 'F-30', 'Parafuso cab qd 400mm M16x2', false, 'CRUZETA', 1.0, 8.0, '1-8 parafusos por cruzeta'),
            '134700028' => new MaterialNT006('PARAFUSO', 'F-30', 'Parafuso cab abaul 16x45mm', false, 'CRUZETA', 1.0, 8.0, '1-8 parafusos por cruzeta'),
            '134700030' => new MaterialNT006('PARAFUSO', 'F-30', 'Parafuso cab abaul 16x150mm', false, 'CRUZETA', 1.0, 8.0, '1-8 parafusos por cruzeta'),
            '134700082' => new MaterialNT006('PARAFUSO', 'F-30', 'Parafuso rosca dupla 16x500', false, 'CRUZETA', 1.0, 8.0, '1-8 parafusos por cruzeta'),
            // PINO: 2-3 por cruzeta (1 por isolador pilar)
            '134280005' => new MaterialNT006('PINO', 'F-36', 'Pino iso pilar autotrav M16x2', false, 'CRUZETA', 2.0, 3.5, '~3 pinos por cruzeta (1 por isolador pilar)'),
            '134280002' => new MaterialNT006('PINO', 'F-37', 'Pino curto suporte topo', false, 'CRUZETA', 1.0, 2.5, '1-2 pinos curtos por cruzeta (est. terminal)'),
            // PORCA: 2-6 por cruzeta
            '134800002' => new MaterialNT006('PORCA', 'A-21', 'Porca quad. M16x2', false, 'CRUZETA', 2.0, 6.5, '2-6 porcas por cruzeta (N2/T2/T3/T4=4)'),
            // SELA DE CRUZETA: 2-4 por cruzeta
            '134380004' => new MaterialNT006('SELA CRUZETA', '-', 'Sela cruzeta 110x116mm', false, 'CRUZETA', 2.0, 4.0, '2-3 selas por cruzeta trifasica'),
            '134380005' => new MaterialNT006('SELA CRUZETA', '-', 'Sela cruzeta', false, 'CRUZETA', 2.0, 4.0, '2-3 selas por cruzeta'),
            // MAO FRANCESA: 1-2 por cruzeta
            '134100006' => new MaterialNT006('MAO FRANCESA', '-', 'Mao francesa plana 726x38x5mm', false, 'CRUZETA', 0.5, 2.5, '1-2 maos-francesas por cruzeta'),
            // GANCHO OLHAL (ancora suspensao U/B)
            '134250015' => new MaterialNT006('GANCHO OLHAL', 'F-13', 'Gancho olhal 5000daN', true, '', 0.0, 0.0, ''),
            // MANILHA / OLHAL: 1:1 com gancho
            '134200006' => new MaterialNT006('MANILHA', 'F-22', 'Manilha sapatilha 5000daN', false, 'GANCHO OLHAL', 0.8, 1.2, '1 manilha por ponto de suspensao'),
            '134250023' => new MaterialNT006('OLHAL PARAFUSO', '-', 'Olhal parafuso M16 5000daN', false, 'GANCHO OLHAL', 0.8, 1.2, '1 olhal por ponto de suspensao'),
            '134740023' => new MaterialNT006('PARAFUSO OLHAL', 'F-34', 'Parafuso olhal M16x250mm', false, 'GANCHO OLHAL', 0.8, 1.2, '1 parafuso olhal por ponto de suspensao'),
            // HASTE DE ATERRAMENTO (ancora AT)
            '134600010' => new MaterialNT006('HASTE TERRA', 'F-17', 'Haste aco-cobreado 14,3mm 2,4m', true, '', 0.0, 0.0, ''),
            '134600004' => new MaterialNT006('HASTE TERRA', 'F-17', 'Haste aco-cobreado 12,7mm 2,4m', true, '', 0.0, 0.0, ''),
            // CONECTOR HASTE: 1:1 com haste
            '124140026' => new MaterialNT006('CONEC HASTE', 'M-10', 'Conector cunha haste 6-16mm', false, 'HASTE TERRA', 0.8, 1.2, '1 conector por haste de aterramento'),
            '124140078' => new MaterialNT006('CONEC HASTE', 'M-10', 'Conector aterramento p/haste', false, 'HASTE TERRA', 0.8, 1.2, '1 conector por haste de aterramento'),
            '124140011' => new MaterialNT006('CONEC HASTE', 'M-10', 'Conector cunha haste', false, 'HASTE TERRA', 0.8, 1.2, '1 conector por haste de aterramento'),
            // SUPORTE PARA-RAIOS (ancora PR)
            '134190064' => new MaterialNT006('SUP PARA-RAIO', 'F-47', 'Suporte L para-raios 38x205', true, '', 0.0, 0.0, ''),
            // PARA-RAIOS: 1:1 com suporte
            '104010001' => new MaterialNT006('PARA-RAIO', 'E-29', 'Para-raios ZnO 12kV 10kA', false, 'SUP PARA-RAIO', 0.8, 1.2, '1 para-raios por suporte (1:1)'),
            '104010004' => new MaterialNT006('PARA-RAIO', 'E-29', 'Para-raios ZnO 15kV', false, 'SUP PARA-RAIO', 0.8, 1.2, '1 para-raios por suporte (1:1)'),
            // CHAVE FUSIVEL (ancora)
            '105300003' => new MaterialNT006('CHAVE FUSIVEL', 'E-09', 'Chave fusivel 15kV 100A base C', true, '', 0.0, 0.0, ''),
            // TRANSFORMADOR (ancora)
            '102100035' => new MaterialNT006('TRAFO', 'E-45', 'Trafo trifasico 13,8kV 500kVA', true, '', 0.0, 0.0, ''),
            '102100036' => new MaterialNT006('TRAFO', 'E-45', 'Trafo trifasico 13,8kV', true, '', 0.0, 0.0, ''),
            '102100030' => new MaterialNT006('TRAFO', 'E-45', 'Trafo monofasico', true, '', 0.0, 0.0, ''),
            // CONECTOR RAMAL (sem ancora definida - informativo)
            '124010010' => new MaterialNT006('CONEC RAMAL', 'O-02', 'Conector cunha CuEst tipo II', true, '', 0.0, 0.0, ''),
            '124010012' => new MaterialNT006('CONEC RAMAL', 'O-02', 'Conector cunha CuEst tipo III', true, '', 0.0, 0.0, ''),
        ];
    }
}
