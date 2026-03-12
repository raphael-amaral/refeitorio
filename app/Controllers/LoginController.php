<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Usuario;
use App\Models\Cardapio;

class LoginController extends Controller
{
    public function __construct()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Exibe a tela de login (GET /).
     */
    public function index(): void
    {
        if (isset($_SESSION['usuario'])) {
            $this->redirect('/painel');
        }

        // Consome a mensagem de erro da sessão e a passa para a view
        $erro = $_SESSION['erro_login'] ?? null;
        unset($_SESSION['erro_login']);

        // Cardápios do dia para exibir sem login
        $cardapiosHoje = [];
        foreach (Cardapio::buscarPorData(date('Y-m-d')) as $c) {
            $cardapiosHoje[$c['tipo']] = $c;
        }

        $this->render('login', ['erro' => $erro, 'cardapiosHoje' => $cardapiosHoje], null);
    }

    /**
     * Processa o formulário de login (POST /login).
     */
    public function autenticar(): void
    {
        $matricula = trim($_POST['matricula'] ?? '');
        $senha     = $_POST['senha'] ?? '';

        $usuario = Usuario::buscarPorMatricula($matricula);

        if ($usuario && password_verify($senha, $usuario['senha'])) {
            $_SESSION['usuario'] = [
                'matricula' => $usuario['matricula'],
                'nome'      => $usuario['nome'],
                'tipo'      => $usuario['tipo'],
            ];

            $this->redirect('/painel');
        }

        $_SESSION['erro_login'] = 'Matrícula ou senha inválidos, ou usuário inativo.';
        $this->redirect('/');
    }

    /**
     * Encerra a sessão (GET /logout).
     */
    public function logout(): void
    {
        session_destroy();
        $this->redirect('/');
    }
}
