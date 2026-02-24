<?php

namespace App\Controllers;

use App\Models\Usuario;

class AuthController
{
    public function index()
    {
        require_once __DIR__ . '/../Views/login.php';
    }

    public function login()
    {
        $matricula = $_POST['matricula'] ?? '';
        $senha = $_POST['senha'] ?? '';

        $userModel = new Usuario();
        $usuario = $userModel->buscarPorMatricula($matricula);

        if ($usuario && password_verify($senha, $usuario['senha'])) {
            session_start();
            $_SESSION['usuario_id'] = $usuario['id'];
            $_SESSION['usuario_matricula'] = $usuario['matricula'];
            $_SESSION['usuario_nome'] = $usuario['nome'];
            $_SESSION['usuario_tipo'] = $usuario['tipo_usuario'];

            header('Location: /dashboard');
            exit;
        } else {
            header('Location: /login?erro=1');
            exit;
        }
    }

    public function logout()
    {
        session_start();
        session_destroy();
        header('Location: /login');
        exit;
    }
}
