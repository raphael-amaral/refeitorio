<?php

namespace App\Core;

class Router
{
    protected array $routes = [];

    /**
     * Registra uma rota, convertendo parâmetros dinâmicos (ex: {id}) em expressões regulares.
     *
     * @param string[] $middleware Lista de middlewares no formato 'Classe' ou 'Classe@metodo'.
     *                             Se apenas 'Classe', o método 'handle' é chamado.
     */
    public function add(
        string $method,
        string $uri,
        string $controller,
        string $action,
        array  $middleware = []
    ): void {
        // Transforma '/cardapio/{id}' em '#^/cardapio/(?P<id>[a-zA-Z0-9_-]+)$#'
        $uriPattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<\1>[a-zA-Z0-9_-]+)', $uri);
        $uriPattern = '#^' . $uriPattern . '$#';

        $this->routes[$method][$uriPattern] = [
            'controller' => $controller,
            'action'     => $action,
            'middleware' => $middleware,
        ];
    }

    public function get(string $uri, string $controller, string $action, array $middleware = []): void
    {
        $this->add('GET', $uri, $controller, $action, $middleware);
    }

    public function post(string $uri, string $controller, string $action, array $middleware = []): void
    {
        $this->add('POST', $uri, $controller, $action, $middleware);
    }

    /**
     * Encontra a rota correspondente à requisição, executa os middlewares e despacha o controller.
     */
    public function dispatch(string $uri, string $method): void
    {
        foreach ($this->routes[$method] ?? [] as $routePattern => $target) {
            if (!preg_match($routePattern, $uri, $matches)) {
                continue;
            }

            // Parâmetros de URL capturados pela regex (ex: ['id' => '42'])
            $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

            // Executa os middlewares antes do controller
            foreach ($target['middleware'] as $mw) {
                [$classe, $metodo] = str_contains($mw, '@')
                    ? explode('@', $mw, 2)
                    : [$mw, 'handle'];

                $classe::$metodo();
            }

            // Instancia o controller e chama a action
            $controllerClass = $target['controller'];
            $action          = $target['action'];

            $instance = new $controllerClass();
            call_user_func_array([$instance, $action], $params);
            return;
        }

        http_response_code(404);
        require __DIR__ . '/../Views/errors/404.php';
    }
}
