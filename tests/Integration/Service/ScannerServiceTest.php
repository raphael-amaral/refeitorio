<?php

namespace Tests\Integration\Service;

use App\Exceptions\AgendamentoCanceladoException;
use App\Exceptions\CredenciaisInvalidasException;
use App\Exceptions\ForaDoHorarioExcecaoException;
use App\Exceptions\JaConcluidoException;
use App\Exceptions\SemAgendamentoException;
use App\Exceptions\SemCardapioHojeException;
use App\Exceptions\TipoNaoPermitidoException;
use App\Models\Registro;
use App\Services\ScannerService;
use Tests\Integration\DatabaseTestCase;

class ScannerServiceTest extends DatabaseTestCase
{
    private array $envOriginal;

    protected function setUp(): void
    {
        parent::setUp();
        $this->envOriginal = $_ENV;
    }

    protected function tearDown(): void
    {
        $_ENV = $this->envOriginal;
        parent::tearDown();
    }

    // =========================================================================
    // autenticar()
    // =========================================================================

    public function testAutenticarComTipoSndRetornaUsuario(): void
    {
        $this->inserirUsuario('20001', 'SND TESTE', 'snd', 1, password_hash('senha123', PASSWORD_BCRYPT, ['cost' => 4]));

        $usuario = ScannerService::autenticar('20001', 'senha123');

        $this->assertSame('SND TESTE', $usuario['nome']);
    }

    public function testAutenticarComTipoDevRetornaUsuario(): void
    {
        $this->inserirUsuario('20002', 'DEV TESTE', 'dev', 1, password_hash('senha123', PASSWORD_BCRYPT, ['cost' => 4]));

        $usuario = ScannerService::autenticar('20002', 'senha123');

        $this->assertSame('DEV TESTE', $usuario['nome']);
    }

    public function testAutenticarLancaCredenciaisInvalidasComSenhaErrada(): void
    {
        $this->inserirUsuario('20003', 'SND TESTE', 'snd', 1, password_hash('senha123', PASSWORD_BCRYPT, ['cost' => 4]));

        $this->expectException(CredenciaisInvalidasException::class);

        ScannerService::autenticar('20003', 'senha_errada');
    }

    public function testAutenticarLancaCredenciaisInvalidasParaTipoComum(): void
    {
        $this->inserirUsuario('20004', 'COMUM TESTE', 'comum', 1, password_hash('senha123', PASSWORD_BCRYPT, ['cost' => 4]));

        $this->expectException(CredenciaisInvalidasException::class);

        ScannerService::autenticar('20004', 'senha123');
    }

    public function testAutenticarLancaCredenciaisInvalidasParaMatriculaInexistente(): void
    {
        $this->expectException(CredenciaisInvalidasException::class);

        ScannerService::autenticar('inexistente', 'qualquer');
    }

    // =========================================================================
    // processar()
    // =========================================================================

    public function testProcessarConcluiAgendamentoDeHoje(): void
    {
        $this->inserirUsuario('20005', 'FUNCIONARIO TESTE');
        $idCardapio = $this->inserirCardapio(date('Y-m-d'), 'almoco');
        $this->inserirRegistro('20005', $idCardapio, 'agendado');

        $registro = ScannerService::processar('20005');

        $this->assertSame('FUNCIONARIO TESTE', $registro['nome_usuario']);
        $this->assertTrue(Registro::jaConcluidoPorCardapio('20005', $idCardapio));
    }

    public function testProcessarLancaSemAgendamentoQuandoNaoHaRegistroHoje(): void
    {
        $this->inserirUsuario('20006', 'FUNCIONARIO TESTE');

        $this->expectException(SemAgendamentoException::class);

        ScannerService::processar('20006');
    }

    public function testProcessarLancaJaConcluidoQuandoRegistroJaConcluido(): void
    {
        $this->inserirUsuario('20007', 'FUNCIONARIO TESTE');
        $idCardapio = $this->inserirCardapio(date('Y-m-d'), 'almoco');
        $this->inserirRegistro('20007', $idCardapio, 'concluido');

        $this->expectException(JaConcluidoException::class);

        ScannerService::processar('20007');
    }

    public function testProcessarLancaAgendamentoCanceladoQuandoRegistroCancelado(): void
    {
        $this->inserirUsuario('20008', 'FUNCIONARIO TESTE');
        $idCardapio = $this->inserirCardapio(date('Y-m-d'), 'almoco');
        $this->inserirRegistro('20008', $idCardapio, 'cancelado');

        $this->expectException(AgendamentoCanceladoException::class);

        ScannerService::processar('20008');
    }

