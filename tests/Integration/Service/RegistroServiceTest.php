<?php

namespace Tests\Integration\Service;

use App\Exceptions\AgendamentoDesabilitadoException;
use App\Exceptions\FimDeSemanaException;
use App\Exceptions\ForaDoPrazoException;
use App\Exceptions\JaAgendadoOuInvalidoException;
use App\Exceptions\JantarNaoAgendavelException;
use App\Exceptions\TipoNaoPermitidoException;
use App\Models\Registro;
use App\Services\RegistroService;
use Tests\Integration\DatabaseTestCase;

class RegistroServiceTest extends DatabaseTestCase
{
    private array $usuarioComum = ['matricula' => '10001', 'nome' => 'FUNCIONARIO TESTE', 'tipo' => 'comum'];

    private array $envOriginal;

    protected function setUp(): void
    {
        parent::setUp();

        $this->envOriginal = $_ENV;
        $this->inserirUsuario($this->usuarioComum['matricula'], $this->usuarioComum['nome']);
    }

    protected function tearDown(): void
    {
        $_ENV = $this->envOriginal;
        parent::tearDown();
    }

    /** Segunda-feira daqui a pelo menos uma semana: nunca é fim de semana nem fora do prazo. */
    private function dataFutura(): string
    {
        return (new \DateTime('monday next week'))->format('Y-m-d');
    }

    /** Sábado daqui a pelo menos uma semana: sempre fim de semana, nunca fora do prazo. */
    private function dataFuturaSabado(): string
    {
        return (new \DateTime('saturday next week'))->format('Y-m-d');
    }

    // =========================================================================
    // registrar()
    // =========================================================================

    public function testRegistrarCriaAgendamentoComSucesso(): void
    {
        $idCardapio = $this->inserirCardapio($this->dataFutura(), 'almoco');

        RegistroService::registrar($this->usuarioComum, $idCardapio);

        $this->assertTrue(Registro::jaAgendado($this->usuarioComum['matricula'], $idCardapio));
    }

    public function testRegistrarLancaAgendamentoDesabilitado(): void
    {
        $_ENV['AGENDAMENTO_HABILITADO'] = 'false';
        $idCardapio = $this->inserirCardapio($this->dataFutura(), 'almoco');

        $this->expectException(AgendamentoDesabilitadoException::class);

        RegistroService::registrar($this->usuarioComum, $idCardapio);
    }

    public function testRegistrarLancaTipoNaoPermitidoParaUsuarioNaoComum(): void
    {
        $usuarioSnd = ['matricula' => '10002', 'nome' => 'SND TESTE', 'tipo' => 'snd'];
        $this->inserirUsuario($usuarioSnd['matricula'], $usuarioSnd['nome'], 'snd');
        $idCardapio = $this->inserirCardapio($this->dataFutura(), 'almoco');

        try {
            RegistroService::registrar($usuarioSnd, $idCardapio);
            $this->fail('Deveria ter lançado TipoNaoPermitidoException.');
        } catch (TipoNaoPermitidoException $e) {
            $this->assertSame('snd', $e->tipoUsuario);
        }
    }

    public function testRegistrarLancaJantarNaoAgendavel(): void
    {
        $idCardapio = $this->inserirCardapio($this->dataFutura(), 'jantar');

        try {
            RegistroService::registrar($this->usuarioComum, $idCardapio);
            $this->fail('Deveria ter lançado JantarNaoAgendavelException.');
        } catch (JantarNaoAgendavelException $e) {
            $this->assertSame($this->dataFutura(), $e->dataCardapio);
        }
    }

    public function testRegistrarLancaFimDeSemanaQuandoNaoPermitido(): void
    {
        $_ENV['AGENDAMENTO_SABADO_DOMINGO'] = 'false';
        $dataSabado = $this->dataFuturaSabado();
        $idCardapio = $this->inserirCardapio($dataSabado, 'almoco');

        try {
            RegistroService::registrar($this->usuarioComum, $idCardapio);
            $this->fail('Deveria ter lançado FimDeSemanaException.');
        } catch (FimDeSemanaException $e) {
            $this->assertSame($dataSabado, $e->dataCardapio);
        }
    }

