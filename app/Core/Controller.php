<?php

namespace App\Core;

abstract class Controller
{
    /**
     * Renderiza uma view dentro de um layout.
     * As chaves do array $dados viram variáveis disponíveis tanto na view quanto no layout.
     *
     * @param string      $view   Caminho relativo a app/Views/ (sem .php), ex: 'painel/index'
     * @param array       $dados  Variáveis a injetar na view
     * @param string|null $layout Nome do layout em app/Views/layouts/ (null = sem layout)
     */
    protected function render(string $view, array $dados = [], ?string $layout = 'base'): void
    {
        extract($dados, EXTR_SKIP);

        $viewPath = __DIR__ . '/../Views/' . $view . '.php';

        if (!file_exists($viewPath)) {
            http_response_code(500);
            throw new \RuntimeException("View não encontrada: {$view}");
        }

        if ($layout === null) {
            require $viewPath;
            return;
        }

        $layoutPath = __DIR__ . '/../Views/layouts/' . $layout . '.php';

        if (!file_exists($layoutPath)) {
            http_response_code(500);
            throw new \RuntimeException("Layout não encontrado: {$layout}");
        }

        ob_start();
        require $viewPath;
        $conteudo = ob_get_clean();

        require $layoutPath;
    }

    /**
     * Redireciona para uma URL e encerra a execução.
     */
    protected function redirect(string $url): never
    {
        header('Location: ' . $url);
        exit;
    }

    /**
     * Retorna uma resposta JSON e encerra a execução.
     */
    protected function json(mixed $dados, int $codigo = 200): never
    {
        http_response_code($codigo);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($dados, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Retorna os dados de um formulário POST sanitizados (string → trim, sem tags).
     */
    protected function input(string $campo, string $padrao = ''): string
    {
        return htmlspecialchars(trim($_POST[$campo] ?? $padrao), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Armazena uma mensagem flash na sessão para ser exibida na próxima requisição.
     * $tipo: 'success' | 'error' | 'warning' | 'info'
     */
    protected function flash(string $mensagem, string $tipo = 'success'): void
    {
        $_SESSION['flash'] = ['mensagem' => $mensagem, 'tipo' => $tipo];
    }
}
