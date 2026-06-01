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

// Endurecimento de sessão
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', $_ENV['SESSION_SAMESITE'] ?? 'Lax');
ini_set('session.cookie_secure', $isHttps ? '1' : '0');

// Inicia a sessão uma única vez para toda a aplicação
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$sessionIdleTimeout = (int) ($_ENV['SESSION_IDLE_TIMEOUT'] ?? 1800);
if (isset($_SESSION['usuario'], $_SESSION['_last_activity'])) {
    $inativoPor = time() - (int) $_SESSION['_last_activity'];
    if ($inativoPor > $sessionIdleTimeout) {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool) $params['secure'], (bool) $params['httponly']);
        }
        session_destroy();
        session_start();
        session_regenerate_id(true);
        $_SESSION['erro_login'] = 'Sessao expirada por inatividade. Faca login novamente.';
    }
}

$_SESSION['_last_activity'] = time();

if (empty($_SESSION['_csrf_token'])) {
    $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
}

function csrf_token(): string
{
    return $_SESSION['_csrf_token'] ?? '';
}

function csrf_field(): string
{
    $token = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');
    return '<input type="hidden" name="_csrf" value="' . $token . '">';
}

function csrf_validate_or_fail(): void
{
    $tokenEnviado = $_POST['_csrf'] ?? '';
    $tokenSessao = $_SESSION['_csrf_token'] ?? '';

    if (!is_string($tokenEnviado) || !is_string($tokenSessao) || !hash_equals($tokenSessao, $tokenEnviado)) {
        header('Location: /logout');
        exit;
    }
}

// Captura a URI e o método HTTP da requisição
$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

// Carrega as rotas e despacha a requisição
$router = require_once __DIR__ . '/../app/routes.php';
$router->dispatch($uri, $method);
