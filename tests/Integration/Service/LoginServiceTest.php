<?php

namespace Tests\Integration\Service;

use App\Exceptions\CredenciaisInvalidasException;
use App\Services\LoginService;
use Tests\Integration\DatabaseTestCase;

class LoginServiceTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->inserirUsuario('70001', 'FUNCIONARIO TESTE', 'comum', 1, password_hash('senha123', PASSWORD_BCRYPT, ['cost' => 4]));
    }

    public function testAutenticarComSucessoRetornaUsuario(): void
    {
        $usuario = LoginService::autenticar('70001', 'senha123');

        $this->assertSame('FUNCIONARIO TESTE', $usuario['nome']);
        $this->assertSame('comum', $usuario['tipo']);
    }

    public function testAutenticarLancaCredenciaisInvalidasComSenhaErrada(): void
    {
        $this->expectException(CredenciaisInvalidasException::class);

        LoginService::autenticar('70001', 'senha_errada');
    }

    public function testAutenticarLancaCredenciaisInvalidasComMatriculaInexistente(): void
    {
        $this->expectException(CredenciaisInvalidasException::class);

        LoginService::autenticar('inexistente', 'qualquer');
    }

    public function testRegistrarLogoutNaoLancaExcecao(): void
    {
        LoginService::registrarLogout('70001', 'FUNCIONARIO TESTE');
        LoginService::registrarLogout(null, null);

        $this->assertTrue(true);
    }
}
