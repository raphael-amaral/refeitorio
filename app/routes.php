<?php

use App\Core\Router;

$router = new Router();

// ---------------------------------------------------------------------------
// Rotas públicas (sem autenticação)
// ---------------------------------------------------------------------------
$router->get('/',       'App\Controllers\LoginController', 'index');
$router->post('/login', 'App\Controllers\LoginController', 'autenticar');
$router->get('/logout', 'App\Controllers\LoginController', 'logout');

// ---------------------------------------------------------------------------
// Painel (requer login)
// ---------------------------------------------------------------------------
$router->get('/painel', 'App\Controllers\PainelController', 'index', ['App\Middleware\Auth']);

// ---------------------------------------------------------------------------
// Cardápios
// Rotas estáticas ANTES das dinâmicas para evitar conflito de padrão regex
// ---------------------------------------------------------------------------
$router->get('/cardapio',      'App\Controllers\CardapioController', 'index', ['App\Middleware\Auth']);
$router->get('/cardapio/novo', 'App\Controllers\CardapioController', 'novo',  ['App\Middleware\Auth@admin']);
$router->post('/cardapio',     'App\Controllers\CardapioController', 'criar', ['App\Middleware\Auth@admin']);

$router->get('/cardapio/{id}/editar',   'App\Controllers\CardapioController', 'editar',    ['App\Middleware\Auth@admin']);
$router->post('/cardapio/{id}',         'App\Controllers\CardapioController', 'atualizar', ['App\Middleware\Auth@admin']);
$router->post('/cardapio/{id}/excluir', 'App\Controllers\CardapioController', 'excluir',   ['App\Middleware\Auth@admin']);

// Edição de cardápio por dia/tipo (copa)
$router->get('/cardapio/{data}/{tipo}/editar', 'App\Controllers\CardapioController', 'editarDia',  ['App\Middleware\Auth@copa']);
$router->post('/cardapio/{data}/{tipo}',        'App\Controllers\CardapioController', 'salvarDia',  ['App\Middleware\Auth@copa']);

// ---------------------------------------------------------------------------
// Agendamentos
// ---------------------------------------------------------------------------
$router->get('/registro',              'App\Controllers\RegistroController', 'index',   ['App\Middleware\Auth']);
$router->post('/registro',             'App\Controllers\RegistroController', 'criar',   ['App\Middleware\Auth']);
$router->post('/registro/{id}/cancelar', 'App\Controllers\RegistroController', 'cancelar', ['App\Middleware\Auth']);
$router->post('/registro/{id}/concluir', 'App\Controllers\RegistroController', 'concluir', ['App\Middleware\Auth@copa']);

// ---------------------------------------------------------------------------
// Usuários (apenas admin / dev)
// ---------------------------------------------------------------------------
$router->get('/usuario',                          'App\Controllers\UsuarioController', 'index',    ['App\Middleware\Auth@admin']);
$router->post('/usuario',                         'App\Controllers\UsuarioController', 'criar',    ['App\Middleware\Auth@admin']);
$router->post('/usuario/{matricula}/inativar',    'App\Controllers\UsuarioController', 'inativar', ['App\Middleware\Auth@admin']);
$router->post('/usuario/{matricula}/ativar',      'App\Controllers\UsuarioController', 'ativar',   ['App\Middleware\Auth@admin']);
$router->post('/usuario/{matricula}/editar',      'App\Controllers\UsuarioController', 'editar',   ['App\Middleware\Auth@admin']);

return $router;
