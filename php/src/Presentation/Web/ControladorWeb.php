<?php

declare(strict_types=1);

namespace Inventario\Presentation\Web;

use Inventario\Application\UseCase\ModoCarga;
use Inventario\Application\UseCase\SolicitacaoAnalise;
use Inventario\Domain\Support\Formatador;
use Inventario\Presentation\Aplicacao;
use Throwable;

/**
 * Pagina unica: formulario de upload, previa/relatorio e download dos
 * arquivos gerados (o mesmo fluxo do CarregarBases).
 */
final class ControladorWeb
{
    private const EXTENSOES = ['xlsx', 'xlsm', 'xls', 'ods', 'csv'];

    public function __construct(private readonly Aplicacao $app)
    {
    }

    public function tratar(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $_SESSION['csrf'] ??= bin2hex(random_bytes(16));

        if (isset($_GET['baixar'])) {
            $this->baixar((string) $_GET['baixar']);

            return;
        }
        $conteudo = '';
        $erros = [];
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            if (!hash_equals($_SESSION['csrf'], (string) ($_POST['csrf'] ?? ''))) {
                $erros[] = 'Sessao expirada. Recarregue a pagina e envie de novo.';
            } else {
                try {
                    [$conteudo, $erros] = $this->analisar();
                } catch (Throwable $e) {
                    $erros[] = 'Erro na analise: ' . $e->getMessage();
                }
            }
        }
        echo $this->pagina($this->formulario() . $this->mensagens($erros) . $conteudo);
    }

    /** @return array{0: string, 1: list<string>} */
    private function analisar(): array
    {
        $tmp = $this->app->pastaDados . '/uploads/' . bin2hex(random_bytes(8));
        mkdir($tmp, 0775, true);
        $arquivos = [];
        $erros = [];
        $f = $_FILES['bases'] ?? null;
        $n = is_array($f['name'] ?? null) ? count($f['name']) : 0;
        for ($i = 0; $i < $n; $i++) {
            if ($f['error'][$i] === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $nome = basename((string) $f['name'][$i]);
            if ($f['error'][$i] !== UPLOAD_ERR_OK) {
                $erros[] = "{$nome}: falha no envio (codigo {$f['error'][$i]})";
                continue;
            }
            $ext = strtolower(pathinfo($nome, PATHINFO_EXTENSION));
            if (!in_array($ext, self::EXTENSOES, true)) {
                $erros[] = "{$nome}: formato nao suportado";
                continue;
            }
            $dest = $tmp . '/' . $i . '.' . $ext;
            move_uploaded_file($f['tmp_name'][$i], $dest);
            $arquivos[$dest] = $nome;
        }
        $precos = null;
        $p = $_FILES['precos'] ?? null;
        if (is_array($p) && ($p['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo((string) $p['name'], PATHINFO_EXTENSION));
            if (in_array($ext, self::EXTENSOES, true)) {
                $precos = $tmp . '/precos.' . $ext;
                move_uploaded_file($p['tmp_name'], $precos);
            }
        }
        if ($arquivos === []) {
            return ['', array_merge($erros, ['Selecione ao menos um arquivo de base.'])];
        }

        $modo = ModoCarga::tryFrom((string) ($_POST['modo'] ?? 'juntar')) ?? ModoCarga::JUNTAR;
        $res = $this->app->analisarInventario()->executar(new SolicitacaoAnalise($arquivos, $modo, $precos));
        $erros = array_merge($erros, $res->leitura->erros, $res->erros);

        $html = $this->previa($res->leitura->previa($modo === ModoCarga::JUNTAR));
        $render = new RenderizadorHtml();
        foreach ($res->analises as $ctx) {
            $rel = $this->app->montador()->montar($ctx);
            $nomeBase = Aplicacao::nomeSaida($ctx->base->nome) . ' - ' . $ctx->agora->format('Ymd-His');
            $links = [];
            foreach ($this->app->exportadores() as $ext => $exp) {
                $arq = $nomeBase . '.' . $ext;
                $exp->exportar($rel, $this->app->pastaSaidas . '/' . $arq);
                $links['Baixar ' . strtoupper($ext)] = '?baixar=' . rawurlencode($arq);
            }
            $html .= '<article class="relatorio">' . $render->relatorio($rel, $links) . '</article>';
        }
        $this->limpar($tmp);

        return [$html, $erros];
    }

    private function baixar(string $arq): void
    {
        $arq = basename($arq);
        $caminho = $this->app->pastaSaidas . '/' . $arq;
        if ($arq === '' || !is_file($caminho)) {
            http_response_code(404);
            echo 'Arquivo nao encontrado.';

            return;
        }
        $tipo = str_ends_with($arq, '.json') ? 'application/json'
            : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
        header('Content-Type: ' . $tipo);
        header('Content-Disposition: attachment; filename="' . str_replace('"', '', $arq) . '"');
        header('Content-Length: ' . filesize($caminho));
        readfile($caminho);
    }

    /** @param list<array{arquivo: string, aba: string, linhas: int, ausentes: list<string>, obrigatoriasFaltando: list<string>}> $previa */
    private function previa(array $previa): string
    {
        if ($previa === []) {
            return '';
        }
        $h = '<details class="previa"><summary>Previa da carga (' . count($previa) . ' arquivo(s))</summary><table><tr><th>ARQUIVO</th><th>ABA</th><th>LINHAS</th><th>COLUNAS</th></tr>';
        foreach ($previa as $p) {
            $col = $p['obrigatoriasFaltando'] !== [] ? 'FALTAM: ' . implode(', ', $p['obrigatoriasFaltando'])
                : ($p['ausentes'] !== [] ? 'sem: ' . implode(', ', $p['ausentes']) . ' (fica vazia)' : 'ok');
            $h .= '<tr><td>' . Html::e($p['arquivo']) . '</td><td>' . Html::e($p['aba']) . '</td><td class="num">'
                . Formatador::inteiro($p['linhas']) . '</td><td>' . Html::e($col) . '</td></tr>';
        }

        return $h . '</table></details>';
    }

    private function formulario(): string
    {
        $csrf = Html::e($_SESSION['csrf']);

        return <<<HTML
<form class="carga" method="post" enctype="multipart/form-data">
  <input type="hidden" name="csrf" value="{$csrf}">
  <label class="campo"><span>Bases de analise (xlsx, xlsm, xls, ods, csv)</span>
    <input type="file" name="bases[]" multiple required accept=".xlsx,.xlsm,.xls,.ods,.csv"></label>
  <fieldset class="campo"><legend>Com mais de um arquivo</legend>
    <label><input type="radio" name="modo" value="juntar" checked> Juntar: uma analise com todas as bases</label>
    <label><input type="radio" name="modo" value="separar"> Separar: uma analise por base (sem historico)</label>
  </fieldset>
  <label class="campo"><span>Base de precos MIN/MAX (opcional)</span>
    <input type="file" name="precos" accept=".xlsx,.xls,.ods,.csv"></label>
  <button type="submit" class="botao primario">Analisar</button>
</form>
HTML;
    }

    /** @param list<string> $erros */
    private function mensagens(array $erros): string
    {
        if ($erros === []) {
            return '';
        }

        return '<ul class="erros">' . implode('', array_map(static fn (string $e): string => '<li>' . Html::e($e) . '</li>', $erros)) . '</ul>';
    }

    private function limpar(string $dir): void
    {
        foreach (glob($dir . '/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($dir);
    }

    private function pagina(string $corpo): string
    {
        $css = file_get_contents(__DIR__ . '/estilo.css');

        return <<<HTML
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Analise de Inventario</title>
<style>{$css}</style>
</head>
<body>
<header class="topo"><h1>Analise de Inventario <small>SAP x PRJ</small></h1></header>
<main>{$corpo}</main>
<script>
document.addEventListener('click', function (ev) {
  var b = ev.target.closest('.guia');
  if (!b) return;
  var rel = b.closest('.relatorio');
  rel.querySelectorAll('.guia, .painel').forEach(function (e) { e.classList.remove('ativo'); });
  b.classList.add('ativo');
  rel.querySelector('#' + b.dataset.alvo).classList.add('ativo');
});
</script>
</body>
</html>
HTML;
    }
}
