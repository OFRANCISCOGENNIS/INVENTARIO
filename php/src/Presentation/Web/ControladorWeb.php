<?php

declare(strict_types=1);

namespace Inventario\Presentation\Web;

use Inventario\Application\UseCase\ModoCarga;
use Inventario\Application\UseCase\SolicitacaoAnalise;
use Inventario\Presentation\Aplicacao;
use Throwable;

/**
 * Site: a pagina (Nova analise / Resultados / Ajuda) e montada no navegador
 * por public/assets/app.js; este controlador entrega a pagina, recebe as
 * bases em POST ?api=analisar (resposta JSON) e serve os arquivos gerados.
 */
final class ControladorWeb
{
    private const EXTENSOES = ['xlsx', 'xlsm', 'xls', 'ods', 'csv'];

    public function __construct(private readonly Aplicacao $app)
    {
    }

    public function tratar(): void
    {
        if (!$this->autorizado()) {
            return;
        }
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $_SESSION['csrf'] ??= bin2hex(random_bytes(16));

        if (isset($_GET['baixar'])) {
            $this->baixar((string) $_GET['baixar']);

            return;
        }
        if (($_GET['api'] ?? '') === 'analisar') {
            $this->api();

            return;
        }
        $html = (string) file_get_contents(__DIR__ . '/pagina.html');
        echo str_replace('{{CSRF}}', Html::e($_SESSION['csrf']), $html);
    }

