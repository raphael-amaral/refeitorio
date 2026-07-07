<?php

namespace App\Services;

/**
 * Concentra as regras de papel/tipo de usuário (comum, snd, dp, dev) — quem pode
 * acessar o quê e para onde vai depois do login. Antes espalhado em Auth, Login,
 * Painel, Registro, Scanner e Usuario, cada um reimplementando os mesmos grupos.
 */
class Perfil
{
    private const TODOS = ['comum', 'snd', 'dp', 'dev'];

    public static function valido(string $tipo): bool
    {
        return in_array($tipo, self::TODOS, true);
    }

    public static function ehComum(string $tipo): bool
    {
        return $tipo === 'comum';
    }

    public static function ehDev(string $tipo): bool
    {
        return $tipo === 'dev';
    }

    public static function ehDp(string $tipo): bool
    {
        return $tipo === 'dp';
    }

    /** snd ou dev — opera o scanner e o cadastro de cardápio. */
    public static function podeScanner(string $tipo): bool
    {
        return in_array($tipo, ['snd', 'dev'], true);
    }

    /** dp ou dev — exportação Questor. */
    public static function podeDp(string $tipo): bool
    {
        return in_array($tipo, ['dp', 'dev'], true);
    }

    /** snd, dp ou dev — relatórios de agendamento. */
    public static function podeRelatorio(string $tipo): bool
    {
        return in_array($tipo, ['snd', 'dp', 'dev'], true);
    }

    /** Rota inicial após login — dp vai direto pro Questor, os demais pro painel. */
    public static function rotaInicial(string $tipo): string
    {
        return $tipo === 'dp' ? '/questor' : '/painel';
    }
}
