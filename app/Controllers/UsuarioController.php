<?php

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Controller;
use App\Models\Usuario;

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
        $matricula = trim($_POST['matricula'] ?? '');
        $nome      = trim($_POST['nome'] ?? '');
        $senha     = $_POST['senha'] ?? '';
        $tipo      = $_POST['tipo'] ?? 'comum';

        $tiposPermitidos = ['comum', 'snd', 'dev'];

        if (!$matricula || !$nome || !$senha || !in_array($tipo, $tiposPermitidos, true)) {
            Audit::write('usuario.criar', 'blocked', $matricula !== '' ? $matricula : null, [
                'motivo' => 'validacao',
                'tipo' => $tipo,
            ]);
            $this->flash('Preencha todos os campos corretamente.', 'error');
            $this->redirect('/usuario');
        }

        try {
            Usuario::novoUsuario($matricula, $nome, $senha, $tipo);
            Audit::write('usuario.criar', 'success', $matricula, ['tipo' => $tipo]);
            $this->flash("Usuário {$nome} criado com sucesso.", 'success');
        } catch (\PDOException $e) {
            Audit::write('usuario.criar', 'error', $matricula, [
                'tipo' => $tipo,
                'erro' => $e->getCode(),
            ]);
            if ($e->getCode() === '23000') {
                $this->flash('Matrícula já cadastrada.', 'error');
            } else {
                $this->flash('Erro ao criar usuário.', 'error');
            }
        }

        $this->redirect('/usuario');
    }

    /**
     * Atualiza nome, tipo e senha de um usuário (POST /usuario/{matricula}/editar).
     */
    public function editar(string $matricula): void
    {
        $nome  = trim($_POST['nome'] ?? '');
        $tipo  = $_POST['tipo'] ?? 'comum';
        $senha = $_POST['senha'] ?? '';

        $tiposPermitidos = ['comum', 'snd', 'dev'];

        if (!$nome || !in_array($tipo, $tiposPermitidos, true)) {
            Audit::write('usuario.editar', 'blocked', $matricula, [
                'motivo' => 'validacao',
                'tipo' => $tipo,
            ]);
            $this->flash('Preencha os campos corretamente.', 'error');
            $this->redirect('/usuario');
        }

        Usuario::atualizarUsuario($matricula, $nome, $tipo, $senha !== '' ? $senha : null);
        Audit::write('usuario.editar', 'success', $matricula, [
            'tipo' => $tipo,
            'senha_alterada' => $senha !== '',
        ]);
        $this->flash('Usuário atualizado com sucesso.', 'success');
        $this->redirect('/usuario');
    }

    /**
     * Inativa um usuário (POST /usuario/{matricula}/inativar).
     */
    public function inativar(string $matricula): void
    {
        // Impede inativar a própria conta
        if ($matricula === ($_SESSION['usuario']['matricula'] ?? '')) {
            Audit::write('usuario.inativar', 'blocked', $matricula, ['motivo' => 'auto_inativacao']);
            $this->flash('Você não pode inativar sua própria conta.', 'error');
            $this->redirect('/usuario');
        }

        Usuario::alterarAtivo($matricula, 0);
        Audit::write('usuario.inativar', 'success', $matricula);
        $this->flash('Usuário inativado.', 'success');
        $this->redirect('/usuario');
    }

    /**
     * Reativa um usuário (POST /usuario/{matricula}/ativar).
     */
    public function ativar(string $matricula): void
    {
        Usuario::alterarAtivo($matricula, 1);
        Audit::write('usuario.ativar', 'success', $matricula);
        $this->flash('Usuário reativado.', 'success');
        $this->redirect('/usuario');
    }

    /**
     * Exclui permanentemente um usuário sem registros (POST /usuario/{matricula}/excluir).
     */
    public function excluir(string $matricula): void
    {
        if ($matricula === ($_SESSION['usuario']['matricula'] ?? '')) {
            Audit::write('usuario.excluir', 'blocked', $matricula, ['motivo' => 'auto_exclusao']);
            $this->flash('Você não pode excluir sua própria conta.', 'error');
            $this->redirect('/usuario');
        }

        if (Usuario::temRegistros($matricula)) {
            Audit::write('usuario.excluir', 'blocked', $matricula, ['motivo' => 'possui_registros']);
            $this->flash('Não é possível excluir: usuário possui agendamentos.', 'error');
            $this->redirect('/usuario');
        }

        Usuario::excluir($matricula);
        Audit::write('usuario.excluir', 'success', $matricula);
        $this->flash('Usuário excluído com sucesso.', 'success');
        $this->redirect('/usuario');
    }
}
