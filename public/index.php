<?php

// Carrega o Autoload do Composer
require_once __DIR__ . '/../vendor/autoload.php';

// Timezone do Brasil (UTC-3)
date_default_timezone_set('America/Sao_Paulo');

// Carrega variáveis do .env manualmente (já que não há vlucas/phpdotenv via composer)
$envPath = __DIR__ . '/../.env';
if (file_exists($envPath)) {
    $envVars = parse_ini_file($envPath);
    if ($envVars) {
        foreach ($envVars as $k => $v) {
            $_ENV[$k] = $v;
            $_SERVER[$k] = $v;
        }
    }
}

// Define exibição de erros com base no ambiente (.env APP_ENV=development para ativar)
$appEnv = $_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: 'production';
if ($appEnv === 'development') {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(E_ALL);               // registra no log mas não exibe
}

// Captura exceções não tratadas e exibe mensagem amigável
set_exception_handler(function (Throwable $e) use ($appEnv): void {
    http_response_code(500);
    if ($appEnv === 'development') {
        echo '<pre>' . htmlspecialchars($e->getMessage() . "\n" . $e->getTraceAsString()) . '</pre>';
    } else {
        echo '<h1>Erro interno</h1><p>Tente novamente em instantes.</p>';
    }
    error_log($e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
});

// Inicia a sessão uma única vez para toda a aplicação
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Captura a URI e o método HTTP da requisição
$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

// Carrega as rotas e despacha a requisição
$router = require_once __DIR__ . '/../app/routes.php';
$router->dispatch($uri, $method);
