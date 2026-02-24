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
        ob_start();
        if (session_status() === PHP_SESSION_NONE) session_start();

        // Limpa a matrícula para garantir que apenas números cheguem ao Model
        $matriculaRaw = $_POST['matricula'] ?? '';
        $matricula = preg_replace('/[^0-9]/', '', $matriculaRaw);
        $senha = $_POST['senha'] ?? '';

        if (empty($matricula)) {
            $_SESSION['erro_login'] = "Matrícula inválida.";
            header('Location: /login');
            exit;
        }

        $userModel = new Usuario();
        $usuario = $userModel->buscarPorMatricula($matricula);

        if ($usuario && password_verify($senha, $usuario['senha'])) {
            $_SESSION['matricula'] = $usuario['matricula'];
            $_SESSION['usuario_nome'] = $usuario['nome'];
            $_SESSION['usuario_tipo'] = $usuario['tipo_usuario'];

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