    private function api(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            $this->json(405, ['erro' => 'Use POST para enviar as bases.']);

            return;
        }
        if (!hash_equals($_SESSION['csrf'], (string) ($_SERVER['HTTP_X_CSRF'] ?? $_POST['csrf'] ?? ''))) {
            $this->json(403, ['erro' => 'Sessão expirada. Recarregue a página e envie de novo.']);

            return;
        }
        // upload acima de post_max_size chega sem $_POST nem $_FILES
        if ($_FILES === [] && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
            $this->json(413, ['erro' => 'Os arquivos passam do limite de envio do servidor ('
                . ini_get('post_max_size') . '). Envie menos arquivos por vez.']);

            return;
        }
        try {
            $this->limparSaidasAntigas();
            $this->json(200, $this->analisar());
        } catch (Throwable $e) {
            $this->json(500, ['erro' => 'Erro na análise: ' . $e->getMessage()]);
        }
    }

    /** @return array<string, mixed> */
    private function analisar(): array
    {
        $tmp = $this->app->pastaDados . '/uploads/' . bin2hex(random_bytes(8));
        mkdir($tmp, 0775, true);
        try {
            $erros = [];
            $arquivos = $this->recebidos('bases', $tmp, $erros);
            $precos = array_key_first($this->recebidos('precos', $tmp, $erros));
            if ($arquivos === []) {
                return ['erro' => $erros !== [] ? implode(' ', $erros) : 'Selecione ao menos um arquivo para executar a análise.'];
            }

            $modo = ModoCarga::tryFrom((string) ($_POST['modo'] ?? 'juntar')) ?? ModoCarga::JUNTAR;
            $res = $this->app->analisarInventario()->executar(new SolicitacaoAnalise($arquivos, $modo, $precos));
            $erros = array_merge($erros, $res->leitura->erros, $res->erros);
            if ($res->analises === []) {
                return ['erro' => 'Nenhuma base pôde ser analisada. ' . implode(' ', $erros)];
            }

            $visao = new VisaoResultados();
            $render = new RenderizadorHtml();
            $analises = [];
            foreach ($res->analises as $ctx) {
                $rel = $this->app->montador()->montar($ctx);
                $nome = Aplicacao::nomeSaida($ctx->base->nome) . ' - ' . $ctx->agora->format('Ymd-His');
                $links = [];
                foreach ($this->app->exportadores() as $ext => $exp) {
                    $arq = $nome . '.' . $ext;
                    $exp->exportar($rel, $this->app->pastaSaidas . '/' . $arq);
                    $links[strtoupper($ext)] = '?baixar=' . rawurlencode($arq);
                }
                $analises[] = $visao->montar($ctx, $links, $render->relatorio($rel));
            }

            return [
                'modo' => count($arquivos) > 1 ? $modo->value : 'unica',
                'arquivos' => array_values($arquivos),
                'precos' => $precos === null ? null : basename((string) ($_FILES['precos']['name'] ?? '')),
                'avisos' => $erros,
                'analises' => $analises,
            ];
        } finally {
            $this->limpar($tmp);
        }
    }

    /**
     * Move os arquivos enviados no campo para a pasta temporaria.
     *
     * @param list<string> $erros
     * @return array<string, string> caminho => nome original
     */
    private function recebidos(string $campo, string $tmp, array &$erros): array
    {
        $f = $_FILES[$campo] ?? null;
        if (!is_array($f)) {
            return [];
        }
        $nomes = (array) $f['name'];
        $out = [];
        foreach (array_keys($nomes) as $i) {
            $erro = (int) ((array) $f['error'])[$i];
            if ($erro === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $nome = basename((string) $nomes[$i]);
            if ($erro !== UPLOAD_ERR_OK) {
                $erros[] = $erro === UPLOAD_ERR_INI_SIZE || $erro === UPLOAD_ERR_FORM_SIZE
                    ? "\"{$nome}\": arquivo maior que o limite do servidor (" . ini_get('upload_max_filesize') . ').'
                    : "\"{$nome}\": falha no envio (código {$erro}).";
                continue;
            }
            $ext = strtolower(pathinfo($nome, PATHINFO_EXTENSION));
            if (!in_array($ext, self::EXTENSOES, true)) {
                $erros[] = "\"{$nome}\": formato não suportado. Use XLSX, XLSM, XLS, ODS ou CSV.";
                continue;
            }
            $dest = "{$tmp}/{$campo}-{$i}.{$ext}";
            move_uploaded_file((string) ((array) $f['tmp_name'])[$i], $dest);
            $out[$dest] = $nome;
        }

        return $out;
    }

    /** @param array<string, mixed> $dados */
    private function json(int $status, array $dados): void
    {
        http_response_code($status === 200 && isset($dados['erro']) ? 422 : $status);
        $corpo = (string) json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        // a resposta de uma base grande passa de 15 MB; comprimida cai ~10x
        if (strlen($corpo) > 2048 && str_contains((string) ($_SERVER['HTTP_ACCEPT_ENCODING'] ?? ''), 'gzip')) {
            $gz = gzencode($corpo, 3);
            if ($gz !== false) {
                header('Content-Encoding: gzip');
                header('Vary: Accept-Encoding');
                $corpo = $gz;
            }
        }
        header('Content-Length: ' . strlen($corpo));
        echo $corpo;
    }

    /** Apaga arquivos gerados ha mais de um dia (o disco do servidor e pequeno). */
    private function limparSaidasAntigas(): void
    {
        $limite = time() - 86400;
        foreach (glob($this->app->pastaSaidas . '/*') ?: [] as $f) {
            if (is_file($f) && (int) @filemtime($f) < $limite) {
                @unlink($f);
            }
        }
    }

    /**
     * Com a variavel INVENTARIO_SENHA definida (site publicado na internet), pede
     * usuario/senha do navegador. Sem ela (uso local), entra direto.
     */
    private function autorizado(): bool
    {
        $senha = (string) getenv('INVENTARIO_SENHA');
        if ($senha === '') {
            return true;
        }
        $usuario = (string) (getenv('INVENTARIO_USUARIO') ?: 'inventario');
        if (hash_equals($usuario, (string) ($_SERVER['PHP_AUTH_USER'] ?? ''))
            && hash_equals($senha, (string) ($_SERVER['PHP_AUTH_PW'] ?? ''))) {
            return true;
        }
        header('WWW-Authenticate: Basic realm="Analise de Inventario", charset="UTF-8"');
        http_response_code(401);
        echo 'Acesso restrito.';

        return false;
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

    private function limpar(string $dir): void
    {
        foreach (glob($dir . '/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($dir);
    }
}
