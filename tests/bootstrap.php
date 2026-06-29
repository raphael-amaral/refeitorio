<?php

require_once __DIR__ . '/../vendor/autoload.php';

// Sobrescreve header() e http_response_code() nos namespaces de produção
// para que os testes possam capturar redirects como exceções.
require_once __DIR__ . '/Support/functions.php';

// Garante sessão ativa para testes que dependem de $_SESSION
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// getallheaders() só existe no SAPI web (Apache/nginx); em CLI não está disponível.
if (!function_exists('getallheaders')) {
    function getallheaders(): array
    {
        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (str_starts_with($name, 'HTTP_')) {
                $key = str_replace('_', '-', ucwords(strtolower(substr($name, 5)), '_'));
                $headers[$key] = $value;
            }
        }
        return $headers;
    }
}

// Funções globais definidas em public/index.php — necessárias para o Router e Controllers.
if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        return $_SESSION['_csrf_token'] ?? 'test_csrf_token';
    }

    function csrf_field(): string
    {
        $token = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');
        return '<input type="hidden" name="_csrf" value="' . $token . '">';
    }

    function csrf_validate_or_fail(): void
    {
        // No-op em testes: CSRF é validado em testes de segurança dedicados.
    }
}
