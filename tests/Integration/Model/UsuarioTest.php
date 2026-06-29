<?php

namespace Tests\Integration\Model;

use App\Models\Usuario;
use Tests\Integration\DatabaseTestCase;

class UsuarioTest extends DatabaseTestCase
{
    // =========================================================================
    // buscarPorMatricula()
    // =========================================================================

    public function testBuscarPorMatriculaRetornaUsuarioAtivo(): void
    {
        $this->inserirUsuario('1001', 'JOAO SILVA', 'comum', 1);

        $usuario = Usuario::buscarPorMatricula('1001');

        $this->assertIsArray($usuario);
        $this->assertSame('1001',      $usuario['matricula']);
        $this->assertSame('JOAO SILVA', $usuario['nome']);
    }

    public function testBuscarPorMatriculaNaoRetornaInativo(): void
    {
        $this->inserirUsuario('1002', 'INATIVO', 'comum', 0);

        $this->assertFalse(Usuario::buscarPorMatricula('1002'));
    }

    public function testBuscarPorMatriculaRetornaFalseSeNaoExiste(): void
    {
        $this->assertFalse(Usuario::buscarPorMatricula('9999'));
    }

    // =========================================================================
    // novoUsuario()
    // =========================================================================

    public function testNovoUsuarioInsereRegistroNoBanco(): void
    {
        Usuario::novoUsuario('2001', 'MARIA SOUZA', 'senha123', 'comum', 4);

        $row = $this->pdo->query("SELECT * FROM usuarios WHERE matricula = '2001'")->fetch();
        $this->assertNotFalse($row);
        $this->assertSame('MARIA SOUZA', $row['nome']);
        $this->assertSame('comum',       $row['tipo']);
        $this->assertSame(1,             (int) $row['ativo']);
    }

    public function testNovoUsuarioHasheaSenhaComBcrypt(): void
    {
        Usuario::novoUsuario('2002', 'CARLOS', 'minha_senha', 'snd', 4);

        $row = $this->pdo->query("SELECT senha FROM usuarios WHERE matricula = '2002'")->fetch();

        $this->assertTrue(password_verify('minha_senha', $row['senha']));
        $this->assertFalse(password_verify('senha_errada', $row['senha']));
    }

    // =========================================================================
    // contarTotal()
    // =========================================================================

    public function testContarTotalSemFiltro(): void
    {
        $this->inserirUsuario('3001');
        $this->inserirUsuario('3002');
        $this->inserirUsuario('3003');

        $this->assertSame(3, Usuario::contarTotal());
    }

    public function testContarTotalComBuscaPorNome(): void
    {
        $this->inserirUsuario('3010', 'ANA CLARA');
        $this->inserirUsuario('3011', 'CARLOS SILVA');
        $this->inserirUsuario('3012', 'BEATRIZ');

        $this->assertSame(1, Usuario::contarTotal('ANA'));
    }

    public function testContarTotalComBuscaPorMatricula(): void
    {
        $this->inserirUsuario('4001');
        $this->inserirUsuario('4002');
        $this->inserirUsuario('5000');

        $this->assertSame(2, Usuario::contarTotal('400'));
    }

    // =========================================================================
    // listarPaginado()
    // =========================================================================

    public function testListarPaginadoRetornaLimiteDePagina(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->inserirUsuario("60{$i}0", "USUARIO {$i}");
        }

        $resultado = Usuario::listarPaginado('', 0, 3, 'nome');

