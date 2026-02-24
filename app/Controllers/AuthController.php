<?php

namespace App\Controllers;

use App\Models\Usuario;

class AuthController
{
    public function index()
    {
        if (session_status() === PHP_SESSION_NONE) session_start();
        require_once __DIR__ . '/../Views/login.php';
    }

    public function login()
    {
        // Garante que não há espaços em branco saindo antes do header
        ob_start();
        if (session_status() === PHP_SESSION_NONE) session_start();

        $matricula = $_POST['matricula'] ?? '';
        $senha = $_POST['senha'] ?? '';

        $userModel = new Usuario();
        $usuario = $userModel->buscarPorMatricula($matricula);

        if ($usuario && password_verify($senha, $usuario['senha'])) {
            $_SESSION['usuario_id'] = $usuario['id'];
            $_SESSION['usuario_matricula'] = $usuario['matricula'];
            $_SESSION['usuario_nome'] = $usuario['nome'];
            $_SESSION['usuario_tipo'] = $usuario['tipo_usuario'];

            // Força a gravação da sessão antes de redirecionar
            session_write_close();
            header('Location: /dashboard');
            exit;
        } else {
            $_SESSION['erro_login'] = "Matrícula ou senha incorretos.";
            session_write_close();
            header('Location: /login');
            exit;
        }
    }

    public function logout()
    {
        if (session_status() === PHP_SESSION_NONE) session_start();
        session_destroy();
        header('Location: /login');
        exit;
    }
}
