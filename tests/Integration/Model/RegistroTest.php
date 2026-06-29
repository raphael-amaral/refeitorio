<?php

namespace Tests\Integration\Model;

use App\Models\Registro;
use Tests\Integration\DatabaseTestCase;

class RegistroTest extends DatabaseTestCase
{
    private string $matricula = '10001';
    private int $idCardapio;

    protected function setUp(): void
    {
        parent::setUp();

        $this->inserirUsuario($this->matricula, 'FUNCIONARIO TESTE');
        $this->idCardapio = $this->inserirCardapio('2026-07-15', 'almoco');
    }

    // =========================================================================
    // jaAgendado()
    // =========================================================================

    public function testJaAgendadoRetornaTrueComRegistroAgendado(): void
    {
        $this->inserirRegistro($this->matricula, $this->idCardapio, 'agendado');

        $this->assertTrue(Registro::jaAgendado($this->matricula, $this->idCardapio));
    }

    public function testJaAgendadoRetornaFalseSemRegistro(): void
    {
        $this->assertFalse(Registro::jaAgendado($this->matricula, $this->idCardapio));
    }

    public function testJaAgendadoRetornaFalseParaCancelado(): void
    {
        $this->inserirRegistro($this->matricula, $this->idCardapio, 'cancelado');

        // Status cancelado não conta como "já agendado"
        $this->assertFalse(Registro::jaAgendado($this->matricula, $this->idCardapio));
    }

    // =========================================================================
    // jaConcluidoPorCardapio()
    // =========================================================================

    public function testJaConcluidoRetornaTrueComStatusConcluido(): void
    {
        $this->inserirRegistro($this->matricula, $this->idCardapio, 'concluido');

        $this->assertTrue(Registro::jaConcluidoPorCardapio($this->matricula, $this->idCardapio));
    }

    public function testJaConcluidoRetornaFalseComStatusAgendado(): void
    {
        $this->inserirRegistro($this->matricula, $this->idCardapio, 'agendado');

        $this->assertFalse(Registro::jaConcluidoPorCardapio($this->matricula, $this->idCardapio));
    }

    // =========================================================================
    // contarPorCardapio()
    // =========================================================================

    public function testContarPorCardapioContaTodosOsStatus(): void
    {
        $this->inserirUsuario('10002', 'USUARIO 2');
        $this->inserirUsuario('10003', 'USUARIO 3');

        $this->inserirRegistro($this->matricula, $this->idCardapio, 'agendado');
        $this->inserirRegistro('10002',           $this->idCardapio, 'cancelado');
        $this->inserirRegistro('10003',           $this->idCardapio, 'concluido');

        $this->assertSame(3, Registro::contarPorCardapio($this->idCardapio));
    }

    public function testContarPorCardapioRetornaZeroSemRegistros(): void
    {
        $this->assertSame(0, Registro::contarPorCardapio($this->idCardapio));
    }

    // =========================================================================
    // cancelarDoUsuario()
    // =========================================================================

    public function testCancelarDoUsuarioAlteraStatusParaCancelado(): void
    {
        $idRegistro = $this->inserirRegistro($this->matricula, $this->idCardapio, 'agendado');

        $ok = Registro::cancelarDoUsuario($idRegistro, $this->matricula);

        $this->assertTrue($ok);
        $row = $this->pdo->query("SELECT status FROM registros WHERE id = {$idRegistro}")->fetch();
        $this->assertSame('cancelado', $row['status']);
    }

    public function testCancelarDoUsuarioNaoCancelaRegistroDeOutroUsuario(): void
    {
        $this->inserirUsuario('10099', 'OUTRO USUARIO');
        $idRegistro = $this->inserirRegistro($this->matricula, $this->idCardapio, 'agendado');

        // Outro usuário tenta cancelar
        Registro::cancelarDoUsuario($idRegistro, '10099');

        $row = $this->pdo->query("SELECT status FROM registros WHERE id = {$idRegistro}")->fetch();
        $this->assertSame('agendado', $row['status'], 'Status não deve mudar se a matrícula não bate');
    }

    public function testCancelarNaoAfetaRegistroJaCancelado(): void
    {
        $idRegistro = $this->inserirRegistro($this->matricula, $this->idCardapio, 'cancelado');

        // WHERE status = 'agendado' não encontra o registro → nenhuma linha afetada
        Registro::cancelarDoUsuario($idRegistro, $this->matricula);

        $row = $this->pdo->query("SELECT status FROM registros WHERE id = {$idRegistro}")->fetch();
        $this->assertSame('cancelado', $row['status']);
    }

    // =========================================================================
    // concluir()
    // =========================================================================

    public function testConcluirAlteraStatusParaConcluido(): void
    {
        $idRegistro = $this->inserirRegistro($this->matricula, $this->idCardapio, 'agendado');

        $ok = Registro::concluir($idRegistro);

        $this->assertTrue($ok);
        $row = $this->pdo->query("SELECT status FROM registros WHERE id = {$idRegistro}")->fetch();
        $this->assertSame('concluido', $row['status']);
    }

    // =========================================================================
    // buscarPorUsuario()
    // =========================================================================

    public function testBuscarPorUsuarioRetornaRegistrosDoUsuario(): void
    {
        $idCard2 = $this->inserirCardapio('2026-07-16', 'almoco');
        $this->inserirRegistro($this->matricula, $this->idCardapio, 'agendado');
        $this->inserirRegistro($this->matricula, $idCard2,          'cancelado');

        $registros = Registro::buscarPorUsuario($this->matricula);

        $this->assertCount(2, $registros);
    }