    public function testRegistrarPermiteFimDeSemanaQuandoHabilitado(): void
    {
        $_ENV['AGENDAMENTO_SABADO_DOMINGO'] = 'true';
        $dataSabado = $this->dataFuturaSabado();
        $idCardapio = $this->inserirCardapio($dataSabado, 'almoco');

        RegistroService::registrar($this->usuarioComum, $idCardapio);

        $this->assertTrue(Registro::jaAgendado($this->usuarioComum['matricula'], $idCardapio));
    }

    public function testRegistrarLancaForaDoPrazoParaDataPassada(): void
    {
        $idCardapio = $this->inserirCardapio('2020-01-01', 'almoco');

        try {
            RegistroService::registrar($this->usuarioComum, $idCardapio);
            $this->fail('Deveria ter lançado ForaDoPrazoException.');
        } catch (ForaDoPrazoException $e) {
            $this->assertSame('2020-01-01', $e->dataCardapio);
        }
    }

    public function testRegistrarLancaJaAgendadoQuandoRegistroJaExiste(): void
    {
        $idCardapio = $this->inserirCardapio($this->dataFutura(), 'almoco');
        $this->inserirRegistro($this->usuarioComum['matricula'], $idCardapio, 'agendado');

        try {
            RegistroService::registrar($this->usuarioComum, $idCardapio);
            $this->fail('Deveria ter lançado JaAgendadoOuInvalidoException.');
        } catch (JaAgendadoOuInvalidoException $e) {
            $this->assertSame($idCardapio, $e->idCardapio);
        }
    }

    public function testRegistrarLancaJaAgendadoParaCardapioInvalido(): void
    {
        $this->expectException(JaAgendadoOuInvalidoException::class);

        RegistroService::registrar($this->usuarioComum, 0);
    }

    // =========================================================================
    // cancelar()
    // =========================================================================

    public function testCancelarCancelaAgendamentoComSucesso(): void
    {
        $idCardapio = $this->inserirCardapio($this->dataFutura(), 'almoco');
        $id         = $this->inserirRegistro($this->usuarioComum['matricula'], $idCardapio, 'agendado');

        RegistroService::cancelar($this->usuarioComum, $id);

        $registro = Registro::buscarPorId($id);
        $this->assertSame('cancelado', $registro['status']);
    }

    public function testCancelarLancaAgendamentoDesabilitado(): void
    {
        $idCardapio = $this->inserirCardapio($this->dataFutura(), 'almoco');
        $id         = $this->inserirRegistro($this->usuarioComum['matricula'], $idCardapio, 'agendado');
        $_ENV['AGENDAMENTO_HABILITADO'] = 'false';

        $this->expectException(AgendamentoDesabilitadoException::class);

        RegistroService::cancelar($this->usuarioComum, $id);
    }

    public function testCancelarLancaForaDoPrazoParaDataPassada(): void
    {
        $idCardapio = $this->inserirCardapio('2020-01-01', 'almoco');
        $id         = $this->inserirRegistro($this->usuarioComum['matricula'], $idCardapio, 'agendado');

        try {
            RegistroService::cancelar($this->usuarioComum, $id);
            $this->fail('Deveria ter lançado ForaDoPrazoException.');
        } catch (ForaDoPrazoException $e) {
            $this->assertSame('2020-01-01', $e->dataCardapio);
        }
    }

    public function testCancelarComRegistroInexistenteNaoLancaExcecao(): void
    {
        // Comportamento herdado do controller original: id inexistente não lança, só não cancela nada.
        RegistroService::cancelar($this->usuarioComum, 99999);

        $this->assertFalse(Registro::buscarPorId(99999));
    }

    // =========================================================================
    // concluir()
    // =========================================================================

    public function testConcluirMarcaRegistroComoConcluido(): void
    {
        $idCardapio = $this->inserirCardapio($this->dataFutura(), 'almoco');
        $id         = $this->inserirRegistro($this->usuarioComum['matricula'], $idCardapio, 'agendado');

        RegistroService::concluir($id);

        $registro = Registro::buscarPorId($id);
        $this->assertSame('concluido', $registro['status']);
    }
}
