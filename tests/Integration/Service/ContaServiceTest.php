<?php

namespace Tests\Integration\Service;

use App\Exceptions\SenhaAtualIncorretaException;
use App\Exceptions\SenhaCurtaException;
use App\Exceptions\SenhasNaoCoincidemException;
use App\Models\Usuario;
use App\Services\ContaService;
use Tests\Integration\DatabaseTestCase;

class ContaServiceTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->inserirUsuario('50001', 'FUNCIONARIO TESTE', 'comum', 1, password_hash('senhaAtual123', PASSWORD_BCRYPT, ['cost' => 4]));
    }

    public function testAlterarSenhaComSucesso(): void
    {
        ContaService::alterarSenha('50001', 'senhaAtual123', 'senhaNova123', 'senhaNova123');

        $usuario = Usuario::buscarPorMatricula('50001');
        $this->assertTrue(password_verify('senhaNova123', $usuario['senha']));
    }

    public function testAlterarSenhaLancaSenhaAtualIncorreta(): void
    {
        $this->expectException(SenhaAtualIncorretaException::class);

        ContaService::alterarSenha('50001', 'senha_errada', 'senhaNova123', 'senhaNova123');
    }

    public function testAlterarSenhaLancaSenhaCurta(): void
    {
        $this->expectException(SenhaCurtaException::class);

        ContaService::alterarSenha('50001', 'senhaAtual123', '123', '123');
    }

    public function testAlterarSenhaLancaSenhasNaoCoincidem(): void
    {
        $this->expectException(SenhasNaoCoincidemException::class);

        ContaService::alterarSenha('50001', 'senhaAtual123', 'senhaNova123', 'outraSenha');
    }

    public function testAlterarSenhaNaoAlteraSenhaQuandoValidacaoFalha(): void
    {
        try {
            ContaService::alterarSenha('50001', 'senhaAtual123', '123', '123');
        } catch (SenhaCurtaException) {
            // esperado
        }

        $usuario = Usuario::buscarPorMatricula('50001');
        $this->assertTrue(password_verify('senhaAtual123', $usuario['senha']));
    }
}
