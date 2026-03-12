<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Usuario;

class UsuarioController extends Controller
{
    /**
     * Lista todos os usuários e exibe formulário de criação.
     */
    public function index(): void
    {
        $this->render('usuario/index', [
            'titulo'   => 'Usuários',
            'usuarios' => Usuario::listar(),
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
            $this->flash('Preencha todos os campos corretamente.', 'error');
            $this->redirect('/usuario');
        }

        try {
            Usuario::novoUsuario($matricula, $nome, $senha, $tipo);
            $this->flash("Usuário {$nome} criado com sucesso.", 'success');
        } catch (\PDOException $e) {
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
            $this->flash('Preencha os campos corretamente.', 'error');
            $this->redirect('/usuario');
        }

        Usuario::atualizarUsuario($matricula, $nome, $tipo, $senha !== '' ? $senha : null);
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
            $this->flash('Você não pode inativar sua própria conta.', 'error');
            $this->redirect('/usuario');
        }

        Usuario::alterarAtivo($matricula, 0);
        $this->flash('Usuário inativado.', 'success');
        $this->redirect('/usuario');
    }

    /**
     * Reativa um usuário (POST /usuario/{matricula}/ativar).
     */
    public function ativar(string $matricula): void
    {
        Usuario::alterarAtivo($matricula, 1);
        $this->flash('Usuário reativado.', 'success');
        $this->redirect('/usuario');
    }
}
