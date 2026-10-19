<?php

declare(strict_types=1);

namespace Inventario\Presentation\Web;

use Inventario\Application\Relatorio\Aba;
use Inventario\Application\Relatorio\Bloco;
use Inventario\Application\Relatorio\Relatorio;

/** Desenha o Relatorio como HTML com uma guia por aba. */
final class RenderizadorHtml
{
    /** Linhas desenhadas por tabela; o resto fica no Excel (paginas enormes travam o navegador). */
    public const MAX_LINHAS = 500;

    /** @param array<string, string> $downloads rotulo => url */
    public function relatorio(Relatorio $r, array $downloads = []): string
    {
        $guias = '';
        $paineis = '';
        foreach ($r->abas as $i => $a) {
            $id = 'aba' . $i;
            $ativo = $i === 0 ? ' ativo' : '';
            $guias .= '<button class="guia' . $ativo . '" data-alvo="' . $id . '" style="--cor:#' . Html::e($a->corGuia) . '">'
                . Html::e($a->nome) . '</button>';
            $paineis .= '<section class="painel' . $ativo . '" id="' . $id . '">' . $this->aba($a) . '</section>';
        }
        $links = '';
        foreach ($downloads as $rot => $url) {
            $links .= '<a class="botao" href="' . Html::e($url) . '">' . Html::e($rot) . '</a> ';
        }
        $selo = $r->selo === 'EXECUCAO OK' ? 'ok' : (str_starts_with($r->selo, 'ANALISE INCOMPLETA') ? 'falha' : 'aviso');

        return '<div class="cabecalho-relatorio"><div><h2>' . Html::e($r->base) . '</h2><p>Gerado em '
            . Html::e($r->geradoEm) . ' <span class="selo ' . $selo . '">' . Html::e($r->selo) . '</span></p></div>'
            . '<div>' . $links . '</div></div>'
            . '<nav class="guias">' . $guias . '</nav>' . $paineis;
    }

    private function aba(Aba $a): string
    {
        $h = '<header class="titulo-aba" style="background:#' . Html::e($a->corGuia) . '"><h3>' . Html::e($a->titulo)
            . '</h3><p>' . Html::e($a->subtitulo) . '</p></header>';
        foreach ($a->blocos as $b) {
            $h .= $this->bloco($b);
        }

        return $h;
    }

    private function bloco(Bloco $b): string
    {
        $h = $b->titulo !== '' ? '<h4>' . Html::e($b->titulo) . '</h4>' : '';
        if ($b->linhas === []) {
            return $h . ($b->vazio !== '' ? '<p class="vazio">' . Html::e($b->vazio) . '</p>' : '');
        }
        $h .= '<div class="tabela"><table><thead><tr>';
        foreach ($b->cabecalho as $c) {
            $h .= '<th>' . Html::e($c) . '</th>';
        }
        $h .= '</tr></thead><tbody>';
        foreach (array_slice($b->linhas, 0, self::MAX_LINHAS) as $l) {
            $cor = $b->corDaLinha($l);
            $h .= '<tr' . ($cor ? ' style="background:#' . Html::e($cor[0]) . '"' : '') . '>';
            foreach (array_values($l) as $i => $v) {
                $chip = $cor !== null && $i === $b->colunaStatus;
                $num = is_int($v) || is_float($v);
                $h .= '<td' . ($num ? ' class="num"' : '') . '>'
                    . ($chip ? '<span class="chip" style="background:#' . Html::e($cor[1]) . '">' : '')
                    . Html::celula($v, in_array($i, $b->colunasMoeda, true))
                    . ($chip ? '</span>' : '') . '</td>';
            }
            $h .= '</tr>';
        }

        $h .= '</tbody></table></div>';
        $total = count($b->linhas);
        if ($total > self::MAX_LINHAS) {
            $h .= '<p class="corte">Mostrando ' . number_format(self::MAX_LINHAS, 0, ',', '.') . ' de '
                . number_format($total, 0, ',', '.') . ' linhas. Baixe o XLSX para ver todas.</p>';
        }

        return $h;
    }
}