    public function testBuscarPorUsuarioFiltraPorStatus(): void
    {
        $idCard2 = $this->inserirCardapio('2026-07-17', 'almoco');
        $this->inserirRegistro($this->matricula, $this->idCardapio, 'agendado');
        $this->inserirRegistro($this->matricula, $idCard2,          'cancelado');

        $agendados = Registro::buscarPorUsuario($this->matricula, 'agendado');

        $this->assertCount(1, $agendados);
        $this->assertSame('agendado', $agendados[0]['status']);
    }

    public function testBuscarPorUsuarioFiltraPorPeriodo(): void
    {
        $idCard2 = $this->inserirCardapio('2026-08-01', 'almoco');
        $this->inserirRegistro($this->matricula, $this->idCardapio, 'agendado'); // Jul
        $this->inserirRegistro($this->matricula, $idCard2,          'agendado'); // Ago

        $resultado = Registro::buscarPorUsuario($this->matricula, '', '2026-08-01', '2026-08-31');

        $this->assertCount(1, $resultado);
        $this->assertSame('2026-08-01', $resultado[0]['data']);
    }

    // =========================================================================
    // contarPorUsuario()
    // =========================================================================

    public function testContarPorUsuarioSemFiltros(): void
    {
        $idCard2 = $this->inserirCardapio('2026-07-20', 'almoco');
        $this->inserirRegistro($this->matricula, $this->idCardapio, 'agendado');
        $this->inserirRegistro($this->matricula, $idCard2,          'concluido');

        $this->assertSame(2, Registro::contarPorUsuario($this->matricula));
    }

    public function testContarPorUsuarioComFiltroStatus(): void
    {
        $idCard2 = $this->inserirCardapio('2026-07-21', 'almoco');
        $this->inserirRegistro($this->matricula, $this->idCardapio, 'agendado');
        $this->inserirRegistro($this->matricula, $idCard2,          'concluido');

        $this->assertSame(1, Registro::contarPorUsuario($this->matricula, 'concluido'));
    }

    // =========================================================================
    // buscarPorUsuarioPaginado()
    // =========================================================================

    public function testBuscarPorUsuarioPaginadoRespeita(): void
    {
        $ids = [];
        for ($i = 1; $i <= 5; $i++) {
            $ids[] = $this->inserirCardapio("2026-0{$i}-10", 'almoco');
        }
        foreach ($ids as $idCard) {
            $this->inserirRegistro($this->matricula, $idCard, 'agendado');
        }

        $pag1 = Registro::buscarPorUsuarioPaginado($this->matricula, '', '', '', 0, 3);
        $pag2 = Registro::buscarPorUsuarioPaginado($this->matricula, '', '', '', 3, 3);

        $this->assertCount(3, $pag1);
        $this->assertCount(2, $pag2);
    }

    // =========================================================================
    // buscarParaRelatorio()
    // =========================================================================

    public function testBuscarParaRelatorioRetornaTodosOsRegistros(): void
    {
        $this->inserirRegistro($this->matricula, $this->idCardapio, 'concluido');

        $relatorio = Registro::buscarParaRelatorio();

        $this->assertCount(1, $relatorio);
        $this->assertArrayHasKey('nome_usuario',   $relatorio[0]);
        $this->assertArrayHasKey('data_cardapio',  $relatorio[0]);
        $this->assertArrayHasKey('tipo_refeicao',  $relatorio[0]);
    }

    public function testBuscarParaRelatorioFiltraPorStatus(): void
    {
        $idCard2 = $this->inserirCardapio('2026-07-22', 'almoco');
        $this->inserirUsuario('10005', 'OUTRO');
        $this->inserirRegistro($this->matricula, $this->idCardapio, 'concluido');
        $this->inserirRegistro('10005',           $idCard2,          'agendado');

        $concluidos = Registro::buscarParaRelatorio('concluido');

        $this->assertCount(1, $concluidos);
        $this->assertSame('concluido', $concluidos[0]['status']);
    }

    public function testBuscarParaRelatorioFiltraPorPeriodoDeData(): void
    {
        $idCard2 = $this->inserirCardapio('2026-10-01', 'almoco');
        $this->inserirUsuario('10006', 'OUTRO 2');
        $this->inserirRegistro($this->matricula, $this->idCardapio, 'agendado'); // Jul
        $this->inserirRegistro('10006',           $idCard2,          'agendado'); // Out

        $out = Registro::buscarParaRelatorio('', '2026-10-01', '2026-10-31');

        $this->assertCount(1, $out);
        $this->assertSame('2026-10-01', $out[0]['data_cardapio']);
    }

    // =========================================================================
    // Métodos MySQL-específicos (pulados no SQLite)
    // =========================================================================

    public function testUpsertAgendadoRequerMySQL(): void
    {
        $this->markTestSkipped('upsertAgendado() usa ON DUPLICATE KEY UPDATE — requer MySQL.');
    }

    public function testConcluirExcecaoRequerMySQL(): void
    {
        $this->markTestSkipped('concluirExcecao() usa ON DUPLICATE KEY UPDATE — requer MySQL.');
    }

    public function testBuscarParaScannerHojeRequerMySQL(): void
    {
        $this->markTestSkipped('buscarParaScannerHoje() usa CURDATE() — requer MySQL.');
    }
}
