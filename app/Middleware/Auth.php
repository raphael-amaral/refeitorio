<?php

namespace App\Middleware;

/**
 * Middleware de autenticação e controle de acesso por tipo de usuário.
 *
 * Uso nas rotas:
 *   'App\Middleware\Auth'         → handle() — apenas usuário logado
 *   'App\Middleware\Auth@dev'     → apenas dev
 *   'App\Middleware\Auth@snd'     → snd / dev
 *   'App\Middleware\Auth@comum'   → apenas comum
 */
class Auth
{
    /**
     * Exige apenas que o usuário esteja logado.
     */
    public static function handle(): void
    {
        if (!isset($_SESSION['usuario'])) {
            header('Location: /');
            exit;
        }
    }

    /**
     * Exige perfil dev.
     */
    public static function dev(): void
    {
        self::handle();

        if ($_SESSION['usuario']['tipo'] !== 'dev') {
            header('Location: /painel');
            exit;
        }
    }

    /**
     * Exige perfil de snd ou dev.
     */
    public static function snd(): void
    {
        self::handle();

        if (!in_array($_SESSION['usuario']['tipo'], ['snd', 'dev'], true)) {
            header('Location: /painel');
            exit;
        }
    }

    /**
     * Exige perfil comum (apenas usuários comuns podem agendar).
     */
    public static function comum(): void
    {
        self::handle();

        if ($_SESSION['usuario']['tipo'] !== 'comum') {
            header('Location: /painel');
            exit;
        }
    }
}
