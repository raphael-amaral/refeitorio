<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Exceptions\AutoAcaoNaoPermitidaException;
use App\Exceptions\ErroAoCriarUsuarioException;
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

class UsuarioController extends Controller
{
    /**
     * Lista todos os usuários e exibe formulário de criação.
     */
    public function index(): void
    {
        $busca     = trim($_GET['busca'] ?? '');
        $porPagina = 20;
        $pagina    = max(1, (int) ($_GET['pagina'] ?? 1));

        $ordenarPor   = ($busca !== '' && ctype_digit($busca)) ? 'matricula' : 'nome';
        $total        = Usuario::contarTotal($busca);
        $totalPaginas = max(1, (int) ceil($total / $porPagina));
        $pagina       = min($pagina, $totalPaginas);
        $offset       = ($pagina - 1) * $porPagina;

        $this->render('usuario/index', [
            'titulo'       => 'Usuários',
            'usuarios'     => Usuario::listarPaginado($busca, $offset, $porPagina, $ordenarPor),
            'busca'        => $busca,
            'pagina'       => $pagina,
            'totalPaginas' => $totalPaginas,
            'total'        => $total,
        ]);
    }

    /**
     * Cria um novo usuário (POST /usuario).
     */
    public function criar(): void
    {
        $matricula      = trim($_POST['matricula'] ?? '');
        $nome           = trim($_POST['nome'] ?? '');
        $senha          = $_POST['senha'] ?? '';
        $confirmarSenha = $_POST['confirmar_senha'] ?? '';
        $tipo           = $_POST['tipo'] ?? 'comum';

        try {
            $nomeCriado = UsuarioService::criar($matricula, $nome, $senha, $confirmarSenha, $tipo);
        } catch (ValidacaoException) {
            $this->oldInput(['matricula' => $matricula, 'nome' => $nome, 'tipo' => $tipo]);
            $this->flash('Preencha todos os campos corretamente.', 'error');
            $this->redirect('/usuario');
        } catch (SenhaCurtaException) {
            $this->oldInput(['matricula' => $matricula, 'nome' => $nome, 'tipo' => $tipo]);
            $this->flash('A senha deve ter pelo menos 5 caracteres.', 'error');
            $this->redirect('/usuario');
        } catch (SenhasNaoCoincidemException) {
            $this->oldInput(['matricula' => $matricula, 'nome' => $nome, 'tipo' => $tipo]);
            $this->flash('As senhas não coincidem.', 'error');
            $this->redirect('/usuario');
        } catch (MatriculaDuplicadaException) {
            $this->oldInput(['matricula' => $matricula, 'nome' => $nome, 'tipo' => $tipo]);
            $this->flash('Matrícula já cadastrada.', 'error');
            $this->redirect('/usuario');
        } catch (ErroAoCriarUsuarioException) {
            $this->oldInput(['matricula' => $matricula, 'nome' => $nome, 'tipo' => $tipo]);
            $this->flash('Erro ao criar usuário.', 'error');
            $this->redirect('/usuario');
        }

        $this->flash("Usuário {$nomeCriado} criado com sucesso.", 'success');
        $this->redirect('/usuario');
    }

