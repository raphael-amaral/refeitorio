<?php

namespace Tests\Unit;

use App\Core\Router;
use PHPUnit\Framework\TestCase;
use Tests\Support\RedirectException;

/**
 * Controlador mínimo usado exclusivamente nos testes do Router.
 * Registra se foi invocado e com quais parâmetros, sem chamar redirect/render.
 */
class RouterTestController
{
    public static bool $chamado       = false;
    public static array $params       = [];
    public static bool $middlewareFoi = false;

    public function acao(string ...$args): void
    {
        self::$chamado = true;
        self::$params  = $args;
    }
}

class RouterTestMiddleware
{
    public static function handle(): void
    {
        RouterTestController::$middlewareFoi = true;
    }
}

class RouterTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        $this->router = new Router();
        RouterTestController::$chamado       = false;
        RouterTestController::$params        = [];
        RouterTestController::$middlewareFoi = false;
        $GLOBALS['_test_http_status']        = 200;
    }

    // -------------------------------------------------------------------------
    // Registro de rotas GET e POST
    // -------------------------------------------------------------------------

    public function testGetRegistraEDespachaRota(): void
    {
        $this->router->get('/teste', RouterTestController::class, 'acao');
        $this->router->dispatch('/teste', 'GET');

        $this->assertTrue(RouterTestController::$chamado);
    }

    public function testPostRegistraEDespachaRota(): void
    {
        $this->router->post('/api/login', RouterTestController::class, 'acao');
        $this->router->dispatch('/api/login', 'POST');

        $this->assertTrue(RouterTestController::$chamado);
    }

    // -------------------------------------------------------------------------
    // Parâmetros dinâmicos
    // -------------------------------------------------------------------------

    public function testParamDinamicoEhCapturadoEPassado(): void
    {
        $this->router->get('/item/{id}', RouterTestController::class, 'acao');
        $this->router->dispatch('/item/42', 'GET');

        $this->assertTrue(RouterTestController::$chamado);
        $this->assertSame('42', RouterTestController::$params['id'] ?? null);
    }

    public function testMultiplosParamsDinamicos(): void
    {
        $this->router->get('/cardapio/{data}/{tipo}', RouterTestController::class, 'acao');
        $this->router->dispatch('/cardapio/2026-06-01/almoco', 'GET');

        $this->assertTrue(RouterTestController::$chamado);
        $this->assertSame('2026-06-01', RouterTestController::$params['data'] ?? null);
        $this->assertSame('almoco',     RouterTestController::$params['tipo'] ?? null);
    }

    public function testParamNaoFazMatchComBarras(): void
    {
        $this->router->get('/item/{id}', RouterTestController::class, 'acao');
        ob_start();
        $this->router->dispatch('/item/42/extra', 'GET');
        ob_end_clean();

        $this->assertFalse(RouterTestController::$chamado);
    }

    // -------------------------------------------------------------------------
    // 404 para rotas não cadastradas
    // -------------------------------------------------------------------------

    public function testRotaNaoEncontradaSetaHttp404(): void
    {
        ob_start();
        $this->router->dispatch('/rota-inexistente', 'GET');
        ob_end_clean();

        $this->assertSame(404, $GLOBALS['_test_http_status'] ?? null);
    }

    public function testMetodoErradoRetorna404(): void
    {
        $this->router->get('/apenas-get', RouterTestController::class, 'acao');

        ob_start();
        $this->router->dispatch('/apenas-get', 'POST');
        ob_end_clean();

        $this->assertFalse(RouterTestController::$chamado);
        $this->assertSame(404, $GLOBALS['_test_http_status'] ?? null);
    }

    // -------------------------------------------------------------------------
    // Middleware executado antes da action
    // -------------------------------------------------------------------------

    public function testMiddlewareEhExecutadoAntesDaAction(): void
    {
        $this->router->get(
            '/protegido',
            RouterTestController::class,
            'acao',
            [RouterTestMiddleware::class]
        );
        $this->router->dispatch('/protegido', 'GET');

        $this->assertTrue(RouterTestController::$middlewareFoi);
        $this->assertTrue(RouterTestController::$chamado);
    }

    public function testMiddlewareRedirecionaInterrompeAction(): void
    {
        // Middleware que lança RedirectException (simula Auth bloqueando)
        $this->router->get('/bloqueado', RouterTestController::class, 'acao', [
            'Tests\\Unit\\BlockingMiddleware',
        ]);

        try {
            $this->router->dispatch('/bloqueado', 'GET');
            $this->fail('Esperava RedirectException');
        } catch (RedirectException $e) {
            $this->assertFalse(RouterTestController::$chamado, 'Action não deve ser chamada após redirect');
        }
    }

    // -------------------------------------------------------------------------
    // Rotas estáticas têm precedência sobre dinâmicas (ordem de registro)
    // -------------------------------------------------------------------------

    public function testRotaEstaticaAntesDeDinamica(): void
    {
        $this->router->get('/cardapio/novo',   RouterTestController::class, 'acao');
        $this->router->get('/cardapio/{id}',   RouterTestController::class, 'acao');

        $this->router->dispatch('/cardapio/novo', 'GET');

        $this->assertTrue(RouterTestController::$chamado);
        // Nenhum parâmetro deve ter sido capturado (rota estática)
        $this->assertArrayNotHasKey('id', RouterTestController::$params);
    }
}

// Middleware auxiliar que sempre redireciona (para testar interrupção)
namespace Tests\Unit;

class BlockingMiddleware
{
    public static function handle(): void
    {
        throw new \Tests\Support\RedirectException('/');
    }
}
