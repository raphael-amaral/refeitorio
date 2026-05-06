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
// Conta (qualquer usuário autenticado)
// ---------------------------------------------------------------------------
$router->post('/conta/senha', 'App\Controllers\ContaController', 'alterarSenha', ['App\Middleware\Auth@comum']);

// ---------------------------------------------------------------------------
// Cardápios
// Rotas estáticas ANTES das dinâmicas para evitar conflito de padrão regex
// ---------------------------------------------------------------------------
$router->get('/cardapio',               'App\Controllers\CardapioController', 'index',    ['App\Middleware\Auth@snd']);
$router->post('/cardapio',              'App\Controllers\CardapioController', 'criar',    ['App\Middleware\Auth@dev']);
$router->get('/cardapio/{id}/editar',   'App\Controllers\CardapioController', 'editar',   ['App\Middleware\Auth@dev']);
$router->post('/cardapio/{id}',         'App\Controllers\CardapioController', 'atualizar', ['App\Middleware\Auth@dev']);
$router->post('/cardapio/{id}/excluir', 'App\Controllers\CardapioController', 'excluir',  ['App\Middleware\Auth@dev']);

// Edição de cardápio por dia/tipo — painel da SND
$router->post('/cardapio/{data}/{tipo}', 'App\Controllers\CardapioController', 'salvarDia', ['App\Middleware\Auth@snd']);

// ---------------------------------------------------------------------------
// Agendamentos (apenas usuários comuns agendam; concluir é snd+)
// ---------------------------------------------------------------------------
$router->get('/registro',              'App\Controllers\RegistroController', 'index',   ['App\Middleware\Auth@comum']);
$router->post('/registro',             'App\Controllers\RegistroController', 'criar',   ['App\Middleware\Auth@comum']);
$router->post('/registro/{id}/cancelar', 'App\Controllers\RegistroController', 'cancelar', ['App\Middleware\Auth@comum']);
$router->post('/registro/{id}/concluir', 'App\Controllers\RegistroController', 'concluir', ['App\Middleware\Auth@snd']);

// ---------------------------------------------------------------------------
// Scanner de crachá (SND) - Via App Android
// ---------------------------------------------------------------------------
$router->post('/api/login', 'App\Controllers\ScannerController', 'loginApp');
$router->post('/scanner/processar', 'App\Controllers\ScannerController', 'processar', ['App\Middleware\Auth@apiSnd']);
$router->get('/scanner/lista-hoje', 'App\Controllers\ScannerController', 'listaHoje', ['App\Middleware\Auth@apiSnd']);

// ---------------------------------------------------------------------------
// Relatórios (snd, dp e dev)
// ---------------------------------------------------------------------------
$router->get('/relatorios/pdf', 'App\Controllers\RelatorioController', 'pdf',   ['App\Middleware\Auth@relatorio']);
$router->get('/relatorios',     'App\Controllers\RelatorioController', 'index', ['App\Middleware\Auth@relatorio']);

// ---------------------------------------------------------------------------
// Exportação Questor (dp e dev)
// ---------------------------------------------------------------------------
$router->get('/questor',          'App\Controllers\QuestorController', 'index',    ['App\Middleware\Auth@dp']);
$router->get('/questor/exportar', 'App\Controllers\QuestorController', 'exportar', ['App\Middleware\Auth@dp']);

// ---------------------------------------------------------------------------
// Usuários (apenas dev)
// ---------------------------------------------------------------------------
$router->get('/usuario',                          'App\Controllers\UsuarioController', 'index',    ['App\Middleware\Auth@dev']);
$router->post('/usuario',                         'App\Controllers\UsuarioController', 'criar',    ['App\Middleware\Auth@dev']);
$router->post('/usuario/importar',                'App\Controllers\UsuarioController', 'importar', ['App\Middleware\Auth@dev']);
$router->post('/usuario/{matricula}/inativar',    'App\Controllers\UsuarioController', 'inativar', ['App\Middleware\Auth@dev']);
$router->post('/usuario/{matricula}/ativar',      'App\Controllers\UsuarioController', 'ativar',   ['App\Middleware\Auth@dev']);
$router->post('/usuario/{matricula}/editar',      'App\Controllers\UsuarioController', 'editar',   ['App\Middleware\Auth@dev']);
$router->post('/usuario/{matricula}/excluir',     'App\Controllers\UsuarioController', 'excluir',  ['App\Middleware\Auth@dev']);

return $router;