        $this->assertCount(3, $resultado);
    }

    public function testListarPaginadoComOffset(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->inserirUsuario("70{$i}0", "USUARIO {$i}");
        }

        $pagina2 = Usuario::listarPaginado('', 3, 3, 'nome');

        $this->assertCount(2, $pagina2);
    }

    public function testListarPaginadoComBusca(): void
    {
        $this->inserirUsuario('8001', 'RAFAEL NUNES');
        $this->inserirUsuario('8002', 'PEDRO ALVES');

        $resultado = Usuario::listarPaginado('RAFAEL', 0, 10, 'nome');

        $this->assertCount(1, $resultado);
        $this->assertSame('RAFAEL NUNES', $resultado[0]['nome']);
    }

    // =========================================================================
    // atualizarUsuario()
    // =========================================================================

    public function testAtualizarUsuarioAlteraNomeETipo(): void
    {
        $this->inserirUsuario('9001', 'NOME ANTIGO', 'comum');

        Usuario::atualizarUsuario('9001', 'NOME NOVO', 'snd');

        $row = $this->pdo->query("SELECT nome, tipo FROM usuarios WHERE matricula = '9001'")->fetch();
        $this->assertSame('NOME NOVO', $row['nome']);
        $this->assertSame('snd',       $row['tipo']);
    }

    public function testAtualizarUsuarioAlteraSenhaQuandoFornecida(): void
    {
        $this->inserirUsuario('9002');

        Usuario::atualizarUsuario('9002', 'NOME', 'comum', 'nova_senha');

        $row = $this->pdo->query("SELECT senha FROM usuarios WHERE matricula = '9002'")->fetch();
        $this->assertTrue(password_verify('nova_senha', $row['senha']));
    }

    public function testAtualizarUsuarioNaoAlteraSenhaQuandoNula(): void
    {
        $senhaOriginal = password_hash('senha_original', PASSWORD_BCRYPT, ['cost' => 4]);
        $this->pdo->prepare("INSERT INTO usuarios (matricula, nome, senha, tipo) VALUES (?, ?, ?, ?)")
            ->execute(['9003', 'NOME', $senhaOriginal, 'comum']);

        Usuario::atualizarUsuario('9003', 'NOME ALTERADO', 'comum', null);

        $row = $this->pdo->query("SELECT senha FROM usuarios WHERE matricula = '9003'")->fetch();
        $this->assertSame($senhaOriginal, $row['senha']);
    }

    // =========================================================================
    // alterarAtivo()
    // =========================================================================

    public function testAlterarAtivoInativaUsuario(): void
    {
        $this->inserirUsuario('9100', 'ATIVO', 'comum', 1);

        Usuario::alterarAtivo('9100', 0);

        $row = $this->pdo->query("SELECT ativo FROM usuarios WHERE matricula = '9100'")->fetch();
        $this->assertSame(0, (int) $row['ativo']);
    }

    public function testAlterarAtivoReativaUsuario(): void
    {
        $this->inserirUsuario('9101', 'INATIVO', 'comum', 0);

        Usuario::alterarAtivo('9101', 1);

        $row = $this->pdo->query("SELECT ativo FROM usuarios WHERE matricula = '9101'")->fetch();
        $this->assertSame(1, (int) $row['ativo']);
    }

    // =========================================================================
    // temRegistros()
    // =========================================================================

    public function testTemRegistrosRetornaTrueComAgendamento(): void
    {
        $this->inserirUsuario('9200');
        $idCard = $this->inserirCardapio('2026-07-01');
        $this->inserirRegistro('9200', $idCard);

        $this->assertTrue(Usuario::temRegistros('9200'));
    }

    public function testTemRegistrosRetornaFalseSemAgendamento(): void
    {
        $this->inserirUsuario('9201');

        $this->assertFalse(Usuario::temRegistros('9201'));
    }

    // =========================================================================
    // matriculasExistentes()
    // =========================================================================

    public function testMatriculasExistentesRetornaAsQueExistem(): void
    {
        $this->inserirUsuario('A001');
        $this->inserirUsuario('A002');

        $existentes = Usuario::matriculasExistentes(['A001', 'A002', 'A999']);

        $this->assertContains('A001', $existentes);
        $this->assertContains('A002', $existentes);
        $this->assertNotContains('A999', $existentes);
    }

    public function testMatriculasExistentesComArrayVazioRetornaVazio(): void
    {
        $this->assertSame([], Usuario::matriculasExistentes([]));
    }

    // =========================================================================
    // excluir()
    // =========================================================================

    public function testExcluirRemoveUsuario(): void
    {
        $this->inserirUsuario('9300');

        Usuario::excluir('9300');

        $row = $this->pdo->query("SELECT * FROM usuarios WHERE matricula = '9300'")->fetch();
        $this->assertFalse($row);
    }

    // =========================================================================
    // Model base: todos(), buscarPorId(), criar(), atualizar(), deletar()
    // =========================================================================

    public function testTodosRetornaTodosOsRegistros(): void
    {
        $this->inserirUsuario('B001', 'CARLOS');
        $this->inserirUsuario('B002', 'ANA');

        $todos = Usuario::todos('nome ASC');

        $this->assertCount(2, $todos);
        $this->assertSame('ANA',    $todos[0]['nome']);
        $this->assertSame('CARLOS', $todos[1]['nome']);
    }

    public function testBuscarPorIdRetornaRegistroCorreto(): void
    {
        $this->inserirUsuario('C001', 'TESTE BUSCA');

        $usuario = Usuario::buscarPorId('C001');

        $this->assertIsArray($usuario);
        $this->assertSame('TESTE BUSCA', $usuario['nome']);
    }

    public function testBuscarPorIdRetornaFalseSeNaoEncontrado(): void
    {
        $this->assertFalse(Usuario::buscarPorId('INEXISTENTE'));
    }

    public function testAtualizarAlteraDadosNoBanco(): void
    {
        $this->inserirUsuario('D001', 'NOME ORIGINAL', 'comum');

        Usuario::atualizar('D001', ['nome' => 'NOME ATUALIZADO', 'tipo' => 'snd']);

        $row = $this->pdo->query("SELECT nome, tipo FROM usuarios WHERE matricula = 'D001'")->fetch();
        $this->assertSame('NOME ATUALIZADO', $row['nome']);
        $this->assertSame('snd',             $row['tipo']);
    }

    public function testDeletarRemoveRegistro(): void
    {
        $this->inserirUsuario('E001');

        Usuario::deletar('E001');

        $this->assertFalse($this->pdo->query("SELECT * FROM usuarios WHERE matricula = 'E001'")->fetch());
    }
}
