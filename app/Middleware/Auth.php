<?php

namespace App\Middleware;

/**
 * Middleware de autenticação e controle de acesso por tipo de usuário.
 *
 * Uso nas rotas:
 *   'App\Middleware\Auth'          → handle() — apenas usuário logado
 *   'App\Middleware\Auth@admin'    → apenas admin / dev
 *   'App\Middleware\Auth@copa'     → copa / admin / dev
 */
class Auth
{
    /**
     * Exige apenas que o usuário esteja logado.
     * Redireciona para '/' caso contrário.
     */
    public static function handle(): void
    {
        if (!isset($_SESSION['usuario'])) {
            header('Location: /');
            exit;
        }
    }

    /**
     * Exige perfil administrativo (admin ou dev).
     */
    public static function admin(): void
    {
        self::handle();

        if (!in_array($_SESSION['usuario']['tipo'], ['admin', 'dev'], true)) {
            header('Location: /painel');
            exit;
        }
    }

    /**
     * Exige perfil de copa ou superior (copa, admin, dev).
     */
    public static function copa(): void
    {
        self::handle();

        if (!in_array($_SESSION['usuario']['tipo'], ['copa', 'admin', 'dev'], true)) {
            header('Location: /painel');
            exit;
        }
    }
}
