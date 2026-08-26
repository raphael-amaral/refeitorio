<?php

namespace Tests\Integration\View;

use PHPUnit\Framework\TestCase;

class PasswordPolicyViewTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
        $_SERVER['REQUEST_URI'] = '/painel';
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        unset($_SERVER['REQUEST_URI']);
    }

    public function testModalDeTrocaDeSenhaExigeSeisCaracteresParaUsuarioComum(): void
    {
        $_SESSION['usuario'] = [
            'matricula' => '50001',
            'nome'      => 'FUNCIONARIO TESTE',
            'tipo'      => 'comum',
        ];
        $titulo = 'Teste';
        $conteudo = '';

        ob_start();
        require dirname(__DIR__, 3) . '/app/Views/layouts/base.php';
        $html = (string) ob_get_clean();

        $this->assertSame(2, substr_count($html, 'minlength="6"'));
        $this->assertStringContainsString('if (nova.length < 6)', $html);
        $this->assertStringContainsString('pelo menos 6 caracteres', $html);
    }

    public function testFormularioDeUsuarioAtualizaMinimoConformePerfil(): void
    {
        $usuarios = [];
        $busca = '';
        $pagina = 1;
        $totalPaginas = 1;

        ob_start();
        require dirname(__DIR__, 3) . '/app/Views/usuario/index.php';
        $html = (string) ob_get_clean();

        $this->assertStringContainsString("selectTipo.value === 'comum' ? 6 : 5", $html);
        $this->assertStringContainsString('inputSenha.minLength = minimo', $html);
        $this->assertStringContainsString('inputConfirmar.minLength = minimo', $html);
    }
}
