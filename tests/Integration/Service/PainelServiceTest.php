<?php

namespace Tests\Integration\Service;

use App\Services\PainelService;
use DateTime;
use Tests\Integration\DatabaseTestCase;

class PainelServiceTest extends DatabaseTestCase
{
    private array $envOriginal;
    private array $usuarioComum = ['matricula' => '50010', 'nome' => 'FUNCIONARIO TESTE', 'tipo' => 'comum'];

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

    public function testMontarSemanaRetornaSeteDiasDeSegundaADomingo(): void
    {
        $dados = PainelService::montarSemana($this->usuarioComum);

        $this->assertCount(7, $dados['semana']);
        $labels = array_column($dados['semana'], 'label');
        $this->assertSame(
            ['Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado', 'Domingo'],
            array_values($labels)
        );
    }

    public function testMontarSemanaPreencheCardapioNoSlotCorreto(): void
    {
        $segundaDaSemana = (new DateTime('monday this week'))->format('Y-m-d');
        $this->inserirCardapio($segundaDaSemana, 'almoco', 'FRANGO');

        $dados = PainelService::montarSemana($this->usuarioComum);

        $this->assertNotNull($dados['semana'][$segundaDaSemana]['almoco']);
        $this->assertSame('FRANGO', $dados['semana'][$segundaDaSemana]['almoco']['descricao']);
        $this->assertNull($dados['semana'][$segundaDaSemana]['jantar']);
    }

    public function testMontarSemanaMapeiaRegistrosDoUsuario(): void
    {
        $segundaDaSemana = (new DateTime('monday this week'))->format('Y-m-d');
        $idCardapio       = $this->inserirCardapio($segundaDaSemana, 'almoco');
        $this->inserirRegistro($this->usuarioComum['matricula'], $idCardapio, 'agendado');

        $dados = PainelService::montarSemana($this->usuarioComum);

        $this->assertArrayHasKey($idCardapio, $dados['registrosSemana']);
        $this->assertSame('agendado', $dados['registrosSemana'][$idCardapio]['status']);
    }

    public function testMontarSemanaBloqueiaAgendamentoParaUsuarioNaoComum(): void
    {
        $_ENV['AGENDAMENTO_HABILITADO'] = 'true';
        $usuarioSnd = ['matricula' => '50011', 'nome' => 'SND TESTE', 'tipo' => 'snd'];
        $this->inserirUsuario($usuarioSnd['matricula'], $usuarioSnd['nome'], 'snd');

        $dados = PainelService::montarSemana($usuarioSnd);

        foreach ($dados['semana'] as $dia) {
            $this->assertFalse($dia['podeAgendar']);
        }
    }

    public function testMontarSemanaBloqueiaAgendamentoQuandoDesabilitadoGlobalmente(): void
    {
        $_ENV['AGENDAMENTO_HABILITADO'] = 'false';

        $dados = PainelService::montarSemana($this->usuarioComum);

        foreach ($dados['semana'] as $dia) {
            $this->assertFalse($dia['podeAgendar']);
        }
        $this->assertFalse($dados['agendamentoHabilitado']);
    }
}
