<?php

declare(strict_types=1);

namespace Inventario\Tests\Integration;

use Inventario\Application\UseCase\SolicitacaoAnalise;
use Inventario\Presentation\Aplicacao;
use Inventario\Presentation\Web\VisaoResultados;
use PHPUnit\Framework\TestCase;

/** JSON da tela de Resultados sobre a base de exemplo. */
final class VisaoResultadosTest extends TestCase
{
    public function testStatusAlertasERanking(): void
    {
        $dir = sys_get_temp_dir() . '/inventario_visao_' . bin2hex(random_bytes(4));
        $app = new Aplicacao($dir);
        $r = $app->analisarInventario()->executar(new SolicitacaoAnalise(
            [__DIR__ . '/../../exemplos/base_exemplo.csv' => 'base_exemplo.csv']));
        $v = (new VisaoResultados())->montar($r->analises[0], ['XLSX' => '?baixar=x'], '<div></div>');

        $porCod = [];
        foreach ($v['linhas'] as $l) {
            $porCod[$l['pep4'] . '|' . $l['code']] = $l;
        }
        self::assertSame('div', $porCod['OBRA-001.01.I|134830013']['status'], 'ARRUELA nao aderente');
        self::assertSame('sem', $porCod['OBRA-006.01.S|133000038']['status'], 'so SAP tem quantidade');
        self::assertSame('ver', $porCod['OBRA-002.01.I|105300003']['status'], 'aderente em obra reprovada');
        self::assertSame('ok', $porCod['OBRA-001.01.I|133000038']['status']);
        self::assertSame(-32.0, $porCod['OBRA-001.01.I|134830013']['diff'], 'PRJ - SAP');

        self::assertCount($v['alertas'] + 2, $v['alertasLista'], 'lista inclui os 2 apontamentos QUALIDADE DA BASE');
        self::assertSame('OBRA-006', $v['ranking'][0]['pep3']);
        self::assertSame(['OBRA-006', 'OBRA-007'], array_column($v['semBaixa'], 'pep'));
        self::assertNotFalse(json_encode($v, JSON_THROW_ON_ERROR));

        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $f) {
            $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
        }
        rmdir($dir);
    }
}
