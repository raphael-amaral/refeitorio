<?php

namespace Tests\Integration\Controller;

use App\Controllers\ContaController;
use App\Models\Usuario;
use Tests\Integration\DatabaseTestCase;
use Tests\Support\RedirectException;

class ContaControllerTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->inserirUsuario(
            '50001',
            'FUNCIONARIO TESTE',
            'comum',
            1,
            password_hash('senhaAtual123', PASSWORD_BCRYPT, ['cost' => 4])
        );
        $_SESSION = [
            'usuario' => [
                'matricula'    => '50001',
                'tipo'         => 'comum',
                'senha_padrao' => true,
            ],
        ];
        $_POST = [];
    }

    protected function tearDown(): void
    {
        $_POST = [];
        $_SESSION = [];

        parent::tearDown();
    }

    public function testAlterarSenhaAtualizaUsuarioEExibeSucesso(): void
    {
        $_POST = [
            'senha_atual'           => 'senhaAtual123',
            'nova_senha'            => 'senhaNova123',
            'confirmar_nova_senha'  => 'senhaNova123',
        ];

        $redirect = $this->executarController();

        $usuario = Usuario::buscarPorMatricula('50001');
        $this->assertSame('/painel', $redirect->getLocation());
        $this->assertTrue(password_verify('senhaNova123', $usuario['senha']));
        $this->assertFalse($_SESSION['usuario']['senha_padrao']);
        $this->assertSame(
            ['mensagem' => 'Senha alterada com sucesso.', 'tipo' => 'success'],
            $_SESSION['flash']
        );
    }

    public function testAlterarSenhaCurtaExibeRegraDeSeisCaracteresSemAlterarSenha(): void
    {
        $_POST = [
            'senha_atual'          => 'senhaAtual123',
            'nova_senha'           => 'dp123',
            'confirmar_nova_senha' => 'dp123',
        ];

        $redirect = $this->executarController();

        $usuario = Usuario::buscarPorMatricula('50001');
        $this->assertSame('/painel', $redirect->getLocation());
        $this->assertTrue(password_verify('senhaAtual123', $usuario['senha']));
        $this->assertTrue($_SESSION['usuario']['senha_padrao']);
        $this->assertSame(
            ['mensagem' => 'A nova senha deve ter pelo menos 6 caracteres.', 'tipo' => 'error'],
            $_SESSION['flash']
        );
    }

    public function testAlterarSenhaComConfirmacaoDiferenteExibeErro(): void
    {
        $_POST = [
            'senha_atual'          => 'senhaAtual123',
            'nova_senha'           => 'senhaNova123',
            'confirmar_nova_senha' => 'outraSenha',
        ];

        $redirect = $this->executarController();

        $this->assertSame('/painel', $redirect->getLocation());
        $this->assertSame(
            ['mensagem' => 'A nova senha e a confirmação não coincidem.', 'tipo' => 'error'],
            $_SESSION['flash']
        );
    }

    private function executarController(): RedirectException
    {
        try {
            (new ContaController())->alterarSenha();
            $this->fail('O controller deveria redirecionar a requisição.');
        } catch (RedirectException $e) {
            return $e;
        }
    }
}
