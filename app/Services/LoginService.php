<?php

namespace App\Services;

use App\Core\Audit;
use App\Exceptions\CredenciaisInvalidasException;
use App\Models\Usuario;

class LoginService
{
    /**
     * Valida as credenciais do login web. Não mexe em sessão — isso é papel do Controller.
     *
     * @return array{matricula: string, nome: string, tipo: string, senha: string}
     */
    public static function autenticar(string $matricula, string $senha): array
    {
        $usuario = Usuario::buscarPorMatricula($matricula);

        if (!$usuario || !password_verify($senha, $usuario['senha'])) {
            Audit::write('auth.login', 'failed', $matricula !== '' ? $matricula : null, ['motivo' => 'credenciais_invalidas']);
            throw new CredenciaisInvalidasException();
        }

        Audit::write('auth.login', 'success', $matricula, ['nome' => $usuario['nome'], 'tipo' => $usuario['tipo']]);
        return $usuario;
    }

    /**
     * Audita o logout. $matricula/$nome vêm do que estava na sessão antes de ser destruída.
     */
    public static function registrarLogout(?string $matricula, ?string $nome): void
    {
        Audit::write('auth.logout', 'success', $matricula, ['nome' => $nome]);
    }
}
