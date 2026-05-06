<?php

namespace App\Middleware;

/**
 * Middleware de autenticação e controle de acesso por tipo de usuário.
 *
 * Uso nas rotas:
 *   'App\Middleware\Auth'           → handle() — apenas usuário logado
 *   'App\Middleware\Auth@dev'       → apenas dev
 *   'App\Middleware\Auth@snd'       → snd / dev
 *   'App\Middleware\Auth@dp'        → dp / dev
 *   'App\Middleware\Auth@relatorio' → snd / dp / dev
 *   'App\Middleware\Auth@comum'     → apenas comum
 */
class Auth
{
    /**
     * Exige apenas que o usuário esteja logado.
     * dp é redirecionado para /questor — suas únicas rotas são @dp e @relatorio.
     */
    public static function handle(): void
    {
        if (!isset($_SESSION['usuario'])) {
            header('Location: /');
            exit;
        }

        if ($_SESSION['usuario']['tipo'] === 'dp') {
            header('Location: /questor');
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
     * Exige perfil snd, dp ou dev (ex: relatórios).
     * Não chama handle() para não redirecionar dp antes de checar o perfil.
     */
    public static function relatorio(): void
    {
        if (!isset($_SESSION['usuario'])) {
            header('Location: /');
            exit;
        }

        if (!in_array($_SESSION['usuario']['tipo'], ['snd', 'dp', 'dev'], true)) {
            header('Location: /painel');
            exit;
        }
    }

    /**
     * Exige perfil dp ou dev.
     * Não chama handle() para não redirecionar dp antes de checar o perfil.
     */
    public static function dp(): void
    {
        if (!isset($_SESSION['usuario'])) {
            header('Location: /');
            exit;
        }

        if (!in_array($_SESSION['usuario']['tipo'], ['dp', 'dev'], true)) {
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

    /**
     * Exige um Token de API válido para o aplicativo do scanner (Kotlin).
     */
    public static function apiSnd(): void
    {
        $tokenEnviado = $_SERVER['HTTP_AUTHORIZATION']
            ?? getallheaders()['Authorization']
            ?? '';

        // Defina uma chave fixa para o seu aplicativo
        $tokenEsperado = 'Bearer ' . ($_ENV['SCANNER_TOKEN'] ?? '');

        if ($tokenEnviado !== $tokenEsperado) {
            header('Content-Type: application/json');
            http_response_code(401);
            echo json_encode(['ok' => false, 'erro' => 'Acesso negado pelo aplicativo.']);
            exit;
        }
    }
}
