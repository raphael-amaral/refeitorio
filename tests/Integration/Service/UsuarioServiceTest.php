<?php

namespace Tests\Integration\Service;

use App\Exceptions\AutoAcaoNaoPermitidaException;
use App\Exceptions\LinhasInvalidasException;
use App\Exceptions\ListaVaziaException;
use App\Exceptions\MatriculaDuplicadaException;
use App\Exceptions\MatriculasDuplicadasException;
use App\Exceptions\PossuiRegistrosException;
use App\Exceptions\SenhaCurtaException;
use App\Exceptions\SenhasNaoCoincidemException;
use App\Exceptions\ValidacaoException;
use App\Models\Usuario;
use App\Services\UsuarioService;
use Tests\Integration\DatabaseTestCase;

class UsuarioServiceTest extends DatabaseTestCase
{
    // =========================================================================
    // criar()
    // =========================================================================

    public function testCriarComSucessoUsandoSenhaInformada(): void
    {
        $nome = UsuarioService::criar('30001', 'joao silva', 'senha123', 'senha123', 'comum');

        $this->assertSame('JOAO SILVA', $nome);
        $usuario = Usuario::buscarPorMatricula('30001');
        $this->assertNotFalse($usuario);
        $this->assertTrue(password_verify('senha123', $usuario['senha']));
    }

    public function testCriarComSenhaVaziaUsaSenhaPadrao(): void
    {
        UsuarioService::criar('30002', 'MARIA', '', '', 'comum');

        $usuario = Usuario::buscarPorMatricula('30002');
        $this->assertTrue(password_verify('stcasa30002', $usuario['senha']));
    }

    public function testCriarLancaValidacaoParaMatriculaVazia(): void
    {
        $this->expectException(ValidacaoException::class);

        UsuarioService::criar('', 'NOME', 'senha123', 'senha123', 'comum');
    }

    public function testCriarLancaValidacaoParaNomeVazio(): void
    {
        $this->expectException(ValidacaoException::class);

        UsuarioService::criar('30003', '', 'senha123', 'senha123', 'comum');
    }

    public function testCriarLancaValidacaoParaTipoInvalido(): void
    {
        $this->expectException(ValidacaoException::class);

        UsuarioService::criar('30004', 'NOME', 'senha123', 'senha123', 'invalido');
    }

    public function testCriarLancaSenhaCurta(): void
    {
        $this->expectException(SenhaCurtaException::class);

        UsuarioService::criar('30005', 'NOME', '123', '123', 'comum');
    }

    public function testCriarLancaSenhasNaoCoincidem(): void
    {
        $this->expectException(SenhasNaoCoincidemException::class);

        UsuarioService::criar('30006', 'NOME', 'senha123', 'outrasenha', 'comum');
    }

    public function testCriarLancaMatriculaDuplicada(): void
    {
        $this->inserirUsuario('30007', 'EXISTENTE');

        $this->expectException(MatriculaDuplicadaException::class);

        UsuarioService::criar('30007', 'NOME NOVO', 'senha123', 'senha123', 'comum');
    }

    // =========================================================================
    // importar()
    // =========================================================================

    public function testImportarComSucessoUsandoPontoEVirgula(): void
    {
        $criados = UsuarioService::importar('comum', "30010;FULANO\n30011;CICLANO");

        $this->assertSame(2, $criados);
        $this->assertNotFalse(Usuario::buscarPorMatricula('30010'));
        $this->assertNotFalse(Usuario::buscarPorMatricula('30011'));
    }

    public function testImportarComSucessoUsandoVirgula(): void
    {
        $criados = UsuarioService::importar('comum', '30012,FULANO');

        $this->assertSame(1, $criados);
    }

    public function testImportarComSucessoUsandoEspaco(): void
    {
        $criados = UsuarioService::importar('comum', '30013 FULANO DA SILVA');

        $this->assertSame(1, $criados);
        $this->assertSame('FULANO DA SILVA', Usuario::buscarPorMatricula('30013')['nome']);
    }

    public function testImportarComTipoInvalidoCaiParaComum(): void
    {
        UsuarioService::importar('tipo_invalido', '30014;FULANO');

        $this->assertSame('comum', Usuario::buscarPorMatricula('30014')['tipo']);
    }

    public function testImportarLancaListaVazia(): void
    {
        $this->expectException(ListaVaziaException::class);

        UsuarioService::importar('comum', '');
    }

    public function testImportarLancaLinhasInvalidasParaLinhaSemNome(): void
    {
        try {
            UsuarioService::importar('comum', "30015;FULANO\n30016");
            $this->fail('Deveria ter lançado LinhasInvalidasException.');
        } catch (LinhasInvalidasException $e) {
            $this->assertCount(1, $e->invalidas);
        }

        // Nem sequer a linha válida foi importada (tudo ou nada)
        $this->assertFalse(Usuario::buscarPorMatricula('30015'));
    }

    public function testImportarLancaLinhasInvalidasParaDuplicataNoArquivo(): void
    {
        try {
            UsuarioService::importar('comum', "30017;FULANO\n30017;FULANO DUPLICADO");
            $this->fail('Deveria ter lançado LinhasInvalidasException.');
        } catch (LinhasInvalidasException $e) {
            $this->assertCount(1, $e->invalidas);
        }
    }

