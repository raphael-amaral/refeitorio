<?php
session_start(); // Sempre a primeira linha
date_default_timezone_set('America/Sao_Paulo');
require_once __DIR__ . '/../vendor/autoload.php';

use App\Controllers\AuthController;
use App\Controllers\RegistroController;

$url = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$auth = new AuthController();

switch ($url) {
    case '/':
    case '/login':
        $auth->index();
        break;

    case '/login/processar':
        $auth->login();
        break;

    case '/logout':
        $auth->logout();
        break;

    case '/dashboard':
        if (!isset($_SESSION['usuario_id'])) {
            header('Location: /login');
            exit;
        }
        require_once __DIR__ . '/../app/Views/dashboard.php';
        break;

    case '/agendar':
        if (!isset($_SESSION['usuario_id'])) {
            exit;
        }
        $controller = new RegistroController();
        $controller->agendar();
        break;

    default:
        http_response_code(404);
        echo "Página não encontrada.";
}
