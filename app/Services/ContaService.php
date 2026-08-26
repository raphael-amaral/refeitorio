<?php

namespace App\Services;

use App\Core\Audit;
use App\Exceptions\SenhaAtualIncorretaException;
use App\Exceptions\SenhaCurtaException;
use App\Exceptions\SenhasNaoCoincidemException;
use App\Models\Usuario;

class ContaService
{
    /**
     * Altera a senha do próprio usuário logado.
     */
    public static function alterarSenha(string $matricula, string $senhaAtual, string $novaSenha, string $confirmacao): void
    {
        $usuario = Usuario::buscarPorMatricula($matricula);

        if (!$usuario || !password_verify($senhaAtual, $usuario['senha'])) {
            Audit::write('conta.senha', 'blocked', $matricula, ['motivo' => 'senha_atual_incorreta']);
            throw new SenhaAtualIncorretaException();
        }

        if ($novaSenha === '' || strlen($novaSenha) < Perfil::tamanhoMinimoSenha($usuario['tipo'])) {
            throw new SenhaCurtaException();
        }

        if ($novaSenha !== $confirmacao) {
            throw new SenhasNaoCoincidemException();
        }

        Usuario::atualizarUsuario($matricula, $usuario['nome'], $usuario['tipo'], $novaSenha);

        // Nota: preserva o comportamento original, que zera a flag de senha padrão
        // na sessão antes de registrar o audit — 'era_senha_padrao' é sempre false aqui.
        Audit::write('conta.senha', 'success', $matricula, ['era_senha_padrao' => false]);
    }
}