    public function testImportarLancaMatriculasDuplicadasQuandoJaExisteNoBanco(): void
    {
        $this->inserirUsuario('30018', 'JA EXISTE');

        try {
            UsuarioService::importar('comum', '30018;FULANO');
            $this->fail('Deveria ter lançado MatriculasDuplicadasException.');
        } catch (MatriculasDuplicadasException $e) {
            $this->assertSame(['30018'], $e->duplicadas);
        }
    }

    // =========================================================================
    // editar()
    // =========================================================================

    public function testEditarAtualizaNomeETipo(): void
    {
        $this->inserirUsuario('30020', 'NOME ANTIGO', 'comum');

        UsuarioService::editar('30020', 'nome novo', 'snd', '', '');

        $usuario = Usuario::buscarPorMatricula('30020');
        $this->assertSame('NOME NOVO', $usuario['nome']);
        $this->assertSame('snd', $usuario['tipo']);
    }

    public function testEditarAtualizaSenhaQuandoInformadaEConfirmada(): void
    {
        $this->inserirUsuario('30021', 'NOME');

        UsuarioService::editar('30021', 'NOME', 'comum', 'novasenha123', 'novasenha123');

        $usuario = Usuario::buscarPorMatricula('30021');
        $this->assertTrue(password_verify('novasenha123', $usuario['senha']));
    }

    public function testEditarLancaValidacaoParaNomeVazio(): void
    {
        $this->inserirUsuario('30022', 'NOME');

        $this->expectException(ValidacaoException::class);

        UsuarioService::editar('30022', '', 'comum', '', '');
    }

    public function testEditarLancaValidacaoParaTipoInvalido(): void
    {
        $this->inserirUsuario('30023', 'NOME');

        $this->expectException(ValidacaoException::class);

        UsuarioService::editar('30023', 'NOME', 'invalido', '', '');
    }

    public function testEditarLancaSenhaCurtaParaSenhaMenorQueCinco(): void
    {
        $this->inserirUsuario('30024', 'NOME');

        $this->expectException(SenhaCurtaException::class);

        UsuarioService::editar('30024', 'NOME', 'comum', '1234', '1234');
    }

    public function testEditarLancaSenhasNaoCoincidemQuandoConfirmacaoDiferente(): void
    {
        $this->inserirUsuario('30025', 'NOME');

        $this->expectException(SenhasNaoCoincidemException::class);

        UsuarioService::editar('30025', 'NOME', 'comum', 'novasenha123', 'outrasenha');
    }

    public function testEditarNaoAlteraSenhaQuandoConfirmacaoDivergeMantemAntiga(): void
    {
        $this->inserirUsuario('30026', 'NOME', 'comum', 1, password_hash('senhaoriginal', PASSWORD_BCRYPT, ['cost' => 4]));

        try {
            UsuarioService::editar('30026', 'NOME', 'comum', 'novasenha123', 'outrasenha');
        } catch (SenhasNaoCoincidemException) {
            // esperado
        }

        $usuario = Usuario::buscarPorMatricula('30026');
        $this->assertTrue(password_verify('senhaoriginal', $usuario['senha']));
    }

    // =========================================================================
    // inativar()
    // =========================================================================

    public function testInativarComSucesso(): void
    {
        $this->inserirUsuario('30030', 'NOME');

        UsuarioService::inativar('30030', 'outra_matricula_logada');

        $usuario = Usuario::buscarPorMatricula('30030');
        $this->assertFalse($usuario); // buscarPorMatricula só retorna ativos
    }

    public function testInativarLancaAutoAcaoNaoPermitida(): void
    {
        $this->inserirUsuario('30031', 'NOME');

        $this->expectException(AutoAcaoNaoPermitidaException::class);

        UsuarioService::inativar('30031', '30031');
    }

    // =========================================================================
    // ativar()
    // =========================================================================

    public function testAtivarReativaUsuarioInativo(): void
    {
        $this->inserirUsuario('30040', 'NOME', 'comum', 0);

        UsuarioService::ativar('30040');

        $this->assertNotFalse(Usuario::buscarPorMatricula('30040'));
    }

    // =========================================================================
    // excluir()
    // =========================================================================

    public function testExcluirComSucesso(): void
    {
        $this->inserirUsuario('30050', 'NOME');

        UsuarioService::excluir('30050', 'outra_matricula_logada');

        $this->assertFalse(Usuario::buscarPorMatricula('30050'));
    }

    public function testExcluirLancaAutoAcaoNaoPermitida(): void
    {
        $this->inserirUsuario('30051', 'NOME');

        $this->expectException(AutoAcaoNaoPermitidaException::class);

        UsuarioService::excluir('30051', '30051');
    }

    public function testExcluirLancaPossuiRegistros(): void
    {
        $this->inserirUsuario('30052', 'NOME');
        $idCardapio = $this->inserirCardapio('2026-07-15', 'almoco');
        $this->inserirRegistro('30052', $idCardapio, 'agendado');

        $this->expectException(PossuiRegistrosException::class);

        UsuarioService::excluir('30052', 'outra_matricula_logada');
    }
}