    /**
     * Importa múltiplos usuários em lote (POST /usuario/importar).
     * Formato esperado: uma linha por usuário → "matricula;nome"
     */
    public function importar(): void
    {
        $tipo = $_POST['tipo_importacao'] ?? 'comum';

        // Arquivo CSV tem prioridade sobre o textarea
        $arquivo = $_FILES['arquivo'] ?? null;
        if (!empty($arquivo['tmp_name']) && is_uploaded_file($arquivo['tmp_name'])) {
            $conteudo = file_get_contents($arquivo['tmp_name']);
            $conteudo = ltrim($conteudo, "\xEF\xBB\xBF"); // remove BOM UTF-8
            $lista    = str_replace(["\r\n", "\r"], "\n", $conteudo);
        } else {
            $lista = trim($_POST['lista'] ?? '');
        }

        try {
            $criados = UsuarioService::importar($tipo, $lista);
        } catch (ListaVaziaException) {
            $this->flash('Nenhum dado para importar.', 'error');
            $this->redirect('/usuario');
        } catch (LinhasInvalidasException $e) {
            $_SESSION['import_invalidas'] = $e->invalidas;
            $this->flash(count($e->invalidas) . ' problema(s) encontrado(s) no arquivo. Corrija e tente novamente.', 'error');
            $this->redirect('/usuario');
        } catch (MatriculasDuplicadasException $e) {
            $_SESSION['import_duplicadas'] = $e->duplicadas;
            $this->flash(count($e->duplicadas) . ' matrícula(s) já cadastrada(s) no sistema. Corrija a lista e tente novamente.', 'error');
            $this->redirect('/usuario');
        }

        $this->flash("{$criados} usuário(s) importado(s) com sucesso.", 'success');
        $this->redirect('/usuario');
    }

    /**
     * Atualiza nome, tipo e senha de um usuário (POST /usuario/{matricula}/editar).
     */
    public function editar(string $matricula): void
    {
        $nome           = trim($_POST['nome'] ?? '');
        $tipo           = $_POST['tipo'] ?? 'comum';
        $senha          = $_POST['senha'] ?? '';
        $confirmarSenha = $_POST['confirmar_senha'] ?? '';

        if ($matricula === ($_SESSION['usuario']['matricula'] ?? '')) {
            $tipo = $_SESSION['usuario']['tipo'];
        }

        $dadosAntigos = ['modo' => 'editar', 'matricula_alvo' => $matricula, 'nome' => $nome, 'tipo' => $tipo];

        try {
            UsuarioService::editar($matricula, $nome, $tipo, $senha, $confirmarSenha);
        } catch (ValidacaoException) {
            $this->oldInput($dadosAntigos);
            $this->flash('Preencha os campos corretamente.', 'error');
            $this->redirect('/usuario');
        } catch (SenhaCurtaException) {
            $this->oldInput($dadosAntigos);
            $this->flash('A senha deve ter pelo menos 5 caracteres.', 'error');
            $this->redirect('/usuario');
        } catch (SenhasNaoCoincidemException) {
            $this->oldInput($dadosAntigos);
            $this->flash('As senhas não coincidem.', 'error');
            $this->redirect('/usuario');
        }

        $this->flash('Usuário atualizado com sucesso.', 'success');
        $this->redirect('/usuario');
    }

    /**
     * Inativa um usuário (POST /usuario/{matricula}/inativar).
     */
    public function inativar(string $matricula): void
    {
        try {
            UsuarioService::inativar($matricula, $_SESSION['usuario']['matricula'] ?? '');
        } catch (AutoAcaoNaoPermitidaException) {
            $this->flash('Você não pode inativar sua própria conta.', 'error');
            $this->redirect('/usuario');
        }

        $this->flash('Usuário inativado.', 'success');
        $this->redirect('/usuario');
    }

    /**
     * Reativa um usuário (POST /usuario/{matricula}/ativar).
     */
    public function ativar(string $matricula): void
    {
        UsuarioService::ativar($matricula);
        $this->flash('Usuário reativado.', 'success');
        $this->redirect('/usuario');
    }

    /**
     * Exclui permanentemente um usuário sem registros (POST /usuario/{matricula}/excluir).
     */
    public function excluir(string $matricula): void
    {
        try {
            UsuarioService::excluir($matricula, $_SESSION['usuario']['matricula'] ?? '');
        } catch (AutoAcaoNaoPermitidaException) {
            $this->flash('Você não pode excluir sua própria conta.', 'error');
            $this->redirect('/usuario');
        } catch (PossuiRegistrosException) {
            $this->flash('Não é possível excluir: usuário possui agendamentos.', 'error');
            $this->redirect('/usuario');
        }

        $this->flash('Usuário excluído com sucesso.', 'success');
        $this->redirect('/usuario');
    }
}