    // =========================================================================
    // registrarExcecao()
    // =========================================================================

    public function testRegistrarExcecaoComSucesso(): void
    {
        $_ENV['EXCECAO_LIMIT_TIME'] = '23:59';
        $this->inserirUsuario('20009', 'FUNCIONARIO TESTE', 'comum', 1, password_hash('senha123', PASSWORD_BCRYPT, ['cost' => 4]));
        $idCardapio = $this->inserirCardapio(date('Y-m-d'), 'almoco');

        $usuario = ScannerService::registrarExcecao('20009', 'senha123');

        $this->assertSame('FUNCIONARIO TESTE', $usuario['nome']);
        $this->assertTrue(Registro::jaConcluidoPorCardapio('20009', $idCardapio));
    }

    public function testRegistrarExcecaoLancaForaDoHorario(): void
    {
        $_ENV['EXCECAO_LIMIT_TIME'] = (new \DateTime('-1 minute'))->format('H:i');
        $this->inserirUsuario('20010', 'FUNCIONARIO TESTE', 'comum', 1, password_hash('senha123', PASSWORD_BCRYPT, ['cost' => 4]));

        $this->expectException(ForaDoHorarioExcecaoException::class);

        ScannerService::registrarExcecao('20010', 'senha123');
    }

    public function testRegistrarExcecaoLancaCredenciaisInvalidas(): void
    {
        $_ENV['EXCECAO_LIMIT_TIME'] = '23:59';
        $this->inserirUsuario('20011', 'FUNCIONARIO TESTE', 'comum', 1, password_hash('senha123', PASSWORD_BCRYPT, ['cost' => 4]));

        $this->expectException(CredenciaisInvalidasException::class);

        ScannerService::registrarExcecao('20011', 'senha_errada');
    }

    public function testRegistrarExcecaoLancaTipoNaoPermitido(): void
    {
        $_ENV['EXCECAO_LIMIT_TIME'] = '23:59';
        $this->inserirUsuario('20012', 'SND TESTE', 'snd', 1, password_hash('senha123', PASSWORD_BCRYPT, ['cost' => 4]));

        try {
            ScannerService::registrarExcecao('20012', 'senha123');
            $this->fail('Deveria ter lançado TipoNaoPermitidoException.');
        } catch (TipoNaoPermitidoException $e) {
            $this->assertSame('snd', $e->tipoUsuario);
        }
    }

    public function testRegistrarExcecaoLancaSemCardapioHoje(): void
    {
        $_ENV['EXCECAO_LIMIT_TIME'] = '23:59';
        $this->inserirUsuario('20013', 'FUNCIONARIO TESTE', 'comum', 1, password_hash('senha123', PASSWORD_BCRYPT, ['cost' => 4]));

        $this->expectException(SemCardapioHojeException::class);

        ScannerService::registrarExcecao('20013', 'senha123');
    }

    public function testRegistrarExcecaoLancaJaConcluido(): void
    {
        $_ENV['EXCECAO_LIMIT_TIME'] = '23:59';
        $this->inserirUsuario('20014', 'FUNCIONARIO TESTE', 'comum', 1, password_hash('senha123', PASSWORD_BCRYPT, ['cost' => 4]));
        $idCardapio = $this->inserirCardapio(date('Y-m-d'), 'almoco');
        $this->inserirRegistro('20014', $idCardapio, 'concluido');

        $this->expectException(JaConcluidoException::class);

        ScannerService::registrarExcecao('20014', 'senha123');
    }

    // =========================================================================
    // listaHoje()
    // =========================================================================

    public function testListaHojeRetornaRegistrosFormatados(): void
    {
        $this->inserirUsuario('20015', 'FUNCIONARIO TESTE');
        $idCardapio = $this->inserirCardapio(date('Y-m-d'), 'almoco');
        $this->inserirRegistro('20015', $idCardapio, 'agendado');

        $lista = ScannerService::listaHoje();

        $this->assertCount(1, $lista);
        $this->assertSame('20015', $lista[0]['matricula']);
        $this->assertSame('FUNCIONARIO TESTE', $lista[0]['nome_usuario']);
        $this->assertSame('Almoço', $lista[0]['tipo_refeicao']);
        $this->assertSame('agendado', $lista[0]['status']);
    }

    public function testListaHojeRetornaVaziaSemRegistros(): void
    {
        $this->assertSame([], ScannerService::listaHoje());
    }
}
