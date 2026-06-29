<?php

namespace Tests\Unit;

use App\Core\Controller;
use PHPUnit\Framework\TestCase;
use Tests\Support\RedirectException;

/**
 * Subclasse concreta de Controller para expor os métodos protegidos nos testes.
 * Controller é abstract e não pode ser instanciado diretamente.
 */
class ControllerTestDouble extends Controller
{
    public function chamarInput(string $campo, string $padrao = ''): string
    {
        return $this->input($campo, $padrao);
    }

    public function chamarFlash(string $mensagem, string $tipo = 'success'): void
    {
        $this->flash($mensagem, $tipo);
    }

    public function chamarRedirect(string $url): never
    {
        $this->redirect($url);
    }
}

class ControllerTest extends TestCase
{
    private ControllerTestDouble $controller;

    protected function setUp(): void
    {
        $this->controller = new ControllerTestDouble();
        $_POST    = [];
        $_SESSION = [];
        $GLOBALS['_test_headers'] = [];
    }

    protected function tearDown(): void
    {
        $_POST    = [];
        $_SESSION = [];
    }

    // =========================================================================
    // input()
    // =========================================================================

    public function testInputRetornaValorDoPost(): void
    {
        $_POST['campo'] = 'valor';

        $this->assertSame('valor', $this->controller->chamarInput('campo'));
    }

    public function testInputRetornaPadraoSeCampoAusente(): void
    {
        $this->assertSame('padrao', $this->controller->chamarInput('inexistente', 'padrao'));
    }

    public function testInputRetornaStringVaziaPorPadrao(): void
    {
        $this->assertSame('', $this->controller->chamarInput('inexistente'));
    }

    public function testInputTrimEspacosEmBranco(): void
    {
        $_POST['campo'] = '  valor com espaços  ';

        $this->assertSame('valor com espaços', $this->controller->chamarInput('campo'));
    }

    public function testInputEscapeTagsHtml(): void
    {
        $_POST['campo'] = '<script>alert(1)</script>';

        $saida = $this->controller->chamarInput('campo');

        $this->assertStringNotContainsString('<script>', $saida);
        $this->assertStringContainsString('&lt;script&gt;', $saida);
    }

    public function testInputEscapeAspasDuplas(): void
    {
        $_POST['campo'] = '"aspas duplas"';

        $saida = $this->controller->chamarInput('campo');

        $this->assertStringContainsString('&quot;', $saida);
    }

    public function testInputEscapeAspasSimplesComEntQuotes(): void
    {
        $_POST['campo'] = "aspas 'simples'";

        $saida = $this->controller->chamarInput('campo');

        $this->assertStringContainsString('&#039;', $saida);
    }

    // =========================================================================
    // flash()
    // =========================================================================

    public function testFlashArmazenaEmSessao(): void
    {
        $this->controller->chamarFlash('Operação concluída');

        $this->assertSame('Operação concluída', $_SESSION['flash']['mensagem']);
    }

    public function testFlashTipoDefaultESuccess(): void
    {
        $this->controller->chamarFlash('msg');

        $this->assertSame('success', $_SESSION['flash']['tipo']);
    }

    public function testFlashAceitaTiposCustomizados(): void
    {
        $this->controller->chamarFlash('Aviso importante', 'warning');

        $this->assertSame('warning', $_SESSION['flash']['tipo']);
        $this->assertSame('Aviso importante', $_SESSION['flash']['mensagem']);
    }

    public function testFlashSobrescreveMensagemAnterior(): void
    {
        $this->controller->chamarFlash('Primeira mensagem', 'info');
        $this->controller->chamarFlash('Segunda mensagem', 'error');

        $this->assertSame('Segunda mensagem', $_SESSION['flash']['mensagem']);
        $this->assertSame('error',            $_SESSION['flash']['tipo']);
    }

    // =========================================================================
    // redirect()
    // =========================================================================

    public function testRedirectEnviaHeaderLocation(): void
    {
        $this->expectException(RedirectException::class);
        $this->expectExceptionMessage('/painel');

        $this->controller->chamarRedirect('/painel');
    }

    public function testRedirectUrlCompleta(): void
    {
        $this->expectException(RedirectException::class);
        $this->expectExceptionMessage('/cardapio?pagina=2');

        $this->controller->chamarRedirect('/cardapio?pagina=2');
    }
}
